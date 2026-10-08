<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaying_same_key_and_payload_returns_stored_response(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->withStock(50)->create();
        $key = (string) Str::uuid();

        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        // First Request
        $res1 = $this->actingAs($user)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', $payload);

        $res1->assertStatus(201);
        $firstOrderId = $res1->json('data.id');

        // Second Request (Replay)
        $res2 = $this->actingAs($user)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', $payload);

        $res2->assertStatus(201)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $firstOrderId);

        // Ensure only one order was created in DB
        $this->assertEquals(1, Order::where('idempotency_key', $key)->count());
    }

    public function test_reusing_key_with_different_payload_returns_422(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create(['user_id' => $user->id]);
        $product1 = Product::factory()->withStock(50)->create();
        $product2 = Product::factory()->withStock(50)->create();
        $key = (string) Str::uuid();

        $this->actingAs($user)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', [
                'items' => [['product_id' => $product1->id, 'quantity' => 1]],
            ])->assertStatus(201);

        $res2 = $this->actingAs($user)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', [
                'items' => [['product_id' => $product2->id, 'quantity' => 1]],
            ]);

        $res2->assertStatus(422)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
    }
}
