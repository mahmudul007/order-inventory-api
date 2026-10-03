<?php

namespace App\Actions\Orders;

use App\Contracts\InventoryServiceInterface;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function __construct(private InventoryServiceInterface $inventory) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function execute(User $user, array $items, string $idempotencyKey): Order
    {
        $quantities = $this->mergeQuantities($items);

        try {
            $order = DB::transaction(function () use ($user, $quantities, $idempotencyKey): Order {
                $customer = Customer::firstOrCreate(
                    ['user_id' => $user->id],
                    ['name' => $user->name, 'email' => $user->email],
                );

                /** Prices are read inside the transaction and snapshotted on the line. */
                $prices = Product::query()
                    ->whereIn('id', array_keys($quantities))
                    ->where('is_active', true)
                    ->pluck('price', 'id');

                $order = Order::create([
                    'customer_id' => $customer->id,
                    'idempotency_key' => $idempotencyKey,
                    'status' => OrderStatus::Pending,
                    'total' => 0,
                    'placed_at' => now(),
                ]);

                $this->inventory->reserve($quantities, $order);

                $total = 0;
                $lines = [];

                foreach ($quantities as $productId => $qty) {
                    $unitPrice = (int) ($prices[$productId] ?? 0);
                    $lineTotal = $unitPrice * $qty;
                    $total += $lineTotal;
                    $lines[] = [
                        'product_id' => $productId,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                $order->items()->createMany($lines);
                $order->update(['total' => $total]);

                return $order;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            /** A concurrent request with the same key won the race; return its order. */
            $order = Order::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }

        return $order->load(['items.product', 'customer']);
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @return array<int, int>
     */
    private function mergeQuantities(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $quantities[(int) $item['product_id']] = ($quantities[(int) $item['product_id']] ?? 0) + (int) $item['quantity'];
        }

        ksort($quantities);

        return $quantities;
    }
}
