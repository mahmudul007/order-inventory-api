<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Repositories\ProductRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ReleaseStockOnCancel implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public function __construct(private ProductRepository $products) {}

    /**
     * Handle the event.
     */
    public function handle(OrderCancelled $event): void
    {
        $this->products->invalidateProducts();

        Log::info("Order #{$event->order->id} cancelled asynchronously processed; cache invalidated.", [
            'order_id' => $event->order->id,
            'customer_id' => $event->order->customer_id,
        ]);
    }
}
