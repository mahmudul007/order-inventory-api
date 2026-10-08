<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    private const TOTAL = 10000;

    private const CHUNK = 1000;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $categoryIds = DB::table('categories')->pluck('id')->all();
        $now = now();

        for ($start = 1; $start <= self::TOTAL; $start += self::CHUNK) {
            $products = [];
            $inventories = [];
            $end = min($start + self::CHUNK - 1, self::TOTAL);

            for ($id = $start; $id <= $end; $id++) {
                $products[] = [
                    'id' => $id,
                    'category_id' => $categoryIds[array_rand($categoryIds)],
                    'sku' => sprintf('SKU-%06d', $id),
                    'name' => "Product {$id}",
                    'description' => null,
                    'price' => random_int(500, 50000),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $inventories[] = [
                    'product_id' => $id,
                    'quantity_on_hand' => random_int(0, 500),
                    'quantity_reserved' => 0,
                    'version' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('products')->insert($products);
            DB::table('inventories')->insert($inventories);
        }
    }
}
