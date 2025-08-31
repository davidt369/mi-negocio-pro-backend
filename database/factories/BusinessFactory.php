<?php
// filepath: c:\Users\David\Documents\APKMobile\apkMobileSaaS\backend-mi-negocio-pro\database\factories\BusinessFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Business;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Business>
 */
class BusinessFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Business::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'name' => $this->faker->company(),
            'owner_name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'address' => $this->faker->address(),
            'currency' => $this->faker->randomElement(['COP', 'USD', 'MXN', 'PEN']),
            'tax_rate' => $this->faker->randomFloat(4, 0, 0.2), // 0% a 20%
        ];
    }
}