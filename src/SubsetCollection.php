<?php

namespace Ozdemir\SubsetFinder;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Ozdemir\SubsetFinder\Exceptions\InvalidArgumentException;

class SubsetCollection implements Countable, IteratorAggregate
{
    /** @var Subset[] */
    private array $subsets = [];

    /**
     * @param iterable<mixed> $subsets Validated to contain only Subset instances.
     * @throws InvalidArgumentException
     */
    public function __construct(iterable $subsets = [])
    {
        foreach ($subsets as $index => $subset) {
            if (!$subset instanceof Subset) {
                throw new InvalidArgumentException(
                    "Item at index {$index} is not a Subset instance. " .
                    "Got: " . (is_object($subset) ? get_class($subset) : gettype($subset))
                );
            }

            $this->subsets[] = $subset;
        }
    }

    /**
     * Add a subset to the collection.
     *
     * @return $this
     */
    public function addSubset(Subset $subset): self
    {
        $this->subsets[] = $subset;

        return $this;
    }

    /**
     * @return Subset[]
     */
    public function all(): array
    {
        return $this->subsets;
    }

    public function count(): int
    {
        return count($this->subsets);
    }

    public function isEmpty(): bool
    {
        return $this->subsets === [];
    }

    /**
     * @return ArrayIterator<int, Subset>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->subsets);
    }

    /**
     * Get all unique item IDs from all subsets.
     *
     * @return array<int, int|string>
     */
    public function getAllItemIds(): array
    {
        $ids = [];

        foreach ($this->subsets as $subset) {
            foreach ($subset->items as $id) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Get the total quantity required across all subsets.
     */
    public function getTotalRequiredQuantity(): int
    {
        return array_sum(array_map(fn(Subset $subset) => $subset->quantity, $this->subsets));
    }

    /**
     * Check if any subset contains a specific item.
     */
    public function containsItem(int|string $itemId): bool
    {
        foreach ($this->subsets as $subset) {
            if ($subset->contains($itemId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter subsets by item ID.
     */
    public function filterByItem(int|string $itemId): self
    {
        return new self(array_filter($this->subsets, fn(Subset $subset) => $subset->contains($itemId)));
    }
}
