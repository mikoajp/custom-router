<?php

namespace Custom\Router\Generator;

use Custom\Router\Collection\RouteCollection;
use Custom\Router\Exception\RouteNotFoundException;
use Custom\Router\Exception\RouterException;
use Custom\Router\Interfaces\UrlGeneratorInterface;

class UrlGenerator implements UrlGeneratorInterface
{
    private RouteCollection $routes;
    private array $context = [
        'host' => '',
        'scheme' => 'http',
        'baseUrl' => '',
        'port' => null
    ];

    public function __construct(RouteCollection $routes)
    {
        $this->routes = $routes;
    }

    /**
     * {@inheritdoc}
     * @throws RouterException
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        if (!$this->hasRoute($name)) {
            throw new RouteNotFoundException("Route not found: " . $name);
        }

        $route = $this->routes->get($name);
        $url = $route->getPath();

        if (preg_match_all('/\{(\w+)}/', $url, $matches)) {
            foreach ($matches[1] as $paramName) {
                if (!isset($parameters[$paramName])) {
                    throw new RouterException("Missing parameter: $paramName for route $name");
                }
                $url = str_replace("{" . $paramName . "}", $parameters[$paramName], $url);
            }
        }

        $extraParams = array_diff_key($parameters, array_flip($matches[1] ?? []));
        if (!empty($extraParams)) {
            $url .= '?' . http_build_query($extraParams);
        }

        switch ($referenceType) {
            case self::ABSOLUTE_URL:
                return $this->getSchemeAndHost() . $this->context['baseUrl'] . $url;
            case self::NETWORK_PATH:
                return '//' . $this->context['host'] . $this->context['baseUrl'] . $url;
            case self::RELATIVE_PATH:
                return $this->getRelativePath($url);
            case self::ABSOLUTE_PATH:
            default:
                return $this->context['baseUrl'] . $url;
        }
    }

    /**
     * {@inheritdoc}
     * @throws RouterException
     */
    public function generateAbsolute(string $name, array $parameters = []): string
    {
        return $this->generate($name, $parameters, self::ABSOLUTE_URL);
    }

    /**
     * {@inheritdoc}
     * @throws RouterException
     */
    public function generateRelative(string $name, array $parameters = []): string
    {
        return $this->generate($name, $parameters, self::RELATIVE_PATH);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRoute(string $name): bool
    {
        return $this->routes->has($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    /**
     * Zwraca schemat i host
     */
    private function getSchemeAndHost(): string
    {
        $url = $this->context['scheme'] . '://' . $this->context['host'];

        if ($this->context['port'] !== null) {
            if (($this->context['scheme'] === 'http' && $this->context['port'] !== 80) ||
                ($this->context['scheme'] === 'https' && $this->context['port'] !== 443)) {
                $url .= ':' . $this->context['port'];
            }
        }

        return $url;
    }

    /**
     * Generuje relatywną ścieżkę
     */
    private function getRelativePath(string $url): string
    {
        return $url;
    }
}