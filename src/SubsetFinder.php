<?php

namespace Ozdemir\SubsetFinder;

use Illuminate\Support\Collection;
use Ozdemir\SubsetFinder\Exceptions\InsufficientQuantityException;
use Ozdemir\SubsetFinder\Exceptions\InvalidArgumentException;

class SubsetFinder
{
    private SubsetFinderConfig $config;

    /** @var Collection<int, Subsetable> Eligible items in sort order */
    private Collection $sortedItems;

    /** @var array<int|string, int> Available quantity per item id */
    private array $availability = [];

    /** @var array<int, int|string> Item ids in sort order (first occurrence) */
    private array $sortedIds = [];

    /** @var array<int|string, Subsetable> First item per id, used to build result objects */
    private array $itemsById = [];

    private Collection $foundSubsets;
    private Collection $remainingSubsets;
    private int $subsetQuantity = 0;
    private float $executionTime = 0.0;

    /**
     * @param Collection $collection
     * @param SubsetCollection $subsetCollection
     * @param SubsetFinderConfig|null $config
     * @throws InvalidArgumentException
     */
    public function __construct(
        private Collection $collection,
        private SubsetCollection $subsetCollection,
        ?SubsetFinderConfig $config = null
    ) {
        $this->config = $config ?? SubsetFinderConfig::default();
        $this->foundSubsets = collect();
        $this->remainingSubsets = collect();
        $this->sortedItems = collect();

        $this->validateInput();
    }

    /**
     * Validate input parameters.
     *
     * @throws InvalidArgumentException
     */
    private function validateInput(): void
    {
        if ($this->collection->isEmpty()) {
            throw new InvalidArgumentException('Collection cannot be empty');
        }

        if ($this->subsetCollection->isEmpty()) {
            throw new InvalidArgumentException('Subset collection cannot be empty');
        }

        if ($this->collection->contains(fn($item) => !$item instanceof Subsetable)) {
            throw new InvalidArgumentException('All collection items must implement Subsetable interface');
        }

        if ($this->subsetCollection->contains(fn($subset) => !$subset instanceof Subset)) {
            throw new InvalidArgumentException('All subset collection items must be Subset instances');
        }
    }

    /**
     * Solve the subset finding problem.
     *
     * Works entirely on per-id quantities; items are never expanded into
     * unit copies, so memory usage is independent of item quantities.
     *
     * @throws InsufficientQuantityException
     */
    public function solve(): void
    {
        $start = microtime(true);

        $this->prepare();

        $this->subsetQuantity = $this->findMaxSubsetQuantity();

        if ($this->subsetQuantity <= 0) {
            throw new InsufficientQuantityException(
                'Insufficient quantity to create any complete subsets. ' .
                'Required quantities exceed available quantities in collection.'
            );
        }

        $allocated = [];
        $this->canAllocate($this->subsetQuantity, $allocated);

        $this->foundSubsets = $this->buildFoundSubsets($allocated);
        $this->remainingSubsets = $this->buildRemaining($allocated);

        $this->executionTime = microtime(true) - $start;
    }

    /**
     * Sort the collection and index eligible items by id.
     *
     * Ids are used as array keys throughout, so numeric strings and integers
     * are treated as the same id ('1' === 1) consistently everywhere.
     */
    private function prepare(): void
    {
        $eligibleIds = array_fill_keys($this->subsetCollection->getAllItemIds()->all(), true);

        $this->sortedItems = $this->collection
            ->sortBy($this->config->sortField, SORT_REGULAR, $this->config->sortDescending)
            ->filter(fn(Subsetable $item) => isset($eligibleIds[$item->getId()]))
            ->values();

        $this->availability = [];
        $this->sortedIds = [];
        $this->itemsById = [];

        foreach ($this->sortedItems as $item) {
            $id = $item->getId();

            if (!isset($this->itemsById[$id])) {
                $this->itemsById[$id] = $item;
                $this->sortedIds[] = $id;
                $this->availability[$id] = 0;
            }

            $this->availability[$id] += max(0, $item->getQuantity());
        }
    }

    /**
     * Find the maximum number of complete subsets via binary search.
     *
     * Feasibility is monotonic: if N subsets can be allocated, any smaller
     * number can too. The upper bound treats each subset in isolation; the
     * allocation check accounts for items shared between subsets.
     */
    private function findMaxSubsetQuantity(): int
    {
        $low = 1;
        $high = $this->upperBound();
        $best = 0;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);

            if ($this->canAllocate($mid)) {
                $best = $mid;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $best;
    }

    /**
     * Upper bound for the subset quantity, ignoring overlap between subsets.
     */
    private function upperBound(): int
    {
        $upper = PHP_INT_MAX;

        foreach ($this->subsetCollection as $subset) {
            $supply = 0;
            foreach (array_keys(array_fill_keys($subset->items, true)) as $id) {
                $supply += $this->availability[$id] ?? 0;
            }

            $upper = min($upper, intdiv($supply, $subset->quantity));
        }

        return $upper;
    }

    /**
     * Try to allocate $quantity complete sets of every subset.
     *
     * Subsets draw from a shared pool in definition order; within a subset,
     * items are consumed in sort order (e.g. cheapest first).
     *
     * @param array<int|string, int> $allocated Filled with quantity taken per id, in allocation order.
     */
    private function canAllocate(int $quantity, array &$allocated = []): bool
    {
        $available = $this->availability;
        $allocated = [];

        foreach ($this->subsetCollection as $subset) {
            $wanted = array_fill_keys($subset->items, true);
            $need = $subset->quantity * $quantity;

            foreach ($this->sortedIds as $id) {
                if ($need <= 0) {
                    break;
                }

                if (!isset($wanted[$id]) || $available[$id] <= 0) {
                    continue;
                }

                $take = min($available[$id], $need);
                $available[$id] -= $take;
                $need -= $take;
                $allocated[$id] = ($allocated[$id] ?? 0) + $take;
            }

            if ($need > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build the found subsets as one item per id with the allocated quantity.
     *
     * @param array<int|string, int> $allocated
     */
    private function buildFoundSubsets(array $allocated): Collection
    {
        $found = collect();

        foreach ($allocated as $id => $quantity) {
            $item = clone $this->itemsById[$id];
            $item->setQuantity($quantity);
            $found->push($item);
        }

        return $found;
    }

    /**
     * Calculate remaining quantities for items not consumed by subsets.
     *
     * @param array<int|string, int> $allocated
     */
    private function buildRemaining(array $allocated): Collection
    {
        return $this->collection
            ->map(function(Subsetable $item) use (&$allocated) {
                $clone = clone $item;
                $taken = min($clone->getQuantity(), $allocated[$clone->getId()] ?? 0);

                if ($taken > 0) {
                    $allocated[$clone->getId()] -= $taken;
                    $clone->setQuantity($clone->getQuantity() - $taken);
                }

                return $clone;
            })
            ->reject(fn(Subsetable $item) => $item->getQuantity() <= 0)
            ->values();
    }

    /**
     * Get the first $count items in sort order, one entry per unit.
     */
    public function getSubsetItems(int $count): Collection
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must be non-negative');
        }

        $result = collect();

        foreach ($this->sortedItems as $item) {
            if ($count <= 0) {
                break;
            }

            $emit = min($item->getQuantity(), $count);
            for ($i = 0; $i < $emit; $i++) {
                $result->push(clone $item);
            }

            $count -= max(0, $emit);
        }

        return $result;
    }

    /**
     * Get the maximum number of complete subsets that can be created.
     */
    public function getSubsetQuantity(): int
    {
        return $this->subsetQuantity;
    }

    /**
     * Get the found subsets.
     */
    public function getFoundSubsets(): Collection
    {
        return $this->foundSubsets;
    }

    /**
     * Get the remaining items not included in any subset.
     */
    public function getRemaining(): Collection
    {
        return $this->remainingSubsets;
    }

    /**
     * Get performance metrics for the last solve() call.
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'execution_time_ms' => round($this->executionTime * 1000, 2),
            'collection_size' => $this->collection->count(),
            'subset_count' => $this->subsetCollection->count(),
            'found_subsets_count' => $this->foundSubsets->count(),
            'remaining_items_count' => $this->remainingSubsets->count(),
        ];
    }

    /**
     * Check if the solution is optimal (no remaining items).
     */
    public function isOptimal(): bool
    {
        return $this->remainingSubsets->isEmpty();
    }

    /**
     * Get the efficiency percentage (items used vs total items).
     */
    public function getEfficiencyPercentage(): float
    {
        $totalItems = $this->collection->sum(fn(Subsetable $item) => $item->getQuantity());
        $usedItems = $this->foundSubsets->sum(fn(Subsetable $item) => $item->getQuantity());

        if ($totalItems <= 0) {
            return 0.0;
        }

        return round(($usedItems / $totalItems) * 100, 2);
    }
}
