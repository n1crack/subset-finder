# SubsetFinder PHP Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ozdemir/subset-finder)](https://packagist.org/packages/ozdemir/subset-finder)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/n1crack/subset-finder/run-tests.yml)](https://github.com/n1crack/subset-finder/actions)
[![GitHub](https://img.shields.io/github/license/n1crack/subset-finder)](https://github.com/n1crack/subset-finder/blob/main/LICENSE.md)

The SubsetFinder PHP Package, utilizing Laravel collection functionality, empowers users to efficiently identify subsets within a given collection according to defined criteria. This tool proves invaluable in scenarios requiring extraction of specific item subsets from a larger collection, such as in discount calculation algorithms or inventory management systems

## Installation
You can install the SubsetFinder package via Composer:

```zsh
composer require ozdemir/subset-finder
```

## Usage
Here's a basic example of how to use the SubsetFinder package: 


```php
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\Subset;

// Define your collection and subset criteria

// Collection should be an instance of Illuminate\Support\Collection
// and contain items that implement the Ozdemir\SubsetFinder\Subsetable interface.

// example class that implements the Subsetable interface 
// if you use field names other than 'id' and 'quantity', you need to define them with defineProps method
class Something implements Subsetable
{
    public function __construct(public int $id, public int $quantity, public int $price)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}

$collection = collect([
  new Something(id: 1, quantity: 11, price: 15),
  new Something(id: 2, quantity: 6, price: 5),
  new Something(id: 3, quantity: 6, price: 5)
   // Add more items...
]);

$subsetCollection = new SubsetCollection([
    Subset::of([1, 2])->take(5),
    Subset::of([3])->take(2),
    // Add more criteria...
]);


// Instantiate SubsetFinder
$subsetter = new SubsetFinder($collection, $subsetCollection);

// Optionally, configure sorting
$subsetter->sortBy('price');
// Solve the problem
$subsetter->solve();

// $subsets will contain the subsets that meet the criteria
$subsets = $subsetter->getFoundSubsets();
//  Illuminate\Support\Collection:
//  all:[
//    Something(id: 2, quantity: 6, price: 5),
//    Something(id: 1, quantity: 9, price: 15),
//    Something(id: 3, quantity: 6, price: 5)
//   ]

// $remaining will contain the items that were not selected for any subset
$remaining = $subsetter->getRemaining();
//  Illuminate\Support\Collection:
//  all:[
//    Something(id: 1, quantity: 2, price: 15),
//  ]

// Get the maximum quantity of sets that can be created from the collection.
$subSetQuantity = $subsetter->getSubsetQuantity()
// 3

```

You can check the tests for more examples.

## Configuration

### Prioritize items to be included in the subset
```php
// Seek subsets that meet the criteria
$subsetCollection = new SubsetCollection([
    Subset::of([1, 2, 3])->take(5), // Find a subset with a total quantity of 5 from items 1, 2, and 3 in the collection
    Subset::of([4, 5])->take(2), // Find a subset with a total quantity of 2 from items 4 and 5 in the collection
    Subset::of([12])->take(5), // Find a subset with a total quantity of 5 from item 12 in the collection
    // etc...
]);

// When we have multiple applicable items for a subset, we can choose to prioritize the ones
// with any field that exists in the main collection.
$subsetter->sortBy('price');
$subsetter->solve();

```

### Define the field names for the quantity, items and id fields. 

```php

$collection = collect([
    new Something() // let's say, we have an object with the ["name" => 1, "amount" => 11, "price" => 15]
    // Add more items...
]);

// Find a subset with a total amount of 5 from items named 1 and 2 in the collection
// this part doesn't change
$setCollection = collect([
     Subset::of([1, 2])->take(5) 
    // define more...
]);

// We need to define the field names for the 'quantity' and 'id' fields.
$subsetter->defineProps(
    id: 'name',
    quantity: 'amount'
);

$subsetter->solve();

```

## Testing
You can run the tests with:

```zsh
composer test
```

## Contributing
Contributions are welcome! If you find any issues or have suggestions for improvements, please open an issue or create a pull request on GitHub.

## License
This package is open-sourced software licensed under the MIT license.
