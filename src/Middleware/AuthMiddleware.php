<?php

namespace Custom\Router\Middleware;

use Custom\Router\Interfaces\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddleware implements MiddlewareInterface
{
    private array $excludedRoutes;

    public function __construct(array $excludedRoutes = [])
    {
        $this->excludedRoutes = $excludedRoutes;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->getPathInfo(), $this->excludedRoutes)) {
            return $next($request);
        }

        $token = $request->headers->get('Authorization');
        if (!$token) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->validateToken($token)) {
            return new Response('Invalid token', Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    private function validateToken(string $token): bool
    {
        return true;
    }
}
