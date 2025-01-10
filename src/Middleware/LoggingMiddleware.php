<?php

namespace Custom\Router\Middleware;

use Custom\Router\Interfaces\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Monolog\Level;
use Throwable;

class LoggingMiddleware implements MiddlewareInterface
{
    private PsrLoggerInterface $logger;

    public function __construct(PsrLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function handle(Request $request, callable $next): Response
    {
        $startTime = microtime(true);

        $this->logger->log(Level::Info, 'Incoming request', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'ip' => $request->getClientIp(),
            'headers' => $request->headers->all(),
            'query' => $request->query->all(),
            'body' => $request->getContent()
        ]);

        try {
            $response = $next($request);

            $this->logger->log(Level::Info, 'Matched route', [
                'route' => $request->attributes->get('_route', 'N/A'),
                'controller' => $request->attributes->get('_controller', 'N/A'),
                'params' => $request->attributes->all(),
                'method' => $request->getMethod()
            ]);

            $this->logger->log(Level::Info, 'Response sent', [
                'status' => $response->getStatusCode(),
                'content_length' => strlen($response->getContent()),
                'execution_time' => round(microtime(true) - $startTime, 4) . 's'
            ]);

            return $response;
        } catch (Throwable $e) {
            $this->logger->log(Level::Error, 'Request failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'status_code' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500,
                'trace' => $e->getTraceAsString(),
                'execution_time' => round(microtime(true) - $startTime, 4) . 's'
            ]);

            throw $e;
        }
    }
}
