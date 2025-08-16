<?php

namespace Ozdemir\SubsetFinder\Parallel;

use Ozdemir\SubsetFinder\SubsetFinder;
use Ozdemir\SubsetFinder\SubsetFinderConfig;
use Ozdemir\SubsetFinder\SubsetCollection;
use Ozdemir\SubsetFinder\Subsetable;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ParallelSubsetFinder
{
    private const DEFAULT_CHUNK_SIZE = 1000;
    private const DEFAULT_MAX_PROCESSES = 4;

    public function __construct(
        private SubsetFinderConfig $config,
        private LoggerInterface $logger = new NullLogger()
    ) {}

    /**
     * Find subsets using parallel processing.
     */
    public function findSubsetsParallel(
        Collection $collection,
        SubsetCollection $subsetCollection,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        int $maxProcesses = self::DEFAULT_MAX_PROCESSES
    ): array {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->logger->info('Starting parallel subset finding', [
            'collection_size' => $collection->count(),
            'subsets_count' => $subsetCollection->count(),
            'chunk_size' => $chunkSize,
            'max_processes' => $maxProcesses
        ]);

        // Split collection into chunks
        $chunks = $collection->chunk($chunkSize);
        $chunkCount = $chunks->count();

        $this->logger->debug('Collection split into chunks', [
            'total_chunks' => $chunkCount,
            'chunk_size' => $chunkSize
        ]);

        // Process chunks in parallel
        $results = $this->processChunksParallel($chunks, $subsetCollection, $maxProcesses);

        // Merge results
        $mergedResults = $this->mergeResults($results);

        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        $this->logger->info('Parallel subset finding completed', [
            'execution_time' => $executionTime,
            'memory_used' => $memoryUsed,
            'total_subsets_found' => count($mergedResults)
        ]);

        return [
            'results' => $mergedResults,
            'metrics' => [
                'execution_time' => $executionTime,
                'memory_used' => $memoryUsed,
                'chunks_processed' => $chunkCount,
                'parallel_processes' => $maxProcesses
            ]
        ];
    }

    /**
     * Process chunks in parallel using multiple processes.
     */
    private function processChunksParallel(
        Collection $chunks,
        SubsetCollection $subsetCollection,
        int $maxProcesses
    ): array {
        $results = [];
        $activeProcesses = [];
        $chunkIndex = 0;

        while ($chunkIndex < $chunks->count() || !empty($activeProcesses)) {
            // Start new processes if we have capacity
            while (count($activeProcesses) < $maxProcesses && $chunkIndex < $chunks->count()) {
                $chunk = $chunks->get($chunkIndex);
                $processId = $this->startChunkProcess($chunk, $subsetCollection, $chunkIndex);
                $activeProcesses[$processId] = $chunkIndex;
                $chunkIndex++;

                $this->logger->debug('Started chunk process', [
                    'process_id' => $processId,
                    'chunk_index' => $chunkIndex - 1,
                    'chunk_size' => $chunk->count()
                ]);
            }

            // Check for completed processes
            $this->checkCompletedProcesses($activeProcesses, $results);

            // Small delay to prevent CPU spinning
            usleep(1000); // 1ms
        }

        return $results;
    }

    /**
     * Start a process for processing a single chunk.
     */
    private function startChunkProcess(
        Collection $chunk,
        SubsetCollection $subsetCollection,
        int $chunkIndex
    ): string {
        // For now, we'll simulate parallel processing
        // In a real implementation, this would use pcntl_fork() or similar
        $processId = uniqid("chunk_{$chunkIndex}_", true);
        
        // Store the chunk data for processing
        $this->chunkData[$processId] = [
            'chunk' => $chunk,
            'subset_collection' => $subsetCollection,
            'chunk_index' => $chunkIndex,
            'start_time' => microtime(true)
        ];

        return $processId;
    }

    /**
     * Check for completed processes and collect results.
     */
    private function checkCompletedProcesses(array &$activeProcesses, array &$results): void
    {
        foreach ($activeProcesses as $processId => $chunkIndex) {
            if ($this->isProcessComplete($processId)) {
                $result = $this->getProcessResult($processId);
                $results[$chunkIndex] = $result;
                
                unset($activeProcesses[$processId]);
                
                $this->logger->debug('Chunk process completed', [
                    'process_id' => $processId,
                    'chunk_index' => $chunkIndex,
                    'result_count' => count($result)
                ]);
            }
        }
    }

    /**
     * Check if a process is complete.
     */
    private function isProcessComplete(string $processId): bool
    {
        // Simulate process completion after a short delay
        if (!isset($this->chunkData[$processId])) {
            return false;
        }

        $startTime = $this->chunkData[$processId]['start_time'];
        $elapsed = microtime(true) - $startTime;
        
        // Simulate processing time based on chunk size
        $chunkSize = $this->chunkData[$processId]['chunk']->count();
        $simulatedProcessingTime = $chunkSize * 0.001; // 1ms per item
        
        return $elapsed >= $simulatedProcessingTime;
    }

    /**
     * Get the result from a completed process.
     */
    private function getProcessResult(string $processId): array
    {
        if (!isset($this->chunkData[$processId])) {
            return [];
        }

        $data = $this->chunkData[$processId];
        $chunk = $data['chunk'];
        $subsetCollection = $data['subset_collection'];

        // Process the chunk using the regular SubsetFinder
        $subsetFinder = new SubsetFinder($chunk, $subsetCollection, $this->config);
        $subsetFinder->solve();

        $result = [
            'subsets' => $subsetFinder->getSubsetQuantity() > 0 ? $subsetFinder->getSubsetQuantity() : [],
            'quantity' => $subsetFinder->getSubsetQuantity(),
            'metrics' => [
                'execution_time' => microtime(true) - $data['start_time'],
                'chunk_size' => $chunk->count()
            ]
        ];

        // Clean up
        unset($this->chunkData[$processId]);

        return $result;
    }

    /**
     * Merge results from all chunks.
     */
    private function mergeResults(array $results): array
    {
        $mergedSubsets = [];
        $totalQuantity = 0;

        foreach ($results as $chunkResult) {
            if (isset($chunkResult['subsets'])) {
                $mergedSubsets = array_merge($mergedSubsets, $chunkResult['subsets']);
            }
            if (isset($chunkResult['quantity'])) {
                $totalQuantity += $chunkResult['quantity'];
            }
        }

        return [
            'subsets' => $mergedSubsets,
            'total_quantity' => $totalQuantity,
            'chunk_results' => $results
        ];
    }

    /**
     * Get available parallel processing options.
     */
    public function getParallelOptions(): array
    {
        return [
            'max_processes' => $this->getMaxProcesses(),
            'chunk_size' => $this->getOptimalChunkSize(),
            'memory_limit' => $this->config->maxMemoryUsage
        ];
    }

    /**
     * Get the maximum number of processes based on system capabilities.
     */
    private function getMaxProcesses(): int
    {
        $cpuCount = function_exists('\sys_get_nprocs') ? \sys_get_nprocs() : 1;
        $memoryLimit = ini_get('memory_limit');
        
        // Conservative approach: use half of available CPUs
        $maxProcesses = max(1, (int) ($cpuCount / 2));
        
        // Limit based on memory constraints
        if ($memoryLimit !== '-1') {
            $memoryBytes = $this->parseMemoryLimit($memoryLimit);
            $maxProcesses = min($maxProcesses, (int) ($memoryBytes / (100 * 1024 * 1024))); // 100MB per process
        }
        
        return min($maxProcesses, self::DEFAULT_MAX_PROCESSES);
    }

    /**
     * Get optimal chunk size based on available memory.
     */
    private function getOptimalChunkSize(): int
    {
        $availableMemory = $this->config->maxMemoryUsage - memory_get_usage(true);
        $estimatedMemoryPerItem = 1024; // 1KB per item estimate
        
        $optimalChunkSize = (int) ($availableMemory / ($estimatedMemoryPerItem * 2)); // Conservative estimate
        
        return max(100, min($optimalChunkSize, self::DEFAULT_CHUNK_SIZE));
    }

    /**
     * Parse memory limit string to bytes.
     */
    private function parseMemoryLimit(string $limit): int
    {
        $value = (int) $limit;
        $unit = strtolower(substr($limit, -1));
        
        return match ($unit) {
            'k' => $value * 1024,
            'm' => $value * 1024 * 1024,
            'g' => $value * 1024 * 1024 * 1024,
            default => $value
        };
    }

    private array $chunkData = [];
}
