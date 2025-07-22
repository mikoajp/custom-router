<?php

namespace Custom\Router\Middleware;

use Custom\Router\Interfaces\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security Headers Middleware
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private array $headers;
    private bool $forceHttps;

    public function __construct(array $customHeaders = [], bool $forceHttps = false)
    {
        $this->forceHttps = $forceHttps;
        $this->headers = array_merge([
            // Prevent XSS attacks
            'X-XSS-Protection' => '1; mode=block',
            
            // Prevent MIME type sniffing
            'X-Content-Type-Options' => 'nosniff',
            
            // Prevent clickjacking
            'X-Frame-Options' => 'DENY',
            
            // Referrer policy
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            
            // Content Security Policy (basic)
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            
            // Permissions policy
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            
            // HSTS (if HTTPS)
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        ], $customHeaders);
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->forceHttps && !$request->isSecure()) {
            $httpsUrl = 'https://' . $request->getHost() . $request->getRequestUri();
            return new Response('', Response::HTTP_MOVED_PERMANENTLY, [
                'Location' => $httpsUrl
            ]);
        }

        $response = $next($request);

        foreach ($this->headers as $name => $value) {
            if ($name === 'Strict-Transport-Security' && !$request->isSecure()) {
                continue;
            }
            
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * Set Content Security Policy
     */
    public function setCSP(string $policy): void
    {
        $this->headers['Content-Security-Policy'] = $policy;
    }

    /**
     * Set custom security header
     */
    public function setHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    /**
     * Remove security header
     */
    public function removeHeader(string $name): void
    {
        unset($this->headers[$name]);
    }
}