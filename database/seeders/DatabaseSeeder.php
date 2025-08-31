<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call specific seeders
        $this->call([
            BusinessSeeder::class,
            UsersSeeder::class,
            CategoriesSeeder::class,
            ProductsSeeder::class,
            SalesSeeder::class,
            SaleItemsSeeder::class,
            PurchasesSeeder::class,
        ]);
    }
}
