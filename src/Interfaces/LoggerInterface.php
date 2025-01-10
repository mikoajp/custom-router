<?php

namespace Custom\Router\Interfaces;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

interface LoggerInterface extends PsrLoggerInterface
{
    /**
     * Loguje żądanie HTTP.
     *
     * @param string $message Komunikat logowania
     * @param array $context Dodatkowe informacje o żądaniu
     */
    public function logRequest(string $message, array $context = []): void;

    /**
     * Loguje odpowiedź HTTP.
     *
     * @param string $message Komunikat logowania
     * @param array $context Dodatkowe informacje o odpowiedzi
     */
    public function logResponse(string $message, array $context = []): void;
}