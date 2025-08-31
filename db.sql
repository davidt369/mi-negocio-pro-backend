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

-- ESQUEMA MULTITENANT PARA "MI NEGOCIO PRO"
-- Soporte para múltiples negocios independientes

-- 0) Extensiones esenciales
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- 1) Tenants/Negocios (múltiples negocios independientes)
CREATE TABLE tenants (
  id              serial PRIMARY KEY,
  slug            varchar(50) UNIQUE NOT NULL, -- URL amigable: mitienda.app
  name            varchar(100) NOT NULL,
  owner_name      varchar(100) NOT NULL,
  phone           varchar(20),
  email           varchar(100),
  address         varchar(200),
  currency        char(3) NOT NULL DEFAULT 'COP',
  tax_rate        numeric(5,4) NOT NULL DEFAULT 0,
  subscription_plan varchar(20) DEFAULT 'free', -- free, basic, pro
  is_active       boolean NOT NULL DEFAULT true,
  created_at      timestamptz NOT NULL DEFAULT now(),
  updated_at      timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX idx_tenants_slug ON tenants(slug);
CREATE INDEX idx_tenants_active ON tenants(is_active);

-- 2) Usuarios (con separación por tenant)
CREATE TYPE user_role AS ENUM ('owner','employee');
CREATE TABLE users (
  id                    serial PRIMARY KEY,
  tenant_id             integer NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  email                 varchar(100) NOT NULL,
  full_name             varchar(100) NOT NULL,
  phone                 varchar(20),
  password_hash         varchar(255),
  role                  user_role NOT NULL DEFAULT 'employee',
  is_active             boolean NOT NULL DEFAULT true,
  created_at            timestamptz NOT NULL DEFAULT now(),
  updated_at            timestamptz NOT NULL DEFAULT now(),
  UNIQUE(tenant_id, email) -- Email único por tenant
);

CREATE INDEX idx_users_tenant ON users(tenant_id, is_active, role);

-- 3) Categorías (por tenant)
CREATE TABLE categories (
  id          serial PRIMARY KEY,
  tenant_id   integer NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  name        varchar(100) NOT NULL,
  is_active   boolean NOT NULL DEFAULT true,
  created_at  timestamptz NOT NULL DEFAULT now(),
  UNIQUE(tenant_id, name)
);

CREATE INDEX idx_categories_tenant ON categories(tenant_id, is_active);

-- 4) Productos (por tenant)
CREATE TABLE products (
  id           serial PRIMARY KEY,
  tenant_id    integer NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  name         varchar(100) NOT NULL,
  barcode      varchar(50),
  category_id  integer REFERENCES categories(id),
  cost_price   numeric(10,2) CHECK (cost_price >= 0),
  sale_price   numeric(10,2) CHECK (sale_price >= 0),
  stock        integer NOT NULL DEFAULT 0,
  min_stock    integer DEFAULT 5,
  is_active    boolean NOT NULL DEFAULT true,
  created_at   timestamptz NOT NULL DEFAULT now(),
  updated_at   timestamptz NOT NULL DEFAULT now(),
  UNIQUE(tenant_id, barcode) WHERE barcode IS NOT NULL
);

CREATE INDEX idx_products_tenant ON products(tenant_id, is_active);
CREATE INDEX idx_products_search ON products USING GIN(to_tsvector('spanish', name));
CREATE INDEX idx_products_low_stock ON products(tenant_id) WHERE stock <= min_stock;

-- 5) Ventas (por tenant)
CREATE TYPE payment_method AS ENUM ('cash','card','transfer','credit');
CREATE TABLE sales (
  id                  serial PRIMARY KEY,
  tenant_id           integer NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  sale_number         varchar(20) NOT NULL,
  customer_name       varchar(100),
  total               numeric(10,2) NOT NULL DEFAULT 0,
  payment_method      payment_method NOT NULL DEFAULT 'cash',
  notes               text,
  sale_date           date NOT NULL DEFAULT CURRENT_DATE,
  sold_by             integer NOT NULL REFERENCES users(id),
  created_at          timestamptz NOT NULL DEFAULT now(),
  UNIQUE(tenant_id, sale_number)
);

CREATE INDEX idx_sales_tenant ON sales(tenant_id, sale_date);
CREATE INDEX idx_sales_user ON sales(sold_by, sale_date);

-- 6) Items de venta (automáticamente por tenant vía sales)
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

-- 7) Compras (por tenant)
CREATE TABLE purchases (
  id                serial PRIMARY KEY,
  tenant_id         integer NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  supplier_name     varchar(100),
  total             numeric(10,2) NOT NULL DEFAULT 0,
  notes             text,
  purchase_date     date NOT NULL DEFAULT CURRENT_DATE,
  received_by       integer NOT NULL REFERENCES users(id),
  created_at        timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX idx_purchases_tenant ON purchases(tenant_id, purchase_date);

-- 8) Items de compra (automáticamente por tenant vía purchases)
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

-- 9) Funciones para manejo automático de numeración por tenant
CREATE OR REPLACE FUNCTION generate_sale_number(p_tenant_id integer)
RETURNS varchar(20) AS $$
DECLARE
  next_number integer;
BEGIN
  SELECT COALESCE(MAX(CAST(SUBSTRING(sale_number FROM 2) AS integer)), 0) + 1
  INTO next_number
  FROM sales
  WHERE tenant_id = p_tenant_id;
  
  RETURN 'V' || LPAD(next_number::text, 6, '0');
END;
$$ LANGUAGE plpgsql;

-- 10) Trigger para auto-asignar número de venta
CREATE OR REPLACE FUNCTION set_sale_number()
RETURNS TRIGGER AS $$
BEGIN
  IF NEW.sale_number IS NULL OR NEW.sale_number = '' THEN
    NEW.sale_number := generate_sale_number(NEW.tenant_id);
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_set_sale_number
  BEFORE INSERT ON sales
  FOR EACH ROW
  EXECUTE FUNCTION set_sale_number();

-- 11) Triggers para updated_at
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_tenants_updated BEFORE UPDATE ON tenants FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER trg_users_updated BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER trg_products_updated BEFORE UPDATE ON products FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- 12) Funciones para el chatbot (por tenant)
CREATE OR REPLACE FUNCTION get_tenant_revenue(p_tenant_id integer, period text DEFAULT 'today')
RETURNS numeric AS $$
BEGIN
  CASE period
    WHEN 'today' THEN
      RETURN (SELECT COALESCE(SUM(total), 0) FROM sales WHERE tenant_id = p_tenant_id AND sale_date = CURRENT_DATE);
    WHEN 'month' THEN
      RETURN (SELECT COALESCE(SUM(total), 0) FROM sales WHERE tenant_id = p_tenant_id AND date_trunc('month', sale_date) = date_trunc('month', CURRENT_DATE));
    ELSE
      RETURN 0;
  END CASE;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION get_tenant_product_stock(p_tenant_id integer, product_name text)
RETURNS integer AS $$
BEGIN
  RETURN (SELECT COALESCE(stock, 0) FROM products 
          WHERE tenant_id = p_tenant_id AND name ILIKE '%' || product_name || '%' 
          LIMIT 1);
END;
$$ LANGUAGE plpgsql;

-- 13) Vistas por tenant (ejemplo)
CREATE OR REPLACE VIEW v_tenant_low_stock AS
SELECT 
  p.tenant_id,
  t.name AS tenant_name,
  p.id,
  p.name AS product_name,
  p.stock,
  p.min_stock
FROM products p
JOIN tenants t ON p.tenant_id = t.id
WHERE p.stock <= p.min_stock AND p.is_active = true;

-- 14) Políticas de RLS (Row Level Security) para mayor seguridad
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
ALTER TABLE sales ENABLE ROW LEVEL SECURITY;
ALTER TABLE purchases ENABLE ROW LEVEL SECURITY;

-- Las políticas se definirían en la aplicación según el contexto del usuario autenticado

-- 15) Datos iniciales para categorías por tenant (se ejecutará por cada nuevo tenant)
CREATE OR REPLACE FUNCTION create_default_categories(p_tenant_id integer)
RETURNS void AS $$
BEGIN
  INSERT INTO categories (tenant_id, name) VALUES 
  (p_tenant_id, 'Bebidas'),
  (p_tenant_id, 'Snacks'),
  (p_tenant_id, 'Dulces'),
  (p_tenant_id, 'Cigarrillos'),
  (p_tenant_id, 'Aseo'),
  (p_tenant_id, 'Otros');
END;
$$ LANGUAGE plpgsql;