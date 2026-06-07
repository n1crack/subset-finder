<?php

namespace Ozdemir\SubsetFinder\Tests;

use Ozdemir\SubsetFinder\Exceptions\InsufficientQuantityException;
use Ozdemir\SubsetFinder\Exceptions\InvalidArgumentException;
use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

class EdgeCaseTest extends TestCase
{
    /**
     * Test with extremely large quantities; quantities are never expanded,
     * so this must be exact and fast.
     */
    public function test_with_extremely_large_quantities(): void
    {
        $collection = [
            $this->mockSubsetable(1, 10000, 100),
            $this->mockSubsetable(2, 10000, 200),
        ];

        $subsetCollection = new SubsetCollection([
            Subset::of([1, 2])->take(1000),
        ]);

        $subsetFinder = new SubsetFinder($collection, $subsetCollection);
        $subsetFinder->solve();

        $this->assertEquals(20, $subsetFinder->getSubsetQuantity());
    }

    /**
     * Test with zero quantities
     */
    public function test_with_zero_quantities(): void
    {
        $collection = [
            $this->mockSubsetable(1, 0, 100),
            $this->mockSubsetable(2, 10, 200),
        ];

        $subsetCollection = new SubsetCollection([
            Subset::of([1, 2])->take(5),
        ]);

        $subsetFinder = new SubsetFinder($collection, $subsetCollection);

        // With quantity 0 for item 1, we can still create 2 subsets
        // because (0 + 10) / 5 = 2
        $subsetFinder->solve();
        $this->assertEquals(2, $subsetFinder->getSubsetQuantity());
    }

    /**
     * Test with empty subset collection
     */
    public function test_with_empty_subset_collection(): void
    {
        $collection = [
            $this->mockSubsetable(1, 10, 100),
        ];

        $emptySubsetCollection = new SubsetCollection([]);

        $this->expectException(InvalidArgumentException::class);
        new SubsetFinder($collection, $emptySubsetCollection);
    }

    /**
     * Test with single item subsets
     */
    public function test_with_single_item_subsets(): void
    {
        $collection = [
            $this->mockSubsetable(1, 100, 100),
            $this->mockSubsetable(2, 50, 200),
        ];

        $subsetCollection = new SubsetCollection([
            Subset::of([1])->take(10),
            Subset::of([2])->take(5),
        ]);

        $subsetFinder = new SubsetFinder($collection, $subsetCollection);
        $subsetFinder->solve();

        $this->assertEquals(10, $subsetFinder->getSubsetQuantity());
        $this->assertTrue($subsetFinder->isOptimal());
    }

    /**
     * Test with overlapping item IDs: subsets drawing from the same pool
     * must not double count availability.
     */
    public function test_with_overlapping_item_ids(): void
    {
        $collection = [
            $this->mockSubsetable(1, 100, 100),
            $this->mockSubsetable(2, 50, 200),
        ];

        $subsetCollection = new SubsetCollection([
            Subset::of([1, 2])->take(10),
            Subset::of([1])->take(20),
            Subset::of([2])->take(15),
        ]);

        $subsetFinder = new SubsetFinder($collection, $subsetCollection);
        $subsetFinder->solve();

        // Each round consumes 10 + 20 + 15 = 45 items from a pool of 150,
        // but item 1 alone caps rounds at 3 (3 * (10 + 20) = 90 <= 100).
        $this->assertEquals(3, $subsetFinder->getSubsetQuantity());

        // 4 rounds would need 4 * 45 = 180 > 150 items in total.
        $allocated = array_sum(array_map(fn($item) => $item->getQuantity(), $subsetFinder->getFoundSubsets()));
        $this->assertEquals(3 * 45, $allocated);
    }

    /**
     * Test with very small quantities
     */
    public function test_with_very_small_quantities(): void
    {
        $collection = [
            $this->mockSubsetable(1, 1, 100),
            $this->mockSubsetable(2, 1, 200),
        ];

        $subsetCollection = new SubsetCollection([
            Subset::of([1, 2])->take(1),
        ]);

        $subsetFinder = new SubsetFinder($collection, $subsetCollection);
        $subsetFinder->solve();

        // With quantities 1 and 1, and requiring 1 of each, we can create 2 subsets
        // (1 + 1) / 1 = 2
        $this->assertEquals(2, $subsetFinder->getSubsetQuantity());
    }

    /**
     * Test with mixed data types in collections
     */
    public function test_with_mixed_data_types(): void
    {
        $collection = [
            $this->mockSubsetable(1, 10, 100),
            $this->mockSubsetable(2, 20, 200),
            'invalid_item', // This should cause an error
        ];

        $subsetCollection = new SubsetCollection([
            Subset::of([1, 2])->take(5),
        ]);

        $this->expectException(InvalidArgumentException::class);
        new SubsetFinder($collection, $subsetCollection);
    }

    /**
     * Test with extreme sorting scenarios
     */
    public function test_with_extreme_sorting_scenarios(): void
    {
        $collection = [
            $this->mockSubsetable(1, 10, 0.001),      // Very small price
            $this->mockSubsetable(2, 20, 999999.99),  // Very large price
            $this->mockSubsetable(3, 15, 100.00),     // Normal price
        ];

        $subsetCollection = new SubsetCollection([
            Subset::of([1, 2, 3])->take(5),
        ]);

        // Test ascending sort
        $ascConfig = new SubsetFinderConfig(sortField: 'price', sortDescending: false);
        $subsetFinder = new SubsetFinder($collection, $subsetCollection, $ascConfig);
        $subsetFinder->solve();

        $this->assertEquals(9, $subsetFinder->getSubsetQuantity());

        // Test descending sort
        $descConfig = new SubsetFinderConfig(sortField: 'price', sortDescending: true);
        $subsetFinder = new SubsetFinder($collection, $subsetCollection, $descConfig);
        $subsetFinder->solve();

        $this->assertEquals(9, $subsetFinder->getSubsetQuantity());
    }

    /**
     * Test with subsets referencing ids that do not exist in the collection
     */
    public function test_with_empty_collections_after_filtering(): void
    {
        $collection = [
            $this->mockSubsetable(1, 10, 100),
        ];

        $subsetCollection = new SubsetCollection([
            Subset::of([999])->take(5), // Non-existent item ID
        ]);

        $subsetFinder = new SubsetFinder($collection, $subsetCollection);

        // Should throw exception for insufficient quantities
        $this->expectException(InsufficientQuantityException::class);
        $subsetFinder->solve();
    }

    /**
     * Test with very large numbers of subsets
     */
    public function test_with_very_large_numbers_of_subsets(): void
    {
        $collection = $this->createLargeCollection(50);
        $subsetCollection = $this->createLargeSubsetCollection(100); // Many subsets

        $subsetFinder = new SubsetFinder($collection, $subsetCollection);

        // Should not crash with many subsets; result is either a valid
        // quantity or a clean insufficient-quantity exception.
        try {
            $subsetFinder->solve();
            $this->assertGreaterThan(0, $subsetFinder->getSubsetQuantity());
        } catch (InsufficientQuantityException $e) {
            $this->assertEquals(0, $subsetFinder->getSubsetQuantity());
        }
    }

    /**
     * Create a large collection for testing
     */
    private function createLargeCollection(int $size): array
    {
        $collection = [];

        for ($i = 1; $i <= $size; $i++) {
            $collection[] = $this->mockSubsetable(
                $i,
                rand(10, 1000),
                rand(1000, 10000) / 100
            );
        }

        return $collection;
    }

    /**
     * Create a large subset collection for testing
     */
    private function createLargeSubsetCollection(int $size): SubsetCollection
    {
        $subsets = [];

        for ($i = 0; $i < $size; $i++) {
            $itemCount = rand(1, 3);
            $items = [];

            for ($j = 0; $j < $itemCount; $j++) {
                $items[] = rand(1, 50); // Ensure item IDs are within collection range
            }

            $subsets[] = Subset::of(array_unique($items))->take(rand(1, 5)); // Smaller quantities
        }

        return new SubsetCollection($subsets);
    }
}
