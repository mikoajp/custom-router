<?php

namespace Custom\Router\Middleware;

use Custom\Router\Interfaces\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MiddlewarePipeline
{
    /**
     * @var MiddlewareInterface[]
     */
    private array $middlewares = [];

    /**
     * Dodaje middleware do kolejki.
     */
    public function add(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Uruchamia pipeline middleware'ów.
     *
     * @param Request $request Żądanie HTTP
     * @param callable $core Główna logika aplikacji (kontroler)
     * @return Response
     */
    public function handle(Request $request, callable $core): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn (callable $next, MiddlewareInterface $middleware) => fn (Request $req) => $middleware->handle($req, $next),
            $core
        );

        return $pipeline($request);
    }
}
