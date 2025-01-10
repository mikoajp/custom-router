<?php

namespace Custom\Router\Interfaces;

use Custom\Router\Collection\RouteCollection;
use Custom\Router\Exception\RouterException;

interface LoaderInterface
{
    /**
     * Ładuje trasy z zasobu.
     *
     * @param string $resource Ścieżka do pliku z konfiguracją tras
     * @throws RouterException gdy wystąpi błąd podczas ładowania
     */
    public function load(string $resource): RouteCollection;

    /**
     * Sprawdza czy loader obsługuje dany zasób.
     *
     * @param string $resource Ścieżka do pliku
     */
    public function supports(string $resource): bool;

    /**
     * Zwraca nazwę loadera.
     */
    public function getName(): string;
}