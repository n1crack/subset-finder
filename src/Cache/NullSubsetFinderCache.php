<?php

namespace Ozdemir\SubsetFinder\Cache;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class NullSubsetFinderCache implements SubsetFinderCache
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function get(string $key): ?array
    {
        $this->logger->debug('Null cache get (no-op)', ['key' => $key]);

        return null;
    }

    public function set(string $key, array $result, int $ttl = 3600): void
    {
        $this->logger->debug('Null cache set (no-op)', ['key' => $key, 'ttl' => $ttl]);
        // Do nothing
    }

    public function has(string $key): bool
    {
        $this->logger->debug('Null cache has (always false)', ['key' => $key]);

        return false;
    }

    public function clear(): void
    {
        $this->logger->debug('Null cache clear (no-op)');
        // Do nothing
    }

    public function generateKey(array $collection, array $subsets, array $config): string
    {
        // Still generate a key for consistency, even though we don't use it
        $data = [
            'collection' => $this->hashCollection($collection),
            'subsets' => $this->hashSubsets($subsets),
            'config' => $this->hashConfig($config),
        ];

        return hash('sha256', json_encode($data));
    }

    private function hashCollection(array $collection): string
    {
        $hash = [];
        foreach ($collection as $item) {
            if (is_object($item) && method_exists($item, 'getId') && method_exists($item, 'getQuantity')) {
                $hash[] = $item->getId() . ':' . $item->getQuantity();
            } else {
                $hash[] = json_encode($item);
            }
        }

        sort($hash);

        return hash('md5', implode('|', $hash));
    }

    private function hashSubsets(array $subsets): string
    {
        $hash = [];
        foreach ($subsets as $subset) {
            if (is_object($subset) && method_exists($subset, 'getItems') && method_exists($subset, 'getQuantity')) {
                $hash[] = implode(',', $subset->getItems()) . ':' . $subset->getQuantity();
            } else {
                $hash[] = json_encode($subset);
            }
        }

        sort($hash);

        return hash('md5', implode('|', $hash));
    }

    private function hashConfig(array $config): string
    {
        $relevantKeys = ['maxMemoryUsage', 'enableLazyEvaluation', 'profile'];
        $relevantConfig = array_intersect_key($config, array_flip($relevantKeys));

        return hash('md5', json_encode($relevantConfig));
    }
}
