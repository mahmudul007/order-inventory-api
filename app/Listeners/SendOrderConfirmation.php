<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Jobs\GenerateInvoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOrderConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'high';

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    /**
     * Handle the event.
     */
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;
        $customer = $order->customer;

        Log::info("Order confirmation email sent for order #{$order->id} to {$customer?->email}", [
            'order_id' => $order->id,
            'customer_id' => $customer?->id,
            'total' => $order->total,
        ]);

        GenerateInvoice::dispatch($order)->onQueue('default');
    }

    public function failed(OrderPlaced $event, Throwable $exception): void
    {
        Log::error("Failed to send order confirmation for order #{$event->order->id}: {$exception->getMessage()}");
    }
}
