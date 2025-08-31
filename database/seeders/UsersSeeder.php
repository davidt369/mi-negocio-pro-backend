<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default owner
        User::create([
            'first_name' => 'Administrador',
            'last_name' => 'Principal',
            'email' => 'admin@minegocio.com',
            'phone' => '+57 300 123 4567',
            'password' => Hash::make('admin123'),
            'role' => 'owner',
            'active' => true,
        ]);

        // Create some sample employees
        User::factory()
            ->count(5)
            ->create();

        // Create one inactive employee
        User::factory()
            ->inactive()
            ->create([
                'first_name' => 'Empleado',
                'last_name' => 'Inactivo',
                'email' => 'inactive@minegocio.com',
            ]);
    }
}
