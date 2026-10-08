<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Repositories\ProductRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class InvalidateProductCache implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public function __construct(private ProductRepository $products) {}

    /**
     * Handle the event.
     */
    public function handle(OrderPlaced $event): void
    {
        $this->products->invalidateProducts();
    }
}
