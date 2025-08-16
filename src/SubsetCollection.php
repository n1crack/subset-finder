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
        
        // Only validate the initial constructor call, not internal Laravel operations
        if (!empty($items) && $this->isInitialConstructorCall()) {
            $this->validateItems($items);
        }
    }

    /**
     * Check if this is the initial constructor call from user code.
     * This prevents validation during internal Laravel Collection operations.
     */
    private function isInitialConstructorCall(): bool
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        
        // Look for calls that are not from Laravel's internal Collection methods
        foreach ($trace as $call) {
            if (isset($call['class']) && str_starts_with($call['class'], 'Illuminate\\Support\\Collection')) {
                // This is an internal Laravel call, skip validation
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate that all items are Subset instances.
     *
     * @param array $items
     * @throws InvalidArgumentException
     */
    private function validateItems($items): void
    {
        if (!is_array($items)) {
            throw new InvalidArgumentException('Items must be an array');
        }

        foreach ($items as $index => $item) {
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
        return $this->pluck('items')->flatten(1)->unique();
    }

    /**
     * Get the total quantity required across all subsets.
     */
    public function getTotalRequiredQuantity(): int
    {
        return $this->sum('quantity');
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
