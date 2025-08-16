<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinderConfig;
use Ozdemir\SubsetFinder\Weighted\WeightedSubsetFinder;
use Ozdemir\SubsetFinder\Cache\CacheFactory;
use Illuminate\Support\Collection;

/**
 * Advanced Features Demo: Weighted Subset Optimization
 * 
 * This example demonstrates:
 * - Weighted subset selection based on multiple criteria
 * - Caching for performance optimization
 * - Advanced constraint handling
 * - Performance metrics and analysis
 */

// Create a product class with weighted criteria
class Product
{
    public function __construct(
        public int $id,
        public string $name,
        public int $quantity,
        public float $price,
        public float $rating,
        public int $stockLevel,
        public float $profitMargin,
        public ?string $category = null
    ) {}

    public function getId(): int { return $this->id; }
    public function getQuantity(): int { return $this->quantity; }
    public function getPrice(): float { return $this->price; }
    public function getRating(): float { return $this->rating; }
    public function getStockLevel(): int { return $this->stockLevel; }
    public function getProfitMargin(): float { return $this->profitMargin; }
    public function getCategory(): ?string { return $this->category; }
}

// Create sample products with various attributes
$products = collect([
    new Product(1, 'Premium Laptop', 50, 1299.99, 4.8, 25, 0.35, 'Electronics'),
    new Product(2, 'Budget Laptop', 100, 599.99, 4.2, 75, 0.25, 'Electronics'),
    new Product(3, 'Gaming Mouse', 200, 89.99, 4.6, 150, 0.40, 'Accessories'),
    new Product(4, 'Wireless Keyboard', 150, 129.99, 4.4, 100, 0.30, 'Accessories'),
    new Product(5, 'Monitor 27"', 75, 299.99, 4.7, 50, 0.28, 'Electronics'),
    new Product(6, 'USB Cable', 500, 9.99, 4.0, 400, 0.50, 'Accessories'),
    new Product(7, 'Webcam HD', 120, 79.99, 4.3, 80, 0.45, 'Electronics'),
    new Product(8, 'Headphones', 180, 159.99, 4.5, 120, 0.32, 'Accessories'),
    new Product(9, 'Tablet', 60, 399.99, 4.6, 40, 0.22, 'Electronics'),
    new Product(10, 'Smartphone', 90, 799.99, 4.9, 60, 0.18, 'Electronics'),
]);

// Define subset requirements
$subsetRequirements = new SubsetCollection([
    Subset::of([1, 2, 5])->take(3),      // Electronics bundle
    Subset::of([3, 4, 6, 8])->take(5),   // Accessories bundle
    Subset::of([7, 9, 10])->take(2),     // Premium bundle
    Subset::of([1, 3, 4, 6])->take(4),   // Mixed bundle
]);

// Define weights for different criteria
$weights = [
    'profitMargin' => 0.35,    // 35% weight on profit margin
    'rating' => 0.25,          // 25% weight on customer rating
    'stockLevel' => 0.20,      // 20% weight on stock availability
    'price' => 0.15,           // 15% weight on price (lower is better)
    'quantity' => 0.05,        // 5% weight on available quantity
];

// Define constraints
$constraints = [
    'price' => ['min' => 10.0, 'max' => 1500.0],           // Price range
    'rating' => ['min' => 4.0],                             // Minimum rating
    'stockLevel' => ['min' => 20],                          // Minimum stock
    'profitMargin' => ['min' => 0.20],                      // Minimum profit margin
    'category' => function($value, $item) {
        // Custom constraint: prefer Electronics over Accessories
        return $value === 'Electronics' ? 1.2 : 1.0;
    }
];

echo "🚀 Advanced Features Demo: Weighted Subset Optimization\n";
echo "=====================================================\n\n";

// Initialize configuration
$config = SubsetFinderConfig::profile('performance');

// Initialize cache
$cache = CacheFactory::create('memory');
echo "✅ Cache initialized: " . get_class($cache) . "\n";

// Initialize weighted subset finder
$weightedFinder = new WeightedSubsetFinder($config);
echo "✅ Weighted Subset Finder initialized\n\n";

echo "📊 Input Data:\n";
echo "- Products: " . $products->count() . "\n";
echo "- Subset Requirements: " . $subsetRequirements->count() . "\n";
echo "- Weight Criteria: " . count($weights) . "\n";
echo "- Constraints: " . count($constraints) . "\n\n";

echo "⚖️ Weight Configuration:\n";
foreach ($weights as $criterion => $weight) {
    echo "  - {$criterion}: " . ($weight * 100) . "%\n";
}
echo "\n";

echo "🔒 Constraints:\n";
foreach ($constraints as $criterion => $constraint) {
    if (is_array($constraint)) {
        $min = $constraint['min'] ?? 'none';
        $max = $constraint['max'] ?? 'none';
        echo "  - {$criterion}: min={$min}, max={$max}\n";
    } elseif (is_callable($constraint)) {
        echo "  - {$criterion}: custom function\n";
    } else {
        echo "  - {$criterion}: {$constraint}\n";
    }
}
echo "\n";

// Find weighted subsets
echo "🔍 Finding optimal subsets with weighted criteria...\n";
$startTime = microtime(true);

$results = $weightedFinder->findWeightedSubsets(
    $products,
    $subsetRequirements,
    $weights,
    $constraints
);

$executionTime = microtime(true) - $startTime;

echo "✅ Analysis completed in " . number_format($executionTime * 1000, 2) . "ms\n\n";

// Display results
echo "📈 Results:\n";
echo "===========\n\n";

foreach ($results['subsets'] as $index => $subsetData) {
    $subset = $subsetData['subset'];
    $selectedItems = $subsetData['selected_items'];
    $totalWeight = $subsetData['total_weight'];
    $efficiency = $subsetData['efficiency'];
    
    echo "Bundle " . ($index + 1) . ":\n";
    echo "  Required Items: " . implode(', ', $subset->getItems()) . "\n";
    echo "  Required Quantity: " . $subset->getQuantity() . "\n";
    echo "  Total Weight Score: " . number_format($totalWeight, 2) . "\n";
    echo "  Efficiency: " . number_format($efficiency, 4) . "\n";
    echo "  Selected Items:\n";
    
    foreach ($selectedItems as $itemData) {
        $item = $itemData['item'];
        $quantity = $itemData['quantity'];
        $weight = $itemData['weight'];
        
        echo "    - {$item->name} (ID: {$item->id}): Qty={$quantity}, Weight={$weight:.4f}\n";
    }
    echo "\n";
}

// Display weighted metrics
$metrics = $results['weighted_metrics'];
echo "📊 Weighted Metrics Summary:\n";
echo "============================\n";
echo "Total Weight Score: " . number_format($metrics['total_weight'], 2) . "\n";
echo "Average Efficiency: " . number_format($metrics['average_efficiency'], 4) . "\n";
echo "Best Efficiency: " . number_format($metrics['best_efficiency'], 4) . "\n";
echo "Worst Efficiency: " . number_format($metrics['worst_efficiency'], 4) . "\n\n";

// Display weight distribution
$distribution = $metrics['weight_distribution'];
echo "📈 Weight Distribution:\n";
echo "======================\n";
echo "Min Weight: " . number_format($distribution['min'], 2) . "\n";
echo "Max Weight: " . number_format($distribution['max'], 2) . "\n";
echo "Median Weight: " . number_format($distribution['median'], 2) . "\n";
echo "Q1 (25th percentile): " . number_format($distribution['quartiles']['q1'], 2) . "\n";
echo "Q3 (75th percentile): " . number_format($distribution['quartiles']['q3'], 2) . "\n\n";

// Performance metrics
$performance = $results['performance'];
echo "⚡ Performance Metrics:\n";
echo "======================\n";
echo "Execution Time: " . number_format($performance['execution_time'] * 1000, 2) . "ms\n";
echo "Memory Used: " . number_format($performance['memory_used'] / 1024 / 1024, 2) . "MB\n";

// Cache statistics (if available)
if (method_exists($cache, 'getStats')) {
    $cacheStats = $cache->getStats();
    echo "Cache Keys: " . $cacheStats['total_keys'] . "\n";
    echo "Cache Memory: " . number_format($cacheStats['memory_usage'] / 1024 / 1024, 2) . "MB\n";
}

echo "\n🎯 Advanced Features Demo Completed!\n";
echo "This demonstrates:\n";
echo "- Weighted subset selection with multiple criteria\n";
echo "- Advanced constraint handling (ranges, custom functions)\n";
echo "- Performance optimization with caching\n";
echo "- Comprehensive metrics and analysis\n";
echo "- Real-world e-commerce optimization scenario\n";
