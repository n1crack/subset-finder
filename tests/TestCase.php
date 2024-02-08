<?php

namespace Ozdemir\SubsetFinder\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Ozdemir\SubsetFinder\Subsetable;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function convertToArray($subsetables): array
    {
        return $subsetables->map(fn ($subsetable) => $subsetable->toArray())->toArray();
    }

    protected function mockSubsetable($id, $quantity, $price): Subsetable
    {
        return new class ($id, $quantity, $price) implements Subsetable {
            public function __construct(
                public mixed $id,
                public mixed $quantity,
                public float|int $price
            ) {
            }

            public function getId(): mixed
            {
                return $this->id;
            }

            public function getQuantity(): mixed
            {
                return $this->quantity;
            }

            public function setQuantity($quantity): void
            {
                $this->quantity = $quantity;
            }

            public function toArray()
            {
                return [
                    'id' => $this->getId(),
                    'quantity' => $this->getQuantity(),
                    'price' => $this->price,
                ];
            }
        };
    }

    protected function mockSubsetableAlt($name, $amount, $price): Subsetable
    {
        return new class ($name, $amount, $price) implements Subsetable {
            public function __construct(
                public mixed $name,
                public mixed $amount,
                public float|int $price
            ) {
            }

            public function getId(): mixed
            {
                return $this->name;
            }

            public function getQuantity(): mixed
            {
                return $this->amount;
            }

            public function setQuantity($amount): void
            {
                $this->amount = $amount;
            }

            public function toArray()
            {
                return [
                    'name' => $this->getId(),
                    'amount' => $this->getQuantity(),
                    'price' => $this->price,
                ];
            }
        };
    }
}
