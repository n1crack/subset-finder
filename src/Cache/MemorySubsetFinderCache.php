<?php

namespace Ozdemir\SubsetFinder\Cache;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MemorySubsetFinderCache implements SubsetFinderCache
{
    private array $cache = [];
    private array $expiry = [];

    public function __construct(
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function get(string $key): ?array
    {
        if (!$this->has($key)) {
            return null;
        }

        $this->logger->debug('Memory cache hit', ['key' => $key]);

        return $this->cache[$key];
    }

    public function set(string $key, array $result, int $ttl = 3600): void
    {
        $this->cache[$key] = $result;
        $this->expiry[$key] = time() + $ttl;

        $this->logger->debug('Memory cache set', ['key' => $key, 'ttl' => $ttl]);

        // Clean up expired entries
        $this->cleanup();
    }

    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        // Check if expired
        if (isset($this->expiry[$key]) && time() > $this->expiry[$key]) {
            unset($this->cache[$key], $this->expiry[$key]);

            return false;
        }

        return true;
    }

    public function clear(): void
    {
        $count = count($this->cache);
        $this->cache = [];
        $this->expiry = [];

        $this->logger->info('Memory cache cleared', ['keys_count' => $count]);
    }

    public function generateKey(array $collection, array $subsets, array $config): string
    {
        // Create a deterministic hash based on input data
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

    private function cleanup(): void
    {
        $now = time();
        $expired = array_filter($this->expiry, fn($expiry) => $now > $expiry);

        foreach (array_keys($expired) as $key) {
            unset($this->cache[$key], $this->expiry[$key]);
        }

        if (!empty($expired)) {
            $this->logger->debug('Memory cache cleanup', ['expired_count' => count($expired)]);
        }
    }

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        $this->cleanup();

        return [
            'total_keys' => count($this->cache),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];
    }
}
