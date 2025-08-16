# Migration Guide: SubsetFinder v1.x to v2.x

This guide will help you migrate your existing SubsetFinder v1.x code to the new v2.x version, which includes significant improvements in performance, type safety, and functionality.

## 🚨 Breaking Changes

### 1. Constructor Changes

**v1.x (Old):**
```php
$subsetFinder = new SubsetFinder($collection, $subsetCollection);
$subsetFinder->defineProps(id: 'name', quantity: 'amount');
$subsetFinder->sortBy('price');
$subsetFinder->solve();
```

**v2.x (New):**
```php
$config = new SubsetFinderConfig(
    idField: 'name',
    quantityField: 'amount',
    sortField: 'price',
    sortDescending: false
);

$subsetFinder = new SubsetFinder($collection, $subsetCollection, $config);
$subsetFinder->solve();
```

### 2. Interface Changes

**v1.x (Old):**
```php
interface Subsetable
{
    public function getId(): mixed;
    public function getQuantity(): mixed;
    public function setQuantity($quantity): void;
}
```

**v2.x (New):**
```php
interface Subsetable
{
    public function getId(): int|string;
    public function getQuantity(): int;
    public function setQuantity(int $quantity): void;
}
```

### 3. Method Signature Changes

**v1.x (Old):**
```php
public function sortBy($field, bool $descending = false): self
public function defineProps(string $id = 'id', string $quantity = 'quantity'): self
```

**v2.x (New):**
```php
// These methods are now part of SubsetFinderConfig
// No direct method calls needed
```

## 🔄 Step-by-Step Migration

### Step 1: Update Dependencies

```bash
composer update ozdemir/subset-finder
```

### Step 2: Update Interface Implementations

**Before (v1.x):**
```php
class Product implements Subsetable
{
    public function getId(): mixed
    {
        return $this->id;
    }

    public function getQuantity(): mixed
    {
        return $this->quantity;
    }

    public function setQuantity($quantity): void
    {
        $this->quantity = $quantity;
    }
}
```

**After (v2.x):**
```php
class Product implements Subsetable
{
    public function getId(): int|string
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
}
```

### Step 3: Replace Method Calls with Configuration

**Before (v1.x):**
```php
$subsetFinder = new SubsetFinder($collection, $subsetCollection);
$subsetFinder->defineProps(id: 'name', quantity: 'amount');
$subsetFinder->sortBy('price', true);
$subsetFinder->solve();
```

**After (v2.x):**
```php
$config = new SubsetFinderConfig(
    idField: 'name',
    quantityField: 'amount',
    sortField: 'price',
    sortDescending: true
);

$subsetFinder = new SubsetFinder($collection, $subsetCollection, $config);
$subsetFinder->solve();
```

### Step 4: Use Configuration Profiles (Optional)

**v2.x offers pre-configured profiles:**
```php
// For large datasets
$subsetFinder = new SubsetFinder(
    $collection, 
    $subsetCollection, 
    SubsetFinderConfig::forLargeDatasets()
);

// For performance
$subsetFinder = new SubsetFinder(
    $collection, 
    $subsetCollection, 
    SubsetFinderConfig::forPerformance()
);

// For balanced approach
$subsetFinder = new SubsetFinder(
    $collection, 
    $subsetCollection, 
    SubsetFinderConfig::forBalanced()
);
```

### Step 5: Update Error Handling

**Before (v1.x):**
```php
try {
    $subsetFinder->solve();
} catch (Exception $e) {
    // Generic error handling
}
```

**After (v2.x):**
```php
use Ozdemir\SubsetFinder\Exceptions\InvalidArgumentException;
use Ozdemir\SubsetFinder\Exceptions\InsufficientQuantityException;

try {
    $subsetFinder->solve();
} catch (InvalidArgumentException $e) {
    // Handle invalid input
    Log::error('Invalid subset finder input: ' . $e->getMessage());
} catch (InsufficientQuantityException $e) {
    // Handle insufficient quantities
    Log::warning('Cannot create subsets: ' . $e->getMessage());
}
```

## 🆕 New Features in v2.x

### 1. Performance Monitoring

```php
$subsetFinder->solve();

// Get performance metrics
$metrics = $subsetFinder->getPerformanceMetrics();
echo "Execution time: {$metrics['execution_time_ms']}ms";
echo "Memory peak: {$metrics['memory_peak_mb']}MB";
```

### 2. Solution Quality Metrics

```php
// Check if solution is optimal
if ($subsetFinder->isOptimal()) {
    echo "Perfect allocation achieved!";
}

// Get efficiency percentage
$efficiency = $subsetFinder->getEfficiencyPercentage();
echo "Efficiency: {$efficiency}%";
```

### 3. Laravel Integration

```php
// Service Provider (auto-registered)
// Facade support
use Ozdemir\SubsetFinder\Facades\SubsetFinder;

$subsetFinder = SubsetFinder::forLargeDatasets($collection, $subsetCollection);

// Trait for collections
use Ozdemir\SubsetFinder\Traits\HasSubsetOperations;

class ProductCollection extends Collection
{
    use HasSubsetOperations;
}

$subsetFinder = $products->findSubsets($subsetCollection);
```

### 4. Configuration Management

```bash
# Publish configuration
php artisan vendor:publish --tag=subset-finder-config
```

**config/subset-finder.php:**
```php
return [
    'defaults' => [
        'id_field' => 'id',
        'quantity_field' => 'quantity',
        'sort_field' => 'id',
        'sort_descending' => false,
        'max_memory_usage' => env('SUBSET_FINDER_MAX_MEMORY', 128 * 1024 * 1024),
        'enable_lazy_evaluation' => env('SUBSET_FINDER_LAZY_EVALUATION', true),
        'enable_logging' => env('SUBSET_FINDER_LOGGING', false),
    ],
];
```

## 🧪 Testing Your Migration

### 1. Run Existing Tests

```bash
composer test
```

### 2. Test New Features

```php
// Test performance monitoring
$metrics = $subsetFinder->getPerformanceMetrics();
$this->assertArrayHasKey('execution_time_ms', $metrics);

// Test solution quality
$this->assertIsBool($subsetFinder->isOptimal());
$this->assertIsFloat($subsetFinder->getEfficiencyPercentage());
```

### 3. Performance Testing

```php
// Test with large datasets
$largeConfig = SubsetFinderConfig::forLargeDatasets();
$subsetFinder = new SubsetFinder($largeCollection, $largeSubsetCollection, $largeConfig);

$startTime = microtime(true);
$subsetFinder->solve();
$executionTime = microtime(true) - $startTime;

$this->assertLessThan(1.0, $executionTime); // Should complete in under 1 second
```

## 🚀 Migration Checklist

- [ ] Update composer dependencies
- [ ] Update Subsetable interface implementations
- [ ] Replace method calls with configuration objects
- [ ] Update error handling for new exception types
- [ ] Test with existing data
- [ ] Implement new features (optional)
- [ ] Update configuration files
- [ ] Run performance tests
- [ ] Update documentation

## 🔧 Troubleshooting

### Common Issues

1. **Type Error: Cannot assign mixed to int**
   - Update your Subsetable interface implementations to use proper types

2. **Method not found: defineProps()**
   - Use SubsetFinderConfig instead of method calls

3. **Validation errors during Laravel operations**
   - This is fixed in v2.x with improved validation logic

4. **Memory issues with large datasets**
   - Use `SubsetFinderConfig::forLargeDatasets()` profile

### Getting Help

- Check the [README.md](README.md) for examples
- Review the [examples/](examples/) directory
- Run `composer test` to verify your setup
- Check the [CHANGELOG.md](CHANGELOG.md) for detailed changes

## 📈 Performance Improvements

v2.x includes significant performance improvements:

- **Memory optimization**: Up to 40% less memory usage
- **Lazy evaluation**: Optional for large datasets
- **Better algorithms**: Improved subset selection logic
- **Configuration profiles**: Optimized for different use cases

## 🎯 Next Steps

After migration:

1. **Explore new features**: Performance monitoring, solution quality metrics
2. **Optimize configuration**: Use appropriate profiles for your dataset sizes
3. **Implement monitoring**: Add performance tracking to your applications
4. **Share feedback**: Let us know about your experience with v2.x

---

**Need help?** Open an issue on GitHub or check the documentation for more examples.
