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
        // Add image_path column only if it doesn't exist
        if (!Schema::hasColumn('products', 'image_path')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('image_path')->nullable()->after('name');
            });
        }

        // Drop barcode column and its index if they exist
        if (Schema::hasColumn('products', 'barcode')) {
            // Drop the index first
            try {
                DB::statement("DROP INDEX IF EXISTS idx_products_barcode");
                Schema::table('products', function (Blueprint $table) {
                    $table->dropIndex(['barcode']);
                });
            } catch (Exception $e) {
                // Index might not exist, continue
            }
            
            // Drop the column
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('barcode');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop image_path column
            $table->dropColumn('image_path');
            
            // Re-add barcode column
            $table->string('barcode', 50)->nullable()->after('name');
            $table->index('barcode');
        });

        // Re-create the barcode index
        DB::statement("CREATE INDEX idx_products_barcode ON products(barcode) WHERE barcode IS NOT NULL");
    }
};
