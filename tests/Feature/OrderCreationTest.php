<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/orders', [
            'items' => [['product_id' => 1, 'quantity' => 1]],
        ]);

        $response->assertStatus(401);
    }

    public function test_order_creation_requires_idempotency_key_header(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => 1, 'quantity' => 1]],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_MISSING');
    }

    public function test_creates_order_successfully_and_reserves_stock(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->withStock(50)->create(['price' => 1500]);
        $key = (string) Str::uuid();

        $response = $this->actingAs($user)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total', 3000)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('orders', [
            'idempotency_key' => $key,
            'total' => 3000,
        ]);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $product->id,
            'quantity_reserved' => 2,
        ]);
    }
}
