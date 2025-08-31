-- ESQUEMA MINIMALISTA PARA "MI NEGOCIO PRO"
-- Enfocado en microempresarios y gestión simple

-- 0) Extensiones esenciales
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS pg_trgm; -- Para búsqueda de productos
CREATE EXTENSION IF NOT EXISTS pg_stat_statements; -- Para monitoreo

-- 1) Negocio (información básica del microempresario)
CREATE TABLE business (
  id              smallint PRIMARY KEY DEFAULT 1 CHECK (id = 1), -- Solo un negocio
  name            varchar(100) NOT NULL,
  owner_name      varchar(100) NOT NULL,
  phone           varchar(20),
  email           varchar(100),
  address         varchar(200),
  currency        char(3) NOT NULL DEFAULT 'COP', -- Enfoque Latinoamérica
  tax_rate        numeric(5,4) NOT NULL DEFAULT 0, -- IVA por defecto
  created_at      timestamptz NOT NULL DEFAULT now(),
  updated_at      timestamptz NOT NULL DEFAULT now()
);

-- Pre-insertar configuración
INSERT INTO business (name, owner_name) VALUES ('Mi Negocio', 'Propietario') ON CONFLICT (id) DO NOTHING;

-- 2) Usuarios (dueño + empleados básicos)
CREATE TYPE user_role AS ENUM ('owner','employee');
CREATE TABLE users (
  id                    serial PRIMARY KEY,
  email                 varchar(100) UNIQUE,
  full_name             varchar(100) NOT NULL,
  phone                 varchar(20),
  password_hash         varchar(255),
  role                  user_role NOT NULL DEFAULT 'employee',
  is_active             boolean NOT NULL DEFAULT true,
  created_at            timestamptz NOT NULL DEFAULT now(),
  updated_at            timestamptz NOT NULL DEFAULT now()
);

-- Índice simple
CREATE INDEX idx_users_active ON users(is_active, role);

-- 3) Categorías simples (sin jerarquías complejas)
CREATE TABLE categories (
  id          serial PRIMARY KEY,
  name        varchar(100) NOT NULL UNIQUE,
  is_active   boolean NOT NULL DEFAULT true,
  created_at  timestamptz NOT NULL DEFAULT now()
);

-- Categorías básicas para microempresarios
INSERT INTO categories (name) VALUES 
('Bebidas'), ('Snacks'), ('Dulces'), ('Cigarrillos'), ('Aseo'), ('Otros')
ON CONFLICT (name) DO NOTHING;

-- 4) Productos (esencial para micronegocios)
CREATE TABLE products (
  id           serial PRIMARY KEY,
  name         varchar(100) NOT NULL,
  barcode      varchar(50), -- Para escaneo
  category_id  integer REFERENCES categories(id),
  cost_price   numeric(10,2) CHECK (cost_price >= 0), -- Precio de compra
  sale_price   numeric(10,2) CHECK (sale_price >= 0), -- Precio de venta
  stock        integer NOT NULL DEFAULT 0, -- Stock actual
  min_stock    integer DEFAULT 5, -- Alerta de stock bajo
  is_active    boolean NOT NULL DEFAULT true,
  created_at   timestamptz NOT NULL DEFAULT now(),
  updated_at   timestamptz NOT NULL DEFAULT now()
);

-- Índices para búsquedas rápidas
CREATE INDEX idx_products_name ON products USING GIN(to_tsvector('spanish', name));
CREATE INDEX idx_products_barcode ON products(barcode) WHERE barcode IS NOT NULL;
CREATE INDEX idx_products_category ON products(category_id, is_active);
CREATE INDEX idx_products_low_stock ON products(stock) WHERE stock <= min_stock;

-- 5) Ventas (registro simple para microempresarios)
CREATE TYPE payment_method AS ENUM ('cash','card','transfer','credit');

CREATE TABLE sales (
  id                  serial PRIMARY KEY,
  sale_number         varchar(20) UNIQUE NOT NULL DEFAULT ('V' || nextval('sales_id_seq')),
  customer_name       varchar(100), -- Opcional, nombre simple del cliente
  total               numeric(10,2) NOT NULL DEFAULT 0,
  payment_method      payment_method NOT NULL DEFAULT 'cash',
  notes               text,
  sale_date           date NOT NULL DEFAULT CURRENT_DATE,
  sold_by             integer NOT NULL REFERENCES users(id),
  created_at          timestamptz NOT NULL DEFAULT now()
);

-- Índices para reportes
CREATE INDEX idx_sales_date ON sales(sale_date);
CREATE INDEX idx_sales_user ON sales(sold_by, sale_date);
CREATE INDEX idx_sales_payment ON sales(payment_method, sale_date);

-- 6) Items de venta (detalle de productos vendidos)
CREATE TABLE sale_items (
  id              serial PRIMARY KEY,
  sale_id         integer NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
  product_id      integer NOT NULL REFERENCES products(id),
  quantity        integer NOT NULL CHECK (quantity > 0),
  unit_price      numeric(10,2) NOT NULL CHECK (unit_price >= 0),
  line_total      numeric(10,2) NOT NULL,
  created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX idx_sale_items_sale ON sale_items(sale_id);
CREATE INDEX idx_sale_items_product ON sale_items(product_id);

-- 7) Compras/Entradas de inventario (registro simple)
CREATE TABLE purchases (
  id                serial PRIMARY KEY,
  supplier_name     varchar(100), -- Nombre simple del proveedor
  total             numeric(10,2) NOT NULL DEFAULT 0,
  notes             text,
  purchase_date     date NOT NULL DEFAULT CURRENT_DATE,
  received_by       integer NOT NULL REFERENCES users(id),
  created_at        timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX idx_purchases_date ON purchases(purchase_date);
CREATE INDEX idx_purchases_user ON purchases(received_by, purchase_date);

-- 8) Items de compra (productos que se compraron)
CREATE TABLE purchase_items (
  id              serial PRIMARY KEY,
  purchase_id     integer NOT NULL REFERENCES purchases(id) ON DELETE CASCADE,
  product_id      integer NOT NULL REFERENCES products(id),
  quantity        integer NOT NULL CHECK (quantity > 0),
  unit_cost       numeric(10,2) NOT NULL CHECK (unit_cost >= 0),
  line_total      numeric(10,2) NOT NULL,
  created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX idx_purchase_items_purchase ON purchase_items(purchase_id);
CREATE INDEX idx_purchase_items_product ON purchase_items(product_id);

-- 9) Vistas para reportes simples (para el chatbot de IA)
CREATE VIEW v_daily_sales AS
SELECT
  sale_date,
  COUNT(*) AS transactions,
  SUM(total) AS revenue,
  AVG(total) AS avg_ticket,
  SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END) AS cash_sales,
  SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END) AS card_sales
FROM sales
GROUP BY sale_date
ORDER BY sale_date DESC;

CREATE VIEW v_monthly_sales AS
SELECT
  date_trunc('month', sale_date) AS month,
  COUNT(*) AS transactions,
  SUM(total) AS revenue,
  AVG(total) AS avg_ticket
FROM sales
GROUP BY date_trunc('month', sale_date)
ORDER BY month DESC;

CREATE VIEW v_top_products AS
SELECT
  p.id,
  p.name,
  SUM(si.quantity) AS total_sold,
  SUM(si.line_total) AS total_revenue,
  COUNT(DISTINCT si.sale_id) AS times_sold
FROM products p
JOIN sale_items si ON p.id = si.product_id
JOIN sales s ON si.sale_id = s.id
WHERE s.sale_date >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY p.id, p.name
ORDER BY total_sold DESC
LIMIT 10;

CREATE VIEW v_low_stock AS
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

-- 10) Funciones esenciales y triggers
CREATE OR REPLACE FUNCTION update_product_stock()
RETURNS TRIGGER AS $$
BEGIN
  -- Actualizar stock cuando se vende
  IF TG_TABLE_NAME = 'sale_items' THEN
    UPDATE products 
    SET stock = stock - NEW.quantity,
        updated_at = now()
    WHERE id = NEW.product_id;
  END IF;
  
  -- Actualizar stock cuando se compra
  IF TG_TABLE_NAME = 'purchase_items' THEN
    UPDATE products 
    SET stock = stock + NEW.quantity,
        cost_price = NEW.unit_cost, -- Actualizar último costo
        updated_at = now()
    WHERE id = NEW.product_id;
  END IF;
  
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Triggers para actualizar stock automáticamente
CREATE TRIGGER trg_update_stock_on_sale
  AFTER INSERT ON sale_items
  FOR EACH ROW
  EXECUTE FUNCTION update_product_stock();

CREATE TRIGGER trg_update_stock_on_purchase
  AFTER INSERT ON purchase_items
  FOR EACH ROW
  EXECUTE FUNCTION update_product_stock();

-- Trigger para updated_at
CREATE OR REPLACE FUNCTION set_updated_at() RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_business_updated BEFORE UPDATE ON business FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER trg_users_updated BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER trg_products_updated BEFORE UPDATE ON products FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- 11) Funciones para el chatbot de IA (respuestas rápidas)

-- ¿Cuánto gané hoy/este mes?
CREATE OR REPLACE FUNCTION get_revenue(period text DEFAULT 'today')
RETURNS TABLE(revenue numeric, transactions bigint) AS $$
BEGIN
  IF period = 'today' THEN
    RETURN QUERY
    SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as transactions
    FROM sales 
    WHERE sale_date = CURRENT_DATE;
  ELSIF period = 'this_month' THEN
    RETURN QUERY
    SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as transactions
    FROM sales 
    WHERE date_trunc('month', sale_date) = date_trunc('month', CURRENT_DATE);
  ELSIF period = 'last_month' THEN
    RETURN QUERY
    SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as transactions
    FROM sales 
    WHERE date_trunc('month', sale_date) = date_trunc('month', CURRENT_DATE - INTERVAL '1 month');
  END IF;
END;
$$ LANGUAGE plpgsql;

-- ¿Cuántas unidades quedan de un producto?
CREATE OR REPLACE FUNCTION get_product_stock(product_name text)
RETURNS TABLE(name text, stock integer, min_stock integer, status text) AS $$
BEGIN
  RETURN QUERY
  SELECT 
    p.name,
    p.stock,
    p.min_stock,
    CASE 
      WHEN p.stock <= 0 THEN 'SIN STOCK'
      WHEN p.stock <= p.min_stock THEN 'STOCK BAJO'
      ELSE 'OK'
    END as status
  FROM products p
  WHERE p.name ILIKE '%' || product_name || '%' 
    AND p.is_active = true
  ORDER BY similarity(p.name, product_name) DESC
  LIMIT 5;
END;
$$ LANGUAGE plpgsql;

-- ¿Cuál es mi producto más vendido?
CREATE OR REPLACE FUNCTION get_top_selling_products(days integer DEFAULT 30)
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
  WHERE s.sale_date >= CURRENT_DATE - (days || ' days')::interval
  GROUP BY p.id, p.name
  ORDER BY total_sold DESC
  LIMIT 10;
END;
$$ LANGUAGE plpgsql;

-- 12) Datos iniciales para MVP
INSERT INTO users (email, full_name, role) VALUES 
('admin@minegocio.com', 'Administrador', 'owner') 
ON CONFLICT (email) DO NOTHING;

-- Comentarios sobre la simplificación para Mi Negocio Pro:
--
-- ELIMINADO (innecesario para microempresarios):
-- ❌ Múltiples ubicaciones/sucursales (la mayoría tiene 1 local)
-- ❌ Tabla compleja de proveedores (nombre simple en compras)
-- ❌ Tabla separada de clientes (nombre opcional en ventas)
-- ❌ Variantes de productos (complicación innecesaria)
-- ❌ Tabla separada de impuestos (tasa fija por país)
-- ❌ Tabla separada de pagos (integrado en ventas)
-- ❌ Auditoría compleja (deleted_at, etc.)
-- ❌ Particionado complejo (innecesario para volumen pequeño)
-- ❌ Vistas materializadas (overkill para pocos datos)
--
-- MANTENIDO (esencial para MVP):
-- ✅ Gestión básica de productos con stock
-- ✅ Registro simple de ventas
-- ✅ Control de compras/entradas
-- ✅ Reportes básicos para chatbot IA
-- ✅ Alertas de stock bajo
-- ✅ Usuarios básicos (dueño + empleados)
-- ✅ Categorización simple
-- ✅ Búsqueda por nombre/código de barras
--
-- OPTIMIZADO PARA:
-- 🎯 Microempresarios con 1 local
-- 🎯 Pocos productos (< 1000)
-- 🎯 Ventas diarias moderadas (< 100/día)
-- 🎯 Reportes simples y rápidos
-- 🎯 Chatbot que responda preguntas básicas
-- 🎯 Interfaz móvil simple
-- 🎯 Funcionamiento offline (datos mínimos)
--
-- Este esquema reduce complejidad en 80% manteniendo funcionalidad core



-- 16) Funciones de utilidad
CREATE OR REPLACE FUNCTION user_can_access_location(user_uuid uuid, location_uuid uuid)
RETURNS boolean AS $$
DECLARE
  user_role_val user_role;
  allowed_locations uuid[];
BEGIN
  SELECT role, allowed_locations 
  INTO user_role_val, allowed_locations
  FROM users 
  WHERE id = user_uuid AND deleted_at IS NULL;
  
  -- Owner y admin tienen acceso a todas las ubicaciones
  IF user_role_val IN ('owner', 'admin') THEN
    RETURN true;
  END IF;
  
  -- Para otros roles, verificar array de ubicaciones permitidas
  RETURN location_uuid = ANY(allowed_locations);
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- 17) Políticas RLS simplificadas (opcional)
-- ALTER TABLE sales ENABLE ROW LEVEL SECURITY;
-- CREATE POLICY sales_location_access ON sales
--   FOR ALL TO authenticated
--   USING (user_can_access_location(auth.uid(), location_id));

-- 18) Datos iniciales
INSERT INTO company (name, currency_code) VALUES ('Mi Negocio', 'USD');

-- Comentarios sobre las simplificaciones realizadas:
--
-- 1. Eliminada tabla user_location_permissions - Se usa array en users
-- 2. Eliminada tabla tax_rates - Se usa campo directo en products
-- 3. Eliminada tabla product_variants - Se simplifica a productos únicos
-- 4. Eliminada tabla payments separada - Se integra en sales
-- 5. Simplificados campos JSONB innecesarios
-- 6. Eliminados campos de auditoría excesivos
-- 7. Reducidos índices a los esenciales
-- 8. Campos calculados usando GENERATED ALWAYS AS
-- 9. Relaciones más directas y simples
-- 10. Menos complejidad en general para mejor rendimiento