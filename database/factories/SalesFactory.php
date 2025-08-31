<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sales>
 */
class SalesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = $this->faker->randomFloat(2, 5, 200); // Entre $5 y $200
        
        return [
            'customer_name' => $this->faker->optional(0.7)->name(), // 70% probability
            'total' => $total,
            'payment_method' => $this->faker->randomElement(['cash', 'card', 'transfer', 'credit']),
            'notes' => $this->faker->optional(0.3)->sentence(), // 30% probability
            'sale_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'sold_by' => User::inRandomOrder()->first()?->id ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the sale is from today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_date' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the sale is from this week.
     */
    public function thisWeek(): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_date' => $this->faker->dateTimeBetween('this week', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the sale is from this month.
     */
    public function thisMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_date' => $this->faker->dateTimeBetween('first day of this month', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the sale is a high value sale.
     */
    public function highValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'total' => $this->faker->randomFloat(2, 100, 500),
        ]);
    }

    /**
     * Indicate that the sale is cash only.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'cash',
        ]);
    }

    /**
     * Indicate that the sale has a specific seller.
     */
    public function bySeller(int $sellerId): static
    {
        return $this->state(fn (array $attributes) => [
            'sold_by' => $sellerId,
        ]);
    }

    /**
     * Indicate that the sale has a customer name.
     */
    public function withCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_name' => $this->faker->name(),
        ]);
    }

    /**
     * Indicate that the sale has notes.
     */
    public function withNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $this->faker->sentence(),
        ]);
    }
}
