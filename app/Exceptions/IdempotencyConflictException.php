<?php

namespace App\Exceptions;

class IdempotencyConflictException extends ApiException
{
    public function __construct(string $message, private int $httpStatus = 409, private string $code_ = 'IDEMPOTENCY_IN_FLIGHT')
    {
        parent::__construct($message);
    }

    public static function inFlight(): self
    {
        return new self('A request with this Idempotency-Key is already being processed.', 409, 'IDEMPOTENCY_IN_FLIGHT');
    }

    public static function payloadMismatch(): self
    {
        return new self('Idempotency-Key was reused with a different request payload.', 422, 'IDEMPOTENCY_KEY_REUSED');
    }

    public static function missing(): self
    {
        return new self('The Idempotency-Key header is required.', 400, 'IDEMPOTENCY_KEY_MISSING');
    }

    public function errorCode(): string
    {
        return $this->code_;
    }

    public function status(): int
    {
        return $this->httpStatus;
    }
}
