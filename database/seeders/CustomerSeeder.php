<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    private const TOTAL = 1000;

    private const CHUNK = 500;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $now = now();

        for ($start = 1; $start <= self::TOTAL; $start += self::CHUNK) {
            $rows = [];
            $end = min($start + self::CHUNK - 1, self::TOTAL);

            for ($id = $start; $id <= $end; $id++) {
                $rows[] = [
                    'id' => $id,
                    'user_id' => null,
                    'name' => "Customer {$id}",
                    'email' => "customer{$id}@example.com",
                    'phone' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('customers')->insert($rows);
        }
    }
}
