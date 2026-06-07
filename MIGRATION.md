# Migration Guide: SubsetFinder v2.x to v3.x

v3 focuses the package on its core job: finding how many complete sets can be built from a pool of quantities. The solver is now purely arithmetic (no item expansion), two correctness bugs are fixed, and all broken or unused extras are removed.

## 🚨 Breaking Changes

### 1. Configuration is reduced to sorting

`idField` and `quantityField` are gone — ids and quantities are always read through the `Subsetable` interface methods (`getId()`, `getQuantity()`), which your items already implement.

**v2.x (Old):**
```php
$config = new SubsetFinderConfig(
    idField: 'name',
    quantityField: 'amount',
    sortField: 'price',
    sortDescending: false
);
```

**v3.x (New):**
```php
$config = new SubsetFinderConfig(
    sortField: 'price',
    sortDescending: false
);
```

The memory and lazy-evaluation options (`maxMemoryUsage`, `enableLazyEvaluation`, `enableLogging`) and the profile factories (`forLargeDatasets()`, `forPerformance()`, `forBalanced()`) are removed. They are obsolete: the solver no longer expands items into unit copies, so memory usage is flat regardless of quantities.

### 2. Removed classes

| Removed | Replacement |
|---|---|
| `Weighted\WeightedSubsetFinder` | none (was non-functional) |
| `Parallel\ParallelSubsetFinder` | none (was non-functional; the core solver is fast enough) |
| `Cache\*` (factory, memory, redis, null) | none |
| `SubsetFinderServiceProvider`, `Facades\SubsetFinder` | construct `SubsetFinder` directly |
| config file `config/subset-finder.php` | constructor arguments |

### 3. Constructor signature

The optional PSR-3 logger argument is removed:

```php
// v2.x
new SubsetFinder($collection, $subsetCollection, $config, $logger);

// v3.x
new SubsetFinder($collection, $subsetCollection, $config);
```

### 4. getPerformanceMetrics()

Memory keys (`memory_peak_mb`, `memory_increase_mb`) are removed; the remaining keys are `execution_time_ms`, `collection_size`, `subset_count`, `found_subsets_count`, `remaining_items_count`.

### 5. Dependencies

The package now requires only `illuminate/collections` (instead of `laravel/framework`) and no longer requires `ext-redis`. No action needed in a Laravel app; standalone users get a much smaller footprint.

## ⚠️ Behavioral changes (bug fixes)

These may change results you previously relied on — the old results were wrong:

1. **Overlapping subsets**: when multiple subsets reference the same item id, availability is no longer double counted. `getSubsetQuantity()` may now report a *lower* (correct) number than v2.
2. **Mixed id types**: numeric string ids and integer ids (`'1'` vs `1`) now match consistently. In v2 this combination silently produced empty `getFoundSubsets()` results.
3. **Large quantities**: quantities above 10.000 were silently capped in v2. They are now handled exactly.

## 🔄 Step-by-Step Migration

1. `composer update ozdemir/subset-finder`
2. Remove `idField`/`quantityField`/memory/lazy arguments from `SubsetFinderConfig` calls; keep only `sortField` and `sortDescending`.
3. Replace profile factories with `SubsetFinderConfig::default()` or an explicit config.
4. Replace facade usage with `new SubsetFinder(...)`.
5. Remove any references to the Weighted/Parallel/Cache classes (they did not work in v2).
6. Run your tests — if you have overlapping subset definitions, expect corrected (possibly lower) set counts.
