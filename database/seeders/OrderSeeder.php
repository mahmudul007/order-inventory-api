<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    private const TOTAL = 100000;

    private const CHUNK = 1000;

    /**
     * Weighted status list (mostly delivered).
     *
     * @var array<string, int>
     */
    private const STATUS_WEIGHTS = [
        'delivered' => 60,
        'shipped' => 10,
        'processing' => 8,
        'confirmed' => 7,
        'pending' => 5,
        'cancelled' => 10,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        /** @var array<int, int> $prices */
        $prices = DB::table('products')->pluck('price', 'id')->all();
        $productIds = array_keys($prices);
        $statusPool = $this->buildStatusPool();
        $nowTs = now()->getTimestamp();
        $windowSeconds = 180 * 86400;

        for ($start = 1; $start <= self::TOTAL; $start += self::CHUNK) {
            $orders = [];
            $items = [];
            $end = min($start + self::CHUNK - 1, self::TOTAL);

            for ($orderId = $start; $orderId <= $end; $orderId++) {
                $placedAt = date('Y-m-d H:i:s', $nowTs - random_int(0, $windowSeconds));
                $status = $statusPool[array_rand($statusPool)];
                $total = 0;

                $pickedKeys = (array) array_rand($productIds, random_int(1, 3));

                foreach ($pickedKeys as $key) {
                    $productId = $productIds[$key];
                    $quantity = random_int(1, 5);
                    $unitPrice = (int) $prices[$productId];
                    $lineTotal = $unitPrice * $quantity;
                    $total += $lineTotal;

                    $items[] = [
                        'order_id' => $orderId,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                $orders[] = [
                    'id' => $orderId,
                    'customer_id' => random_int(1, 1000),
                    'idempotency_key' => (string) Str::uuid(),
                    'status' => $status,
                    'total' => $total,
                    'placed_at' => $placedAt,
                    'cancelled_at' => $status === 'cancelled' ? $placedAt : null,
                    'created_at' => $placedAt,
                    'updated_at' => $placedAt,
                ];
            }

            DB::table('orders')->insert($orders);

            foreach (array_chunk($items, 1000) as $itemChunk) {
                DB::table('order_items')->insert($itemChunk);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function buildStatusPool(): array
    {
        $pool = [];

        foreach (self::STATUS_WEIGHTS as $status => $weight) {
            array_push($pool, ...array_fill(0, $weight, $status));
        }

        return $pool;
    }
}
