## SubsetFinder PHP Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ozdemir/subset-finder)](https://packagist.org/packages/ozdemir/subset-finder)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/n1crack/subset-finder/run-tests.yml)](https://github.com/n1crack/subset-finder/actions)
[![GitHub](https://img.shields.io/github/license/n1crack/subset-finder)](https://github.com/n1crack/subset-finder/blob/main/LICENSE.md)

The SubsetFinder package allows you to find subsets within a given collection based on specified criteria. It's particularly useful for scenarios where you need to extract subsets of items from a larger collection, such as in discount calculation or inventory management systems.

### Installation
You can install the SubsetFinder package via Composer:

```zsh
composer require ozdemir/subset-finder
```

### Usage
Here's a basic example of how to use the SubsetFinder package:

```php
use Ozdemir\SubsetFinder\SubsetFinder;

// Define your collection and subset criteria

$collection = collect([
  ["id" => 1, "quantity" => 11, "price" => 15],
  ["id" => 2, "quantity" => 6, "price" => 5],
  ["id" => 3, "quantity" => 6, "price" => 5]
    // Add more items...
]);

$subsetCriteria = collect([
    ["quantity" => 5, "items" => [1, 2]],
    ["quantity" => 2, "items" => [3]],
    // Add more criteria...
]);


// Instantiate SubsetFinder
$subsetter = new SubsetFinder($collection, $subsetCriteria);

// Optionally, configure sorting
$subsetter->sortBy('price');

// All subsets that meet the criteria
$subsets = $subsetter->get();

// $subsets will contain the subsets that meet the criteria
// output:
//  Illuminate\Support\Collection:
//  all:[
//    ["id" => 1, "quantity" => 11, "price" => 15],
//    ["id" => 2, "quantity" => 6, "price" => 5],
//    ["id" => 3, "quantity" => 6, "price" => 5]
//   ]

// Get remaining items
$remaining = $subsetter->getRemaining();
// $remaining will contain the items that were not included in any subset
// output: 
//  Illuminate\Support\Collection:
//  all:[
//    ["id" => 1, "quantity" => 2, "price" => 15],
//  ]

// Get the maximum quantity of sets that can be created from the collection.
$subSetQuantity = $subset->getSetQuantity()
// returns :
// 3

```

### Configuration

#### Prioritize items to be included in the subset
```php
// Seek subsets that meet the criteria
$subsetCriteria = collect([
    ["quantity" => 5, "items" => [1, 2, 3]], // Find a subset with a total quantity of 5 from items 1, 2, and 3 in the collection
    ["quantity" => 2, "items" => [4, 5]], // Find a subset with a total quantity of 2 from items 4 and 5 in the collection
    ["quantity" => 5, "items" => [12]], // Find a subset with a total quantity of 5 from item 12 in the collection
    // etc...
]);

// When we have multiple applicable subsets, we can choose to prioritize the ones
// with any field that exists in the main collection.
$subsetter->sortBy('price');
```

#### Define the field names for the quantity, items and id fields. 

```php
// We can use the fields with the defined names.
$subsetter->defineProps(
    quantity: 'amount', 
    items: 'products', 
    id: 'name'
);
$collection = collect([
    ["name" => 1, "amount" => 11, "price" => 15],
    // Add more items...
]);

$setCollection = collect([
    ["amount" => 5, "products" => [1, 2]],
    // Add more sets...
]);
```

### Testing
You can run the tests with:

```zsh
composer test
```

### Contributing
Contributions are welcome! If you find any issues or have suggestions for improvements, please open an issue or create a pull request on GitHub.

### License
This package is open-sourced software licensed under the MIT license.
