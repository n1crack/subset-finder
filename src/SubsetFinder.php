<?php

namespace Ozdemir\SubsetFinder;

use Illuminate\Support\Collection;
use Ozdemir\SubsetFinder\Exceptions\InsufficientQuantityException;
use Ozdemir\SubsetFinder\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SubsetFinder
{
    private SubsetFinderConfig $config;
    private LoggerInterface $logger;

    private Collection $flatCollection;
    private Collection $filteredFlatCollection;
    private Collection $foundSubsets;
    private Collection $remainingSubsets;
    private int $subsetQuantity = 0;
    private float $startTime;
    private int $startMemory;

    /**
     * SubsetFinder constructor.
     *
     * @param Collection $collection
     * @param SubsetCollection $subsetCollection
     * @param SubsetFinderConfig|null $config
     * @param LoggerInterface|null $logger
     * @throws InvalidArgumentException
     */
    public function __construct(
        private Collection $collection,
        private SubsetCollection $subsetCollection,
        ?SubsetFinderConfig $config = null,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config ?? SubsetFinderConfig::default();
        $this->logger = $logger ?? new NullLogger();

        $this->validateInput();
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
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

        // Validate that all collection items implement Subsetable
        $invalidItems = $this->collection->reject(fn($item) => $item instanceof Subsetable);
        if ($invalidItems->isNotEmpty()) {
            throw new InvalidArgumentException('All collection items must implement Subsetable interface');
        }

        // Check memory usage
        if (memory_get_usage(true) > $this->config->maxMemoryUsage) {
            throw new InvalidArgumentException('Memory usage exceeds configured limit');
        }
    }

    /**
     * Solve the subset finding problem.
     *
     * @throws InsufficientQuantityException
     */
    public function solve(): void
    {
        $this->logger->info('Starting subset calculation', [
            'collection_size' => $this->collection->count(),
            'subset_count' => $this->subsetCollection->count(),
            'config' => [
                'id_field' => $this->config->idField,
                'quantity_field' => $this->config->quantityField,
                'sort_field' => $this->config->sortField,
                'enable_lazy_evaluation' => $this->config->enableLazyEvaluation,
            ],
        ]);

        try {
            $this->calculateSubsetQuantity();
            $this->prepareCollection();
            $this->findSubsets();
            $this->calculateRemaining();

            $this->logCompletion();
        } catch (\Exception $e) {
            $this->logger->error('Error during subset calculation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate the maximum number of complete subsets that can be created.
     *
     * @throws InsufficientQuantityException
     */
    private function calculateSubsetQuantity(): void
    {
        $this->subsetQuantity = $this->subsetCollection
            ->map(fn(Subset $subset) => $this->calculateQuantityForSet($subset))
            ->min();

        if ($this->subsetQuantity <= 0) {
            throw new InsufficientQuantityException(
                'Insufficient quantity to create any complete subsets. ' .
                'Required quantities exceed available quantities in collection.'
            );
        }

        $this->logger->info('Calculated subset quantity', ['subset_quantity' => $this->subsetQuantity]);
    }

    /**
     * Calculate the quantity for a given set item.
     */
    private function calculateQuantityForSet(Subset $subset): int
    {
        $quantity = $this->collection
            ->whereIn($this->config->idField, $subset->items)
            ->sum($this->config->quantityField);

        return (int)floor($quantity / $subset->quantity);
    }

    /**
     * Prepare the collection for processing.
     */
    private function prepareCollection(): void
    {
        $this->flatCollection = $this->collection
            ->sortBy($this->config->sortField, SORT_REGULAR, $this->config->sortDescending)
            ->whereIn($this->config->idField, $this->subsetCollection->getAllItemIds())
            ->flatMap(fn(Subsetable $item) => $this->duplicateItemForQuantity($item));

        $this->filteredFlatCollection = clone $this->flatCollection;

        $this->logger->info('Collection prepared', [
            'flat_collection_size' => $this->flatCollection->count(),
            'sort_field' => $this->config->sortField,
            'sort_descending' => $this->config->sortDescending,
        ]);
    }

    /**
     * Duplicate an item in the collection based on its quantity field value.
     */
    private function duplicateItemForQuantity(Subsetable $item): Collection
    {
        if ($this->config->enableLazyEvaluation) {
            return collect()->lazy()->times($item->getQuantity(), fn() => clone $item)->collect();
        }

        return Collection::times($item->getQuantity(), fn() => clone $item);
    }

    /**
     * Find all subsets based on the criteria.
     */
    private function findSubsets(): void
    {
        $cartFlatten = collect();

        foreach ($this->subsetCollection as $subset) {
            $filteredItems = $this->filterAndLimit(
                $subset->items,
                $subset->quantity * $this->subsetQuantity
            );

            $cartFlatten->push($filteredItems);
        }

        $this->foundSubsets = $cartFlatten
            ->flatten(1)
            ->groupBy($this->config->idField)
            ->map(fn($itemGroup) => $this->mapItemGroup($itemGroup))
            ->values();

        $this->logger->info('Subsets found', [
            'found_subsets_count' => $this->foundSubsets->count(),
            'total_items_found' => $this->foundSubsets->sum($this->config->quantityField),
        ]);
    }

    /**
     * Filter the collection based on the set criteria and limit the result.
     */
    private function filterAndLimit(array $filterIds, int $filterLimit): Collection
    {
        $filtered = $this->filteredFlatCollection
            ->filter(fn(Subsetable $item) => in_array($item->getId(), $filterIds, true))
            ->take($filterLimit);

        // Remove the filtered items from the collection
        $this->filteredFlatCollection->forget($filtered->keys()->toArray());

        return $filtered;
    }

    /**
     * Map the item group to set quantity and return the Subsetable.
     */
    private function mapItemGroup(Collection $itemGroup): Subsetable
    {
        $firstItem = clone $itemGroup->first();
        $firstItem->setQuantity($itemGroup->count());

        return $firstItem;
    }

    /**
     * Calculate remaining quantities for items not included in subsets.
     */
    private function calculateRemaining(): void
    {
        $setItems = $this->foundSubsets->pluck($this->config->quantityField, $this->config->idField)->toArray();

        $this->remainingSubsets = clone($this->collection)
            ->map(fn($item) => $this->calculateRemainingQuantity(clone $item, $setItems))
            ->reject(fn($item) => $item->getQuantity() <= 0)
            ->values();

        $this->logger->info('Remaining quantities calculated', [
            'remaining_items_count' => $this->remainingSubsets->count(),
            'total_remaining_quantity' => $this->remainingSubsets->sum($this->config->quantityField),
        ]);
    }

    /**
     * Calculate the remaining quantity for the given item after applying subsets.
     */
    private function calculateRemainingQuantity(Subsetable $item, array $setItems): Subsetable
    {
        $remainingQuantity = $item->getQuantity() - ($setItems[$item->getId()] ?? 0);
        $item->setQuantity(max($remainingQuantity, 0));

        return $item;
    }

    /**
     * Log completion information.
     */
    private function logCompletion(): void
    {
        $executionTime = microtime(true) - $this->startTime;
        $memoryPeak = memory_get_peak_usage(true);
        $memoryIncrease = $memoryPeak - $this->startMemory;

        $this->logger->info('Subset calculation completed', [
            'execution_time_ms' => round($executionTime * 1000, 2),
            'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            'memory_increase_mb' => round($memoryIncrease / 1024 / 1024, 2),
            'subset_quantity' => $this->subsetQuantity,
        ]);
    }

    /**
     * Get a subset of the flattened collection.
     */
    public function getSubsetItems(int $count): Collection
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Count must be non-negative');
        }

        return $this->flatCollection->take($count);
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
     * Get performance metrics.
     */
    public function getPerformanceMetrics(): array
    {
        $executionTime = microtime(true) - $this->startTime;
        $memoryPeak = memory_get_peak_usage(true);
        $memoryIncrease = $memoryPeak - $this->startMemory;

        return [
            'execution_time_ms' => round($executionTime * 1000, 2),
            'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            'memory_increase_mb' => round($memoryIncrease / 1024 / 1024, 2),
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
        $totalItems = $this->collection->sum($this->config->quantityField);
        $usedItems = $this->foundSubsets->sum($this->config->quantityField);

        if ($totalItems === 0) {
            return 0.0;
        }

        return round(($usedItems / $totalItems) * 100, 2);
    }
}
