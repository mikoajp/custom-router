<?php

namespace Custom\Router\Interfaces;

interface CacheInterface
{
    /**
     * Pobiera wartość z cache
     *
     * @param string $key
     * @return mixed|null Wartość lub null jeśli nie znaleziono
     */
    public function get(string $key): mixed;

    /**
     * Zapisuje wartość w cache
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl Czas życia w sekundach
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Usuwa wartość z cache
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * Sprawdza czy klucz istnieje w cache
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Czyści cały cache
     *
     * @return bool
     */
    public function clear(): bool;
}