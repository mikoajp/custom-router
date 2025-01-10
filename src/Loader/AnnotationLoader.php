<?php

namespace Custom\Router\Loader;

use Custom\Router\Collection\RouteCollection;
use Custom\Router\Interfaces\LoaderInterface;
use Custom\Router\Route;
use Custom\Router\Exception\RouterException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionAttribute;

class AnnotationLoader implements LoaderInterface
{
    private string $controllerDirectory;

    public function __construct(string $controllerDirectory)
    {
        $this->controllerDirectory = rtrim($controllerDirectory, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function load(string $resource): RouteCollection
    {
        if (!is_dir($this->controllerDirectory)) {
            throw new RouterException(sprintf('Directory "%s" not found.', $this->controllerDirectory));
        }

        $routes = new RouteCollection();
        $controllerFiles = $this->findControllerFiles();

        foreach ($controllerFiles as $file) {
            $className = $this->getClassNameFromFile($file);
            if (!$className) {
                continue;
            }

            $this->loadClassRoutes($className, $routes);
        }

        return $routes;
    }

    /**
     * Ładuje trasy z klasy kontrolera
     */
    private function loadClassRoutes(string $className, RouteCollection $routes): void
    {
        $reflectionClass = new ReflectionClass($className);

        $classPrefix = '';
        $classAttributes = $reflectionClass->getAttributes(Route::class);
        if (!empty($classAttributes)) {
            $classRoute = $classAttributes[0]->newInstance();
            $classPrefix = rtrim($classRoute->getPath(), '/');
        }

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF);

            foreach ($attributes as $attribute) {
                $route = $attribute->newInstance();
                $path = $classPrefix . '/' . ltrim($route->getPath(), '/');

                $routeName = $this->generateRouteName($className, $method->getName());
                $defaults = array_merge($route->getDefaults(), [
                    '_controller' => $className . '::' . $method->getName()
                ]);

                $routes->add($routeName, new Route(
                    $path,
                    $defaults,
                    $route->getRequirements(),
                    $route->getOptions()
                ));
            }
        }
    }

    /**
     * Znajduje wszystkie pliki kontrolerów
     *
     * @return array<string>
     */
    private function findControllerFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->controllerDirectory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }

    /**
     * Generuje nazwę trasy na podstawie klasy i metody
     */
    private function generateRouteName(string $className, string $methodName): string
    {
        $className = str_replace('\\', '_', $className);
        return strtolower(sprintf('%s_%s', $className, $methodName));
    }

    /**
     * Pobiera nazwę klasy z pliku
     */
    private function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        if (preg_match('/namespace\s+([^;]+);/i', $content, $matches)) {
            $namespace = $matches[1];
            if (preg_match('/class\s+(\w+)/i', $content, $matches)) {
                return $namespace . '\\' . $matches[1];
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $resource): bool
    {
        return is_dir($resource) && str_ends_with($resource, 'Controller');
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'annotation';
    }
}