<?php

namespace Custom\Router\Cache;

use Custom\Router\Collection\RouteCollection;
use Custom\Router\Route;

/**
 * Memory optimization utilities for router
 */
class MemoryOptimizer
{
    private static array $compiledRoutes = [];
    private static array $routeCache = [];
    private static int $maxCacheSize = 1000;
    private static bool $enabled = true;

    /**
     * Enable/disable memory optimization
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Set maximum cache size
     */
    public static function setMaxCacheSize(int $size): void
    {
        self::$maxCacheSize = $size;
    }

    /**
     * Get compiled route pattern (cached)
     */
    public static function getCompiledRoute(string $routeName, Route $route): string
    {
        if (!self::$enabled) {
            return $route->compile();
        }

        if (!isset(self::$compiledRoutes[$routeName])) {
            self::$compiledRoutes[$routeName] = $route->compile();

            if (count(self::$compiledRoutes) > self::$maxCacheSize) {
                self::$compiledRoutes = array_slice(self::$compiledRoutes, -self::$maxCacheSize, null, true);
            }
        }

        return self::$compiledRoutes[$routeName];
    }

    /**
     * Cache route match result
     */
    public static function cacheRouteMatch(string $path, string $method, array $result): void
    {
        if (!self::$enabled) {
            return;
        }

        $key = self::getCacheKey($path, $method);
        self::$routeCache[$key] = $result;

        if (count(self::$routeCache) > self::$maxCacheSize) {
            self::$routeCache = array_slice(self::$routeCache, -self::$maxCacheSize, null, true);
        }
    }

    /**
     * Get cached route match result
     */
    public static function getCachedRouteMatch(string $path, string $method): ?array
    {
        if (!self::$enabled) {
            return null;
        }

        $key = self::getCacheKey($path, $method);
        return self::$routeCache[$key] ?? null;
    }

    /**
     * Optimize route collection by removing unused data
     */
    public static function optimizeRouteCollection(RouteCollection $routes): RouteCollection
    {
        if (!self::$enabled) {
            return $routes;
        }

        $optimized = new RouteCollection();
        
        foreach ($routes->all() as $name => $route) {
            $optimizedRoute = new Route(
                $route->getPath(),
                $route->getDefaults(),
                $route->getRequirements(),
                [],
                $route->getHost(),
                $route->getSchemes(),
                $route->getMethods()
            );
            
            $optimized->add($name, $optimizedRoute);
        }

        return $optimized;
    }

    /**
     * Clear all caches
     */
    public static function clearCache(): void
    {
        self::$compiledRoutes = [];
        self::$routeCache = [];
    }

    /**
     * Get memory usage statistics
     */
    public static function getMemoryStats(): array
    {
        return [
            'compiled_routes_count' => count(self::$compiledRoutes),
            'route_cache_count' => count(self::$routeCache),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'cache_enabled' => self::$enabled,
            'max_cache_size' => self::$maxCacheSize
        ];
    }

    /**
     * Generate cache key for route matching
     */
    private static function getCacheKey(string $path, string $method): string
    {
        return md5($method . ':' . $path);
    }

    /**
     * Optimize string memory usage
     */
    public static function optimizeString(string $str): string
    {
        if (!self::$enabled) {
            return $str;
        }

        $str = trim($str);

        if (function_exists('str_intern')) {
            return str_intern($str);
        }

        return $str;
    }

    /**
     * Lazy load routes to save memory
     */
    public static function lazyLoadRoutes(callable $loader): \Generator
    {
        if (!self::$enabled) {
            yield from $loader();
            return;
        }

        foreach ($loader() as $name => $route) {
            yield $name => $route;

            if (count(self::$compiledRoutes) % 100 === 0) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Compress route data for storage
     */
    public static function compressRouteData(array $data): string
    {
        if (!self::$enabled || !function_exists('gzcompress')) {
            return serialize($data);
        }

        return gzcompress(serialize($data), 6);
    }

    /**
     * Decompress route data
     */
    public static function decompressRouteData(string $compressed): array
    {
        if (!self::$enabled || !function_exists('gzuncompress')) {
            return unserialize($compressed);
        }

        return unserialize(gzuncompress($compressed));
    }
}