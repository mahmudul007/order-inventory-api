<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_cancel_pending_order_and_stock_is_released(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create();
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 2,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Pending,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 1000,
            'line_total' => 2000,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('inventories', [
            'product_id' => $product->id,
            'quantity_reserved' => 0,
        ]);
    }

    public function test_cannot_cancel_delivered_order(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Delivered,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
    }
}
