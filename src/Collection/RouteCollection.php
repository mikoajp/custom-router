<?php

namespace Custom\Router\Collection;

use ArrayIterator;
use Custom\Router\Route;
use Custom\Router\Exception\RouterException;
use Traversable;

class RouteCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var array<string, Route>
     */
    private array $routes = [];

    /**
     * Dodaje trasę do kolekcji.
     *
     * @param string $name Nazwa trasy
     * @param Route $route Obiekt trasy
     * @throws RouterException jeśli trasa o danej nazwie już istnieje
     */
    public function add(string $name, Route $route): void
    {
        if (isset($this->routes[$name])) {
            throw new RouterException(sprintf('Route "%s" already exists', $name));
        }
        $this->routes[$name] = $route;
    }

    /**
     * Zwraca wszystkie trasy.
     *
     * @return array<string, Route>
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Zwraca trasę o podanej nazwie.
     *
     * @param string $name Nazwa trasy
     * @return Route|null
     */
    public function get(string $name): ?Route
    {
        return $this->routes[$name] ?? null;
    }

    /**
     * Sprawdza czy trasa o podanej nazwie istnieje.
     *
     * @param string $name Nazwa trasy
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->routes[$name]);
    }

    /**
     * Usuwa trasę o podanej nazwie.
     *
     * @param string $name Nazwa trasy
     * @return void
     */
    public function remove(string $name): void
    {
        unset($this->routes[$name]);
    }

    /**
     * Szuka trasy według ścieżki.
     *
     * @param string $path
     * @return Route|null
     */
    public function findByPath(string $path): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->getPath() === $path) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Zwraca nazwy wszystkich tras.
     *
     * @return array<string>
     */
    public function getNames(): array
    {
        return array_keys($this->routes);
    }

    /**
     * Łączy dwie kolekcje tras.
     *
     * @param RouteCollection $collection
     * @throws RouterException jeśli występują konflikty nazw
     */
    public function addCollection(RouteCollection $collection): void
    {
        foreach ($collection->all() as $name => $route) {
            $this->add($name, $route);
        }
    }

    /**
     * Implementacja interfejsu IteratorAggregate.
     *
     * @return Traversable<string, Route>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->routes);
    }

    /**
     * Implementacja interfejsu Countable.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Czyści wszystkie trasy.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->routes = [];
    }
}