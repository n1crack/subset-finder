<?php

namespace Ozdemir\SubsetFinder\Tests;

use Ozdemir\SubsetFinder\Exceptions\InsufficientQuantityException;
use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;

class PerformanceTest extends TestCase
{
    /**
     * Test performance with different dataset sizes
     */
    public function test_performance_with_different_dataset_sizes(): void
    {
        $datasetSizes = [50, 100, 200, 500];

        foreach ($datasetSizes as $size) {
            $this->runPerformanceTest($size);
        }
    }

    /**
     * Test memory usage with large datasets: the solver works on per-id
     * quantities, so memory must stay flat regardless of item quantities.
     */
    public function test_memory_usage_with_large_datasets(): void
    {
        $largeCollection = $this->createLargeCollection(1000);
        $largeSubsetCollection = $this->createLargeSubsetCollection(20);

        $startMemory = memory_get_usage(true);

        $subsetFinder = new SubsetFinder($largeCollection, $largeSubsetCollection);
        $this->solveIgnoringInsufficiency($subsetFinder);

        $endMemory = memory_get_usage(true);
        $memoryUsed = $endMemory - $startMemory;

        // Should use less than 10MB for 1k items
        $this->assertLessThan(
            10 * 1024 * 1024,
            $memoryUsed,
            "Memory usage should be less than 10MB for 1k items, got: " .
            number_format($memoryUsed / 1024 / 1024, 2) . "MB"
        );
    }

    /**
     * Quantities are not expanded into unit items, so even absurd
     * quantities must solve instantly with flat memory.
     */
    public function test_huge_quantities_solve_fast(): void
    {
        $collection = [
            $this->mockSubsetable(1, 2_000_000_000, 10),
            $this->mockSubsetable(2, 1_000_000_000, 20),
        ];

        $subsetCollection = new SubsetCollection([
            Subset::of([1, 2])->take(7),
        ]);

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $subsetFinder = new SubsetFinder($collection, $subsetCollection);
        $subsetFinder->solve();

        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        $this->assertEquals(intdiv(3_000_000_000, 7), $subsetFinder->getSubsetQuantity());
        $this->assertLessThan(0.1, $executionTime, 'Huge quantities should solve in under 100ms');
        $this->assertLessThan(1024 * 1024, $memoryUsed, 'Huge quantities should not allocate memory');
    }

    /**
     * Test performance metrics accuracy
     */
    public function test_performance_metrics_accuracy(): void
    {
        $collection = $this->createLargeCollection(200);
        $subsetCollection = $this->createLargeSubsetCollection(10);

        $startTime = microtime(true);

        $subsetFinder = new SubsetFinder($collection, $subsetCollection);
        $this->solveIgnoringInsufficiency($subsetFinder);

        $actualTime = microtime(true) - $startTime;

        $metrics = $subsetFinder->getPerformanceMetrics();

        $this->assertArrayHasKey('execution_time_ms', $metrics);
        $this->assertArrayHasKey('collection_size', $metrics);
        $this->assertArrayHasKey('subset_count', $metrics);

        $this->assertEquals(200, $metrics['collection_size']);
        $this->assertEquals(10, $metrics['subset_count']);
        $this->assertLessThanOrEqual($actualTime * 1000, $metrics['execution_time_ms']);
    }

    /**
     * Test scalability with increasing complexity
     */
    public function test_scalability_with_increasing_complexity(): void
    {
        $baseSize = 50;
        $complexityLevels = [1, 2, 4];

        foreach ($complexityLevels as $complexity) {
            $collectionSize = $baseSize * $complexity;
            $subsetSize = $complexity * 3;

            $collection = $this->createLargeCollection($collectionSize);
            $subsetCollection = $this->createLargeSubsetCollection($subsetSize);

            $startTime = microtime(true);

            $subsetFinder = new SubsetFinder($collection, $subsetCollection);
            $this->solveIgnoringInsufficiency($subsetFinder);

            $executionTime = microtime(true) - $startTime;

            if (getenv('DEBUG_PERFORMANCE') === 'true') {
                echo "Size: {$collectionSize}, Subsets: {$subsetSize}, Time: " .
                     number_format($executionTime * 1000, 2) . "ms\n";
            }

            // Performance should scale reasonably (not exponentially)
            $expectedTime = $complexity * 0.5; // Linear scaling assumption
            $this->assertLessThan(
                $expectedTime,
                $executionTime,
                "Performance should scale reasonably with complexity level {$complexity}"
            );
        }
    }

    /**
     * Solve and treat a clean insufficient-quantity result as acceptable
     * for randomized datasets.
     */
    private function solveIgnoringInsufficiency(SubsetFinder $subsetFinder): void
    {
        try {
            $subsetFinder->solve();
        } catch (InsufficientQuantityException $e) {
            // Randomized data may not allow a complete set; that is fine here.
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
            $itemCount = rand(2, 5);
            $items = [];

            for ($j = 0; $j < $itemCount; $j++) {
                $items[] = rand(1, $size);
            }

            $subsets[] = Subset::of(array_unique($items))->take(rand(5, 20));
        }

        return new SubsetCollection($subsets);
    }

    /**
     * Run a performance test with given dataset size
     */
    private function runPerformanceTest(int $size): void
    {
        $collection = $this->createLargeCollection($size);
        $subsetCollection = $this->createLargeSubsetCollection(intdiv($size, 10));

        $startTime = microtime(true);

        $subsetFinder = new SubsetFinder($collection, $subsetCollection);
        $this->solveIgnoringInsufficiency($subsetFinder);

        $executionTime = microtime(true) - $startTime;

        if (getenv('DEBUG_PERFORMANCE') === 'true') {
            echo "Dataset size: {$size}, Time: " . number_format($executionTime * 1000, 2) . "ms\n";
        }

        // Performance should be reasonable for each dataset size
        $maxTime = $size / 100; // 1 second per 100 items
        $this->assertLessThan(
            $maxTime,
            $executionTime,
            "Dataset size {$size} should complete in under {$maxTime} seconds"
        );
    }
}
