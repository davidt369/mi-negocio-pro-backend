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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name', 100)->nullable()->comment('Nombre simple del proveedor');
            $table->decimal('total', 10, 2)->default(0)->comment('Total de la compra');
            $table->text('notes')->nullable()->comment('Notas adicionales');
            $table->date('purchase_date')->default(DB::raw('CURRENT_DATE'))->comment('Fecha de la compra');
            $table->foreignId('received_by')->constrained('users')->onDelete('cascade')->comment('Usuario que recibió la compra');
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->softDeletes();
            // Índices para optimización
            $table->index('purchase_date', 'idx_purchases_date');
            $table->index(['received_by', 'purchase_date'], 'idx_purchases_user');
        });

        // Trigger para actualizar total automáticamente cuando se agregan/modifican items
        DB::unprepared('
            CREATE OR REPLACE FUNCTION calculate_purchase_total()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE purchases 
                SET total = (
                    SELECT COALESCE(SUM(line_total), 0) 
                    FROM purchase_items 
                    WHERE purchase_id = NEW.purchase_id
                )
                WHERE id = NEW.purchase_id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS calculate_purchase_total() CASCADE;');
        Schema::dropIfExists('purchases');
    }
};
