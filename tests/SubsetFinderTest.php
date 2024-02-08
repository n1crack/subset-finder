<?php

use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;

it('can find subsets in a collection', function () {
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

    $subsetFinder = new SubsetFinder($collection, $setCollection);
    $subsetFinder->sortBy('price');
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

it('can find subsets in a collection with different field names', function () {
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

    $subsetter = new SubsetFinder($collection, $setCollection);
    $subsetter->defineProps(id: 'name', quantity: 'amount');
    $subsetter->sortBy('price');
    $subsetter->solve();

    expect($subsetter->getSubsetQuantity())->toBe(3)
        ->and($this->convertToArray($subsetter->getFoundSubsets()))->toBe([
            ['name' => 2, 'amount' => 6, 'price' => 5,],
            ['name' => 1, 'amount' => 9, 'price' => 15,],
            ['name' => 3, 'amount' => 6, 'price' => 10,],
        ]);
});

it('returns blank if it doesnt find anything', function () {
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

    $subsetFinder = new SubsetFinder($collection, $setCollection);
    $subsetFinder->sortBy('price');
    $subsetFinder->solve();

    expect($subsetFinder->getSubsetQuantity())->toBe(0)
        // blank array
        ->and($this->convertToArray($subsetFinder->getFoundSubsets()))->toBe([])
        // its same as the collection
        ->and($this->convertToArray($subsetFinder->getRemaining()))->toBe($this->convertToArray($collection));
});

it('can cover all items in the collection ', function () {
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

    $subsetFinder = new SubsetFinder($collection, $setCollection);
    $subsetFinder->sortBy('price');
    $subsetFinder->solve();

    expect($subsetFinder->getSubsetQuantity())->toBe(1)
        // its same as the collection
        ->and($this->convertToArray($subsetFinder->getFoundSubsets()))->toBe($this->convertToArray($collection))

        // blank array
        ->and($this->convertToArray($subsetFinder->getRemaining()))->toBe([]);
});

it('can have multiple items from the collection to look up', function () {
    $collection = collect([
        $this->mockSubsetable(1, 11, 15),
        $this->mockSubsetable(2, 6, 5),
    ]);

    $setCollection = new SubsetCollection([
        Subset::of([1, 2])->take(11),
        Subset::of([1, 2])->take(6),
    ]);

    $subsetFinder = new SubsetFinder($collection, $setCollection);
    // it will be the same as the collection
    // the main collection is already sorted so should get the subsets with the same order
    // default is $subsetFinder->sortBy('id'); so no need to call it
    // but normally we would want to sort it by price
    $subsetFinder->sortBy('price', true);
    $subsetFinder->solve();

    expect($subsetFinder->getSubsetQuantity())->toBe(1)
        // blank array
        ->and($this->convertToArray($subsetFinder->getFoundSubsets()))->toBe($this->convertToArray($collection))
        // its same as the collection
        ->and($this->convertToArray($subsetFinder->getRemaining()))->toBe([]);
});

it('can have a single item in the setCollections ', function () {
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

    $subsetFinder = new SubsetFinder($collection, $setCollection);
    $subsetFinder->sortBy('price');
    $subsetFinder->solve();

    // total count is 44 so 44 / 5 = 8 + 4 remaining
    expect($subsetFinder->getSubsetQuantity())->toBe(8)
        // we get almost all item except the last 4 items
        // and we sorted the collection by price
        // so the last 4 item will be the most expensive ones
        ->and($this->convertToArray($subsetFinder->getFoundSubsets()))->toBe([
            ["id" => 5, "quantity" => 4, "price" => 2],
            ["id" => 2, "quantity" => 6, "price" => 5],
            ["id" => 12, "quantity" => 5, "price" => 6],
            ["id" => 3, "quantity" => 18, "price" => 10],
            ["id" => 1, "quantity" => 7, "price" => 15],
        ])
        // last 4 item will be the most expensive ones so its product 1 in this case
        ->and($this->convertToArray($subsetFinder->getRemaining()))->toBe([
            ["id" => 1, "quantity" => 4, "price" => 15],
        ]);
});

it('can get n many items as ordered', function () {
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

    $subsetFinder = new SubsetFinder($collection, $setCollection);
    $subsetFinder->sortBy('price');
    $subsetFinder->solve();

    // somemethod will return the first 11 items that is ordered
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


it('can get the subsets with large number of sets', function () {
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

    $subsetFinder = new SubsetFinder($collection, $setCollection);
    $subsetFinder->sortBy('price');
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
