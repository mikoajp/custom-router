<?php

namespace Custom\Router\Exception;

use Throwable;

class RateLimitExceededException extends RouterException
{
    /**
     * @param string $message Komunikat błędu
     * @param int $code Kod HTTP (domyślnie 429 Too Many Requests)
     * @param Throwable|null $previous Poprzedni wyjątek
     */
    public function __construct(
        string     $message = 'Rate limit exceeded',
        int        $code = 429,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Zwraca czas do resetu w sekundach
     */
    public function getRetryAfter(): ?int
    {
        return $this->getCode() === 429 ? 60 : null;
    }
}