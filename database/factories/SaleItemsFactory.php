<?php

namespace Database\Factories;

use App\Models\SaleItems;
use App\Models\Sales;
use App\Models\Products;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleItems>
 */
class SaleItemsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SaleItems::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Obtener una venta y producto aleatorios
        $sale = Sales::inRandomOrder()->first();
        $product = Products::where('is_active', true)->inRandomOrder()->first();

        // Si no hay productos o ventas, usar IDs por defecto
        $saleId = $sale ? $sale->id : 1;
        $productId = $product ? $product->id : 1;
        
        // Generar cantidad aleatoria basada en tipo de producto
        $quantity = $this->faker->numberBetween(1, 10);
        
        // Precio unitario basado en el precio de venta del producto si existe
        if ($product) {
            // Usar el precio del producto con una pequeña variación
            $basePrice = (float) $product->sale_price;
            $unitPrice = $this->faker->randomFloat(2, 
                max(0.01, $basePrice * 0.9), // 10% descuento máximo
                $basePrice * 1.1 // 10% aumento máximo
            );
        } else {
            // Precios típicos de productos de microempresarios colombianos
            $unitPrice = $this->faker->randomFloat(2, 500, 50000); // Entre $500 y $50,000 COP
        }

        return [
            'sale_id' => $saleId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice, // Se calculará automáticamente por trigger
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * Factory state para items de bebidas
     */
    public function beverage(): Factory
    {
        return $this->state(function (array $attributes) {
            $product = Products::whereHas('category', function ($query) {
                $query->where('name', 'Bebidas');
            })->where('is_active', true)->inRandomOrder()->first();

            if ($product) {
                $quantity = $this->faker->numberBetween(1, 6); // 1-6 bebidas
                $unitPrice = (float) $product->sale_price;
                
                return [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                ];
            }

            return $attributes;
        });
    }

    /**
     * Factory state para items de snacks
     */
    public function snack(): Factory
    {
        return $this->state(function (array $attributes) {
            $product = Products::whereHas('category', function ($query) {
                $query->where('name', 'Snacks');
            })->where('is_active', true)->inRandomOrder()->first();

            if ($product) {
                $quantity = $this->faker->numberBetween(1, 5); // 1-5 snacks
                $unitPrice = (float) $product->sale_price;
                
                return [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                ];
            }

            return $attributes;
        });
    }

    /**
     * Factory state para items de dulces
     */
    public function sweet(): Factory
    {
        return $this->state(function (array $attributes) {
            $product = Products::whereHas('category', function ($query) {
                $query->where('name', 'Dulces');
            })->where('is_active', true)->inRandomOrder()->first();

            if ($product) {
                $quantity = $this->faker->numberBetween(1, 8); // 1-8 dulces
                $unitPrice = (float) $product->sale_price;
                
                return [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                ];
            }

            return $attributes;
        });
    }

    /**
     * Factory state para ventas grandes (más items)
     */
    public function largeQuantity(): Factory
    {
        return $this->state(function (array $attributes) {
            $quantity = $this->faker->numberBetween(10, 50);
            $unitPrice = $attributes['unit_price'] ?? $this->faker->randomFloat(2, 1000, 5000);
            
            return [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
            ];
        });
    }

    /**
     * Factory state para productos específicos
     */
    public function forProduct(Products $product): Factory
    {
        return $this->state(function (array $attributes) use ($product) {
            $quantity = $this->faker->numberBetween(1, 10);
            $unitPrice = (float) $product->sale_price;
            
            return [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
            ];
        });
    }

    /**
     * Factory state para venta específica
     */
    public function forSale(Sales $sale): Factory
    {
        return $this->state(function (array $attributes) use ($sale) {
            return [
                'sale_id' => $sale->id,
                'created_at' => $sale->created_at,
            ];
        });
    }

    /**
     * Factory state con fecha específica
     */
    public function onDate($date): Factory
    {
        return $this->state(function (array $attributes) use ($date) {
            return [
                'created_at' => $date,
            ];
        });
    }
}
