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
        // Crear tipo enum para métodos de pago solo si no existe
        DB::statement("DO $$ BEGIN
            CREATE TYPE payment_method AS ENUM ('cash','card','transfer','credit');
        EXCEPTION
            WHEN duplicate_object THEN null;
        END $$;");
        
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number', 20)->unique();
            $table->string('customer_name', 100)->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->string('notes')->nullable();
            $table->date('sale_date')->default(DB::raw('CURRENT_DATE'));
            $table->foreignId('sold_by')->constrained('users')->onDelete('cascade');
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));
            $table->softDeletes();
        });
        
        // Agregar columna con tipo enum personalizado
        DB::statement('ALTER TABLE sales ADD COLUMN payment_method payment_method NOT NULL DEFAULT \'cash\'');
        
        // Agregar constraints
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_total_positive CHECK (total >= 0)');
        
        // Crear función para generar número de venta automático solo si no existe
        DB::statement("
            CREATE OR REPLACE FUNCTION generate_sale_number()
            RETURNS varchar(20) AS \$\$
            DECLARE
                next_number integer;
            BEGIN
                SELECT COALESCE(MAX(CAST(SUBSTRING(sale_number FROM 2) AS integer)), 0) + 1
                INTO next_number
                FROM sales;
                
                RETURN 'V' || LPAD(next_number::text, 6, '0');
            END;
            \$\$ LANGUAGE plpgsql;
        ");
        
        // Crear trigger para auto-asignar número de venta
        DB::statement("
            CREATE OR REPLACE FUNCTION set_sale_number()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NEW.sale_number IS NULL OR NEW.sale_number = '' THEN
                    NEW.sale_number := generate_sale_number();
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
        
        DB::statement("
            CREATE TRIGGER trg_set_sale_number
                BEFORE INSERT ON sales
                FOR EACH ROW
                EXECUTE FUNCTION set_sale_number();
        ");
        
        // Crear índices para reportes
        DB::statement('CREATE INDEX idx_sales_date ON sales(sale_date)');
        DB::statement('CREATE INDEX idx_sales_user ON sales(sold_by, sale_date)');
        DB::statement('CREATE INDEX idx_sales_payment ON sales(payment_method, sale_date)');
        DB::statement('CREATE INDEX idx_sales_customer ON sales(customer_name) WHERE customer_name IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar trigger y función
        DB::statement('DROP TRIGGER IF EXISTS trg_set_sale_number ON sales');
        DB::statement('DROP FUNCTION IF EXISTS set_sale_number()');
        DB::statement('DROP FUNCTION IF EXISTS generate_sale_number()');
        
        Schema::dropIfExists('sales');
        
        // Eliminar tipo enum
        DB::statement('DROP TYPE IF EXISTS payment_method');
    }
};
