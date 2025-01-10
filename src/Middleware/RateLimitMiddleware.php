<?php

namespace Custom\Router\Middleware;

use Custom\Router\Interfaces\MiddlewareInterface;
use Custom\Router\Interfaces\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Custom\Router\Exception\RateLimitExceededException;

class RateLimitMiddleware implements MiddlewareInterface
{
    private CacheInterface $cache;
    private int $limit;
    private int $window;
    private array $excludedPaths;
    private array $excludedIps;

    public function __construct(
        CacheInterface $cache,
        int $limit = 100,
        int $window = 3600,
        array $excludedPaths = [],
        array $excludedIps = []
    ) {
        $this->cache = $cache;
        $this->limit = $limit;
        $this->window = $window;
        $this->excludedPaths = $excludedPaths;
        $this->excludedIps = $excludedIps;
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->isPathExcluded($request->getPathInfo())) {
            return $next($request);
        }

        if ($this->isIpExcluded($request->getClientIp())) {
            return $next($request);
        }

        $key = $this->generateCacheKey($request);
        $rateLimit = $this->getRateLimit($key);

        if ($rateLimit['remaining'] <= 0) {
            $resetTime = $rateLimit['reset'] - time();
            throw new RateLimitExceededException(
                sprintf('Rate limit exceeded. Try again in %d seconds.', $resetTime)
            );
        }

        $rateLimit['hits']++;
        $rateLimit['remaining']--;
        $this->cache->set($key, $rateLimit, $this->window);

        $response = $next($request);

        $response->headers->add([
            'X-RateLimit-Limit' => $this->limit,
            'X-RateLimit-Remaining' => $rateLimit['remaining'],
            'X-RateLimit-Reset' => $rateLimit['reset']
        ]);

        return $response;
    }

    private function generateCacheKey(Request $request): string
    {
        return sprintf(
            'rate_limit_%s_%s',
            $request->getClientIp(),
            md5($request->getPathInfo())
        );
    }

    private function getRateLimit(string $key): array
    {
        $data = $this->cache->get($key);

        if ($data === null || time() > $data['reset']) {
            return [
                'hits' => 0,
                'remaining' => $this->limit,
                'reset' => time() + $this->window
            ];
        }

        return $data;
    }

    private function isPathExcluded(string $path): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            if (preg_match('#^' . $excludedPath . '#', $path)) {
                return true;
            }
        }
        return false;
    }

    private function isIpExcluded(string $ip): bool
    {
        return in_array($ip, $this->excludedIps);
    }
}