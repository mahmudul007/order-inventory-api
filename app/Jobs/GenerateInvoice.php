<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function __construct(public Order $order)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $invoiceNumber = 'INV-'.str_pad((string) $this->order->id, 8, '0', STR_PAD_LEFT);

        Log::info("Generated invoice {$invoiceNumber} for Order #{$this->order->id}", [
            'order_id' => $this->order->id,
            'customer_id' => $this->order->customer_id,
            'total' => $this->order->total,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Failed generating invoice for Order #{$this->order->id}: {$exception->getMessage()}");
    }
}
