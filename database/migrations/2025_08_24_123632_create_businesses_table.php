<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->smallInteger('id')->primary()->default(1);
            $table->string('name', 100);
            $table->string('owner_name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('address', 200)->nullable();
            $table->char('currency', 3)->default('COP');
            $table->decimal('tax_rate', 5, 4)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        // Agregar constraint después de crear la tabla
        DB::statement('ALTER TABLE businesses ADD CONSTRAINT chk_singleton CHECK (id = 1)');

        // Pre-insertar configuración por defecto
        DB::table('businesses')->insert([
            'id' => 1,
            'name' => 'Mi Negocio',
            'owner_name' => 'Propietario',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};