<?php

namespace Custom\Router\Middleware;

use Custom\Router\Interfaces\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Custom\Router\Exception\RouterException;

/**
 * CSRF Protection Middleware
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private string $tokenName;
    private string $headerName;
    private array $excludedMethods;
    private array $excludedPaths;
    private int $tokenLength;

    public function __construct(
        string $tokenName = '_csrf_token',
        string $headerName = 'X-CSRF-Token',
        array $excludedMethods = ['GET', 'HEAD', 'OPTIONS'],
        array $excludedPaths = [],
        int $tokenLength = 32
    ) {
        $this->tokenName = $tokenName;
        $this->headerName = $headerName;
        $this->excludedMethods = array_map('strtoupper', $excludedMethods);
        $this->excludedPaths = $excludedPaths;
        $this->tokenLength = $tokenLength;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->getMethod(), $this->excludedMethods)) {
            return $next($request);
        }

        foreach ($this->excludedPaths as $path) {
            if (fnmatch($path, $request->getPathInfo())) {
                return $next($request);
            }
        }

        if (!$this->validateToken($request)) {
            return new Response(
                json_encode(['error' => 'CSRF token mismatch']),
                Response::HTTP_FORBIDDEN,
                ['Content-Type' => 'application/json']
            );
        }

        return $next($request);
    }

    /**
     * Validate CSRF token from request
     */
    private function validateToken(Request $request): bool
    {
        $sessionToken = $this->getSessionToken($request);
        if (!$sessionToken) {
            return false;
        }

        $requestToken = $request->request->get($this->tokenName);

        if (!$requestToken) {
            $requestToken = $request->headers->get($this->headerName);
        }

        if (!$requestToken) {
            return false;
        }

        return hash_equals($sessionToken, $requestToken);
    }

    /**
     * Get CSRF token from session
     */
    private function getSessionToken(Request $request): ?string
    {
        $session = $request->getSession();
        if (!$session) {
            return null;
        }

        $token = $session->get('_csrf_token');
        if (!$token) {
            $token = $this->generateToken();
            $session->set('_csrf_token', $token);
        }

        return $token;
    }

    /**
     * Generate new CSRF token
     */
    public function generateToken(): string
    {
        return bin2hex(random_bytes($this->tokenLength));
    }

    /**
     * Get current CSRF token for forms
     */
    public function getToken(Request $request): string
    {
        return $this->getSessionToken($request) ?? $this->generateToken();
    }
}