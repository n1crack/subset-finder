<?php

namespace Ozdemir\SubsetFinder;

use Illuminate\Support\Collection;

class SubsetFinder
{
    protected Collection $flatCollection;

    private string $idFieldName = 'id';
    private string $quantityFieldName = 'quantity';
    private string $sortByField = 'id';
    private bool $sortByDesc = false;

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

    /**
     * Get the maximum quantity of sets that can be created from the collection.
     *
     * @return int
     */
    public function getSetQuantity(): int
    {
        return $this->subsetCollection
            ->map(fn ($subset) => $this->calculateQuantityForSet($subset))
            ->min();
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
     * Get the flattened collection based on the set criteria.
     *
     * @return Collection
     */
    protected function getFlatCollection(): Collection
    {
        return $this->collection
            ->sortBy($this->sortByField, SORT_REGULAR, $this->sortByDesc)
            ->whereIn($this->idFieldName, $this->subsetCollection->pluck('items')->flatten(1))
            ->flatMap(fn ($item) => $this->duplicateItemForQuantity($item));
    }

    /**
     * Duplicate an item in the collection based on its quantity field value.
     *
     * @param array $item
     * @return Collection
     */
    protected function duplicateItemForQuantity(array $item): Collection
    {
        return Collection::times($item[$this->quantityFieldName], fn () => $item);
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
        $filtered = $this->flatCollection
            ->filter(fn ($item) => in_array($item[$this->idFieldName], $filterIds))
            ->take($filterLimit);

        $this->flatCollection->forget($filtered->keys()->toArray());

        return $filtered;
    }

    /**
     * Get the subset of the collection based on the set criteria.
     *
     * @return Collection
     */
    public function get(): Collection
    {
        // Get the maximum quantity of sets that can be created from the collection
        $maxSetQuantity = $this->getSetQuantity();


        // Flatten the collection
        $this->flatCollection = $this->getFlatCollection();

        // Initialize a collection to store flattened items
        $cartFlatten = collect();

        // Iterate over the subset criteria
        foreach ($this->subsetCollection as $subset) {
            // Filter and limit items based on subset criteria
            $filteredItems = $this->filterAndLimit(
                $subset->items,
                $subset->quantity * $maxSetQuantity
            );

            // Add filtered items to the collection
            $cartFlatten->push($filteredItems);
        }

        // Flatten the collection of collections, group by ID, update the quantity and return the values
        return $cartFlatten
            ->flatten(1)
            ->groupBy($this->idFieldName)
            ->mapWithKeys(fn ($itemGroup) => $this->mapItemGroup($itemGroup))
            ->values();
    }

    /**
     * Map the item group to set quantity and return the mapped key-value pair.
     *
     * @param Collection $itemGroup
     * @return array
     */
    protected function mapItemGroup(Collection $itemGroup): array
    {
        $item = $itemGroup->first();
        $item[$this->quantityFieldName] = $itemGroup->count();

        return [$item[$this->idFieldName] => $item];
    }

    /**
     *  Get the remaining items in the collection.
     *
     * @return Collection
     */
    public function getRemaining(): Collection
    {
        // Get the set items with their quantities
        $setItems = $this->get()->pluck($this->quantityFieldName, $this->idFieldName)->toArray();

        // Calculate remaining quantities for each item, filter out items with zero or negative quantities and return the values
        return $this->collection
            ->map(fn ($item) => $this->calculateRemainingQuantity($item, $setItems))
            ->reject(fn ($item) => $item[$this->quantityFieldName] <= 0)
            ->values();
    }

    /**
     * Calculate the remaining quantity for the given item after applying discounts.
     *
     * @param array $item
     * @param array $setItems
     * @return array
     */
    protected function calculateRemainingQuantity(array $item, array $setItems): array
    {
        // Calculate the remaining quantity by subtracting the quantity of the item included in the discount sets
        $remainingQuantity = $item[$this->quantityFieldName] - ($setItems[$item[$this->idFieldName]] ?? 0);

        // Ensure the remaining quantity is non-negative
        $item[$this->quantityFieldName] = max($remainingQuantity, 0);

        return $item;
    }

    /**
     * Return a subset of the collection based on the given integer.
     *
     * @param int $int
     * @return Collection
     */
    public function getSubsetItems(int $int)
    {
        return $this->getFlatCollection()->take($int);
    }
}
