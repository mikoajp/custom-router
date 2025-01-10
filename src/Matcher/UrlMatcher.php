<?php

namespace Custom\Router\Matcher;

use Custom\Router\Route;
use Custom\Router\Collection\RouteCollection;
use Custom\Router\Interfaces\UrlMatcherInterface;
use Custom\Router\Exception\ResourceNotFoundException;

class UrlMatcher implements UrlMatcherInterface
{
    private RouteCollection $routes;

    public function __construct(RouteCollection $routes)
    {
        $this->routes = $routes;
    }

    /**
     * Dopasowuje ścieżkę URL do odpowiedniej trasy.
     *
     * @param string $path Ścieżka URL do dopasowania
     * @return array Zwraca tablicę parametrów i domyślnych wartości trasy
     * @throws ResourceNotFoundException Jeśli trasa nie została znaleziona
     */
    public function match(string $path): array
    {
        foreach ($this->routes->all() as $name => $route) {
            $pattern = $route->compile();

            if (preg_match($pattern, $path, $matches)) {
                return array_merge(
                    $this->extractParameters($route, $matches),
                    ['_route' => $name]
                );
            }
        }

        throw new ResourceNotFoundException("No route found for path: " . $path);
    }
    /**
     * Wyodrębnia parametry z dopasowanej trasy.
     *
     * @param Route $route Dopasowana trasa
     * @param array $matches Dopasowania z wyrażenia regularnego
     * @return array Zwraca tablicę z parametrami oraz wartościami domyślnymi
     */
    private function extractParameters(Route $route, array $matches): array
    {
        $params = [];

        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }

        return array_merge($route->getDefaults(), $params);
    }
}