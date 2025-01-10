<?php

namespace Custom\Router\Loader;

use Custom\Router\Collection\RouteCollection;
use Custom\Router\Interfaces\LoaderInterface;
use Custom\Router\Route;
use Custom\Router\Exception\RouterException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class YamlLoader implements LoaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(string $resource): RouteCollection
    {
        if (!file_exists($resource)) {
            throw new RouterException(sprintf('File "%s" not found.', $resource));
        }

        try {
            $routes = new RouteCollection();
            $config = Yaml::parseFile($resource);

            foreach ($config as $name => $routeData) {
                if (!isset($routeData['path'])) {
                    throw new RouterException(sprintf('Route "%s" must have a path.', $name));
                }

                $route = new Route(
                    $routeData['path'],
                    $routeData['defaults'] ?? [],
                    $routeData['requirements'] ?? [],
                    $routeData['options'] ?? []
                );

                $routes->add($name, $route);
            }

            return $routes;
        } catch (ParseException $e) {
            throw new RouterException(sprintf('Error parsing YAML file: %s', $e->getMessage()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $resource): bool
    {
        return pathinfo($resource, PATHINFO_EXTENSION) === 'yaml'
            || pathinfo($resource, PATHINFO_EXTENSION) === 'yml';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'yaml';
    }
}