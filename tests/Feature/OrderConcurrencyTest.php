<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_50_parallel_order_requests_for_stock_10_results_in_exactly_10_successes(): void
    {
        $product = Product::factory()->withStock(10)->create(['price' => 1000]);
        $user = User::factory()->create();
        Customer::factory()->create(['user_id' => $user->id]);

        $action = app(CreateOrderAction::class);
        $successCount = 0;
        $failureCount = 0;

        for ($i = 0; $i < 50; $i++) {
            try {
                $action->execute(
                    $user,
                    [['product_id' => $product->id, 'quantity' => 1]],
                    (string) Str::uuid()
                );
                $successCount++;
            } catch (InsufficientStockException $e) {
                $failureCount++;
            }
        }

        $this->assertEquals(10, $successCount);
        $this->assertEquals(40, $failureCount);

        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(10, $inventory->quantity_on_hand);
        $this->assertEquals(10, $inventory->quantity_reserved);
        $this->assertLessThanOrEqual($inventory->quantity_on_hand, $inventory->quantity_reserved);
    }
}
