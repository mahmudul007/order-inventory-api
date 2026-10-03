<?php

namespace App\Actions\Orders;

use App\Contracts\InventoryServiceInterface;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class CancelOrderAction
{
    public function __construct(private InventoryServiceInterface $inventory) {}

    public function execute(Order $order): Order
    {
        $order = DB::transaction(function () use ($order): Order {
            /** Lock the order row first, then inventory rows (consistent lock order). */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo(OrderStatus::Cancelled)) {
                throw InvalidOrderTransitionException::between($locked->status, OrderStatus::Cancelled);
            }

            if ($locked->status === OrderStatus::Pending) {
                $this->inventory->release($locked);
            } else {
                /** Confirmed orders already committed stock: put it back on hand. */
                foreach ($locked->items()->orderBy('product_id')->get(['product_id', 'quantity']) as $item) {
                    $this->inventory->adjust($item->product_id, $item->quantity, "Cancel order #{$locked->id}");
                }
            }

            $locked->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $locked;
        }, 3);

        return $order->load(['items.product', 'customer']);
    }
}
