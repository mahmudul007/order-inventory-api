<?php

namespace App\Exceptions;

class InsufficientStockException extends ApiException
{
    public static function forProduct(int $productId, int $requested, int $available): self
    {
        return new self('Insufficient stock for one or more products.', [
            'product_id' => $productId,
            'requested' => $requested,
            'available' => $available,
        ]);
    }

    public function errorCode(): string
    {
        return 'INSUFFICIENT_STOCK';
    }

    public function status(): int
    {
        return 409;
    }
}
