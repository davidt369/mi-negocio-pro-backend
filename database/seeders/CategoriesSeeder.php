<?php

namespace Database\Seeders;

use App\Models\Categories;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Las categorías básicas ya se insertan en la migración
        // Aquí podemos agregar categorías adicionales para testing
        
        // Verificar si ya existen las categorías básicas
        if (Categories::count() > 0) {
            // Si ya existen, crear algunas adicionales para pruebas
            Categories::factory()
                ->count(3)
                ->active()
                ->create();

            // Crear una categoría inactiva para pruebas
            Categories::factory()
                ->inactive()
                ->create([
                    'name' => 'Categoría Inactiva de Prueba'
                ]);
        }
    }
}
