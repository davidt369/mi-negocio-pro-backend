<?php

namespace Database\Seeders;

use App\Models\Sales;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar que existan usuarios
        if (User::count() === 0) {
            $this->command->warn('No hay usuarios disponibles. Ejecutando UsersSeeder primero...');
            $this->call(UsersSeeder::class);
        }

        // Obtener usuarios disponibles
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->command->error('No se pudieron crear usuarios. Saltando SalesSeeder.');
            return;
        }

        $this->command->info('Creando ventas de ejemplo...');

        // Crear ventas de hoy (5 ventas)
        Sales::factory()
            ->count(5)
            ->today()
            ->bySeller($users->random()->id)
            ->create();

        // Crear ventas de esta semana (10 ventas)
        Sales::factory()
            ->count(10)
            ->thisWeek()
            ->create();

        // Crear ventas de este mes (20 ventas)
        Sales::factory()
            ->count(20)
            ->thisMonth()
            ->create();

        // Crear algunas ventas de alto valor
        Sales::factory()
            ->count(5)
            ->highValue()
            ->thisMonth()
            ->withCustomer()
            ->withNotes()
            ->create();

        // Crear ventas solo en efectivo
        Sales::factory()
            ->count(8)
            ->cash()
            ->thisWeek()
            ->create();

        // Crear ventas para cada usuario específicamente
        foreach ($users as $user) {
            Sales::factory()
                ->count(3)
                ->bySeller($user->id)
                ->thisMonth()
                ->create();
        }

        $this->command->info('✅ Se crearon ' . Sales::count() . ' ventas de ejemplo');
        
        // Mostrar estadísticas
        $this->command->info('📊 Estadísticas:');
        $this->command->info('   - Ventas de hoy: ' . Sales::whereDate('sale_date', today())->count());
        $this->command->info('   - Total del día: $' . number_format(Sales::whereDate('sale_date', today())->sum('total'), 2));
        $this->command->info('   - Ventas del mes: ' . Sales::whereMonth('sale_date', now()->month)->count());
        $this->command->info('   - Total del mes: $' . number_format(Sales::whereMonth('sale_date', now()->month)->sum('total'), 2));
    }
}
