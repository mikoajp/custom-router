<?php

namespace Custom\Router;

use Custom\Router\Interfaces\RouterInterface;
use Custom\Router\Collection\RouteCollection;
use Custom\Router\Matcher\UrlMatcher;
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

    public function __construct()
    {
        $this->routes = new RouteCollection();
        $this->matcher = new UrlMatcher($this->routes);
        $this->generator = new UrlGenerator($this->routes);
        $this->middlewarePipeline = new MiddlewarePipeline();

        $this->logger = new Logger('router');
        $this->logger->pushHandler(new StreamHandler(__DIR__.'/../../logs/router.log', Level::Debug));

        $this->middlewarePipeline->add(new LoggingMiddleware($this->logger));
    }

    /**
     * Dodaje trasę do routera.
     *
     * @param string $name Nazwa trasy
     * @param Route $route Obiekt trasy
     * @throws RouterException
     */
    public function addRoute(string $name, Route $route): void
    {
        $this->routes->add($name, $route);
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

                return new Response(
                    json_encode([
                        'status' => 'success',
                        'route' => $params
                    ]),
                    Response::HTTP_OK,
                    ['Content-Type' => 'application/json']
                );
            } catch (ResourceNotFoundException $e) {
                return new Response(
                    json_encode(['error' => 'Route not found']),
                    Response::HTTP_NOT_FOUND,
                    ['Content-Type' => 'application/json']
                );
            } catch (RouterException $e) {
                return new Response(
                    json_encode(['error' => 'Router error', 'message' => $e->getMessage()]),
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    ['Content-Type' => 'application/json']
                );
            }
        });
    }
}
