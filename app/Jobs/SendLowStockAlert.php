<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendLowStockAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 45];

    public function __construct(
        public int $productId,
        public int $availableStock,
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        Log::warning("Low stock alert for Product #{$this->productId}: Only {$this->availableStock} units remaining.", [
            'product_id' => $this->productId,
            'available_stock' => $this->availableStock,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Failed sending low stock alert for Product #{$this->productId}: {$exception->getMessage()}");
    }
}
