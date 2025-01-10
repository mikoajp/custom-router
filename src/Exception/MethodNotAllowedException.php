<?php

namespace Custom\Router\Exception;

class MethodNotAllowedException extends RouterException
{
    private array $allowedMethods;

    public function __construct(array $allowedMethods, string $message = '', int $code = 405)
    {
        $this->allowedMethods = array_unique($allowedMethods);
        $message = $message ?: sprintf(
            'The allowed methods are: "%s"',
            implode(', ', $this->allowedMethods)
        );

        parent::__construct($message, $code);
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}