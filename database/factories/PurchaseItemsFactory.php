<?php

namespace Database\Factories;

use App\Models\PurchaseItems;
use App\Models\Purchases;
use App\Models\Products;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseItems>
 */
class PurchaseItemsFactory extends Factory
{
    protected $model = PurchaseItems::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitCost = round($this->faker->randomFloat(2, 1, 500), 2);
        $quantity = $this->faker->numberBetween(1, 100);

        return [
            'purchase_id' => Purchases::factory(),
            'product_id' => Products::factory(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now')
        ];
    }

    /**
     * Indicate that the purchase item is for an existing purchase.
     */
    public function forPurchase(int $purchaseId): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_id' => $purchaseId,
        ]);
    }

    /**
     * Indicate that the purchase item is for an existing product.
     */
    public function forProduct(int $productId): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    /**
     * Indicate that the purchase item has a high quantity.
     */
    public function highQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $this->faker->numberBetween(50, 500),
        ]);
    }

    /**
     * Indicate that the purchase item has a low quantity.
     */
    public function lowQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $this->faker->numberBetween(1, 10),
        ]);
    }

    /**
     * Indicate that the purchase item has a high unit cost.
     */
    public function expensive(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_cost' => round($this->faker->randomFloat(2, 100, 1000), 2),
        ]);
    }

    /**
     * Indicate that the purchase item has a low unit cost.
     */
    public function cheap(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_cost' => round($this->faker->randomFloat(2, 0.50, 20), 2),
        ]);
    }

    /**
     * Indicate that the purchase item is recent.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the purchase item is old.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-2 years', '-6 months'),
        ]);
    }
}
