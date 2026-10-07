<?php

namespace App\Contracts;

use App\Models\Inventory;
use App\Models\Order;

interface InventoryServiceInterface
{
    /**
     * Reserve stock for items in an order.
     *
     * @param  array<int, int>  $quantities  Keyed by product_id => quantity
     */
    public function reserve(array $quantities, Order $order): void;

    /**
     * Release reserved stock back to available for a pending order.
     */
    public function release(Order $order): void;

    /**
     * Commit reserved stock (deducting from both reserved and on-hand) upon order confirmation.
     */
    public function commit(Order $order): void;

    /**
     * Adjust physical on-hand inventory by a delta amount.
     */
    public function adjust(int $productId, int $delta, ?string $note = null): Inventory;
}
