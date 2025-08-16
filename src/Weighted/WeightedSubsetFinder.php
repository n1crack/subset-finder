<?php

namespace Ozdemir\SubsetFinder\Weighted;

use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\Subsetable;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class WeightedSubsetFinder
{
    public function __construct(
        private SubsetFinderConfig $config,
        private LoggerInterface $logger = new NullLogger()
    ) {}

    /**
     * Find subsets with weighted criteria.
     */
    public function findWeightedSubsets(
        Collection $collection,
        SubsetCollection $subsetCollection,
        array $weights = [],
        array $constraints = []
    ): array {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->logger->info('Starting weighted subset finding', [
            'collection_size' => $collection->count(),
            'subsets_count' => $subsetCollection->count(),
            'weights_count' => count($weights),
            'constraints_count' => count($constraints)
        ]);

        // Apply weights to collection items
        $weightedCollection = $this->applyWeights($collection, $weights);
        
        // Apply constraints
        $constrainedCollection = $this->applyConstraints($weightedCollection, $constraints);
        
        // Find optimal subsets using weighted criteria
        $optimalSubsets = $this->findOptimalSubsets($constrainedCollection, $subsetCollection, $weights);
        
        // Calculate weighted metrics
        $weightedMetrics = $this->calculateWeightedMetrics($optimalSubsets, $weights);

        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        $this->logger->info('Weighted subset finding completed', [
            'execution_time' => $executionTime,
            'memory_used' => $memoryUsed,
            'optimal_subsets' => count($optimalSubsets)
        ]);

        return [
            'subsets' => $optimalSubsets,
            'weighted_metrics' => $weightedMetrics,
            'performance' => [
                'execution_time' => $executionTime,
                'memory_used' => $memoryUsed
            ]
        ];
    }

    /**
     * Apply weights to collection items.
     */
    private function applyWeights(Collection $collection, array $weights): Collection
    {
        if (empty($weights)) {
            return $collection;
        }

        return $collection->map(function ($item) use ($weights) {
            $weightedItem = clone $item;
            
            // Calculate weighted score based on item properties
            $weightedScore = $this->calculateItemWeight($item, $weights);
            
            // Add weighted score to item (if it's an object)
            if (is_object($weightedItem) && method_exists($weightedItem, 'setWeightedScore')) {
                $weightedItem->setWeightedScore($weightedScore);
            } elseif (is_object($weightedItem)) {
                $weightedItem->weightedScore = $weightedScore;
            }
            
            return $weightedItem;
        });
    }

    /**
     * Calculate weighted score for an item.
     */
    private function calculateItemWeight($item, array $weights): float
    {
        $score = 0.0;
        
        foreach ($weights as $criterion => $weight) {
            $value = $this->extractCriterionValue($item, $criterion);
            $score += $value * $weight;
        }
        
        return $score;
    }

    /**
     * Extract criterion value from item.
     */
    private function extractCriterionValue($item, string $criterion): float
    {
        if (is_object($item)) {
            // Try different ways to access the criterion
            if (method_exists($item, 'get' . ucfirst($criterion))) {
                $method = 'get' . ucfirst($criterion);
                return (float) $item->$method();
            }
            
            if (property_exists($item, $criterion)) {
                return (float) $item->$criterion;
            }
            
            if (method_exists($item, 'getAttribute')) {
                return (float) $item->getAttribute($criterion);
            }
        }
        
        if (is_array($item) && isset($item[$criterion])) {
            return (float) $item[$criterion];
        }
        
        return 0.0;
    }

    /**
     * Apply constraints to the collection.
     */
    private function applyConstraints(Collection $collection, array $constraints): Collection
    {
        if (empty($constraints)) {
            return $collection;
        }

        return $collection->filter(function ($item) use ($constraints) {
            foreach ($constraints as $criterion => $constraint) {
                if (!$this->satisfiesConstraint($item, $criterion, $constraint)) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Check if item satisfies a constraint.
     */
    private function satisfiesConstraint($item, string $criterion, $constraint): bool
    {
        $value = $this->extractCriterionValue($item, $criterion);
        
        if (is_array($constraint)) {
            // Range constraint: ['min' => 10, 'max' => 100]
            if (isset($constraint['min']) && $value < $constraint['min']) {
                return false;
            }
            if (isset($constraint['max']) && $value > $constraint['max']) {
                return false;
            }
            return true;
        }
        
        if (is_callable($constraint)) {
            return $constraint($value, $item);
        }
        
        // Exact match constraint
        return $value == $constraint;
    }

    /**
     * Find optimal subsets using weighted criteria.
     */
    private function findOptimalSubsets(
        Collection $collection,
        SubsetCollection $subsetCollection,
        array $weights
    ): array {
        $optimalSubsets = [];
        
        foreach ($subsetCollection as $subset) {
            $subsetItems = $subset->getItems();
            $requiredQuantity = $subset->getQuantity();
            
            // Find best items for this subset based on weights
            $bestItems = $this->findBestItemsForSubset($collection, $subsetItems, $requiredQuantity, $weights);
            
            if (!empty($bestItems)) {
                $optimalSubsets[] = [
                    'subset' => $subset,
                    'selected_items' => $bestItems,
                    'total_weight' => $this->calculateSubsetWeight($bestItems, $weights),
                    'efficiency' => $this->calculateEfficiency($bestItems, $weights)
                ];
            }
        }
        
        // Sort by efficiency (highest first)
        usort($optimalSubsets, function ($a, $b) {
            return $b['efficiency'] <=> $a['efficiency'];
        });
        
        return $optimalSubsets;
    }

    /**
     * Find best items for a specific subset.
     */
    private function findBestItemsForSubset(
        Collection $collection,
        array $subsetItems,
        int $requiredQuantity,
        array $weights
    ): array {
        $availableItems = $collection->filter(function ($item) use ($subsetItems) {
            $itemId = $this->extractItemId($item);
            return in_array($itemId, $subsetItems);
        });
        
        // Sort by weighted score (highest first)
        $sortedItems = $availableItems->sortByDesc(function ($item) use ($weights) {
            return $this->calculateItemWeight($item, $weights);
        });
        
        // Select top items up to required quantity
        $selectedItems = [];
        $currentQuantity = 0;
        
        foreach ($sortedItems as $item) {
            $itemQuantity = $this->extractItemQuantity($item);
            $canAdd = min($itemQuantity, $requiredQuantity - $currentQuantity);
            
            if ($canAdd > 0) {
                $selectedItems[] = [
                    'item' => $item,
                    'quantity' => $canAdd,
                    'weight' => $this->calculateItemWeight($item, $weights)
                ];
                $currentQuantity += $canAdd;
            }
            
            if ($currentQuantity >= $requiredQuantity) {
                break;
            }
        }
        
        return $selectedItems;
    }

    /**
     * Extract item ID.
     */
    private function extractItemId($item): int|string
    {
        if (is_object($item) && method_exists($item, 'getId')) {
            return $item->getId();
        }
        
        if (is_array($item) && isset($item['id'])) {
            return $item['id'];
        }
        
        return 0;
    }

    /**
     * Extract item quantity.
     */
    private function extractItemQuantity($item): int
    {
        if (is_object($item) && method_exists($item, 'getQuantity')) {
            return $item->getQuantity();
        }
        
        if (is_array($item) && isset($item['quantity'])) {
            return $item['quantity'];
        }
        
        return 1;
    }

    /**
     * Calculate total weight for a subset.
     */
    private function calculateSubsetWeight(array $items, array $weights): float
    {
        $totalWeight = 0.0;
        
        foreach ($items as $itemData) {
            $totalWeight += $itemData['weight'] * $itemData['quantity'];
        }
        
        return $totalWeight;
    }

    /**
     * Calculate efficiency score for a subset.
     */
    private function calculateEfficiency(array $items, array $weights): float
    {
        if (empty($items)) {
            return 0.0;
        }
        
        $totalWeight = $this->calculateSubsetWeight($items, $weights);
        $totalQuantity = array_sum(array_column($items, 'quantity'));
        
        return $totalWeight / $totalQuantity;
    }

    /**
     * Calculate weighted metrics for all subsets.
     */
    private function calculateWeightedMetrics(array $subsets, array $weights): array
    {
        if (empty($subsets)) {
            return [];
        }
        
        $totalWeight = 0.0;
        $totalEfficiency = 0.0;
        $subsetCount = count($subsets);
        
        foreach ($subsets as $subset) {
            $totalWeight += $subset['total_weight'];
            $totalEfficiency += $subset['efficiency'];
        }
        
        return [
            'total_weight' => $totalWeight,
            'average_efficiency' => $totalEfficiency / $subsetCount,
            'best_efficiency' => max(array_column($subsets, 'efficiency')),
            'worst_efficiency' => min(array_column($subsets, 'efficiency')),
            'weight_distribution' => $this->calculateWeightDistribution($subsets)
        ];
    }

    /**
     * Calculate weight distribution across subsets.
     */
    private function calculateWeightDistribution(array $subsets): array
    {
        $weights = array_column($subsets, 'total_weight');
        
        if (empty($weights)) {
            return [];
        }
        
        sort($weights);
        $count = count($weights);
        
        return [
            'min' => $weights[0],
            'max' => $weights[$count - 1],
            'median' => $count % 2 === 0 
                ? ($weights[$count / 2 - 1] + $weights[$count / 2]) / 2
                : $weights[($count - 1) / 2],
            'quartiles' => [
                'q1' => $weights[(int) ($count * 0.25)],
                'q3' => $weights[(int) ($count * 0.75)]
            ]
        ];
    }
}
