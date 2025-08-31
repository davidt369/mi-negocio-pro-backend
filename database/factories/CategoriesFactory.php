<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Categories>
 */
class CategoriesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Electrónicos', 'Libros', 'Ropa', 'Hogar', 'Deportes', 
            'Belleza', 'Automóviles', 'Juguetes', 'Música', 'Películas',
            'Jardinería', 'Mascotas', 'Salud', 'Oficina', 'Arte'
        ];

        return [
            'name' => fake()->unique()->randomElement($categories),
            'is_active' => fake()->boolean(85), // 85% probabilidad de estar activa
            'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * Indicate that the category should be inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the category should be active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
