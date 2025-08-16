<?php

use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;
use Ozdemir\SubsetFinder\Exceptions\InvalidArgumentException;
use Ozdemir\SubsetFinder\Exceptions\InsufficientQuantityException;

it('can create a simple subset', function() {
    $subset = Subset::of([1, 2])->take(5);
    
    expect($subset)->toBeInstanceOf(Subset::class)
        ->and($subset->items)->toBe([1, 2])
        ->and($subset->quantity)->toBe(5);
});

it('can create a subset collection', function() {
    $subsetCollection = new SubsetCollection([
        Subset::of([1, 2])->take(5),
        Subset::of([3])->take(2),
    ]);
    
    expect($subsetCollection)->toBeInstanceOf(SubsetCollection::class)
        ->and($subsetCollection->count())->toBe(2);
});

it('can find subsets in a collection', function() {
    $collection = collect([
        $this->mockSubsetable(id: 1, quantity: 11, price: 15),
        $this->mockSubsetable(id: 2, quantity: 6, price: 5),
        $this->mockSubsetable(id: 3, quantity: 18, price: 10),
        $this->mockSubsetable(id: 5, quantity: 4, price: 2),
        $this->mockSubsetable(id: 12, quantity: 5, price: 6),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(5),
        Subset::of([3])->take(2),
    ]);

    $config = new SubsetFinderConfig(
        idField: 'id',
        quantityField: 'quantity',
        sortField: 'price',
        sortDescending: false
    );

    $subsetFinder = new SubsetFinder($collection, $setCollection, $config);
    $subsetFinder->solve();

    expect($subsetFinder->getSubsetQuantity())->toBe(3)
        ->and($this->convertToArray($subsetFinder->getRemaining()))->toBe([
            ["id" => 1, "quantity" => 2, "price" => 15],
            ["id" => 3, "quantity" => 12, "price" => 10],
            ["id" => 5, "quantity" => 4, "price" => 2],
            ["id" => 12, "quantity" => 5, "price" => 6],
        ])
        ->and($this->convertToArray($subsetFinder->getFoundSubsets()))->toBe([
            ['id' => 2, 'quantity' => 6, 'price' => 5,],
            ['id' => 1, 'quantity' => 9, 'price' => 15,],
            ['id' => 3, 'quantity' => 6, 'price' => 10,],
        ])
        ->and($subsetFinder->getFoundSubsets()->pluck('quantity', 'id')->toArray())->toBe([
            2 => 6,
            1 => 9,
            3 => 6,
        ]);
});

it('can find subsets in a collection with different field names', function() {
    $collection = collect([
        $this->mockSubsetableAlt(1, 11, 15),
        $this->mockSubsetableAlt(2, 6, 5),
        $this->mockSubsetableAlt(3, 18, 10),
        $this->mockSubsetableAlt(5, 4, 2),
        $this->mockSubsetableAlt(12, 5, 6),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(5),
        Subset::of([3])->take(2),
    ]);

    $config = new SubsetFinderConfig(
        idField: 'name',
        quantityField: 'amount',
        sortField: 'price',
        sortDescending: false
    );

    $subsetter = new SubsetFinder($collection, $setCollection, $config);
    $subsetter->solve();

    expect($subsetter->getSubsetQuantity())->toBe(3)
        ->and($this->convertToArray($subsetter->getFoundSubsets()))->toBe([
            ['name' => 2, 'amount' => 6, 'price' => 5,],
            ['name' => 1, 'amount' => 9, 'price' => 15,],
            ['name' => 3, 'amount' => 6, 'price' => 10,],
        ]);
});

it('can return empty if there is no subset found', function() {
    $collection = collect([
        $this->mockSubsetable(1, 11, 15),
        $this->mockSubsetable(2, 6, 5),
        $this->mockSubsetable(3, 18, 10),
        $this->mockSubsetable(5, 4, 2),
        $this->mockSubsetable(12, 5, 6),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(22),
        Subset::of([3])->take(2),
    ]);

    $config = new SubsetFinderConfig(
        idField: 'id',
        quantityField: 'quantity',
        sortField: 'price',
        sortDescending: false
    );

    $subsetFinder = new SubsetFinder($collection, $setCollection, $config);
    
    expect(fn() => $subsetFinder->solve())->toThrow(InsufficientQuantityException::class);
});

it('can cover all items in the collection and remaining will be empty array ', function() {
    $collection = collect([
        $this->mockSubsetable(1, 11, 15),
        $this->mockSubsetable(2, 6, 5),
        $this->mockSubsetable(3, 18, 10),
        $this->mockSubsetable(5, 4, 2),
        $this->mockSubsetable(12, 5, 6),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1])->take(11),
        Subset::of([2])->take(6),
        Subset::of([3])->take(18),
        Subset::of([5])->take(4),
        Subset::of([12])->take(5),
    ]);

    $config = new SubsetFinderConfig(
        idField: 'id',
        quantityField: 'quantity',
        sortField: 'price',
        sortDescending: false
    );

    $subsetFinder = new SubsetFinder($collection, $setCollection, $config);
    $subsetFinder->solve();

    expect($subsetFinder->getSubsetQuantity())->toBe(1)
        ->and($this->convertToArray($subsetFinder->getFoundSubsets()))->toBe($this->convertToArray($collection))
        ->and($this->convertToArray($subsetFinder->getRemaining()))->toBe([]);
});

it('can have multiple items from the collection to look up', function() {
    $collection = collect([
        $this->mockSubsetable(1, 11, 15),
        $this->mockSubsetable(2, 6, 5),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(11),
        Subset::of([1, 2])->take(6),
    ]);

    $config = new SubsetFinderConfig(
        idField: 'id',
        quantityField: 'quantity',
        sortField: 'price',
        sortDescending: true
    );

    $subsetFinder = new SubsetFinder($collection, $setCollection, $config);
    $subsetFinder->solve();

    expect($subsetFinder->getSubsetQuantity())->toBe(1)
        ->and($this->convertToArray($subsetFinder->getFoundSubsets()))->toBe($this->convertToArray($collection))
        ->and($this->convertToArray($subsetFinder->getRemaining()))->toBe([]);
});

it('can have a single item in the setCollections ', function() {
    $collection = collect([
        $this->mockSubsetable(1, 11, 15),
        $this->mockSubsetable(2, 6, 5),
        $this->mockSubsetable(3, 18, 10),
        $this->mockSubsetable(5, 4, 2),
        $this->mockSubsetable(12, 5, 6),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2, 3, 5, 12])->take(5),
    ]);

    $config = new SubsetFinderConfig(
        idField: 'id',
        quantityField: 'quantity',
        sortField: 'price',
        sortDescending: false
    );

    $subsetFinder = new SubsetFinder($collection, $setCollection, $config);
    $subsetFinder->solve();

    expect($subsetFinder->getSubsetQuantity())->toBe(8)
        ->and($this->convertToArray($subsetFinder->getFoundSubsets()))->toBe([
            ["id" => 5, "quantity" => 4, "price" => 2],
            ["id" => 2, "quantity" => 6, "price" => 5],
            ["id" => 12, "quantity" => 5, "price" => 6],
            ["id" => 3, "quantity" => 18, "price" => 10],
            ["id" => 1, "quantity" => 7, "price" => 15],
        ])
        ->and($this->convertToArray($subsetFinder->getRemaining()))->toBe([
            ["id" => 1, "quantity" => 4, "price" => 15],
        ]);
});

it('can get "n" many items by current order', function() {
    $collection = collect([
        $this->mockSubsetable(1, 11, 15),
        $this->mockSubsetable(2, 6, 5),
        $this->mockSubsetable(3, 18, 10),
        $this->mockSubsetable(5, 4, 2),
        $this->mockSubsetable(12, 5, 6),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2, 3, 5, 12])->take(5),
    ]);

    $config = new SubsetFinderConfig(
        idField: 'id',
        quantityField: 'quantity',
        sortField: 'price',
        sortDescending: false
    );

    $subsetFinder = new SubsetFinder($collection, $setCollection, $config);
    $subsetFinder->solve();

    expect($this->convertToArray($subsetFinder->getSubsetItems(11)))->toBe([
        ["id" => 5, "quantity" => 4, "price" => 2],
        ["id" => 5, "quantity" => 4, "price" => 2],
        ["id" => 5, "quantity" => 4, "price" => 2],
        ["id" => 5, "quantity" => 4, "price" => 2],
        ["id" => 2, "quantity" => 6, "price" => 5],
        ["id" => 2, "quantity" => 6, "price" => 5],
        ["id" => 2, "quantity" => 6, "price" => 5],
        ["id" => 2, "quantity" => 6, "price" => 5],
        ["id" => 2, "quantity" => 6, "price" => 5],
        ["id" => 2, "quantity" => 6, "price" => 5],
        ["id" => 12, "quantity" => 5, "price" => 6],
    ]);
});

it('can get the subsets with large number of sets', function() {
    $collection = collect([
        $this->mockSubsetable(1, 2500, 15),
        $this->mockSubsetable(2, 2000, 5),
        $this->mockSubsetable(3, 8000, 10),
        $this->mockSubsetable(5, 2900, 2),
        $this->mockSubsetable(12, 3650, 6),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(5),
        Subset::of([3])->take(5),
        Subset::of([5, 12])->take(5),
    ]);

    $config = new SubsetFinderConfig(
        idField: 'id',
        quantityField: 'quantity',
        sortField: 'price',
        sortDescending: false
    );

    $subsetFinder = new SubsetFinder($collection, $setCollection, $config);
    $subsetFinder->solve();

    expect($subsetFinder->getSubsetQuantity())->toBe(900)
        ->and($this->convertToArray($subsetFinder->getFoundSubsets()))->toBe([
            ["id" => 2, "quantity" => 2000, "price" => 5],
            ["id" => 1, "quantity" => 2500, "price" => 15],
            ["id" => 3, "quantity" => 4500, "price" => 10],
            ["id" => 5, "quantity" => 2900, "price" => 2],
            ["id" => 12, "quantity" => 1600, "price" => 6],
        ])
        ->and($this->convertToArray($subsetFinder->getRemaining()))->toBe([
            ["id" => 3, "quantity" => 3500, "price" => 10],
            ["id" => 12, "quantity" => 2050, "price" => 6],
        ]);
});

it('can use configuration profiles', function() {
    $collection = collect([
        $this->mockSubsetable(1, 100, 15),
        $this->mockSubsetable(2, 50, 5),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(10),
    ]);

    $subsetFinder = new SubsetFinder($collection, $setCollection, SubsetFinderConfig::forLargeDatasets());
    $subsetFinder->solve();

    expect($subsetFinder->getSubsetQuantity())->toBe(15);
});

it('can get performance metrics', function() {
    $collection = collect([
        $this->mockSubsetable(1, 10, 15),
        $this->mockSubsetable(2, 5, 5),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(5),
    ]);

    $subsetFinder = new SubsetFinder($collection, $setCollection);
    $subsetFinder->solve();

    $metrics = $subsetFinder->getPerformanceMetrics();
    
    expect($metrics)->toHaveKeys([
        'execution_time_ms',
        'memory_peak_mb',
        'memory_increase_mb',
        'collection_size',
        'subset_count',
        'found_subsets_count',
        'remaining_items_count'
    ]);
});

it('can check if solution is optimal', function() {
    $collection = collect([
        $this->mockSubsetable(1, 10, 15),
        $this->mockSubsetable(2, 5, 5),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1])->take(10),
        Subset::of([2])->take(5),
    ]);

    $subsetFinder = new SubsetFinder($collection, $setCollection);
    $subsetFinder->solve();

    expect($subsetFinder->isOptimal())->toBeTrue();
});

it('can calculate efficiency percentage', function() {
    $collection = collect([
        $this->mockSubsetable(1, 10, 15),
        $this->mockSubsetable(2, 5, 5),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(10),
    ]);

    $subsetFinder = new SubsetFinder($collection, $setCollection);
    $subsetFinder->solve();

    // Total items: 15, Used items: 10, Efficiency: 10/15 = 66.67%
    expect($subsetFinder->getEfficiencyPercentage())->toBe(66.67);
});

it('throws exception for invalid input', function() {
    $emptyCollection = collect();
    $setCollection = new SubsetCollection([
        Subset::of([1])->take(5),
    ]);

    expect(fn() => new SubsetFinder($emptyCollection, $setCollection))
        ->toThrow(InvalidArgumentException::class, 'Collection cannot be empty');
});

it('throws exception for invalid subset items', function() {
    expect(fn() => new Subset([], 5))
        ->toThrow(InvalidArgumentException::class, 'Items array cannot be empty');
});

it('throws exception for invalid quantity', function() {
    expect(fn() => new Subset([1, 2], 0))
        ->toThrow(InvalidArgumentException::class, 'Quantity must be greater than zero');
});

it('can use SubsetCollection helper methods', function() {
    $subsetCollection = new SubsetCollection([
        Subset::of([1, 2])->take(5),
        Subset::of([3])->take(2),
    ]);

    expect($subsetCollection->getAllItemIds()->toArray())->toBe([1, 2, 3])
        ->and($subsetCollection->getTotalRequiredQuantity())->toBe(7)
        ->and($subsetCollection->containsItem(1))->toBeTrue()
        ->and($subsetCollection->containsItem(4))->toBeFalse();
});

it('validates SubsetCollection items properly', function() {
    // This should work - valid Subset objects
    $validCollection = new SubsetCollection([
        Subset::of([1, 2])->take(5),
        Subset::of([3])->take(2),
    ]);
    
    expect($validCollection)->toBeInstanceOf(SubsetCollection::class)
        ->and($validCollection->count())->toBe(2);
});

it('throws exception for invalid SubsetCollection items', function() {
    // This should fail - mixing Subset objects with invalid data
    expect(fn() => new SubsetCollection([
        Subset::of([1, 2])->take(5),
        'invalid_item', // This should cause validation to fail
    ]))->toThrow(InvalidArgumentException::class, 'Item at index 1 is not a Subset instance');
});
