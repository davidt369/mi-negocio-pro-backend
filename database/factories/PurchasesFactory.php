<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Purchases>
 */
class PurchasesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Lista de proveedores típicos para micronegocios
        $suppliers = [
            'Distribuidora Central',
            'Mayorista Los Andes',
            'Comercial San José',
            'Distribuciones El Sol',
            'Almacén La Rebaja',
            'Surtidora La Economía',
            'Distribuidora Express',
            'Comercial La Plaza',
            'Mayorista El Ahorro',
            'Surtidora Central',
            null, // Algunas compras sin proveedor específico
        ];

        // Notas típicas de compras
        $notes = [
            'Compra regular de productos básicos',
            'Reposición de stock bajo',
            'Productos promocionales',
            'Compra urgente por falta de stock',
            'Pedido especial del cliente',
            'Compra de temporada',
            'Reposición semanal',
            null, // Algunas sin notas
        ];

        return [
            'supplier_name' => $this->faker->randomElement($suppliers),
            'total' => 0, // Se calculará automáticamente con los items
            'notes' => $this->faker->randomElement($notes),
            'purchase_date' => $this->faker->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'received_by' => User::factory(),
            'created_at' => $this->faker->dateTimeBetween('-90 days', 'now'),
        ];
    }

    /**
     * Compra de hoy
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_date' => now()->format('Y-m-d'),
            'created_at' => now(),
        ]);
    }

    /**
     * Compra de esta semana
     */
    public function thisWeek(): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_date' => $this->faker->dateTimeBetween('-7 days', 'now')->format('Y-m-d'),
            'created_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Compra de este mes
     */
    public function thisMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Compra con proveedor específico
     */
    public function withSupplier(string $supplierName): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_name' => $supplierName,
        ]);
    }

    /**
     * Compra con notas específicas
     */
    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes,
        ]);
    }

    /**
     * Compra recibida por usuario específico
     */
    public function receivedBy(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'received_by' => $userId,
        ]);
    }

    /**
     * Compra grande (más de $100,000)
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_name' => $this->faker->randomElement([
                'Distribuidora Central',
                'Mayorista Los Andes',
                'Comercial San José',
            ]),
            'notes' => 'Compra grande de reposición de inventario',
        ]);
    }

    /**
     * Compra pequeña (menos de $50,000)
     */
    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => 'Compra menor de productos específicos',
        ]);
    }

    /**
     * Compra urgente
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => 'URGENTE: Reposición por falta de stock crítico',
            'purchase_date' => now()->format('Y-m-d'),
            'created_at' => now(),
        ]);
    }
}
