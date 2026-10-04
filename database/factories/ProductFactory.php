<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-????-#####')),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(500, 50000),
            'is_active' => true,
        ];
    }

    public function withStock(int $qty = 100): static
    {
        return $this->afterCreating(fn (Product $p) => Inventory::create([
            'product_id' => $p->id,
            'quantity_on_hand' => $qty,
            'quantity_reserved' => 0,
        ])
        );
    }
}
