<?php

namespace App\Actions\Orders;

use App\Contracts\InventoryServiceInterface;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class UpdateOrderStatusAction
{
    public function __construct(
        private InventoryServiceInterface $inventory,
        private CancelOrderAction $cancelOrder,
    ) {}

    public function execute(Order $order, OrderStatus $to): Order
    {
        if ($to === OrderStatus::Cancelled) {
            return $this->cancelOrder->execute($order);
        }

        $order = DB::transaction(function () use ($order, $to): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo($to)) {
                throw InvalidOrderTransitionException::between($locked->status, $to);
            }

            if ($to === OrderStatus::Confirmed) {
                $this->inventory->commit($locked);
            }

            $locked->update(['status' => $to]);

            return $locked;
        }, 3);

        return $order->load(['items.product', 'customer']);
    }
}
