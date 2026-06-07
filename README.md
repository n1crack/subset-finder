# SubsetFinder PHP Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ozdemir/subset-finder)](https://packagist.org/packages/ozdemir/subset-finder)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/n1crack/subset-finder/run-tests.yml)](https://github.com/n1crack/subset-finder/actions)
[![GitHub](https://github.com/n1crack/subset-finder/blob/main/LICENSE.md)](https://github.com/n1crack/subset-finder/blob/main/LICENSE.md)

A dependency-free PHP package for finding subsets within collections based on quantity criteria.

Given a pool of items with quantities, it answers: *"How many complete sets can I build, which items go into them, and what is left over?"* — useful for bundle pricing, cart discounts ("buy 5 of X and 2 of Y"), and inventory allocation.

## Features

- **Pure arithmetic solver**: quantities are never expanded into unit items, so memory stays flat and quantities in the billions solve in milliseconds
- **Overlap aware**: subsets sharing the same item ids draw from a shared pool and are never double counted
- **Flexible ordering**: allocate cheapest (or any sort order) items first
- **Type safe**: PHP 8.1+, strict `Subsetable` interface
- **Zero dependencies**: plain PHP; accepts arrays or any iterable (including Laravel collections)

## Installation

```bash
composer require ozdemir/subset-finder
```

## Quick Start

```php
use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

// Define your collection and subset criteria.
// Any iterable works: a plain array, a generator, or a Laravel collection.
$collection = [
    new Product(id: 1, quantity: 11, price: 15),
    new Product(id: 2, quantity: 6, price: 5),
    new Product(id: 3, quantity: 6, price: 5),
];

$subsetCollection = new SubsetCollection([
    Subset::of([1, 2])->take(5), // Each set needs 5 items from products 1 and 2
    Subset::of([3])->take(2),    // ...and 2 items from product 3
]);

// Allocate the cheapest items first
$config = new SubsetFinderConfig(sortField: 'price');

$subsetFinder = new SubsetFinder($collection, $subsetCollection, $config);
$subsetFinder->solve();

$subsetFinder->getSubsetQuantity(); // Max number of complete sets
$subsetFinder->getFoundSubsets();   // Items used per id (Subsetable[])
$subsetFinder->getRemaining();      // Leftover quantities (Subsetable[])
```

### The Subsetable interface

Collection items must implement `Subsetable`:

```php
use Ozdemir\SubsetFinder\Subsetable;

class Product implements Subsetable
{
    public function __construct(
        public int|string $id,
        public int $quantity,
        public float $price,
    ) {
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
```

Item ids and quantities are read through the interface, so your property names don't matter. Only `sortField` in the config refers to a property of your objects.

### Configuration

```php
$config = new SubsetFinderConfig(
    sortField: 'price',    // Property used to order allocation (default: 'id')
    sortDescending: false  // Ascending = cheapest first (default)
);
```

### Using the Trait

Add subset operations to any iterable collection class of your own — for example a Laravel collection:

```php
use Illuminate\Support\Collection;
use Ozdemir\SubsetFinder\Traits\HasSubsetOperations;

class ProductCollection extends Collection
{
    use HasSubsetOperations;
}

$products = new ProductCollection([/* Subsetable items */]);

$subsetFinder = $products->findSubsets($subsetCollection);
$products->canSatisfySubsets($subsetCollection);   // bool
$products->getMaxSubsetQuantity($subsetCollection); // int
```

### Other methods

```php
$subsetFinder->getSubsetItems(10);          // First 10 units in sort order
$subsetFinder->isOptimal();                 // true if nothing is left over
$subsetFinder->getEfficiencyPercentage();   // Used / total quantity
$subsetFinder->getPerformanceMetrics();     // Timing and counts of the last solve()
```

## How it works

1. Quantities are aggregated per item id; items are sorted by `sortField`.
2. The maximum number of complete sets is found by binary search. For each candidate, subsets claim quantities from the shared pool in definition order, consuming items in sort order.
3. The winning allocation becomes `getFoundSubsets()`; whatever is left becomes `getRemaining()`.

The solver never materializes individual units, so runtime and memory depend on the number of *distinct items*, not their quantities.

## Error Handling

```php
use Ozdemir\SubsetFinder\Exceptions\InsufficientQuantityException;
use Ozdemir\SubsetFinder\Exceptions\InvalidArgumentException;

try {
    $subsetFinder = new SubsetFinder($collection, $subsetCollection);
    $subsetFinder->solve();
} catch (InvalidArgumentException $e) {
    // Empty collection, or items not implementing Subsetable
} catch (InsufficientQuantityException $e) {
    // Not even one complete set can be built
}
```

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer analyse
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
