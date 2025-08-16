<?php

namespace Ozdemir\SubsetFinder;

use Ozdemir\SubsetFinder\Exceptions\InvalidArgumentException;

class Subset
{
    public readonly array $items;
    public readonly int $quantity;

    /**
     * Subset constructor.
     *
     * @param array $items Array of item identifiers
     * @param int $quantity Required quantity for this subset
     * @throws InvalidArgumentException
     */
    public function __construct(array $items, int $quantity = 1)
    {
        $this->validateItems($items);
        $this->validateQuantity($quantity);

        $this->items = $items;
        $this->quantity = $quantity;
    }

    /**
     * Create a new instance of Subset with the given items.
     *
     * @param array $items Array of item identifiers
     * @return self
     */
    public static function of(array $items): self
    {
        return new static($items);
    }

    /**
     * Create a new instance of Subset with the given items and quantity.
     *
     * @param int $quantity Required quantity for this subset
     * @return self
     */
    public function take(int $quantity): self
    {
        return new static($this->items, $quantity);
    }

    /**
     * Validate that items array is not empty and contains valid identifiers.
     *
     * @param array $items
     * @throws InvalidArgumentException
     */
    private function validateItems(array $items): void
    {
        if (empty($items)) {
            throw new InvalidArgumentException('Items array cannot be empty');
        }

        foreach ($items as $item) {
            if (!is_int($item) && !is_string($item)) {
                throw new InvalidArgumentException('Item identifiers must be integers or strings');
            }
        }
    }

    /**
     * Validate that quantity is positive.
     *
     * @param int $quantity
     * @throws InvalidArgumentException
     */
    private function validateQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero');
        }
    }

    /**
     * Get the total number of items in this subset.
     */
    public function getItemCount(): int
    {
        return count($this->items);
    }

    /**
     * Check if this subset contains a specific item.
     */
    public function contains(int|string $itemId): bool
    {
        return in_array($itemId, $this->items, true);
    }
}
