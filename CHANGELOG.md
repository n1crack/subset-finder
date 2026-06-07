# Changelog

All notable changes to `subset-finder` will be documented in this file.

## v3.0.0 - 2026-06-07

### Fixed

- **Overlap bug**: subsets sharing item ids no longer double count availability. `getSubsetQuantity()` previously reported set counts that could not actually be built.
- **Id comparison bug**: numeric string ids and integer ids (`'1'` vs `1`) are now treated consistently. Previously the quantity calculation matched loosely while allocation matched strictly, silently producing empty results.
- Items with quantities above 10.000 are no longer silently capped.

### Changed

- The solver now works purely on per-id quantities instead of expanding every item into unit copies. Memory usage is flat and independent of quantities; quantities in the billions solve in milliseconds.
- `SubsetFinderConfig` is reduced to `sortField` and `sortDescending`. Item ids and quantities are read through the `Subsetable` interface, so `idField`/`quantityField` are gone. Memory and lazy-evaluation options are obsolete and removed.
- **Zero dependencies**: the package no longer requires Laravel, `illuminate/collections`, `psr/log` or `ext-redis`. `SubsetFinder` accepts any iterable (arrays, generators, Laravel collections); `getFoundSubsets()`, `getRemaining()` and `getSubsetItems()` return plain arrays. `SubsetCollection` is a standalone `Countable`/`IteratorAggregate` class instead of extending Laravel's `Collection`.
- Minimum PHP version is 8.2 (matching the CI test matrix).

### Added

- Public examples page (`docs/`, served via GitHub Pages): five practical use cases whose outputs are captured from the actual package by `php docs/build.php`.

### Removed

- `WeightedSubsetFinder` (was broken: called methods that did not exist).
- `ParallelSubsetFinder` (was broken: simulated parallelism, crashed on chunked input).
- Cache layer (`CacheFactory`, Memory/Redis/Null caches).
- Laravel service provider, facade and publishable config file (the singleton was broken and the config file was never read).
- Configuration profiles (`forLargeDatasets()`, `forPerformance()`, `forBalanced()`) and memory limit checks.
- PSR-3 logger integration.
