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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')
                  ->constrained('sales')
                  ->onDelete('cascade');
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onDelete('cascade');
            $table->integer('quantity')
                  ->checkIn(function ($query) {
                      $query->where('quantity', '>', 0);
                  });
            $table->decimal('unit_price', 10, 2)
                  ->checkIn(function ($query) {
                      $query->where('unit_price', '>=', 0);
                  });
            $table->decimal('line_total', 10, 2);
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->softDeletes();
            
            // Índices para optimización
            $table->index('sale_id', 'idx_sale_items_sale');
            $table->index('product_id', 'idx_sale_items_product');
            $table->index(['sale_id', 'product_id'], 'idx_sale_items_sale_product');
        });

        // Agregar constraints usando SQL directo para PostgreSQL
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_quantity CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_unit_price CHECK (unit_price >= 0)');
        
        // Trigger para calcular automáticamente line_total
        DB::statement("
            CREATE OR REPLACE FUNCTION calculate_sale_item_line_total()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.line_total := NEW.quantity * NEW.unit_price;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_calculate_sale_item_line_total
                BEFORE INSERT OR UPDATE ON sale_items
                FOR EACH ROW
                EXECUTE FUNCTION calculate_sale_item_line_total();
        ");

        // Trigger para actualizar el total de la venta
        DB::statement("
            CREATE OR REPLACE FUNCTION update_sale_total()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    UPDATE sales 
                    SET total = (
                        SELECT COALESCE(SUM(line_total), 0) 
                        FROM sale_items 
                        WHERE sale_id = OLD.sale_id
                    )
                    WHERE id = OLD.sale_id;
                    RETURN OLD;
                ELSE
                    UPDATE sales 
                    SET total = (
                        SELECT COALESCE(SUM(line_total), 0) 
                        FROM sale_items 
                        WHERE sale_id = NEW.sale_id
                    )
                    WHERE id = NEW.sale_id;
                    RETURN NEW;
                END IF;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_update_sale_total
                AFTER INSERT OR UPDATE OR DELETE ON sale_items
                FOR EACH ROW
                EXECUTE FUNCTION update_sale_total();
        ");

        // Trigger para actualizar stock de productos
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_stock_on_sale()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    -- Restaurar stock cuando se elimina un item
                    UPDATE products 
                    SET stock = stock + OLD.quantity
                    WHERE id = OLD.product_id;
                    RETURN OLD;
                ELSIF TG_OP = 'UPDATE' THEN
                    -- Ajustar stock por diferencia en cantidad
                    UPDATE products 
                    SET stock = stock + OLD.quantity - NEW.quantity
                    WHERE id = NEW.product_id;
                    RETURN NEW;
                ELSE
                    -- Reducir stock en nueva venta
                    UPDATE products 
                    SET stock = stock - NEW.quantity
                    WHERE id = NEW.product_id;
                    RETURN NEW;
                END IF;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_update_product_stock_on_sale
                AFTER INSERT OR UPDATE OR DELETE ON sale_items
                FOR EACH ROW
                EXECUTE FUNCTION update_product_stock_on_sale();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar triggers y funciones
        DB::statement('DROP TRIGGER IF EXISTS trg_update_product_stock_on_sale ON sale_items');
        DB::statement('DROP TRIGGER IF EXISTS trg_update_sale_total ON sale_items');
        DB::statement('DROP TRIGGER IF EXISTS trg_calculate_sale_item_line_total ON sale_items');
        
        DB::statement('DROP FUNCTION IF EXISTS update_product_stock_on_sale()');
        DB::statement('DROP FUNCTION IF EXISTS update_sale_total()');
        DB::statement('DROP FUNCTION IF EXISTS calculate_sale_item_line_total()');
        
        Schema::dropIfExists('sale_items');
    }
};