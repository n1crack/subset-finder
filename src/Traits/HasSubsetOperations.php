<?php

namespace Ozdemir\SubsetFinder\Traits;

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
     * Check if the collection can satisfy the given subset requirements.
     */
    public function canSatisfySubsets(SubsetCollection $subsetCollection): bool
    {
        return $this->getMaxSubsetQuantity($subsetCollection) > 0;
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
