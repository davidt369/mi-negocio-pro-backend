<?php
// filepath: c:\Users\David\Documents\APKMobile\apkMobileSaaS\backend-mi-negocio-pro\database\seeders\BusinessSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Business;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Business::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Mi Negocio Demo',
                'owner_name' => 'Juan Pérez',
                'phone' => '+57 300 123 4567',
                'email' => 'juan@minegocio.com',
                'address' => 'Calle 123 #45-67, Bogotá, Colombia',
                'currency' => 'COP',
                'tax_rate' => 0.19 // 19% IVA Colombia
            ]
        );
    }
}