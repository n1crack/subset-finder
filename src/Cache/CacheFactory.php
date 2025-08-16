<?php

namespace Ozdemir\SubsetFinder\Cache;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CacheFactory
{
    public const TYPE_MEMORY = 'memory';
    public const TYPE_REDIS = 'redis';
    public const TYPE_NULL = 'null';

    /**
     * Create a cache instance based on type.
     */
    public static function create(
        string $type = self::TYPE_MEMORY,
        array $config = [],
        ?LoggerInterface $logger = null
    ): SubsetFinderCache {
        $logger ??= new NullLogger();

        return match ($type) {
            self::TYPE_MEMORY => new MemorySubsetFinderCache($logger),
            self::TYPE_REDIS => self::createRedisCache($config, $logger),
            self::TYPE_NULL => new NullSubsetFinderCache($logger),
            default => throw new \InvalidArgumentException("Unknown cache type: {$type}")
        };
    }

    /**
     * Create Redis cache with configuration.
     */
    private static function createRedisCache(array $config, LoggerInterface $logger): SubsetFinderCache
    {
        if (!extension_loaded('redis')) {
            $logger->warning('Redis extension not available, falling back to memory cache');
            return new MemorySubsetFinderCache($logger);
        }

        try {
            if (!class_exists('\Redis')) {
                throw new \Exception('Redis class not available');
            }
            
            $redis = new \Redis();
            
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 6379;
            $timeout = $config['timeout'] ?? 0.0;
            $retryInterval = $config['retry_interval'] ?? 0;
            $readTimeout = $config['read_timeout'] ?? 0.0;
            
            $redis->connect($host, $port, $timeout, null, $retryInterval, $readTimeout);
            
            if (isset($config['password'])) {
                $redis->auth($config['password']);
            }
            
            if (isset($config['database'])) {
                $redis->select($config['database']);
            }
            
            $prefix = $config['prefix'] ?? 'subset_finder:';
            
            return new RedisSubsetFinderCache($redis, $logger, $prefix);
        } catch (\Exception $e) {
            $logger->error('Failed to connect to Redis, falling back to memory cache', [
                'error' => $e->getMessage()
            ]);
            return new MemorySubsetFinderCache($logger);
        }
    }

    /**
     * Get available cache types.
     */
    public static function getAvailableTypes(): array
    {
        $types = [self::TYPE_MEMORY, self::TYPE_NULL];
        
        if (extension_loaded('redis')) {
            $types[] = self::TYPE_REDIS;
        }
        
        return $types;
    }

    /**
     * Check if a cache type is available.
     */
    public static function isTypeAvailable(string $type): bool
    {
        return in_array($type, self::getAvailableTypes());
    }
}
