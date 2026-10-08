<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Events\StockLow;
use App\Jobs\SendLowStockAlert;
use App\Models\Inventory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CheckLowStock implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'low';

    /**
     * Handle the event.
     */
    public function handle(OrderPlaced $event): void
    {
        $productIds = $event->order->items()->pluck('product_id')->unique();

        $inventories = Inventory::query()
            ->whereIn('product_id', $productIds)
            ->get();

        foreach ($inventories as $inv) {
            $available = $inv->available();
            if ($available <= 10) {
                StockLow::dispatch($inv->product_id, $available);
                SendLowStockAlert::dispatch($inv->product_id, $available)->onQueue('low');
            }
        }
    }
}
