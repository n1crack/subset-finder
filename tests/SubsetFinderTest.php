<?php

use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;

it('can find subsets in a collection', function() {
    $collection = collect([
        ["id" => 1, "quantity" => 11, "price" => 15],
        ["id" => 2, "quantity" => 6, "price" => 5],
        ["id" => 3, "quantity" => 18, "price" => 10],
        ["id" => 5, "quantity" => 4, "price" => 2],
        ["id" => 12, "quantity" => 5, "price" => 6],
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(5),
        Subset::of([3])->take(2),
    ]);

    $subsetter = new SubsetFinder($collection, $setCollection);
    $subsetter->sortBy('price');

    expect($subsetter->getSetQuantity())->toBe(3)
        ->and($subsetter->getRemaining()->toArray())->toBe([
            ["id" => 1, "quantity" => 2, "price" => 15],
            ["id" => 3, "quantity" => 12, "price" => 10],
            ["id" => 5, "quantity" => 4, "price" => 2],
            ["id" => 12, "quantity" => 5, "price" => 6],
        ])
        ->and($subsetter->get()->toArray())->toBe([
            ['id' => 2, 'quantity' => 6, 'price' => 5,],
            ['id' => 1, 'quantity' => 9, 'price' => 15,],
            ['id' => 3, 'quantity' => 6, 'price' => 10,],
        ])
        ->and($subsetter->get()->pluck('quantity', 'id')->toArray())->toBe([
            2 => 6,
            1 => 9,
            3 => 6,
        ]);
});

it('can find subsets in a collection with different field names', function() {
    $collection = collect([
        ["name" => 1, "amount" => 11, "price" => 15],
        ["name" => 2, "amount" => 6, "price" => 5],
        ["name" => 3, "amount" => 18, "price" => 10],
        ["name" => 5, "amount" => 4, "price" => 2],
        ["name" => 12, "amount" => 5, "price" => 6],
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(5),
        Subset::of([3])->take(2),
    ]);

    $subsetter = new SubsetFinder($collection, $setCollection);
    $subsetter->defineProps(id: 'name', quantity: 'amount');
    $subsetter->sortBy('price');

    expect($subsetter->getSetQuantity())->toBe(3)
        ->and($subsetter->get()->toArray())->toBe([
            ['name' => 2, 'amount' => 6, 'price' => 5,],
            ['name' => 1, 'amount' => 9, 'price' => 15,],
            ['name' => 3, 'amount' => 6, 'price' => 10,],
        ]);
});

it('returns blank if it doesnt find anything', function() {
    $collection = collect([
        ["id" => 1, "quantity" => 11, "price" => 15],
        ["id" => 2, "quantity" => 6, "price" => 5],
        ["id" => 3, "quantity" => 18, "price" => 10],
        ["id" => 5, "quantity" => 4, "price" => 2],
        ["id" => 12, "quantity" => 5, "price" => 6],
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(22),
        Subset::of([3])->take(2),
    ]);

    $subsetter = new SubsetFinder($collection, $setCollection);
    $subsetter->sortBy('price');

    expect($subsetter->getSetQuantity())->toBe(0)
        // blank array
        ->and($subsetter->get()->toArray())->toBe([])
        // its same as the collection
        ->and($subsetter->getRemaining()->toArray())->toBe($collection->toArray());
});

it('can cover all items in the collection ', function() {
    $collection = collect([
        ["id" => 1, "quantity" => 11, "price" => 15],
        ["id" => 2, "quantity" => 6, "price" => 5],
        ["id" => 3, "quantity" => 18, "price" => 10],
        ["id" => 5, "quantity" => 4, "price" => 2],
        ["id" => 12, "quantity" => 5, "price" => 6],
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1])->take(11),
        Subset::of([2])->take(6),
        Subset::of([3])->take(18),
        Subset::of([5])->take(4),
        Subset::of([12])->take(5),
    ]);

    $subsetter = new SubsetFinder($collection, $setCollection);
    $subsetter->sortBy('price');

    expect($subsetter->getSetQuantity())->toBe(1)
        // blank array
        ->and($subsetter->get()->toArray())->toBe($collection->toArray())
        // its same as the collection
        ->and($subsetter->getRemaining()->toArray())->toBe([]);
});

it('can have multiple items from the collection to look up', function() {
    $collection = collect([
        ["id" => 1, "quantity" => 11, "price" => 15],
        ["id" => 2, "quantity" => 6, "price" => 5],
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(11),
        Subset::of([1, 2])->take(6),
    ]);

    $subsetter = new SubsetFinder($collection, $setCollection);
    // it will be the same as the collection
    // the main collection is already sorted so should get the subsets with the same order
    // default is $subsetter->sortBy('id'); so no need to call it
    // but normally we would want to sort it by price
    $subsetter->sortBy('price', true);

    expect($subsetter->getSetQuantity())->toBe(1)
        // blank array
        ->and($subsetter->get()->toArray())->toBe($collection->toArray())
        // its same as the collection
        ->and($subsetter->getRemaining()->toArray())->toBe([]);
});

it('can have a single item in the setCollections ', function() {
    $collection = collect([
        ["id" => 1, "quantity" => 11, "price" => 15],
        ["id" => 2, "quantity" => 6, "price" => 5],
        ["id" => 3, "quantity" => 18, "price" => 10],
        ["id" => 5, "quantity" => 4, "price" => 2],
        ["id" => 12, "quantity" => 5, "price" => 6],
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2, 3, 5, 12])->take(5),
    ]);

    $subsetter = new SubsetFinder($collection, $setCollection);
    $subsetter->sortBy('price');

    // total count is 44 so 44 / 5 = 8 + 4 remaining
    expect($subsetter->getSetQuantity())->toBe(8)
        // we get almost all item except the last 4 items
        // and we sorted the collection by price
        // so the last 4 item will be the most expensive ones
        ->and($subsetter->get()->toArray())->toBe([
            ["id" => 5, "quantity" => 4, "price" => 2],
            ["id" => 2, "quantity" => 6, "price" => 5],
            ["id" => 12, "quantity" => 5, "price" => 6],
            ["id" => 3, "quantity" => 18, "price" => 10],
            ["id" => 1, "quantity" => 7, "price" => 15],
        ])
        // last 4 item will be the most expensive ones so its product 1 in this case
        ->and($subsetter->getRemaining()->toArray())->toBe([
            ["id" => 1, "quantity" => 4, "price" => 15],
        ]);
});

it('can get n many items as ordered', function() {
    $collection = collect([
        ["id" => 1, "quantity" => 11, "price" => 15],
        ["id" => 2, "quantity" => 6, "price" => 5],
        ["id" => 3, "quantity" => 18, "price" => 10],
        ["id" => 5, "quantity" => 4, "price" => 2],
        ["id" => 12, "quantity" => 5, "price" => 6],
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2, 3, 5, 12])->take(5),
    ]);

    $subsetter = new SubsetFinder($collection, $setCollection);
    $subsetter->sortBy('price');


    // somemethod will return the first 11 items that is ordered
    expect($subsetter->getSubsetItems(11)->toArray())->toBe([
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
        ["id" => 1, "quantity" => 2500, "price" => 15],
        ["id" => 2, "quantity" => 2000, "price" => 5],
        ["id" => 3, "quantity" => 8000, "price" => 10],
        ["id" => 5, "quantity" => 2900, "price" => 2],
        ["id" => 12, "quantity" => 3650, "price" => 6],
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(5),
        Subset::of([3])->take(5),
        Subset::of([5, 12])->take(5),
    ]);

    $subsetter = new SubsetFinder($collection, $setCollection);
    $subsetter->sortBy('price');

    expect($subsetter->getSetQuantity())->toBe(900)
        ->and($subsetter->get()->toArray())->toBe([
            ["id" => 2, "quantity" => 2000, "price" => 5],
            ["id" => 1, "quantity" => 2500, "price" => 15],
            ["id" => 3, "quantity" => 4500, "price" => 10],
            ["id" => 5, "quantity" => 2900, "price" => 2],
            ["id" => 12, "quantity" => 1600, "price" => 6],
        ])
        ->and($subsetter->getRemaining()->toArray())->toBe([
            ["id" => 3, "quantity" => 3500, "price" => 10],
            ["id" => 12, "quantity" => 2050, "price" => 6],
        ]);
});
