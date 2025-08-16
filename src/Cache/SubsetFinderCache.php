<?php

namespace Ozdemir\SubsetFinder\Cache;

interface SubsetFinderCache
{
    /**
     * Get cached result for a subset operation.
     */
    public function get(string $key): ?array;

    /**
     * Store result for a subset operation.
     */
    public function set(string $key, array $result, int $ttl = 3600): void;

    /**
     * Check if a result is cached.
     */
    public function has(string $key): bool;

    /**
     * Clear all cached results.
     */
    public function clear(): void;

    /**
     * Generate cache key for a subset operation.
     */
    public function generateKey(array $collection, array $subsets, array $config): string;
}
