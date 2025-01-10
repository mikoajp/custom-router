<?php

namespace Custom\Router\Interfaces;

interface RouteInterface
{
    public function getPath(): string;
    public function setPath(string $path): self;

    public function getDefaults(): array;
    public function setDefaults(array $defaults): self;
    public function addDefault(string $name, mixed $value): self;

    public function getRequirements(): array;
    public function setRequirements(array $requirements): self;
    public function addRequirement(string $name, string $requirement): self;

    public function getOptions(): array;
    public function setOptions(array $options): self;

    public function getMethods(): array;
    public function setMethods(array $methods): self;
    public function hasMethod(string $method): bool;

    public function getSchemes(): array;
    public function setSchemes(array $schemes): self;
    public function hasScheme(string $scheme): bool;

    public function getHost(): string;
    public function setHost(string $host): self;

    public function getMiddlewares(): array;
    public function setMiddlewares(array $middlewares): self;
    public function addMiddleware(string $middleware): self;

    public function compile(): string;
}
