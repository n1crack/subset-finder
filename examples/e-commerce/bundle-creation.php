<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

/**
 * E-commerce Bundle Creation Example
 * 
 * This example demonstrates how to create product bundles for promotions,
 * ensuring optimal inventory usage and maximum profit.
 */

// Product class implementing Subsetable interface
class Product implements \Ozdemir\SubsetFinder\Subsetable
{
    public function __construct(
        public int $id,
        public string $name,
        public int $quantity,
        public float $price,
        public string $category,
        public float $cost,
        public int $minOrderQuantity = 1
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getProfit(): float
    {
        return $this->price - $this->cost;
    }

    public function getProfitMargin(): float
    {
        return $this->price > 0 ? ($this->getProfit() / $this->price) * 100 : 0;
    }
}

echo "🛍️  E-commerce Bundle Creation Example\n";
echo "=====================================\n\n";

// Create inventory with various products
$inventory = collect([
    new Product(1, 'Premium T-Shirt', 100, 29.99, 'Clothing', 15.00),
    new Product(2, 'Basic Socks', 200, 9.99, 'Clothing', 4.50),
    new Product(3, 'Designer Hat', 50, 49.99, 'Accessories', 25.00),
    new Product(4, 'Leather Belt', 75, 39.99, 'Accessories', 20.00),
    new Product(5, 'Running Shoes', 60, 89.99, 'Footwear', 45.00),
    new Product(6, 'Sports Socks', 150, 12.99, 'Footwear', 6.00),
    new Product(7, 'Phone Case', 300, 19.99, 'Electronics', 8.00),
    new Product(8, 'Screen Protector', 500, 14.99, 'Electronics', 5.00),
]);

echo "📦 Inventory Overview:\n";
$inventory->each(function (Product $product) {
    printf("  %s (ID: %d): %d units @ $%.2f (Cost: $%.2f, Profit: $%.2f)\n",
        $product->name,
        $product->id,
        $product->quantity,
        $product->price,
        $product->cost,
        $product->getProfit()
    );
});

echo "\n";

// Define bundle configurations
$bundles = new SubsetCollection([
    // Summer Bundle: T-Shirt + Socks + Hat
    Subset::of([1, 2, 3])->take(3),
    
    // Athlete Bundle: Shoes + Sports Socks
    Subset::of([5, 6])->take(2),
    
    // Tech Bundle: Phone Case + Screen Protector
    Subset::of([7, 8])->take(2),
    
    // Fashion Bundle: T-Shirt + Belt
    Subset::of([1, 4])->take(2),
]);

echo "🎁 Bundle Configurations:\n";
$bundles->each(function (Subset $bundle, $index) use ($inventory) {
    $bundleProducts = $inventory->whereIn('id', $bundle->items);
    $totalPrice = $bundleProducts->sum('price');
    $totalCost = $bundleProducts->sum('cost');
    $profit = $totalPrice - $totalCost;
    
    printf("  Bundle %d: %s items, Total: $%.2f, Profit: $%.2f\n",
        $index + 1,
        implode(' + ', $bundleProducts->pluck('name')->toArray()),
        $totalPrice,
        $profit
    );
});

echo "\n";

// Create SubsetFinder with performance configuration for large inventory
$config = SubsetFinderConfig::forLargeDatasets();
$subsetFinder = new SubsetFinder($inventory, $bundles, $config);

echo "🔍 Calculating optimal bundle combinations...\n";
$startTime = microtime(true);
$subsetFinder->solve();
$executionTime = microtime(true) - $startTime;

echo "\n";

// Display results
echo "📊 Bundle Creation Results:\n";
echo "==========================\n\n";

$maxBundles = $subsetFinder->getSubsetQuantity();
echo "🎯 Maximum complete bundles that can be created: {$maxBundles}\n\n";

echo "✅ Bundles Created:\n";
$foundSubsets = $subsetFinder->getFoundSubsets();
$foundSubsets->each(function (Product $product) {
    printf("  %s: %d units\n", $product->name, $product->quantity);
});

echo "\n";

echo "📦 Remaining Inventory:\n";
$remaining = $subsetFinder->getRemaining();
if ($remaining->isEmpty()) {
    echo "  🎉 All inventory used! Perfect allocation.\n";
} else {
    $remaining->each(function (Product $product) {
        printf("  %s: %d units remaining\n", $product->name, $product->quantity);
    });
}

echo "\n";

// Performance metrics
$metrics = $subsetFinder->getPerformanceMetrics();
echo "⚡ Performance Metrics:\n";
echo "  Execution Time: {$metrics['execution_time_ms']}ms\n";
echo "  Memory Peak: {$metrics['memory_peak_mb']}MB\n";
echo "  Memory Increase: {$metrics['memory_increase_mb']}MB\n";
echo "  Efficiency: {$subsetFinder->getEfficiencyPercentage()}%\n";
echo "  Optimal Solution: " . ($subsetFinder->isOptimal() ? 'Yes' : 'No') . "\n";

echo "\n";

// Business insights
$totalRevenue = $foundSubsets->sum(fn($p) => $p->price * $p->quantity);
$totalCost = $foundSubsets->sum(fn($p) => $p->cost * $p->quantity);
$totalProfit = $totalRevenue - $totalCost;
$profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

echo "💰 Business Impact:\n";
echo "  Total Revenue: $" . number_format($totalRevenue, 2) . "\n";
echo "  Total Cost: $" . number_format($totalCost, 2) . "\n";
echo "  Total Profit: $" . number_format($totalProfit, 2) . "\n";
echo "  Profit Margin: " . number_format($profitMargin, 1) . "%\n";

echo "\n";
echo "✨ Bundle creation completed successfully!\n";
