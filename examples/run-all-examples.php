<?php

/**
 * Run All Examples Script
 * 
 * This script demonstrates all the real-world examples
 * and provides performance benchmarks.
 */

echo "🚀 SubsetFinder Real-World Examples\n";
echo "===================================\n\n";

$examples = [
    'e-commerce/bundle-creation.php' => '🛍️  E-commerce Bundle Creation',
    'inventory/warehouse-management.php' => '🏭 Warehouse Management',
];

$totalStartTime = microtime(true);
$totalMemory = memory_get_usage(true);

foreach ($examples as $file => $description) {
    echo "Running: {$description}\n";
    echo str_repeat('-', strlen($description) + 10) . "\n";
    
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);
    
    try {
        include __DIR__ . '/' . $file;
        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;
        
        echo "\n⏱️  Example completed in " . number_format($executionTime * 1000, 2) . "ms";
        echo "\n💾 Memory used: " . number_format($memoryUsed / 1024 / 1024, 2) . "MB\n";
        
    } catch (Exception $e) {
        echo "\n❌ Error running example: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat('=', 50) . "\n\n";
}

$totalTime = microtime(true) - $totalStartTime;
$totalMemoryUsed = memory_get_usage(true) - $totalMemory;

echo "📊 Summary\n";
echo "==========\n";
echo "Total execution time: " . number_format($totalTime * 1000, 2) . "ms\n";
echo "Total memory used: " . number_format($totalMemoryUsed / 1024 / 1024, 2) . "MB\n";
echo "Examples completed: " . count($examples) . "\n\n";

echo "✨ All examples completed successfully!\n";
echo "Check the individual example files for more details and customization options.\n";
