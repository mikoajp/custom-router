<?php

namespace Custom\Router\Matcher;

use Custom\Router\Collection\RouteCollection;
use Custom\Router\Interfaces\UrlMatcherInterface;
use Custom\Router\Exception\ResourceNotFoundException;
use Custom\Router\Exception\MethodNotAllowedException;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher\GroupCountBased as GroupCountBasedDispatcher;

/**
 * FastRoute integration for high-performance URL matching
 */
class FastRouteMatcher implements UrlMatcherInterface
{
    private RouteCollection $routes;
    private ?Dispatcher $dispatcher = null;
    private array $routeMap = [];

    public function __construct(RouteCollection $routes)
    {
        $this->routes = $routes;
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $path): array
    {
        $dispatcher = $this->getDispatcher();
        $httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        $routeInfo = $dispatcher->dispatch($httpMethod, $path);
        
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new ResourceNotFoundException("No route found for path: " . $path);
                
            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                throw new MethodNotAllowedException(
                    sprintf('Method "%s" not allowed. Allowed methods: %s', 
                        $httpMethod, 
                        implode(', ', $allowedMethods)
                    )
                );
                
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                
                if (!isset($this->routeMap[$handler])) {
                    throw new ResourceNotFoundException("Route handler not found: " . $handler);
                }
                
                $route = $this->routeMap[$handler]['route'];
                $routeName = $this->routeMap[$handler]['name'];
                
                return array_merge(
                    $route->getDefaults(),
                    $vars,
                    ['_route' => $routeName]
                );
                
            default:
                throw new ResourceNotFoundException("Unknown routing result");
        }
    }

    /**
     * Get or create FastRoute dispatcher
     */
    private function getDispatcher(): Dispatcher
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = $this->createDispatcher();
        }
        
        return $this->dispatcher;
    }

    /**
     * Create FastRoute dispatcher from route collection
     */
    private function createDispatcher(): GroupCountBasedDispatcher
    {
        $routeParser = new RouteParser();
        $dataGenerator = new DataGenerator();
        
        foreach ($this->routes->all() as $name => $route) {
            $methods = $route->getMethods();
            if (empty($methods)) {
                $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
            }
            
            $handler = $this->generateHandler($name);
            $this->routeMap[$handler] = [
                'name' => $name,
                'route' => $route
            ];
            
            $routeDatas = $routeParser->parse($route->getPath());
            
            foreach ($methods as $method) {
                foreach ($routeDatas as $routeData) {
                    $dataGenerator->addRoute($method, $routeData, $handler);
                }
            }
        }
        
        return new GroupCountBasedDispatcher($dataGenerator->getData());
    }

    /**
     * Generate unique handler identifier
     */
    private function generateHandler(string $routeName): string
    {
        return 'route_' . md5($routeName);
    }

    /**
     * Clear dispatcher cache (useful for testing)
     */
    public function clearCache(): void
    {
        $this->dispatcher = null;
        $this->routeMap = [];
    }
}