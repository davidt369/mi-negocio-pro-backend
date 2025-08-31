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
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->onDelete('cascade')->comment('ID de la compra');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade')->comment('ID del producto comprado');
            $table->integer('quantity')->comment('Cantidad comprada');
            $table->decimal('unit_cost', 10, 2)->comment('Costo unitario');
            $table->decimal('line_total', 10, 2)->comment('Total de la línea');
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->softDeletes();
            
            // Índices para optimización
            $table->index('purchase_id', 'idx_purchase_items_purchase');
            $table->index('product_id', 'idx_purchase_items_product');
        });

        // Agregar restricciones usando SQL raw
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_unit_cost_positive CHECK (unit_cost >= 0)');

        // Trigger para calcular line_total automáticamente
        DB::unprepared('
            CREATE OR REPLACE FUNCTION calculate_purchase_item_line_total()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.line_total = NEW.quantity * NEW.unit_cost;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_calculate_purchase_item_line_total
                BEFORE INSERT OR UPDATE ON purchase_items
                FOR EACH ROW
                EXECUTE FUNCTION calculate_purchase_item_line_total();
        ');

        // Trigger para actualizar total de la compra
        DB::unprepared('
            CREATE TRIGGER trg_update_purchase_total_on_items
                AFTER INSERT OR UPDATE OR DELETE ON purchase_items
                FOR EACH ROW
                EXECUTE FUNCTION calculate_purchase_total();
        ');

        // Trigger para actualizar stock del producto
        DB::unprepared('
            CREATE OR REPLACE FUNCTION update_product_stock_on_purchase()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = \'INSERT\' THEN
                    -- Aumentar stock y actualizar costo
                    UPDATE products 
                    SET stock = stock + NEW.quantity,
                        cost_price = NEW.unit_cost,
                        updated_at = now()
                    WHERE id = NEW.product_id;
                    RETURN NEW;
                    
                ELSIF TG_OP = \'UPDATE\' THEN
                    -- Ajustar stock por la diferencia
                    UPDATE products 
                    SET stock = stock - OLD.quantity + NEW.quantity,
                        cost_price = NEW.unit_cost,
                        updated_at = now()
                    WHERE id = NEW.product_id;
                    RETURN NEW;
                    
                ELSIF TG_OP = \'DELETE\' THEN
                    -- Reducir stock
                    UPDATE products 
                    SET stock = stock - OLD.quantity,
                        updated_at = now()
                    WHERE id = OLD.product_id;
                    RETURN OLD;
                END IF;
                
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_update_product_stock_on_purchase
                AFTER INSERT OR UPDATE OR DELETE ON purchase_items
                FOR EACH ROW
                EXECUTE FUNCTION update_product_stock_on_purchase();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_update_product_stock_on_purchase ON purchase_items;');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_update_purchase_total_on_items ON purchase_items;');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_calculate_purchase_item_line_total ON purchase_items;');
        DB::unprepared('DROP FUNCTION IF EXISTS update_product_stock_on_purchase() CASCADE;');
        DB::unprepared('DROP FUNCTION IF EXISTS calculate_purchase_item_line_total() CASCADE;');
        
        Schema::dropIfExists('purchase_items');
    }
};
