<?php

namespace Database\Factories;

use App\Models\Categories;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Products>
 */
class ProductsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $costPrice = $this->faker->randomFloat(2, 5, 50);
        $salePrice = $costPrice * $this->faker->randomFloat(2, 1.2, 2.5); // 20% to 150% markup
        
        return [
            'name' => $this->faker->randomElement([
                'Coca Cola 600ml',
                'Pepsi 500ml',
                'Agua Cristal 500ml',
                'Papas Margarita',
                'Doritos Nacho',
                'Chocolatina Jet',
                'Bon Bon Bum',
                'Chiclets Trident',
                'Galletas Noel',
                'Café Nescafé',
                'Cerveza Águila',
                'Cerveza Poker',
                'Pan Bimbo',
                'Leche Alquería',
                'Yogurt Alpina',
                'Jabón Rey',
                'Papel Higiénico Scott',
                'Detergente Fab',
                'Shampoo Head & Shoulders',
                'Pasta Colgate'
            ]),

            'category_id' => Categories::inRandomOrder()->first()?->id,
            'cost_price' => $costPrice,
            'sale_price' => round($salePrice, 2),
            'stock' => $this->faker->numberBetween(0, 100),
            'min_stock' => $this->faker->numberBetween(5, 20),
            'is_active' => $this->faker->boolean(90), // 90% active
        ];
    }

    /**
     * Indicate that the product should be inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the product should have low stock.
     */
    public function lowStock(): static
    {
        return $this->state(function (array $attributes) {
            $minStock = $attributes['min_stock'] ?? 5;
            return [
                'stock' => $this->faker->numberBetween(0, $minStock),
            ];
        });
    }

    /**
     * Indicate that the product should be out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    /**
     * Indicate that the product should be a beverage.
     */
    public function beverage(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement([
                'Coca Cola 600ml',
                'Pepsi 500ml',
                'Agua Cristal 500ml',
                'Café Nescafé',
                'Cerveza Águila',
                'Jugo Hit Mango'
            ]),
        ]);
    }

    /**
     * Indicate that the product should be a snack.
     */
    public function snack(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->randomElement([
                'Papas Margarita',
                'Doritos Nacho',
                'Galletas Noel',
                'Maní Japonés',
                'Platanitos'
            ]),
        ]);
    }
}
