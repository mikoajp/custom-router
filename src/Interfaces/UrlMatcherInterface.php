<?php

namespace Custom\Router\Interfaces;

use Custom\Router\Exception\ResourceNotFoundException;
use Custom\Router\Exception\RouterException;

interface UrlMatcherInterface
{
    /**
     * Dopasowuje ścieżkę URL do odpowiedniej trasy.
     *
     * @param string $path Ścieżka URL do dopasowania
     * @return array Zwraca tablicę parametrów i domyślnych wartości trasy
     * @throws ResourceNotFoundException Jeśli trasa nie została znaleziona
     * @throws RouterException Jeśli wystąpił błąd w przetwarzaniu trasy
     */
    public function match(string $path): array;
}