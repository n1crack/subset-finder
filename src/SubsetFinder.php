<?php

namespace Ozdemir\SubsetFinder;

use Illuminate\Support\Collection;

class SubsetFinder
{
    protected Collection $flatCollection;

    private string $quantityFieldName = 'quantity';
    private string $idFieldName = 'id';
    private string $itemsFieldName = 'items';

    private string $sortByField = 'id';
    private bool $sortByDesc = false;

    /**
     * SubsetFinder constructor.
     *
     * @param Collection $collection
     * @param Collection $subSetCriteria
     */
    public function __construct(
        public Collection $collection,
        public Collection $subSetCriteria
    ) {
    }

    /**
     * Define the field names for the quantity, items and id fields.
     *
     * @param string $quantity
     * @param string $items
     * @param string $id
     * @return $this
     */
    public function defineProps(string $quantity = 'quantity', string $items = 'items', string $id = 'id'): self
    {
        $this->quantityFieldName = $quantity;
        $this->itemsFieldName = $items;
        $this->idFieldName = $id;

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
        return $this->subSetCriteria
            ->map(fn ($setItem) => $this->calculateQuantityForSet($setItem))
            ->min();
    }

    /**
     * Calculate the quantity for a given set item.
     *
     * @param array $setItem
     * @return int
     */
    protected function calculateQuantityForSet(array $setItem): int
    {
        $quantity = $this->collection
            ->whereIn($this->idFieldName, $setItem[$this->itemsFieldName])
            ->sum($this->quantityFieldName);

        return (int)floor($quantity / $setItem[$this->quantityFieldName]);
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
            ->whereIn($this->idFieldName, $this->subSetCriteria->pluck($this->itemsFieldName)->flatten(1))
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

        // Initialize a collection to store flattened items
        $cartFlatten = collect();

        // Flatten the collection
        $this->flatCollection = $this->getFlatCollection();

        // Iterate over the subset criteria
        foreach ($this->subSetCriteria as $subsetCriteria) {
            // Filter and limit items based on subset criteria
            $filteredItems = $this->filterAndLimit(
                $subsetCriteria[$this->itemsFieldName],
                $subsetCriteria[$this->quantityFieldName] * $maxSetQuantity
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
        $setItem = $itemGroup->first();
        $setItem[$this->quantityFieldName] = $itemGroup->count();

        return [$setItem[$this->idFieldName] => $setItem];
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
}
