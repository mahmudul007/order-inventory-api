<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Base class for domain exceptions rendered as {error:{code,message,details}}.
 */
abstract class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(string $message, protected array $details = [])
    {
        parent::__construct($message);
    }

    abstract public function errorCode(): string;

    abstract public function status(): int;

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
