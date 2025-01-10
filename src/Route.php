<?php

namespace Custom\Router;

use Custom\Router\Interfaces\RouteInterface;

class Route implements RouteInterface
{
    private string $path;
    private array $defaults;
    private array $requirements;
    private array $options;
    private array $methods;
    private array $schemes;
    private string $host;
    private array $middlewares;

    public function __construct(
        string $path,
        array $defaults = [],
        array $requirements = [],
        array $options = [],
        ?string $host = null,
        array $schemes = [],
        array $methods = [],
        array $middlewares = []
    ) {
        $this->path = $this->normalizePath($path);
        $this->defaults = $defaults;
        $this->requirements = $requirements;
        $this->options = $options;
        $this->host = $host ?? '';
        $this->schemes = array_map('strtolower', $schemes);
        $this->methods = array_map('strtoupper', $methods);
        $this->middlewares = $middlewares;
    }


    private function normalizePath(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    public function getPath(): string { return $this->path; }
    public function setPath(string $path): self { $this->path = $this->normalizePath($path); return $this; }

    public function getDefaults(): array { return $this->defaults; }
    public function setDefaults(array $defaults): self { $this->defaults = $defaults; return $this; }
    public function addDefault(string $name, mixed $value): self { $this->defaults[$name] = $value; return $this; }

    public function getRequirements(): array { return $this->requirements; }
    public function setRequirements(array $requirements): self { $this->requirements = $requirements; return $this; }
    public function addRequirement(string $name, string $requirement): self { $this->requirements[$name] = $requirement; return $this; }

    public function getOptions(): array { return $this->options; }
    public function setOptions(array $options): self { $this->options = $options; return $this; }

    public function getMethods(): array { return $this->methods; }
    public function setMethods(array $methods): self { $this->methods = array_map('strtoupper', $methods); return $this; }
    public function hasMethod(string $method): bool { return empty($this->methods) || in_array(strtoupper($method), $this->methods); }

    public function getSchemes(): array { return $this->schemes; }
    public function setSchemes(array $schemes): self { $this->schemes = array_map('strtolower', $schemes); return $this; }
    public function hasScheme(string $scheme): bool { return empty($this->schemes) || in_array(strtolower($scheme), $this->schemes); }

    public function getHost(): string { return $this->host; }
    public function setHost(string $host): self { $this->host = $host; return $this; }

    public function getMiddlewares(): array { return $this->middlewares; }
    public function setMiddlewares(array $middlewares): self { $this->middlewares = $middlewares; return $this; }
    public function addMiddleware(string $middleware): self {
        if (!in_array($middleware, $this->middlewares)) { $this->middlewares[] = $middleware; }
        return $this;
    }

    public function compile(): string
    {
        $path = $this->path;
        foreach ($this->requirements as $name => $requirement) {
            $path = str_replace('{' . $name . '}', '(?P<' . $name . '>' . $requirement . ')', $path);
        }
        $path = preg_replace('/\{([^}]+)}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $path . '$#sD';
    }
}