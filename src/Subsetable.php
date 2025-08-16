<?php

namespace Ozdemir\SubsetFinder;

interface Subsetable
{
    /**
     * Get the unique identifier for this item.
     */
    public function getId(): int|string;

    /**
     * Get the quantity of this item.
     */
    public function getQuantity(): int;

    /**
     * Set the quantity of this item.
     */
    public function setQuantity(int $quantity): void;
}
