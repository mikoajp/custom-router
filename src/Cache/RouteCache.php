<?php

namespace Custom\Router\Cache;

use Custom\Router\Collection\RouteCollection;
use Custom\Router\Interfaces\CacheInterface;

class RouteCache implements CacheInterface
{
    private string $cacheDir;
    private array $cache = [];
    private const CACHE_EXTENSION = '.php.cache';

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    public function get(string $key): mixed
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $file = $this->getCacheFile($key);
        if (file_exists($file) && is_readable($file)) {
            $data = include $file;
            $this->cache[$key] = $data;
            return $data;
        }

        return null;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->cache[$key] = $value;

        $file = $this->getCacheFile($key);
        $content = sprintf('<?php return %s;', var_export($value, true));

        if (file_put_contents($file, $content) === false) {
            return false;
        }

        if ($ttl !== null) {
            touch($file, time() + $ttl);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key]);

        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function has(string $key): bool
    {
        if (isset($this->cache[$key])) {
            return true;
        }

        $file = $this->getCacheFile($key);
        if (!file_exists($file)) {
            return false;
        }

        $mtime = filemtime($file);
        if ($mtime === false || $mtime < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];

        $files = glob($this->cacheDir . '/*' . self::CACHE_EXTENSION);
        if ($files === false) {
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Zapisuje kolekcję tras do cache
     */
    public function cacheRoutes(RouteCollection $routes, string $key = 'routes'): bool
    {
        $data = [
            'routes' => $routes,
            'created_at' => time()
        ];

        return $this->set($key, $data);
    }

    /**
     * Pobiera kolekcję tras z cache
     */
    public function getRoutes(string $key = 'routes'): ?RouteCollection
    {
        $data = $this->get($key);
        if ($data === null) {
            return null;
        }

        return $data['routes'];
    }

    /**
     * Generuje nazwę pliku cache
     */
    private function getCacheFile(string $key): string
    {
        return sprintf(
            '%s/%s%s',
            $this->cacheDir,
            md5($key),
            self::CACHE_EXTENSION
        );
    }

    /**
     * Zwraca ścieżkę do katalogu cache
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }
}