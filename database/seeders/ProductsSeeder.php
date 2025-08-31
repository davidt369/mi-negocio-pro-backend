<?php

namespace Database\Seeders;

use App\Models\Products;
use App\Models\Categories;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get categories for realistic product assignment
        $bebidasCategory = Categories::where('name', 'Bebidas')->first();
        $snacksCategory = Categories::where('name', 'Snacks')->first();
        $dulcesCategory = Categories::where('name', 'Dulces')->first();
        $cigarrillosCategory = Categories::where('name', 'Cigarrillos')->first();
        $aseoCategory = Categories::where('name', 'Aseo')->first();
        $otrosCategory = Categories::where('name', 'Otros')->first();

        // Create specific products for each category
        $products = [
            // Bebidas
            [
                'name' => 'Coca Cola 600ml',
                'category_id' => $bebidasCategory?->id,
                'cost_price' => 1500.00,
                'sale_price' => 2000.00,
                'stock' => 50,
                'min_stock' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Agua Cristal 500ml',
                'category_id' => $bebidasCategory?->id,
                'cost_price' => 800.00,
                'sale_price' => 1200.00,
                'stock' => 80,
                'min_stock' => 15,
                'is_active' => true,
            ],
            [
                'name' => 'Cerveza Águila 330ml',
                'category_id' => $bebidasCategory?->id,
                'cost_price' => 2200.00,
                'sale_price' => 3000.00,
                'stock' => 30,
                'min_stock' => 5,
                'is_active' => true,
            ],

            // Snacks
            [
                'name' => 'Papas Margarita Original',
                'category_id' => $snacksCategory?->id,
                'cost_price' => 1200.00,
                'sale_price' => 1800.00,
                'stock' => 40,
                'min_stock' => 8,
                'is_active' => true,
            ],
            [
                'name' => 'Doritos Nacho',
                'category_id' => $snacksCategory?->id,
                'cost_price' => 1500.00,
                'sale_price' => 2200.00,
                'stock' => 25,
                'min_stock' => 5,
                'is_active' => true,
            ],

            // Dulces
            [
                'name' => 'Chocolatina Jet',
                'category_id' => $dulcesCategory?->id,
                'cost_price' => 800.00,
                'sale_price' => 1300.00,
                'stock' => 60,
                'min_stock' => 12,
                'is_active' => true,
            ],
            [
                'name' => 'Bon Bon Bum',
                'category_id' => $dulcesCategory?->id,
                'cost_price' => 300.00,
                'sale_price' => 500.00,
                'stock' => 100,
                'min_stock' => 20,
                'is_active' => true,
            ],

            // Cigarrillos
            [
                'name' => 'Marlboro Box',
                'category_id' => $cigarrillosCategory?->id,
                'cost_price' => 8000.00,
                'sale_price' => 10000.00,
                'stock' => 15,
                'min_stock' => 3,
                'is_active' => true,
            ],

            // Aseo
            [
                'name' => 'Jabón Rey Azul',
                'category_id' => $aseoCategory?->id,
                'cost_price' => 2500.00,
                'sale_price' => 3500.00,
                'stock' => 20,
                'min_stock' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Papel Higiénico Scott x4',
                'category_id' => $aseoCategory?->id,
                'cost_price' => 6000.00,
                'sale_price' => 8500.00,
                'stock' => 12,
                'min_stock' => 3,
                'is_active' => true,
            ],

            // Otros
            [
                'name' => 'Pilas AA Duracell',
                'category_id' => $otrosCategory?->id,
                'cost_price' => 3000.00,
                'sale_price' => 4500.00,
                'stock' => 25,
                'min_stock' => 5,
                'is_active' => true,
            ],
        ];

        // Create the specific products
        foreach ($products as $productData) {
            Products::create($productData);
        }

        // Create additional random products using factory
        Products::factory()
            ->count(20)
            ->create();

        // Create some products with low stock
        Products::factory()
            ->lowStock()
            ->count(5)
            ->create();

        // Create some out of stock products
        Products::factory()
            ->outOfStock()
            ->count(3)
            ->create();

        // Create some inactive products
        Products::factory()
            ->inactive()
            ->count(4)
            ->create();
    }
}
