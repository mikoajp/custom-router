<?php

namespace Custom\Router;

use Custom\Router\Interfaces\RouterInterface;
use Custom\Router\Collection\RouteCollection;
use Custom\Router\Matcher\UrlMatcher;
use Custom\Router\Matcher\FastRouteMatcher;
use Custom\Router\Cache\MemoryOptimizer;
use Custom\Router\Generator\UrlGenerator;
use Custom\Router\Exception\ResourceNotFoundException;
use Custom\Router\Exception\RouterException;
use Custom\Router\Middleware\LoggingMiddleware;
use Custom\Router\Middleware\MiddlewarePipeline;
use Custom\Router\Interfaces\MiddlewareInterface;
use Monolog\Level;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Router implements RouterInterface
{
    private RouteCollection $routes;
    private UrlMatcher $matcher;
    private UrlGenerator $generator;
    private MiddlewarePipeline $middlewarePipeline;
    private LoggerInterface $logger;

    public function __construct(RouteCollection $routes = null, bool $useFastRoute = true)
    {
        $this->routes = $routes ?? new RouteCollection();
        
        // Use FastRoute for better performance if available
        if ($useFastRoute && class_exists('FastRoute\Dispatcher')) {
            $this->matcher = new FastRouteMatcher($this->routes);
        } else {
            $this->matcher = new UrlMatcher($this->routes);
        }
        
        $this->generator = new UrlGenerator($this->routes);
        $this->middlewarePipeline = new MiddlewarePipeline();

        $logDir = __DIR__.'/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $this->logger = new Logger('router');
        $this->logger->pushHandler(new StreamHandler($logDir.'/router.log', Level::Debug));

        $this->middlewarePipeline->add(new LoggingMiddleware($this->logger));
    }

    /**
     * Dodaje trasę do routera.
     *
     * @throws RouterException
     */
    public function addRoute(string $name, Route $route): void
    {
        $this->routes->add($name, $route);
        $this->logger->debug(sprintf('Added route: %s with path: %s', $name, $route->getPath()));
    }

    /**
     * Pobiera wszystkie trasy.
     */
    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Dopasowuje ścieżkę URL do trasy i zwraca parametry.
     * @throws ResourceNotFoundException
     */
    public function match(string $path): array
    {
        $this->logger->debug(sprintf('Matching path: %s', $path));
        return $this->matcher->match($path);
    }

    /**
     * Generuje URL na podstawie nazwy trasy.
     * @throws RouterException
     */
    public function generateUrl(string $name, array $params = []): string
    {
        return $this->generator->generate($name, $params);
    }

    /**
     * Dodaje middleware do kolejki.
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewarePipeline->add($middleware);
    }

    /**
     * Obsługuje żądanie HTTP i zwraca odpowiedź.
     */
    public function handle(Request $request): Response
    {
        return $this->middlewarePipeline->handle($request, function (Request $req) {
            try {
                $params = $this->matcher->match($req->getPathInfo());

                if (isset($params['_controller'])) {
                    return $this->callController($params, $req);
                }

                return new Response(
                    json_encode([
                        'status' => 'success',
                        'route' => $params
                    ]),
                    Response::HTTP_OK,
                    ['Content-Type' => 'application/json']
                );
            } catch (ResourceNotFoundException $e) {
                $this->logger->error('Route not found', ['path' => $req->getPathInfo()]);
                return new Response(
                    json_encode(['error' => 'Route not found']),
                    Response::HTTP_NOT_FOUND,
                    ['Content-Type' => 'application/json']
                );
            } catch (RouterException $e) {
                $this->logger->error('Router error', [
                    'message' => $e->getMessage(),
                    'path' => $req->getPathInfo()
                ]);
                return new Response(
                    json_encode(['error' => 'Router error', 'message' => $e->getMessage()]),
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    ['Content-Type' => 'application/json']
                );
            }
        });
    }

    /**
     * Wywołuje kontroler dla dopasowanej trasy.
     *
     * @throws RouterException
     */
    private function callController(array $params, Request $request): Response
    {
        $controller = $params['_controller'];

        if (is_string($controller)) {
            [$class, $method] = explode('::', $controller);

            if (!class_exists($class)) {
                throw new RouterException(sprintf('Controller class "%s" not found', $class));
            }

            $controller = [new $class(), $method];
        }

        if (!is_callable($controller)) {
            throw new RouterException('Controller is not callable');
        }

        $routeParams = array_filter($params, fn($key) => !str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);

        try {
            $response = call_user_func($controller, $request, ...array_values($routeParams));

            if (!$response instanceof Response) {
                throw new RouterException('Controller must return Response object');
            }

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Controller error', [
                'controller' => $params['_controller'],
                'message' => $e->getMessage()
            ]);
            throw new RouterException('Controller execution failed: ' . $e->getMessage());
        }
    }
}