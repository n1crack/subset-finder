<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

/**
 * Warehouse Management Example
 * 
 * This example demonstrates how to optimize warehouse operations,
 * including order fulfillment, storage optimization, and shipping preparation.
 */

class WarehouseItem implements \Ozdemir\SubsetFinder\Subsetable
{
    public function __construct(
        public int $id,
        public string $name,
        public int $quantity,
        public string $category,
        public string $location,
        public float $weight,
        public int $priority,
        public ?string $expiryDate = null
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

    public function getVolume(): float
    {
        // Simple volume calculation based on weight and category
        $density = match($this->category) {
            'Electronics' => 0.1,
            'Clothing' => 0.05,
            'Food' => 0.08,
            'Furniture' => 0.15,
            default => 0.1
        };
        
        return $this->weight / $density;
    }

    public function isExpiringSoon(): bool
    {
        if (!$this->expiryDate) return false;
        $expiry = new DateTime($this->expiryDate);
        $now = new DateTime();
        $diff = $now->diff($expiry);
        return $diff->days <= 30 && $diff->invert == 0;
    }
}

echo "🏭 Warehouse Management Example\n";
echo "==============================\n\n";

// Create warehouse inventory
$warehouse = collect([
    new WarehouseItem(1, 'Laptop', 50, 'Electronics', 'A1-01', 2.5, 1),
    new WarehouseItem(2, 'T-Shirt', 200, 'Clothing', 'B2-03', 0.2, 2),
    new WarehouseItem(3, 'Coffee Beans', 100, 'Food', 'C3-02', 0.5, 3, '2024-12-31'),
    new WarehouseItem(4, 'Office Chair', 25, 'Furniture', 'D4-01', 15.0, 2),
    new WarehouseItem(5, 'Smartphone', 75, 'Electronics', 'A1-02', 0.3, 1),
    new WarehouseItem(6, 'Jeans', 150, 'Clothing', 'B2-04', 0.8, 2),
    new WarehouseItem(7, 'Cereal', 80, 'Food', 'C3-03', 0.4, 3, '2024-11-30'),
    new WarehouseItem(8, 'Desk', 30, 'Furniture', 'D4-02', 25.0, 2),
    new WarehouseItem(9, 'Tablet', 40, 'Electronics', 'A1-03', 0.6, 1),
    new WarehouseItem(10, 'Sneakers', 120, 'Clothing', 'B2-05', 1.2, 2),
]);

echo "📦 Warehouse Inventory Overview:\n";
$warehouse->each(function (WarehouseItem $item) {
    $expiryInfo = $item->expiryDate ? " (Expires: {$item->expiryDate})" : "";
    printf("  %s (ID: %d): %d units @ %s, Weight: %.1fkg, Priority: %d%s\n",
        $item->name,
        $item->id,
        $item->quantity,
        $item->location,
        $item->weight,
        $item->priority,
        $expiryInfo
    );
});

echo "\n";

// Define order fulfillment requirements
$orders = new SubsetCollection([
    // Priority 1: Electronics order
    Subset::of([1, 5, 9])->take(3),
    
    // Priority 2: Office furniture order
    Subset::of([4, 8])->take(2),
    
    // Priority 3: Clothing order
    Subset::of([2, 6, 10])->take(3),
    
    // Priority 4: Food order (expiring soon)
    Subset::of([3, 7])->take(2),
]);

echo "📋 Order Fulfillment Requirements:\n";
$orders->each(function (Subset $order, $index) use ($warehouse) {
    $orderItems = $warehouse->whereIn('id', $order->items);
    $totalWeight = $orderItems->sum('weight');
    $totalVolume = $orderItems->sum(fn($item) => $item->getVolume());
    
    printf("  Order %d: %s items, Total Weight: %.1fkg, Volume: %.1f m³\n",
        $index + 1,
        implode(' + ', $orderItems->pluck('name')->toArray()),
        $totalWeight,
        $totalVolume
    );
});

echo "\n";

// Create SubsetFinder with balanced configuration
$config = SubsetFinderConfig::forBalanced();
$subsetFinder = new SubsetFinder($warehouse, $orders, $config);

echo "🔍 Optimizing warehouse operations...\n";
$startTime = microtime(true);
$subsetFinder->solve();
$executionTime = microtime(true) - $startTime;

echo "\n";

// Display results
echo "📊 Warehouse Optimization Results:\n";
echo "=================================\n\n";

$maxOrders = $subsetFinder->getSubsetQuantity();
echo "🎯 Maximum orders that can be fulfilled: {$maxOrders}\n\n";

echo "✅ Items Allocated for Orders:\n";
$foundSubsets = $subsetFinder->getFoundSubsets();
$foundSubsets->each(function (WarehouseItem $item) {
    printf("  %s: %d units allocated\n", $item->name, $item->quantity);
});

echo "\n";

echo "📦 Remaining Inventory:\n";
$remaining = $subsetFinder->getRemaining();
if ($remaining->isEmpty()) {
    echo "  🎉 All inventory allocated! Perfect optimization.\n";
} else {
    $remaining->each(function (WarehouseItem $item) {
        $expiryWarning = $item->isExpiringSoon() ? " ⚠️ EXPIRING SOON" : "";
        printf("  %s: %d units remaining%s\n", $item->name, $item->quantity, $expiryWarning);
    });
}

echo "\n";

// Performance metrics
$metrics = $subsetFinder->getPerformanceMetrics();
echo "⚡ Performance Metrics:\n";
echo "  Execution Time: {$metrics['execution_time_ms']}ms\n";
echo "  Memory Peak: {$metrics['memory_peak_mb']}MB\n";
echo "  Efficiency: {$subsetFinder->getEfficiencyPercentage()}%\n";

echo "\n";

// Warehouse insights
$totalWeight = $foundSubsets->sum(fn($item) => $item->weight * $item->quantity);
$totalVolume = $foundSubsets->sum(fn($item) => $item->getVolume() * $item->quantity);
$expiringItems = $remaining->filter(fn($item) => $item->isExpiringSoon());

echo "🏭 Warehouse Insights:\n";
echo "  Total Weight Shipped: " . number_format($totalWeight, 1) . "kg\n";
echo "  Total Volume Shipped: " . number_format($totalVolume, 1) . "m³\n";
echo "  Items Expiring Soon: {$expiringItems->count()}\n";
echo "  Storage Optimization: " . ($subsetFinder->isOptimal() ? 'Optimal' : 'Sub-optimal') . "\n";

echo "\n";
echo "✨ Warehouse optimization completed successfully!\n";
