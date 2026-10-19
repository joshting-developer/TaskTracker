<?php

namespace App\Domain;

use RuntimeException;

class DomainError extends RuntimeException
{
    public function __construct(public string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function fail(string $code, string $message): never
    {
        throw new self($code, $message);
    }
}
