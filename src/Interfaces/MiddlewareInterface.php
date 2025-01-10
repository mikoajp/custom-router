<?php

namespace Custom\Router\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface MiddlewareInterface
{
    /**
     * Obsługuje żądanie HTTP i opcjonalnie modyfikuje odpowiedź.
     *
     * @param Request $request Żądanie HTTP
     * @param callable $next Następne middleware w kolejce
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}