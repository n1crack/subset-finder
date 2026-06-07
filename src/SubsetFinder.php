<?php

namespace Ozdemir\SubsetFinder;

use Ozdemir\SubsetFinder\Exceptions\InsufficientQuantityException;
use Ozdemir\SubsetFinder\Exceptions\InvalidArgumentException;

class SubsetFinder
{
    private SubsetFinderConfig $config;

    /** @var Subsetable[] Items in input order */
    private array $items;

    /** @var Subsetable[] Eligible items in sort order */
    private array $sortedItems = [];

    /** @var array<int|string, int> Available quantity per item id */
    private array $availability = [];

    /** @var array<int, int|string> Item ids in sort order (first occurrence) */
    private array $sortedIds = [];

    /** @var array<int|string, Subsetable> First item per id, used to build result objects */
    private array $itemsById = [];

    /** @var Subsetable[] */
    private array $foundSubsets = [];

    /** @var Subsetable[] */
    private array $remainingSubsets = [];

    private int $subsetQuantity = 0;
    private float $executionTime = 0.0;

    /**
     * @param iterable<Subsetable> $collection Items to draw from; arrays and any Traversable (e.g. Laravel collections) are accepted.
     * @throws InvalidArgumentException
     */
    public function __construct(
        iterable $collection,
        private SubsetCollection $subsetCollection,
        ?SubsetFinderConfig $config = null
    ) {
        $this->items = is_array($collection) ? array_values($collection) : iterator_to_array($collection, false);
        $this->config = $config ?? SubsetFinderConfig::default();

        $this->validateInput();
    }

    /**
     * Validate input parameters.
     *
     * @throws InvalidArgumentException
     */
    private function validateInput(): void
    {
        if ($this->items === []) {
            throw new InvalidArgumentException('Collection cannot be empty');
        }

        if ($this->subsetCollection->isEmpty()) {
            throw new InvalidArgumentException('Subset collection cannot be empty');
        }

        foreach ($this->items as $item) {
            if (!$item instanceof Subsetable) {
                throw new InvalidArgumentException('All collection items must implement Subsetable interface');
            }
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
        $eligibleIds = array_fill_keys($this->subsetCollection->getAllItemIds(), true);

        $field = $this->config->sortField;
        $direction = $this->config->sortDescending ? -1 : 1;

        $sorted = $this->items;
        usort($sorted, fn(Subsetable $a, Subsetable $b) => $direction * (($a->{$field} ?? null) <=> ($b->{$field} ?? null)));

        $this->sortedItems = array_values(
            array_filter($sorted, fn(Subsetable $item) => isset($eligibleIds[$item->getId()]))
        );

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
     * @return Subsetable[]
     */
    private function buildFoundSubsets(array $allocated): array
    {
        $found = [];

        foreach ($allocated as $id => $quantity) {
            $item = clone $this->itemsById[$id];
            $item->setQuantity($quantity);
            $found[] = $item;
        }

        return $found;
    }

    /**
     * Calculate remaining quantities for items not consumed by subsets.
     *
     * @param array<int|string, int> $allocated
     * @return Subsetable[]
     */
    private function buildRemaining(array $allocated): array
    {
        $remaining = [];

        foreach ($this->items as $item) {
            $clone = clone $item;
            $taken = min($clone->getQuantity(), $allocated[$clone->getId()] ?? 0);

            if ($taken > 0) {
                $allocated[$clone->getId()] -= $taken;
                $clone->setQuantity($clone->getQuantity() - $taken);
            }

            if ($clone->getQuantity() > 0) {
                $remaining[] = $clone;
            }
        }

        return $remaining;
    }

    /**
     * Get the first $count items in sort order, one entry per unit.
     *
     * @return Subsetable[]
     */
    public function getSubsetItems(int $count): array
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must be non-negative');
        }

        $result = [];

        foreach ($this->sortedItems as $item) {
            if ($count <= 0) {
                break;
            }

            $emit = min($item->getQuantity(), $count);
            for ($i = 0; $i < $emit; $i++) {
                $result[] = clone $item;
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
     *
     * @return Subsetable[]
     */
    public function getFoundSubsets(): array
    {
        return $this->foundSubsets;
    }

    /**
     * Get the remaining items not included in any subset.
     *
     * @return Subsetable[]
     */
    public function getRemaining(): array
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
            'collection_size' => count($this->items),
            'subset_count' => $this->subsetCollection->count(),
            'found_subsets_count' => count($this->foundSubsets),
            'remaining_items_count' => count($this->remainingSubsets),
        ];
    }

    /**
     * Check if the solution is optimal (no remaining items).
     */
    public function isOptimal(): bool
    {
        return $this->remainingSubsets === [];
    }

    /**
     * Get the efficiency percentage (items used vs total items).
     */
    public function getEfficiencyPercentage(): float
    {
        $totalItems = array_sum(array_map(fn(Subsetable $item) => $item->getQuantity(), $this->items));
        $usedItems = array_sum(array_map(fn(Subsetable $item) => $item->getQuantity(), $this->foundSubsets));

        if ($totalItems <= 0) {
            return 0.0;
        }

        return round(($usedItems / $totalItems) * 100, 2);
    }
}
