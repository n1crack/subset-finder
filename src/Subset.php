<?php

namespace Ozdemir\SubsetFinder;

class Subset
{
    public array $items;
    public int $quantity;

    /**
     * Subset constructor.
     *
     * @param array $items
     * @param int $quantity
     */
    public function __construct(array $items, int $quantity = 1)
    {
        $this->items = $items;
        $this->quantity = $quantity;
    }

    /**
     * Create a new instance of Subset with the given items.
     *
     * @param array $items
     * @return self
     */
    public static function of(array $items): self
    {
        return new static($items);
    }

    /**
     * Create a new instance of Subset with the given items and quantity.
     *
     * @param int $quantity
     * @return $this
     */
    public function take(int $quantity): self
    {
        return new static($this->items, $quantity);
    }
}
