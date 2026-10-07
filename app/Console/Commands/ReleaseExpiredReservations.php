<?php

namespace App\Console\Commands;

use App\Actions\Orders\CancelOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('orders:release-expired {--minutes=15 : Minutes after which pending orders expire}')]
#[Description('Release reserved stock for pending orders older than the expiry threshold')]
class ReleaseExpiredReservations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CancelOrderAction $cancelOrder): int
    {
        $minutes = (int) $this->option('minutes');
        $threshold = now()->subMinutes($minutes);

        $expiredOrders = Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('placed_at', '<', $threshold)
            ->get();

        $count = 0;

        foreach ($expiredOrders as $order) {
            try {
                $cancelOrder->execute($order);
                $count++;
            } catch (Throwable $e) {
                Log::error("Failed to cancel expired order #{$order->id}: {$e->getMessage()}", [
                    'order_id' => $order->id,
                    'exception' => $e,
                ]);
            }
        }

        $this->info("Released {$count} expired orders older than {$minutes} minutes.");

        return self::SUCCESS;
    }
}
