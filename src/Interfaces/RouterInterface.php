<?php

namespace Custom\Router\Interfaces;

use Custom\Router\Route;
use Custom\Router\Collection\RouteCollection;
use Custom\Router\Exception\RouterException;
use Custom\Router\Exception\ResourceNotFoundException;

interface RouterInterface
{
    /**
     * Dodaje trasę do routera.
     *
     * @param string $name Nazwa trasy
     * @param Route $route Obiekt trasy
     */
    public function addRoute(string $name, Route $route): void;

    /**
     * Pobiera wszystkie trasy.
     *
     * @return RouteCollection
     */
    public function getRoutes(): RouteCollection;

    /**
     * Dopasowuje ścieżkę URL do trasy i zwraca jej parametry.
     *
     * @param string $path Ścieżka URL
     * @return array
     * @throws ResourceNotFoundException
     */
    public function match(string $path): array;

    /**
     * Generuje URL na podstawie nazwy trasy i parametrów.
     *
     * @param string $name Nazwa trasy
     * @param array $params Parametry do wstawienia w URL
     * @return string
     * @throws RouterException
     */
    public function generateUrl(string $name, array $params = []): string;
}
