<?php

namespace Ozdemir\SubsetFinder\Traits;

use Illuminate\Support\Collection;
use Ozdemir\SubsetFinder\Subset;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;

trait HasSubsetOperations
{
    /**
     * Find subsets in the collection based on the given criteria.
     */
    public function findSubsets(SubsetCollection $subsetCollection, ?SubsetFinderConfig $config = null): SubsetFinder
    {
        $subsetFinder = new SubsetFinder($this, $subsetCollection, $config);
        $subsetFinder->solve();

        return $subsetFinder;
    }

    /**
     * Find subsets with a specific configuration profile.
     */
    public function findSubsetsWithProfile(SubsetCollection $subsetCollection, string $profile): SubsetFinder
    {
        $config = match ($profile) {
            'large_datasets' => SubsetFinderConfig::forLargeDatasets(),
            'performance' => SubsetFinderConfig::forPerformance(),
            'balanced' => SubsetFinderConfig::forBalanced(),
            default => SubsetFinderConfig::default(),
        };

        return $this->findSubsets($subsetCollection, $config);
    }

    /**
     * Create a subset collection from the given criteria.
     */
    public function createSubsetCollection(array $criteria): SubsetCollection
    {
        $subsets = [];

        foreach ($criteria as $item) {
            if (is_array($item) && isset($item['items']) && isset($item['quantity'])) {
                $subsets[] = new Subset($item['items'], $item['quantity']);
            } elseif (is_array($item) && count($item) === 2) {
                $subsets[] = new Subset($item[0], $item[1]);
            }
        }

        return new SubsetCollection($subsets);
    }

    /**
     * Check if the collection can satisfy the given subset requirements.
     */
    public function canSatisfySubsets(SubsetCollection $subsetCollection): bool
    {
        try {
            $subsetFinder = new SubsetFinder($this, $subsetCollection);
            $subsetFinder->solve();

            return $subsetFinder->getSubsetQuantity() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the maximum number of complete subsets that can be created.
     */
    public function getMaxSubsetQuantity(SubsetCollection $subsetCollection): int
    {
        try {
            $subsetFinder = new SubsetFinder($this, $subsetCollection);
            $subsetFinder->solve();

            return $subsetFinder->getSubsetQuantity();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
