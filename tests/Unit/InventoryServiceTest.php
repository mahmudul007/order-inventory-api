<?php

namespace Tests\Unit;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InventoryService;
    }

    public function test_reserves_stock_successfully(): void
    {
        $product = Product::factory()->withStock(10)->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        DB::transaction(fn () => $this->service->reserve([$product->id => 3], $order));

        $inventory = Inventory::where('product_id', $product->id)->first();
        $this->assertEquals(10, $inventory->quantity_on_hand);
        $this->assertEquals(3, $inventory->quantity_reserved);
        $this->assertEquals(7, $inventory->available());
    }

    public function test_throws_insufficient_stock_exception_when_quantity_exceeds_available(): void
    {
        $this->expectException(InsufficientStockException::class);

        $product = Product::factory()->withStock(5)->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        DB::transaction(fn () => $this->service->reserve([$product->id => 10], $order));
    }
}
