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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->default(DB::raw('now()'));
            $table->softDeletes();
        });

        // Insertar categorías básicas para microempresarios
        DB::table('categories')->insert([
            ['name' => 'Bebidas', 'created_at' => now()],
            ['name' => 'Snacks', 'created_at' => now()],
            ['name' => 'Dulces', 'created_at' => now()],
            ['name' => 'Cigarrillos', 'created_at' => now()],
            ['name' => 'Aseo', 'created_at' => now()],
            ['name' => 'Otros', 'created_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
