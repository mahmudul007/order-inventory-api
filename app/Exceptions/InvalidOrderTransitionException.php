<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;

class InvalidOrderTransitionException extends ApiException
{
    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self("Cannot transition order from {$from->value} to {$to->value}.", [
            'from' => $from->value,
            'to' => $to->value,
            'allowed' => array_map(fn (OrderStatus $s) => $s->value, $from->allowedTransitions()),
        ]);
    }

    public function errorCode(): string
    {
        return 'INVALID_STATUS_TRANSITION';
    }

    public function status(): int
    {
        return 422;
    }
}
