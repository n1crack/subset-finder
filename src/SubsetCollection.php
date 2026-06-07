<?php

namespace Ozdemir\SubsetFinder;

use Illuminate\Support\Collection;
use Ozdemir\SubsetFinder\Exceptions\InvalidArgumentException;

class SubsetCollection extends Collection
{
    /**
     * Create a new collection instance.
     *
     * @param array $items
     * @throws InvalidArgumentException
     */
    public function __construct($items = [])
    {
        parent::__construct($items);

        foreach ($this->items as $index => $item) {
            if (!$item instanceof Subset) {
                throw new InvalidArgumentException(
                    "Item at index {$index} is not a Subset instance. " .
                    "Got: " . (is_object($item) ? get_class($item) : gettype($item))
                );
            }
        }
    }

    /**
     * Add a subset to the collection.
     *
     * @param Subset $subset
     * @return $this
     */
    public function addSubset(Subset $subset): self
    {
        $this->push($subset);

        return $this;
    }

    /**
     * Get all unique item IDs from all subsets.
     */
    public function getAllItemIds(): Collection
    {
        return $this->toBase()->pluck('items')->flatten(1)->unique()->values();
    }

    /**
     * Get the total quantity required across all subsets.
     */
    public function getTotalRequiredQuantity(): int
    {
        return (int) $this->sum('quantity');
    }

    /**
     * Check if any subset contains a specific item.
     */
    public function containsItem(int|string $itemId): bool
    {
        return $this->contains(fn(Subset $subset) => $subset->contains($itemId));
    }

    /**
     * Filter subsets by item ID.
     */
    public function filterByItem(int|string $itemId): self
    {
        return $this->filter(fn(Subset $subset) => $subset->contains($itemId));
    }
}
