<?php

namespace Custom\Router\Matcher;

use Custom\Router\Collection\RouteCollection;
use Custom\Router\Exception\ResourceNotFoundException;
use Custom\Router\Exception\MethodNotAllowedException;
use Custom\Router\Exception\RouterException;
use Custom\Router\Interfaces\UrlMatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class RequestMatcher implements UrlMatcherInterface
{
    private RouteCollection $routes;
    private array $context;

    public function __construct(RouteCollection $routes)
    {
        $this->routes = $routes;
        $this->context = [
            'host' => null,
            'method' => 'GET',
            'scheme' => 'http'
        ];
    }

    public function match(string $path): array
    {
        $allowedMethods = [];

        foreach ($this->routes->all() as $name => $route) {
            $pattern = $this->compilePattern($route->getPath());

            if (!preg_match($pattern, $path, $matches)) {
                continue;
            }

            $methods = $route->getDefaults()['methods'] ?? ['GET'];
            if (!in_array($this->context['method'], $methods)) {
                $allowedMethods = array_merge($allowedMethods, $methods);
                continue;
            }

            $schemes = $route->getDefaults()['schemes'] ?? ['http', 'https'];
            if (!in_array($this->context['scheme'], $schemes)) {
                continue;
            }
            $host = $route->getDefaults()['host'] ?? null;
            if ($host !== null && $host !== $this->context['host']) {
                continue;
            }

            return $this->extractRouteData($name, $route, $matches);
        }

        if (!empty($allowedMethods)) {
            throw new MethodNotAllowedException($allowedMethods);
        }

        throw new ResourceNotFoundException(sprintf('No route found for path "%s"', $path));
    }

    /**
     * @throws ResourceNotFoundException
     * @throws RouterException
     */
    public function matchRequest(Request $request): array
    {
        $this->context = [
            'host' => $request->getHost(),
            'method' => $request->getMethod(),
            'scheme' => $request->getScheme()
        ];

        return $this->match($request->getPathInfo());
    }

    private function compilePattern(string $path): string
    {
        $pattern = preg_replace_callback(
            '/{(\w+)}/',
            function ($matches) {
                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $path
        );

        return '#^' . $pattern . '$#';
    }

    private function extractRouteData(string $name, $route, array $matches): array
    {
        $params = $this->extractParameters($matches);

        return array_merge(
            [
                '_route' => $name,
                '_controller' => $route->getDefaults()['_controller'] ?? null,
            ],
            $route->getDefaults(),
            $params
        );
    }

    private function extractParameters(array $matches): array
    {
        $parameters = [];

        foreach ($matches as $key => $value) {
            if (is_string($key) && $value !== '') {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Ustawia kontekst dopasowywania
     */
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    /**
     * Sprawdza wymagania parametrów trasy
     */
    private function checkRequirements(array $requirements, array $parameters): bool
    {
        foreach ($requirements as $key => $pattern) {
            if (!isset($parameters[$key]) || !preg_match('#^' . $pattern . '$#', $parameters[$key])) {
                return false;
            }
        }

        return true;
    }
}