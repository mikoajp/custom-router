<?php

namespace Custom\Router\Interfaces;

use Custom\Router\Exception\RouteNotFoundException;

interface UrlGeneratorInterface
{
    /**
     * Typy referencji URL
     */
    public const ABSOLUTE_URL = 0;    // Pełny URL z protokołem i hostem (np. https://example.com/blog)
    public const ABSOLUTE_PATH = 1;    // Ścieżka bezwzględna (np. /blog)
    public const RELATIVE_PATH = 2;    // Ścieżka względna (np. ../blog)
    public const NETWORK_PATH = 3;     // URL sieciowy bez protokołu (np. //example.com/blog)

    /**
     * Generuje URL dla podanej trasy
     *
     * @param string $name Nazwa trasy
     * @param array $parameters Parametry do wstawienia w URL
     * @param int $referenceType Typ generowanego URL-a (jedna ze stałych powyżej)
     * @return string Wygenerowany URL
     * @throws RouteNotFoundException jeśli trasa nie istnieje
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string;

    /**
     * Generuje absolutny URL (z protokołem i hostem)
     *
     * @param string $name Nazwa trasy
     * @param array $parameters Parametry do wstawienia w URL
     * @return string Pełny URL
     * @throws RouteNotFoundException jeśli trasa nie istnieje
     */
    public function generateAbsolute(string $name, array $parameters = []): string;

    /**
     * Generuje relatywny URL względem aktualnej ścieżki
     *
     * @param string $name Nazwa trasy
     * @param array $parameters Parametry do wstawienia w URL
     * @return string Relatywny URL
     * @throws RouteNotFoundException jeśli trasa nie istnieje
     */
    public function generateRelative(string $name, array $parameters = []): string;

    /**
     * Sprawdza czy trasa o podanej nazwie istnieje
     *
     * @param string $name Nazwa trasy
     * @return bool
     */
    public function hasRoute(string $name): bool;

    /**
     * Zwraca aktualny kontekst URL (bazowy URL, host, scheme itp.)
     *
     * @return array Kontekst URL
     */
    public function getContext(): array;

    /**
     * Ustawia nowy kontekst URL
     *
     * @param array $context Nowy kontekst
     * @return void
     */
    public function setContext(array $context): void;
}