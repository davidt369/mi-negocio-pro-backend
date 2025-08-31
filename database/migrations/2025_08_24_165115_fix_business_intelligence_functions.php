<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Eliminar funciones existentes primero
        DB::unprepared('DROP FUNCTION IF EXISTS get_product_stock(text) CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS get_top_selling_products(integer) CASCADE');

        // Crear función corregida para consultar stock de productos
        DB::unprepared('
            CREATE OR REPLACE FUNCTION get_product_stock(product_name text)
            RETURNS TABLE(name varchar(100), stock integer, min_stock integer, status text) AS $$
            BEGIN
              RETURN QUERY
              SELECT 
                p.name,
                p.stock,
                p.min_stock,
                CASE 
                  WHEN p.stock <= 0 THEN \'SIN STOCK\'::text
                  WHEN p.stock <= p.min_stock THEN \'STOCK BAJO\'::text
                  ELSE \'OK\'::text
                END as status
              FROM products p
              WHERE p.name ILIKE \'%\' || product_name || \'%\' 
                AND p.is_active = true
              ORDER BY similarity(p.name, product_name) DESC
              LIMIT 5;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Crear función corregida para productos más vendidos
        DB::unprepared('
            CREATE OR REPLACE FUNCTION get_top_selling_products(days integer DEFAULT 30)
            RETURNS TABLE(product_name varchar(100), total_sold bigint, revenue numeric) AS $$
            BEGIN
              RETURN QUERY
              SELECT 
                p.name as product_name,
                SUM(si.quantity) as total_sold,
                SUM(si.line_total) as revenue
              FROM products p
              JOIN sale_items si ON p.id = si.product_id
              JOIN sales s ON si.sale_id = s.id
              WHERE s.sale_date >= CURRENT_DATE - (days || \' days\')::interval
              GROUP BY p.id, p.name
              ORDER BY total_sold DESC
              LIMIT 10;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Las funciones anteriores se mantendrán
    }
};
