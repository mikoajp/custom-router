<?php

namespace Custom\Router\Cache;

use Custom\Router\Interfaces\CacheInterface;
use Custom\Router\Collection\RouteCollection;
use Custom\Router\Route;

/**
 * Memory-optimized route cache with lazy loading and compression
 */
class MemoryOptimizedRouteCache implements CacheInterface
{
    private string $cacheDir;
    private array $memoryCache = [];
    private array $loadedChunks = [];
    private int $maxMemoryItems;
    private bool $compressionEnabled;
    
    public function __construct(
        string $cacheDir = null,
        int $maxMemoryItems = 1000,
        bool $compressionEnabled = true
    ) {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/custom_router_cache';
        $this->maxMemoryItems = $maxMemoryItems;
        $this->compressionEnabled = $compressionEnabled;
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        // Check memory cache first
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
        }
        
        // Load from disk cache
        $data = $this->loadFromDisk($key);
        if ($data !== null) {
            $this->storeInMemory($key, $data);
            return $data;
        }
        
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, int|null $ttl = 3600): bool
    {
        $this->storeInMemory($key, $value);

        return $this->storeToDisk($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        unset($this->memoryCache[$key]);
        
        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->memoryCache = [];
        $this->loadedChunks = [];
        
        $files = glob($this->cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }

    /**
     * Cache compiled routes with chunking for memory efficiency
     */
    public function cacheCompiledRoutes(RouteCollection $routes): void
    {
        $compiledRoutes = [];
        $chunkSize = 100;
        $currentChunk = 0;
        $chunkData = [];
        
        foreach ($routes->all() as $name => $route) {
            $chunkData[$name] = [
                'path' => $route->getPath(),
                'compiled' => $route->compile(),
                'defaults' => $route->getDefaults(),
                'requirements' => $route->getRequirements(),
                'methods' => $route->getMethods(),
                'schemes' => $route->getSchemes(),
                'host' => $route->getHost()
            ];
            
            if (count($chunkData) >= $chunkSize) {
                $this->set("routes_chunk_{$currentChunk}", $chunkData);
                $chunkData = [];
                $currentChunk++;
            }
        }

        if (!empty($chunkData)) {
            $this->set("routes_chunk_{$currentChunk}", $chunkData);
        }

        $this->set('routes_metadata', [
            'total_chunks' => $currentChunk + 1,
            'total_routes' => count($routes),
            'cache_time' => time()
        ]);
    }

    /**
     * Load routes lazily by chunk
     */
    public function loadRouteChunk(int $chunkId): ?array
    {
        if (isset($this->loadedChunks[$chunkId])) {
            return $this->loadedChunks[$chunkId];
        }
        
        $chunkData = $this->get("routes_chunk_{$chunkId}");
        if ($chunkData !== null) {
            $this->loadedChunks[$chunkId] = $chunkData;
        }
        
        return $chunkData;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $memoryUsage = 0;
        foreach ($this->memoryCache as $item) {
            $memoryUsage += strlen(serialize($item));
        }
        
        return [
            'memory_items' => count($this->memoryCache),
            'memory_usage_bytes' => $memoryUsage,
            'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'loaded_chunks' => count($this->loadedChunks),
            'cache_dir' => $this->cacheDir,
            'compression_enabled' => $this->compressionEnabled
        ];
    }

    /**
     * Store item in memory with LRU eviction
     */
    private function storeInMemory(string $key, mixed $value): void
    {
        if (isset($this->memoryCache[$key])) {
            unset($this->memoryCache[$key]);
        }

        $this->memoryCache[$key] = $value;

        if (count($this->memoryCache) > $this->maxMemoryItems) {
            $keysToRemove = array_slice(array_keys($this->memoryCache), 0, 
                count($this->memoryCache) - $this->maxMemoryItems);
            
            foreach ($keysToRemove as $keyToRemove) {
                unset($this->memoryCache[$keyToRemove]);
            }
        }
    }

    /**
     * Load data from disk cache
     */
    private function loadFromDisk(string $key): mixed
    {
        $filePath = $this->getFilePath($key);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $data = file_get_contents($filePath);
        if ($data === false) {
            return null;
        }

        if ($this->compressionEnabled && function_exists('gzuncompress')) {
            $data = gzuncompress($data);
            if ($data === false) {
                return null;
            }
        }
        
        $unserialized = unserialize($data);
        
        // Check TTL
        if (isset($unserialized['expires']) && $unserialized['expires'] < time()) {
            unlink($filePath);
            return null;
        }
        
        return $unserialized['data'] ?? null;
    }

    /**
     * Store data to disk cache
     */
    private function storeToDisk(string $key, mixed $value, int $ttl): bool
    {
        $data = [
            'data' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        $serialized = serialize($data);

        if ($this->compressionEnabled && function_exists('gzcompress')) {
            $serialized = gzcompress($serialized, 6);
        }
        
        $filePath = $this->getFilePath($key);
        return file_put_contents($filePath, $serialized, LOCK_EX) !== false;
    }

    /**
     * Get file path for cache key
     */
    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    /**
     * Cleanup expired cache files
     */
    public function cleanup(): int
    {
        $cleaned = 0;
        $files = glob($this->cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            $data = file_get_contents($file);
            if ($data === false) continue;
            
            if ($this->compressionEnabled && function_exists('gzuncompress')) {
                $data = gzuncompress($data);
                if ($data === false) continue;
            }
            
            $unserialized = unserialize($data);
            if (isset($unserialized['expires']) && $unserialized['expires'] < time()) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }

    public function has(string $key): bool
    {
        // TODO: Implement has() method.
    }
}