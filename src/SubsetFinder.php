<?php

namespace Ozdemir\SubsetFinder;

use Illuminate\Support\Collection;

class SubsetFinder
{
    private string $idFieldName = 'id';
    private string $quantityFieldName = 'quantity';
    private string $sortByField = 'id';
    private bool $sortByDesc = false;

    protected Collection $flatCollection;

    protected Collection $filteredFlatCollection;

    protected Collection $foundSubsets;

    protected Collection $remainingSubsets;

    protected int $subsetQuantity;

    /**
     * SubsetFinder constructor.
     *
     * @param Collection $collection
     * @param SubsetCollection $subsetCollection
     */
    public function __construct(
        public Collection $collection,
        public SubsetCollection $subsetCollection
    ) {
    }

    /**
     * Define the field names for the quantity, items and id fields.
     *
     * @param string $quantity
     * @param string $id
     * @return $this
     */
    public function defineProps(string $id = 'id', string $quantity = 'quantity'): self
    {
        $this->idFieldName = $id;
        $this->quantityFieldName = $quantity;

        return $this;
    }

    /**
     * Set the field to sort the collection by.
     *
     * @param $field
     * @param bool $descending
     * @return $this
     */
    public function sortBy($field, bool $descending = false): self
    {
        $this->sortByField = $field;
        $this->sortByDesc = $descending;

        return $this;
    }

    public function solve(): void
    {
        // Get the maximum quantity of sets that can be created from the collection.
        $this->subsetQuantity = $this->subsetCollection
            ->map(fn ($subset) => $this->calculateQuantityForSet($subset))
            ->min();

        // Get the flattened collection based on the set criteria.
        $this->flatCollection = $this->collection
            ->sortBy($this->sortByField, SORT_REGULAR, $this->sortByDesc)
            ->whereIn($this->idFieldName, $this->subsetCollection->pluck('items')->flatten(1))
            ->flatMap(fn ($item) => $this->duplicateItemForQuantity($item));

        // Find Subsets
        // Initialize a collection to store flattened items
        $cartFlatten = collect();
        $this->filteredFlatCollection = clone $this->flatCollection;

        // Iterate over the subset criteria
        foreach ($this->subsetCollection as $subset) {
            // Filter and limit items based on subset criteria
            $filteredItems = $this->filterAndLimit(
                $subset->items,
                $subset->quantity * $this->subsetQuantity
            );

            // Add filtered items to the collection
            $cartFlatten->push($filteredItems);
        }

        // Flatten the collection of collections, group by ID, update the quantity and return the values
        $this->foundSubsets = $cartFlatten
            ->flatten(1)
            ->groupBy($this->idFieldName)
            ->map(fn ($itemGroup) => $this->mapItemGroup($itemGroup))
            ->values();

        // Get the set items with their quantities
        $setItems = $this->foundSubsets->pluck($this->quantityFieldName, $this->idFieldName)->toArray();

        // Calculate remaining quantities for each item, filter out items with zero or negative quantities and return the values
        $this->remainingSubsets = clone($this->collection)
            ->map(fn ($item) => $this->calculateRemainingQuantity(clone $item, $setItems))
            ->reject(fn ($item) => $item->getQuantity() <= 0)
            ->values();
    }

    /**
     * Calculate the quantity for a given set item.
     *
     * @param Subset $subset
     * @return int
     */
    protected function calculateQuantityForSet(Subset $subset): int
    {
        $quantity = $this->collection
            ->whereIn($this->idFieldName, $subset->items)
            ->sum($this->quantityFieldName);

        return (int)floor($quantity / $subset->quantity);
    }

    /**
     * Duplicate an item in the collection based on its quantity field value.
     *
     * @param Subsetable $item
     * @return Collection
     */
    protected function duplicateItemForQuantity(Subsetable $item): Collection
    {
        return Collection::times($item->getQuantity(), fn () => $item);
    }

    /**
     * Filter the collection based on the set criteria and limit the result.
     *
     * @param $filterIds
     * @param $filterLimit
     * @return Collection
     */
    protected function filterAndLimit($filterIds, $filterLimit): Collection
    {
        $filtered = $this->filteredFlatCollection
            ->filter(fn (Subsetable $item) => in_array($item->getId(), $filterIds))
            ->map(fn (Subsetable $item) => $item)
            ->take($filterLimit);

        // Remove the filtered items from the collection, so it won't be included in the next iteration
        $this->filteredFlatCollection->forget($filtered->keys()->toArray());

        return $filtered;
    }

    /**
     * Map the item group to set quantity and return the Subsetable.
     *
     * @param Collection<int, Subsetable> $itemGroup
     * @return Subsetable
     */
    protected function mapItemGroup(Collection $itemGroup): Subsetable
    {
        $firstItem = clone $itemGroup->first();
        $firstItem->setQuantity($itemGroup->count());

        return $firstItem;
    }

    /**
     * Calculate the remaining quantity for the given item after applying discounts.
     *
     * @param Subsetable $item
     * @param array $setItems
     * @return Subsetable
     */
    protected function calculateRemainingQuantity(Subsetable $item, array $setItems): Subsetable
    {
        // Calculate the remaining quantity by subtracting the quantity of the item included in the discount sets
        $remainingQuantity = $item->getQuantity() - ($setItems[$item->getId()] ?? 0);

        // Ensure the remaining quantity is non-negative
        $item->setQuantity(max($remainingQuantity, 0));

        return $item;
    }

    /**
     * Return a subset of the collection based on the given integer.
     *
     * @param int $int
     * @return Collection
     */
    public function getSubsetItems(int $int): Collection
    {
        return $this->flatCollection->take($int);
    }

    public function getSubsetQuantity(): int
    {
        return $this->subsetQuantity;
    }

    public function getFoundSubsets(): Collection
    {
        return $this->foundSubsets;
    }

    public function getRemaining(): Collection
    {
        return $this->remainingSubsets;
    }
}
