<?php

namespace Ozdemir\SubsetFinder\Facades;

use Illuminate\Support\Facades\Facade;
use Ozdemir\SubsetFinder\SubsetFinder as SubsetFinderClass;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

/**
 * @method static SubsetFinderClass create(\Illuminate\Support\Collection $collection, \Ozdemir\SubsetFinder\SubsetCollection $subsetCollection, ?SubsetFinderConfig $config = null)
 * @method static SubsetFinderClass withConfig(SubsetFinderConfig $config)
 * @method static SubsetFinderClass forLargeDatasets()
 * @method static SubsetFinderClass forPerformance()
 *
 * @see \Ozdemir\SubsetFinder\SubsetFinder
 */
class SubsetFinder extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'subset-finder';
    }

    /**
     * Create a new SubsetFinder instance with default configuration.
     */
    public static function create(\Illuminate\Support\Collection $collection, \Ozdemir\SubsetFinder\SubsetCollection $subsetCollection, ?SubsetFinderConfig $config = null): SubsetFinderClass
    {
        return new SubsetFinderClass($collection, $subsetCollection, $config);
    }

    /**
     * Create a SubsetFinder instance optimized for large datasets.
     */
    public static function forLargeDatasets(\Illuminate\Support\Collection $collection, \Ozdemir\SubsetFinder\SubsetCollection $subsetCollection): SubsetFinderClass
    {
        return new SubsetFinderClass($collection, $subsetCollection, SubsetFinderConfig::forLargeDatasets());
    }

    /**
     * Create a SubsetFinder instance optimized for performance.
     */
    public static function forPerformance(\Illuminate\Support\Collection $collection, \Ozdemir\SubsetFinder\SubsetCollection $subsetCollection): SubsetFinderClass
    {
        return new SubsetFinderClass($collection, $subsetCollection, SubsetFinderConfig::forPerformance());
    }
}
