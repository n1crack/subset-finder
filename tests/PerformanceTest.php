<?php

namespace Ozdemir\SubsetFinder\Tests;

use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

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
     * Test memory usage with large datasets
     */
    public function test_memory_usage_with_large_datasets(): void
    {
        $largeCollection = $this->createLargeCollection(1000);
        $largeSubsetCollection = $this->createLargeSubsetCollection(20);
        
        $startMemory = memory_get_usage(true);
        
        $subsetFinder = new SubsetFinder($largeCollection, $largeSubsetCollection);
        $subsetFinder->solve();
        
        $endMemory = memory_get_usage(true);
        $memoryUsed = $endMemory - $startMemory;
        
        // Should use less than 50MB for 1k items
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 
            "Memory usage should be less than 50MB for 1k items, got: " . 
            number_format($memoryUsed / 1024 / 1024, 2) . "MB"
        );
    }

    /**
     * Test configuration profiles performance
     */
    public function test_configuration_profiles_performance(): void
    {
        $collection = $this->createLargeCollection(500);
        $subsetCollection = $this->createLargeSubsetCollection(20);
        
        $profiles = [
            'default' => SubsetFinderConfig::default(),
            'large_datasets' => SubsetFinderConfig::forLargeDatasets(),
            'performance' => SubsetFinderConfig::forPerformance(),
            'balanced' => SubsetFinderConfig::forBalanced(),
        ];
        
        foreach ($profiles as $name => $config) {
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            $subsetFinder = new SubsetFinder($collection, $subsetCollection, $config);
            $subsetFinder->solve();
            
            $executionTime = microtime(true) - $startTime;
            $memoryUsed = memory_get_usage(true) - $startMemory;
            
            if (getenv('DEBUG_PERFORMANCE') === 'true') {
                echo "Profile: {$name} - Time: " . number_format($executionTime * 1000, 2) . "ms, Memory: " . 
                     number_format($memoryUsed / 1024 / 1024, 2) . "MB\n";
            }
            
            // All profiles should complete in reasonable time
            $this->assertLessThan(2.0, $executionTime, 
                "Profile {$name} should complete in under 2 seconds"
            );
        }
    }

    /**
     * Test lazy evaluation performance
     */
    public function test_lazy_evaluation_performance(): void
    {
        $collection = $this->createLargeCollection(1000);
        $subsetCollection = $this->createLargeSubsetCollection(20);
        
        // Test with lazy evaluation enabled
        $lazyConfig = new SubsetFinderConfig(enableLazyEvaluation: true);
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $subsetFinder = new SubsetFinder($collection, $subsetCollection, $lazyConfig);
        $subsetFinder->solve();
        
        $lazyTime = microtime(true) - $startTime;
        $lazyMemory = memory_get_usage(true) - $startMemory;
        
        // Test with lazy evaluation disabled
        $eagerConfig = new SubsetFinderConfig(enableLazyEvaluation: false);
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $subsetFinder = new SubsetFinder($collection, $subsetCollection, $eagerConfig);
        $subsetFinder->solve();
        
        $eagerTime = microtime(true) - $startTime;
        $eagerMemory = memory_get_usage(true) - $startMemory;
        
        if (getenv('DEBUG_PERFORMANCE') === 'true') {
            echo "Lazy Evaluation: Time: " . number_format($lazyTime * 1000, 2) . "ms, Memory: " . 
                 number_format($lazyMemory / 1024 / 1024, 2) . "MB\n";
            echo "Eager Evaluation: Time: " . number_format($eagerTime * 1000, 2) . "ms, Memory: " . 
                 number_format($eagerMemory / 1024 / 1024, 2) . "MB\n";
        }
        
        // Lazy evaluation memory usage can vary, but should be reasonable
        // Handle cases where memory measurements might be 0 or very small
        if ($eagerMemory < 1024) {
            // Eager evaluation used very little memory, just ensure lazy evaluation is reasonable
            $this->assertLessThan(10 * 1024 * 1024, $lazyMemory, 
                "Lazy evaluation should not use more than 10MB when eager evaluation uses very little memory"
            );
        } elseif ($lazyMemory < 1024) {
            // Lazy evaluation used very little memory, just ensure eager evaluation is reasonable
            $this->assertLessThan(10 * 1024 * 1024, $eagerMemory, 
                "Eager evaluation should not use more than 10MB when lazy evaluation uses very little memory"
            );
        } else {
            // Both used measurable memory, allow for up to 2x difference
            $this->assertLessThanOrEqual($eagerMemory * 2, $lazyMemory, 
                "Lazy evaluation should not use more than 2x the memory of eager evaluation"
            );
        }
    }

    /**
     * Test performance metrics accuracy
     */
    public function test_performance_metrics_accuracy(): void
    {
        $collection = $this->createLargeCollection(200);
        $subsetCollection = $this->createLargeSubsetCollection(10);
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $subsetFinder = new SubsetFinder($collection, $subsetCollection);
        $subsetFinder->solve();
        
        $actualTime = microtime(true) - $startTime;
        $actualMemory = memory_get_usage(true) - $startMemory;
        
        $metrics = $subsetFinder->getPerformanceMetrics();
        
        // Metrics should be reasonably accurate
        $this->assertArrayHasKey('execution_time_ms', $metrics);
        $this->assertArrayHasKey('memory_peak_mb', $metrics);
        $this->assertArrayHasKey('memory_increase_mb', $metrics);
        
        // Execution time should be within 10% accuracy
        $reportedTime = $metrics['execution_time_ms'] / 1000;
        $timeDifference = abs($actualTime - $reportedTime);
        $timeAccuracy = $timeDifference / $actualTime;
        
        $this->assertLessThan(0.1, $timeAccuracy, 
            "Execution time accuracy should be within 10%, got: " . 
            number_format($timeAccuracy * 100, 1) . "%"
        );
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
            $subsetFinder->solve();
            
            $executionTime = microtime(true) - $startTime;
            
            if (getenv('DEBUG_PERFORMANCE') === 'true') {
                echo "Size: {$collectionSize}, Subsets: {$subsetSize}, Time: " . 
                     number_format($executionTime * 1000, 2) . "ms\n";
            }
            
            // Performance should scale reasonably (not exponentially)
            $expectedTime = $complexity * 0.5; // Linear scaling assumption
            $this->assertLessThan($expectedTime, $executionTime, 
                "Performance should scale reasonably with complexity level {$complexity}"
            );
        }
    }

    /**
     * Create a large collection for testing
     */
    private function createLargeCollection(int $size): \Illuminate\Support\Collection
    {
        $collection = collect();
        
        for ($i = 1; $i <= $size; $i++) {
            $collection->push($this->mockSubsetable(
                $i,
                rand(10, 1000),
                rand(1000, 10000) / 100
            ));
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
        $subsetCollection = $this->createLargeSubsetCollection($size / 10);
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $subsetFinder = new SubsetFinder($collection, $subsetCollection);
        $subsetFinder->solve();
        
        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;
        
        if (getenv('DEBUG_PERFORMANCE') === 'true') {
            echo "Dataset size: {$size}, Time: " . number_format($executionTime * 1000, 2) . 
                 "ms, Memory: " . number_format($memoryUsed / 1024 / 1024, 2) . "MB\n";
        }
        
        // Performance should be reasonable for each dataset size
        $maxTime = $size / 100; // 1 second per 100 items
        $this->assertLessThan($maxTime, $executionTime, 
            "Dataset size {$size} should complete in under {$maxTime} seconds"
        );
    }
}
