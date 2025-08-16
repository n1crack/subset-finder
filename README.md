# SubsetFinder PHP Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ozdemir/subset-finder)](https://packagist.org/packages/ozdemir/subset-finder)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/n1crack/subset-finder/run-tests.yml)](https://github.com/n1crack/subset-finder/actions)
[![GitHub](https://github.com/n1crack/subset-finder/blob/main/LICENSE.md)](https://github.com/n1crack/subset-finder/blob/main/LICENSE.md)

A powerful and flexible PHP package for efficiently finding subsets within collections based on quantity criteria. Built with Laravel collections and optimized for performance, memory efficiency, and developer experience.

## Features

- **High Performance**: Optimized algorithms with configurable memory limits
- **Flexible Configuration**: Multiple configuration profiles for different use cases
- **Performance Monitoring**: Built-in metrics and logging capabilities
- **Robust Error Handling**: Comprehensive validation and meaningful error messages
- **Type Safety**: Full PHP 8.1+ type support with strict validation
- **Comprehensive Testing**: 100% test coverage with Pest PHP
- **Laravel Integration**: Service provider, facade, and trait support
- **Memory Efficient**: Optional lazy evaluation for large datasets

## Installation

```bash
composer require ozdemir/subset-finder
```

### Laravel Integration

The package automatically registers with Laravel. If you need to publish the configuration:

```bash
php artisan vendor:publish --tag=subset-finder-config
```

## Quick Start

### Basic Usage

```php
use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

// Define your collection and subset criteria
$collection = collect([
    new Product(id: 1, quantity: 11, price: 15),
    new Product(id: 2, quantity: 6, price: 5),
    new Product(id: 3, quantity: 6, price: 5),
]);

$subsetCollection = new SubsetCollection([
    Subset::of([1, 2])->take(5),  // Find 5 items from products 1 and 2
    Subset::of([3])->take(2),      // Find 2 items from product 3
]);

// Create and configure SubsetFinder
$config = new SubsetFinderConfig(
    idField: 'id',
    quantityField: 'quantity',
    sortField: 'price',        // Sort by price (ascending)
    sortDescending: false
);

$subsetFinder = new SubsetFinder($collection, $subsetCollection, $config);
$subsetFinder->solve();

// Get results
$foundSubsets = $subsetFinder->getFoundSubsets();
$remaining = $subsetFinder->getRemaining();
$maxSubsets = $subsetFinder->getSubsetQuantity();
```

### Using Configuration Profiles

```php
// For large datasets (512MB memory, lazy evaluation enabled)
$subsetFinder = new SubsetFinder(
    $collection, 
    $subsetCollection, 
    SubsetFinderConfig::forLargeDatasets()
);

// For performance (64MB memory, lazy evaluation disabled)
$subsetFinder = new SubsetFinder(
    $collection, 
    $subsetCollection, 
    SubsetFinderConfig::forPerformance()
);

// For balanced approach (256MB memory, lazy evaluation enabled)
$subsetFinder = new SubsetFinder(
    $collection, 
    $subsetCollection, 
    SubsetFinderConfig::forBalanced()
);
```

### Using the Facade

```php
use Ozdemir\SubsetFinder\Facades\SubsetFinder;

// Create with default configuration
$subsetFinder = SubsetFinder::create($collection, $subsetCollection);

// Create with specific profile
$subsetFinder = SubsetFinder::forLargeDatasets($collection, $subsetCollection);
$subsetFinder = SubsetFinder::forPerformance($collection, $subsetCollection);
```

### Using the Trait

```php
use Ozdemir\SubsetFinder\Traits\HasSubsetOperations;

class ProductCollection extends Collection
{
    use HasSubsetOperations;
}

$products = new ProductCollection([...]);

// Find subsets directly on the collection
$subsetFinder = $products->findSubsets($subsetCollection);

// Use profiles
$subsetFinder = $products->findSubsetsWithProfile($subsetCollection, 'large_datasets');

// Check feasibility
if ($products->canSatisfySubsets($subsetCollection)) {
    // Proceed with subset creation
}
```

## Use Cases

### E-commerce Bundle Creation
```php
$products = collect([
    new Product(id: 1, quantity: 100, price: 10),  // T-shirt
    new Product(id: 2, quantity: 50, price: 5),    // Socks
    new Product(id: 3, quantity: 25, price: 20),   // Hat
]);

$bundles = new SubsetCollection([
    Subset::of([1, 2])->take(2),  // T-shirt + Socks bundle
    Subset::of([1, 3])->take(1),  // T-shirt + Hat bundle
]);

$subsetFinder = new SubsetFinder($products, $bundles);
$subsetFinder->solve();

// Create 25 T-shirt + Socks bundles
// Create 25 T-shirt + Hat bundles
// Remaining: 25 T-shirts, 0 socks, 0 hats
```

### Inventory Management
```php
$inventory = collect([
    new Item(id: 'A', quantity: 100, category: 'electronics'),
    new Item(id: 'B', quantity: 200, category: 'clothing'),
    new Item(id: 'C', quantity: 150, category: 'books'),
]);

$orders = new SubsetCollection([
    Subset::of(['A', 'B'])->take(10),  // Electronics + Clothing order
    Subset::of(['C'])->take(5),        // Books order
]);

$subsetFinder = new SubsetFinder($inventory, $orders);
$subsetFinder->solve();
```

## Configuration

### Environment Variables

```env
SUBSET_FINDER_MAX_MEMORY=256M
SUBSET_FINDER_LAZY_EVALUATION=true
SUBSET_FINDER_LOGGING=true
SUBSET_FINDER_LOG_CHANNEL=subset-finder
SUBSET_FINDER_LOG_LEVEL=info
```

### Configuration File

```php
// config/subset-finder.php
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
    
    'profiles' => [
        'large_datasets' => [
            'max_memory_usage' => 512 * 1024 * 1024,
            'enable_lazy_evaluation' => true,
            'enable_logging' => true,
        ],
        'performance' => [
            'max_memory_usage' => 64 * 1024 * 1024,
            'enable_lazy_evaluation' => false,
            'enable_logging' => false,
        ],
    ],
];
```

## Performance Monitoring

```php
$subsetFinder = new SubsetFinder($collection, $subsetCollection);
$subsetFinder->solve();

// Get performance metrics
$metrics = $subsetFinder->getPerformanceMetrics();
// [
//     'execution_time_ms' => 45.23,
//     'memory_peak_mb' => 12.5,
//     'memory_increase_mb' => 8.2,
//     'collection_size' => 1000,
//     'subset_count' => 5,
//     'found_subsets_count' => 5,
//     'remaining_items_count' => 50
// ]

// Check solution quality
$isOptimal = $subsetFinder->isOptimal();           // true if no remaining items
$efficiency = $subsetFinder->getEfficiencyPercentage(); // 95.2%
```

## Error Handling

```php
use Ozdemir\SubsetFinder\Exceptions\InvalidArgumentException;
use Ozdemir\SubsetFinder\Exceptions\InsufficientQuantityException;

try {
    $subsetFinder = new SubsetFinder($collection, $subsetCollection);
    $subsetFinder->solve();
} catch (InvalidArgumentException $e) {
    // Handle invalid input (empty collection, invalid items, etc.)
    Log::error('Invalid subset finder input: ' . $e->getMessage());
} catch (InsufficientQuantityException $e) {
    // Handle insufficient quantities
    Log::warning('Cannot create subsets: ' . $e->getMessage());
}
```

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer analyse
```

## Performance Tips

1. **Use appropriate configuration profiles** for your dataset size
2. **Enable lazy evaluation** for large collections to reduce memory usage
3. **Monitor memory usage** and adjust `max_memory_usage` accordingly
4. **Use meaningful sort fields** to optimize subset selection
5. **Consider batch processing** for very large datasets

## Advanced Usage

### Custom Logging

```php
use Psr\Log\LoggerInterface;

class CustomLogger implements LoggerInterface
{
    // Implement logger methods
}

$subsetFinder = new SubsetFinder(
    $collection, 
    $subsetCollection, 
    $config, 
    new CustomLogger()
);
```

### Memory Management

```php
// Check memory before processing
if (memory_get_usage(true) > $config->maxMemoryUsage) {
    throw new \Exception('Insufficient memory for processing');
}

// Process in batches for very large datasets
$batchSize = 1000;
foreach ($collection->chunk($batchSize) as $batch) {
    // Process batch
}
```

## Contributing

Contributions are welcome! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## License

This package is open-sourced software licensed under the [MIT License](LICENSE.md).

## Support

- **Documentation**: [GitHub Wiki](https://github.com/n1crack/subset-finder/wiki)
- **Issues**: [GitHub Issues](https://github.com/n1crack/subset-finder/issues)
- **Discussions**: [GitHub Discussions](https://github.com/n1crack/subset-finder/discussions)

## Roadmap

- [ ] Support for weighted subset selection
- [ ] Parallel processing for large datasets
- [ ] Machine learning-based optimization
- [ ] GraphQL integration
- [ ] Redis caching support
- [ ] More configuration profiles
- [ ] Performance benchmarking tools


