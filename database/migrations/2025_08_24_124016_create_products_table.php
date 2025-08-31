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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('image_path')->nullable(); // Ruta de la imagen del producto
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->decimal('cost_price', 10, 2)->nullable(); // Precio de compra
            $table->decimal('sale_price', 10, 2); // Precio de venta
            $table->integer('stock')->default(0); // Stock actual
            $table->integer('min_stock')->default(5); // Alerta de stock bajo
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletes();
            // Indexes for fast searches
            $table->index('name');
            $table->index(['category_id', 'is_active']);
            $table->index('stock');
        });

        // Add constraints using raw SQL
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_cost_price CHECK (cost_price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_sale_price CHECK (sale_price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_stock CHECK (stock >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_min_stock CHECK (min_stock >= 0)');

        // Create GIN index for full-text search on name (PostgreSQL specific)
        DB::statement("CREATE INDEX idx_products_name ON products USING GIN(to_tsvector('spanish', name))");
        
        // Create partial index for low stock alert
        DB::statement("CREATE INDEX idx_products_low_stock ON products(stock) WHERE stock <= min_stock");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
