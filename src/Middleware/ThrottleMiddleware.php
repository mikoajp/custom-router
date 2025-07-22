<?php

namespace Custom\Router\Middleware;

use Custom\Router\Interfaces\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Custom\Router\Exception\RateLimitExceededException;

/**
 * Rate limiting / Throttling Middleware
 */
class ThrottleMiddleware implements MiddlewareInterface
{
    private int $maxAttempts;
    private int $decayMinutes;
    private array $storage = [];
    private string $keyPrefix;
    private array $excludedPaths;
    private array $customLimits;

    public function __construct(
        int $maxAttempts = 60,
        int $decayMinutes = 1,
        string $keyPrefix = 'throttle',
        array $excludedPaths = [],
        array $customLimits = []
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
        $this->keyPrefix = $keyPrefix;
        $this->excludedPaths = $excludedPaths;
        $this->customLimits = $customLimits;
    }

    public function handle(Request $request, callable $next): Response
    {
        foreach ($this->excludedPaths as $path) {
            if (fnmatch($path, $request->getPathInfo())) {
                return $next($request);
            }
        }

        $key = $this->resolveRequestSignature($request);
        $limits = $this->getRouteLimits($request);
        
        if ($this->tooManyAttempts($key, $limits['max_attempts'])) {
            throw new RateLimitExceededException(
                'Too many requests. Rate limit exceeded.',
                $this->getRetryAfter($key)
            );
        }

        $this->incrementAttempts($key);
        
        $response = $next($request);
        
        // Add rate limit headers
        return $this->addHeaders(
            $response,
            $limits['max_attempts'],
            $this->calculateRemainingAttempts($key, $limits['max_attempts']),
            $this->getRetryAfter($key)
        );
    }

    /**
     * Determine if the given key has been "accessed" too many times
     */
    protected function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        if (!isset($this->storage[$key])) {
            return false;
        }

        $record = $this->storage[$key];

        $record['attempts'] = array_filter(
            $record['attempts'],
            fn($timestamp) => $timestamp > (time() - ($this->decayMinutes * 60))
        );

        $this->storage[$key] = $record;

        return count($record['attempts']) >= $maxAttempts;
    }

    /**
     * Increment the counter for a given key
     */
    protected function incrementAttempts(string $key): void
    {
        if (!isset($this->storage[$key])) {
            $this->storage[$key] = ['attempts' => []];
        }

        $this->storage[$key]['attempts'][] = time();

        $this->cleanupStorage();
    }

    /**
     * Calculate remaining attempts
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        if (!isset($this->storage[$key])) {
            return $maxAttempts;
        }

        $attempts = count($this->storage[$key]['attempts']);
        return max(0, $maxAttempts - $attempts);
    }

    /**
     * Get the number of seconds until the next retry
     */
    protected function getRetryAfter(string $key): int
    {
        if (!isset($this->storage[$key]) || empty($this->storage[$key]['attempts'])) {
            return 0;
        }

        $oldestAttempt = min($this->storage[$key]['attempts']);
        $retryAfter = ($oldestAttempt + ($this->decayMinutes * 60)) - time();
        
        return max(0, $retryAfter);
    }

    /**
     * Create a unique signature for the request
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $route = $request->attributes->get('_route', 'unknown');
        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent', '');
        
        return $this->keyPrefix . ':' . sha1($route . '|' . $ip . '|' . $userAgent);
    }

    /**
     * Get rate limits for current route
     */
    protected function getRouteLimits(Request $request): array
    {
        $route = $request->attributes->get('_route');
        
        if ($route && isset($this->customLimits[$route])) {
            return [
                'max_attempts' => $this->customLimits[$route]['max_attempts'] ?? $this->maxAttempts,
                'decay_minutes' => $this->customLimits[$route]['decay_minutes'] ?? $this->decayMinutes
            ];
        }

        return [
            'max_attempts' => $this->maxAttempts,
            'decay_minutes' => $this->decayMinutes
        ];
    }

    /**
     * Add rate limit headers to response
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remaining, int $retryAfter): Response
    {
        $response->headers->set('X-RateLimit-Limit', (string)$maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string)$remaining);
        
        if ($retryAfter > 0) {
            $response->headers->set('X-RateLimit-Reset', (string)(time() + $retryAfter));
            $response->headers->set('Retry-After', (string)$retryAfter);
        }

        return $response;
    }

    /**
     * Clean up old storage entries
     */
    protected function cleanupStorage(): void
    {
        $cutoff = time() - ($this->decayMinutes * 60);
        
        foreach ($this->storage as $key => $record) {
            $record['attempts'] = array_filter(
                $record['attempts'],
                fn($timestamp) => $timestamp > $cutoff
            );
            
            if (empty($record['attempts'])) {
                unset($this->storage[$key]);
            } else {
                $this->storage[$key] = $record;
            }
        }
    }

    /**
     * Set custom rate limits for specific routes
     */
    public function setRouteLimit(string $route, int $maxAttempts, int $decayMinutes = null): void
    {
        $this->customLimits[$route] = [
            'max_attempts' => $maxAttempts,
            'decay_minutes' => $decayMinutes ?? $this->decayMinutes
        ];
    }

    /**
     * Clear all rate limit data
     */
    public function clearLimits(): void
    {
        $this->storage = [];
    }

    /**
     * Get current rate limit statistics
     */
    public function getStats(): array
    {
        $totalEntries = count($this->storage);
        $totalAttempts = 0;
        
        foreach ($this->storage as $record) {
            $totalAttempts += count($record['attempts']);
        }

        return [
            'total_entries' => $totalEntries,
            'total_attempts' => $totalAttempts,
            'max_attempts' => $this->maxAttempts,
            'decay_minutes' => $this->decayMinutes,
            'memory_usage' => memory_get_usage(true)
        ];
    }
}