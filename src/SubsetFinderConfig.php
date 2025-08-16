<?php

namespace Ozdemir\SubsetFinder;

class SubsetFinderConfig
{
    public function __construct(
        public readonly string $idField = 'id',
        public readonly string $quantityField = 'quantity',
        public readonly string $sortField = 'id',
        public readonly bool $sortDescending = false,
        public readonly int $maxMemoryUsage = 128 * 1024 * 1024, // 128MB
        public readonly bool $enableLazyEvaluation = true,
        public readonly bool $enableLogging = false
    ) {
    }

    /**
     * Create a default configuration.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Create a configuration optimized for large datasets.
     */
    public static function forLargeDatasets(): self
    {
        return new self(
            maxMemoryUsage: 512 * 1024 * 1024, // 512MB
            enableLazyEvaluation: true
        );
    }

    /**
     * Create a configuration optimized for performance.
     */
    public static function forPerformance(): self
    {
        return new self(
            enableLazyEvaluation: false,
            enableLogging: false
        );
    }

    /**
     * Create a configuration with balanced settings.
     */
    public static function forBalanced(): self
    {
        return new self(
            maxMemoryUsage: 256 * 1024 * 1024, // 256MB
            enableLazyEvaluation: true,
            enableLogging: false
        );
    }
}
