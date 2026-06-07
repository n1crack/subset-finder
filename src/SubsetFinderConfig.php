<?php

namespace Ozdemir\SubsetFinder;

class SubsetFinderConfig
{
    public function __construct(
        public readonly string $sortField = 'id',
        public readonly bool $sortDescending = false
    ) {
    }

    /**
     * Create a default configuration.
     */
    public static function default(): self
    {
        return new self();
    }
}
