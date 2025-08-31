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
        // Crear extensiones PostgreSQL si no existen
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm'); // Para búsqueda de productos
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_stat_statements'); // Para monitoreo

        // Función para obtener ingresos por período (chatbot IA)
        DB::unprepared('DROP FUNCTION IF EXISTS get_revenue(text) CASCADE');
        DB::unprepared('
            CREATE FUNCTION get_revenue(period text DEFAULT \'today\')
            RETURNS TABLE(revenue numeric, transactions bigint) AS $$
            BEGIN
              IF period = \'today\' THEN
                RETURN QUERY
                SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as transactions
                FROM sales 
                WHERE sale_date = CURRENT_DATE;
              ELSIF period = \'this_month\' THEN
                RETURN QUERY
                SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as transactions
                FROM sales 
                WHERE date_trunc(\'month\', sale_date) = date_trunc(\'month\', CURRENT_DATE);
              ELSIF period = \'last_month\' THEN
                RETURN QUERY
                SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as transactions
                FROM sales 
                WHERE date_trunc(\'month\', sale_date) = date_trunc(\'month\', CURRENT_DATE - INTERVAL \'1 month\');
              END IF;
            END;
            $$ LANGUAGE plpgsql;
        ');

                // Función para consultar stock de productos (chatbot IA)
        DB::unprepared('DROP FUNCTION IF EXISTS get_product_stock(text) CASCADE');
        DB::unprepared('
            CREATE FUNCTION get_product_stock(product_name text)
            RETURNS TABLE(name text, stock integer, min_stock integer, status text) AS $$
            BEGIN
              RETURN QUERY
              SELECT 
                p.name,
                p.stock,
                p.min_stock,
                CASE 
                  WHEN p.stock <= 0 THEN \'SIN STOCK\'
                  WHEN p.stock <= p.min_stock THEN \'STOCK BAJO\'      
                  ELSE \'OK\'
                END as status
              FROM products p
              WHERE p.name ILIKE \'%\' || product_name || \'%\' 
                AND p.is_active = true
              ORDER BY similarity(p.name, product_name) DESC
              LIMIT 5;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Función para productos más vendidos (chatbot IA)
        DB::unprepared('DROP FUNCTION IF EXISTS get_top_selling_products(integer) CASCADE');
        DB::unprepared('
            CREATE FUNCTION get_top_selling_products(days integer DEFAULT 30)
            RETURNS TABLE(product_name text, total_sold bigint, revenue numeric) AS $$
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

        // Crear vistas para reportes simples (chatbot IA)
        DB::unprepared('
            CREATE OR REPLACE VIEW v_daily_sales AS
            SELECT
              sale_date,
              COUNT(*) AS transactions,
              SUM(total) AS revenue,
              AVG(total) AS avg_ticket,
              SUM(CASE WHEN payment_method = \'cash\' THEN total ELSE 0 END) AS cash_sales,
              SUM(CASE WHEN payment_method = \'card\' THEN total ELSE 0 END) AS card_sales
            FROM sales
            GROUP BY sale_date
            ORDER BY sale_date DESC;
        ');

        DB::unprepared('
            CREATE OR REPLACE VIEW v_monthly_sales AS
            SELECT
              date_trunc(\'month\', sale_date) AS month,
              COUNT(*) AS transactions,
              SUM(total) AS revenue,
              AVG(total) AS avg_ticket
            FROM sales
            GROUP BY date_trunc(\'month\', sale_date)
            ORDER BY month DESC;
        ');

        DB::unprepared('
            CREATE OR REPLACE VIEW v_top_products AS
            SELECT
              p.id,
              p.name,
              SUM(si.quantity) AS total_sold,
              SUM(si.line_total) AS total_revenue,
              COUNT(DISTINCT si.sale_id) AS times_sold
            FROM products p
            JOIN sale_items si ON p.id = si.product_id
            JOIN sales s ON si.sale_id = s.id
            WHERE s.sale_date >= CURRENT_DATE - INTERVAL \'30 days\'
            GROUP BY p.id, p.name
            ORDER BY total_sold DESC
            LIMIT 10;
        ');

        DB::unprepared('
            CREATE OR REPLACE VIEW v_low_stock AS
            SELECT
              p.id,
              p.name,
              p.stock,
              p.min_stock,
              c.name AS category
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.stock <= p.min_stock AND p.is_active = true
            ORDER BY (p.stock::float / NULLIF(p.min_stock, 0)) ASC;
        ');

        // Optimizar índice de productos para búsqueda de texto completo
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_name_gin ON products USING GIN(to_tsvector(\'spanish\', name))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS v_low_stock CASCADE');
        DB::unprepared('DROP VIEW IF EXISTS v_top_products CASCADE');
        DB::unprepared('DROP VIEW IF EXISTS v_monthly_sales CASCADE');
        DB::unprepared('DROP VIEW IF EXISTS v_daily_sales CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS get_top_selling_products(integer) CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS get_product_stock(text) CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS get_revenue(text) CASCADE');
        DB::statement('DROP INDEX IF EXISTS idx_products_name_gin');
    }
};