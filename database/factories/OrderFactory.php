<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            'customer_id' => Customer::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'status' => OrderStatus::Pending,
            'total' => 0,
            'placed_at' => now(),
        ];
    }
}
