<?php

namespace Ozdemir\SubsetFinder\Cache;

use Redis;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RedisSubsetFinderCache implements SubsetFinderCache
{
    private const CACHE_PREFIX = 'subset_finder:';
    private const DEFAULT_TTL = 3600; // 1 hour

    public function __construct(
        private Redis $redis,
        private LoggerInterface $logger = new NullLogger(),
        private string $prefix = self::CACHE_PREFIX
    ) {}

    public function get(string $key): ?array
    {
        try {
            $fullKey = $this->prefix . $key;
            $cached = $this->redis->get($fullKey);
            
            if ($cached === false) {
                return null;
            }

            $result = json_decode($cached, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning('Failed to decode cached result', [
                    'key' => $key,
                    'error' => json_last_error_msg()
                ]);
                return null;
            }

            $this->logger->debug('Cache hit', ['key' => $key]);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Redis get operation failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function set(string $key, array $result, int $ttl = self::DEFAULT_TTL): void
    {
        try {
            $fullKey = $this->prefix . $key;
            $serialized = json_encode($result);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Failed to serialize result for caching', [
                    'key' => $key,
                    'error' => json_last_error_msg()
                ]);
                return;
            }

            $this->redis->setex($fullKey, $ttl, $serialized);
            $this->logger->debug('Cache set', ['key' => $key, 'ttl' => $ttl]);
        } catch (\Exception $e) {
            $this->logger->error('Redis set operation failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function has(string $key): bool
    {
        try {
            $fullKey = $this->prefix . $key;
            return $this->redis->exists($fullKey) > 0;
        } catch (\Exception $e) {
            $this->logger->error('Redis exists operation failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function clear(): void
    {
        try {
            $keys = $this->redis->keys($this->prefix . '*');
            if (!empty($keys)) {
                $this->redis->del($keys);
                $this->logger->info('Cache cleared', ['keys_count' => count($keys)]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Redis clear operation failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function generateKey(array $collection, array $subsets, array $config): string
    {
        // Create a deterministic hash based on input data
        $data = [
            'collection' => $this->hashCollection($collection),
            'subsets' => $this->hashSubsets($subsets),
            'config' => $this->hashConfig($config)
        ];

        return hash('sha256', json_encode($data));
    }

    private function hashCollection(array $collection): string
    {
        // Create a hash based on collection structure and content
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
        // Only hash relevant configuration options
        $relevantKeys = ['maxMemoryUsage', 'enableLazyEvaluation', 'profile'];
        $relevantConfig = array_intersect_key($config, array_flip($relevantKeys));
        
        return hash('md5', json_encode($relevantConfig));
    }
}
