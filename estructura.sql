--
-- PostgreSQL database dump
--

-- Dumped from database version 17.5
-- Dumped by pg_dump version 17.5

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_stat_statements; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_stat_statements WITH SCHEMA public;


--
-- Name: EXTENSION pg_stat_statements; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION pg_stat_statements IS 'track planning and execution statistics of all SQL statements executed';


--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


--
-- Name: payment_method; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.payment_method AS ENUM (
    'cash',
    'card',
    'transfer',
    'credit'
);


ALTER TYPE public.payment_method OWNER TO postgres;

--
-- Name: user_role; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.user_role AS ENUM (
    'owner',
    'employee'
);


ALTER TYPE public.user_role OWNER TO postgres;

--
-- Name: calculate_purchase_item_line_total(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.calculate_purchase_item_line_total() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                NEW.line_total = NEW.quantity * NEW.unit_cost;
                RETURN NEW;
            END;
            $$;


ALTER FUNCTION public.calculate_purchase_item_line_total() OWNER TO postgres;

--
-- Name: calculate_purchase_total(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.calculate_purchase_total() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
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
            $$;


ALTER FUNCTION public.calculate_purchase_total() OWNER TO postgres;

--
-- Name: calculate_sale_item_line_total(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.calculate_sale_item_line_total() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                NEW.line_total := NEW.quantity * NEW.unit_price;
                RETURN NEW;
            END;
            $$;


ALTER FUNCTION public.calculate_sale_item_line_total() OWNER TO postgres;

--
-- Name: generate_sale_number(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.generate_sale_number() RETURNS character varying
    LANGUAGE plpgsql
    AS $$
            DECLARE
                next_number integer;
            BEGIN
                SELECT COALESCE(MAX(CAST(SUBSTRING(sale_number FROM 2) AS integer)), 0) + 1
                INTO next_number
                FROM sales;

                RETURN 'V' || LPAD(next_number::text, 6, '0');
            END;
            $$;


ALTER FUNCTION public.generate_sale_number() OWNER TO postgres;

--
-- Name: get_product_stock(text); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.get_product_stock(product_name text) RETURNS TABLE(name character varying, stock integer, min_stock integer, status text)
    LANGUAGE plpgsql
    AS $$
            BEGIN
              RETURN QUERY
              SELECT
                p.name,
                p.stock,
                p.min_stock,
                CASE
                  WHEN p.stock <= 0 THEN 'SIN STOCK'::text
                  WHEN p.stock <= p.min_stock THEN 'STOCK BAJO'::text
                  ELSE 'OK'::text
                END as status
              FROM products p
              WHERE p.name ILIKE '%' || product_name || '%'
                AND p.is_active = true
              ORDER BY similarity(p.name, product_name) DESC
              LIMIT 5;
            END;
            $$;


ALTER FUNCTION public.get_product_stock(product_name text) OWNER TO postgres;

--
-- Name: get_revenue(text); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.get_revenue(period text DEFAULT 'today'::text) RETURNS TABLE(revenue numeric, transactions bigint)
    LANGUAGE plpgsql
    AS $$

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

            $$;


ALTER FUNCTION public.get_revenue(period text) OWNER TO postgres;

--
-- Name: get_top_selling_products(integer); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.get_top_selling_products(days integer DEFAULT 30) RETURNS TABLE(product_name character varying, total_sold bigint, revenue numeric)
    LANGUAGE plpgsql
    AS $$
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
            $$;


ALTER FUNCTION public.get_top_selling_products(days integer) OWNER TO postgres;

--
-- Name: set_sale_number(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.set_sale_number() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF NEW.sale_number IS NULL OR NEW.sale_number = '' THEN
                    NEW.sale_number := generate_sale_number();
                END IF;
                RETURN NEW;
            END;
            $$;


ALTER FUNCTION public.set_sale_number() OWNER TO postgres;

--
-- Name: update_product_stock_on_purchase(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_product_stock_on_purchase() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    -- Aumentar stock y actualizar costo
                    UPDATE products
                    SET stock = stock + NEW.quantity,
                        cost_price = NEW.unit_cost,
                        updated_at = now()
                    WHERE id = NEW.product_id;
                    RETURN NEW;

                ELSIF TG_OP = 'UPDATE' THEN
                    -- Ajustar stock por la diferencia
                    UPDATE products
                    SET stock = stock - OLD.quantity + NEW.quantity,
                        cost_price = NEW.unit_cost,
                        updated_at = now()
                    WHERE id = NEW.product_id;
                    RETURN NEW;

                ELSIF TG_OP = 'DELETE' THEN
                    -- Reducir stock
                    UPDATE products
                    SET stock = stock - OLD.quantity,
                        updated_at = now()
                    WHERE id = OLD.product_id;
                    RETURN OLD;
                END IF;

                RETURN NULL;
            END;
            $$;


ALTER FUNCTION public.update_product_stock_on_purchase() OWNER TO postgres;

--
-- Name: update_product_stock_on_sale(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_product_stock_on_sale() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
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
            $$;


ALTER FUNCTION public.update_product_stock_on_sale() OWNER TO postgres;

--
-- Name: update_sale_total(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_sale_total() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
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
            $$;


ALTER FUNCTION public.update_sale_total() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: businesses; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.businesses (
    id smallint DEFAULT '1'::smallint NOT NULL,
    name character varying(100) NOT NULL,
    owner_name character varying(100) NOT NULL,
    phone character varying(20),
    email character varying(100),
    address character varying(200),
    currency character(3) DEFAULT 'COP'::bpchar NOT NULL,
    tax_rate numeric(5,4) DEFAULT '0'::numeric NOT NULL,
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT chk_singleton CHECK ((id = 1))
);


ALTER TABLE public.businesses OWNER TO postgres;

--
-- Name: cache; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO postgres;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO postgres;

--
-- Name: categories; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.categories (
    id bigint NOT NULL,
    name character varying(100) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.categories OWNER TO postgres;

--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.categories_id_seq OWNER TO postgres;

--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.categories.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO postgres;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO postgres;

--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO postgres;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_access_tokens_id_seq OWNER TO postgres;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: products; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.products (
    id bigint NOT NULL,
    name character varying(100) NOT NULL,
    image_path character varying(255),
    category_id bigint,
    cost_price numeric(10,2),
    sale_price numeric(10,2) NOT NULL,
    stock integer DEFAULT 0 NOT NULL,
    min_stock integer DEFAULT 5 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT chk_products_cost_price CHECK ((cost_price >= (0)::numeric)),
    CONSTRAINT chk_products_min_stock CHECK ((min_stock >= 0)),
    CONSTRAINT chk_products_sale_price CHECK ((sale_price >= (0)::numeric)),
    CONSTRAINT chk_products_stock CHECK ((stock >= 0))
);


ALTER TABLE public.products OWNER TO postgres;

--
-- Name: products_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.products_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.products_id_seq OWNER TO postgres;

--
-- Name: products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.products_id_seq OWNED BY public.products.id;


--
-- Name: purchase_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.purchase_items (
    id bigint NOT NULL,
    purchase_id bigint NOT NULL,
    product_id bigint NOT NULL,
    quantity integer NOT NULL,
    unit_cost numeric(10,2) NOT NULL,
    line_total numeric(10,2) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT chk_quantity_positive CHECK ((quantity > 0)),
    CONSTRAINT chk_unit_cost_positive CHECK ((unit_cost >= (0)::numeric))
);


ALTER TABLE public.purchase_items OWNER TO postgres;

--
-- Name: COLUMN purchase_items.quantity; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.purchase_items.quantity IS 'Cantidad comprada';


--
-- Name: COLUMN purchase_items.unit_cost; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.purchase_items.unit_cost IS 'Costo unitario';


--
-- Name: COLUMN purchase_items.line_total; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.purchase_items.line_total IS 'Total de la línea';


--
-- Name: purchase_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.purchase_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.purchase_items_id_seq OWNER TO postgres;

--
-- Name: purchase_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.purchase_items_id_seq OWNED BY public.purchase_items.id;


--
-- Name: purchases; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.purchases (
    id bigint NOT NULL,
    supplier_name character varying(100),
    total numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    notes text,
    purchase_date date DEFAULT CURRENT_DATE NOT NULL,
    received_by bigint NOT NULL,
    created_at timestamp(0) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.purchases OWNER TO postgres;

--
-- Name: COLUMN purchases.supplier_name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.purchases.supplier_name IS 'Nombre simple del proveedor';


--
-- Name: COLUMN purchases.total; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.purchases.total IS 'Total de la compra';


--
-- Name: COLUMN purchases.notes; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.purchases.notes IS 'Notas adicionales';


--
-- Name: COLUMN purchases.purchase_date; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.purchases.purchase_date IS 'Fecha de la compra';


--
-- Name: purchases_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.purchases_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.purchases_id_seq OWNER TO postgres;

--
-- Name: purchases_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.purchases_id_seq OWNED BY public.purchases.id;


--
-- Name: sale_items; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sale_items (
    id bigint NOT NULL,
    sale_id bigint NOT NULL,
    product_id bigint NOT NULL,
    quantity integer NOT NULL,
    unit_price numeric(10,2) NOT NULL,
    line_total numeric(10,2) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT chk_sale_items_quantity CHECK ((quantity > 0)),
    CONSTRAINT chk_sale_items_unit_price CHECK ((unit_price >= (0)::numeric))
);


ALTER TABLE public.sale_items OWNER TO postgres;

--
-- Name: sale_items_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sale_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sale_items_id_seq OWNER TO postgres;

--
-- Name: sale_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sale_items_id_seq OWNED BY public.sale_items.id;


--
-- Name: sales; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sales (
    id bigint NOT NULL,
    sale_number character varying(20) NOT NULL,
    customer_name character varying(100),
    total numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    notes character varying(255),
    sale_date date DEFAULT CURRENT_DATE NOT NULL,
    sold_by bigint NOT NULL,
    created_at timestamp(0) with time zone DEFAULT now() NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT now() NOT NULL,
    deleted_at timestamp(0) without time zone,
    payment_method public.payment_method DEFAULT 'cash'::public.payment_method NOT NULL,
    CONSTRAINT sales_total_positive CHECK ((total >= (0)::numeric))
);


ALTER TABLE public.sales OWNER TO postgres;

--
-- Name: sales_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sales_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sales_id_seq OWNER TO postgres;

--
-- Name: sales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sales_id_seq OWNED BY public.sales.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO postgres;

--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    business_name timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    phone character varying(255),
    role character varying(255) DEFAULT 'employee'::character varying NOT NULL,
    active boolean DEFAULT true NOT NULL,
    remember_token character varying(100),
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT users_role_check CHECK (((role)::text = ANY ((ARRAY['owner'::character varying, 'employee'::character varying, 'admin'::character varying])::text[])))
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: v_daily_sales; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.v_daily_sales AS
 SELECT sale_date,
    count(*) AS transactions,
    sum(total) AS revenue,
    avg(total) AS avg_ticket,
    sum(
        CASE
            WHEN (payment_method = 'cash'::public.payment_method) THEN total
            ELSE (0)::numeric
        END) AS cash_sales,
    sum(
        CASE
            WHEN (payment_method = 'card'::public.payment_method) THEN total
            ELSE (0)::numeric
        END) AS card_sales
   FROM public.sales
  GROUP BY sale_date
  ORDER BY sale_date DESC;


ALTER VIEW public.v_daily_sales OWNER TO postgres;

--
-- Name: v_low_stock; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.v_low_stock AS
 SELECT p.id,
    p.name,
    p.stock,
    p.min_stock,
    c.name AS category
   FROM (public.products p
     LEFT JOIN public.categories c ON ((p.category_id = c.id)))
  WHERE ((p.stock <= p.min_stock) AND (p.is_active = true))
  ORDER BY ((p.stock)::double precision / (NULLIF(p.min_stock, 0))::double precision);


ALTER VIEW public.v_low_stock OWNER TO postgres;

--
-- Name: v_monthly_sales; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.v_monthly_sales AS
 SELECT date_trunc('month'::text, (sale_date)::timestamp with time zone) AS month,
    count(*) AS transactions,
    sum(total) AS revenue,
    avg(total) AS avg_ticket
   FROM public.sales
  GROUP BY (date_trunc('month'::text, (sale_date)::timestamp with time zone))
  ORDER BY (date_trunc('month'::text, (sale_date)::timestamp with time zone)) DESC;


ALTER VIEW public.v_monthly_sales OWNER TO postgres;

--
-- Name: v_top_products; Type: VIEW; Schema: public; Owner: postgres
--

CREATE VIEW public.v_top_products AS
 SELECT p.id,
    p.name,
    sum(si.quantity) AS total_sold,
    sum(si.line_total) AS total_revenue,
    count(DISTINCT si.sale_id) AS times_sold
   FROM ((public.products p
     JOIN public.sale_items si ON ((p.id = si.product_id)))
     JOIN public.sales s ON ((si.sale_id = s.id)))
  WHERE (s.sale_date >= (CURRENT_DATE - '30 days'::interval))
  GROUP BY p.id, p.name
  ORDER BY (sum(si.quantity)) DESC
 LIMIT 10;


ALTER VIEW public.v_top_products OWNER TO postgres;

--
-- Name: categories id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: products id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products ALTER COLUMN id SET DEFAULT nextval('public.products_id_seq'::regclass);


--
-- Name: purchase_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.purchase_items ALTER COLUMN id SET DEFAULT nextval('public.purchase_items_id_seq'::regclass);


--
-- Name: purchases id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.purchases ALTER COLUMN id SET DEFAULT nextval('public.purchases_id_seq'::regclass);


--
-- Name: sale_items id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sale_items ALTER COLUMN id SET DEFAULT nextval('public.sale_items_id_seq'::regclass);


--
-- Name: sales id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales ALTER COLUMN id SET DEFAULT nextval('public.sales_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: businesses businesses_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.businesses
    ADD CONSTRAINT businesses_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: categories categories_name_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_name_unique UNIQUE (name);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: purchase_items purchase_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.purchase_items
    ADD CONSTRAINT purchase_items_pkey PRIMARY KEY (id);


--
-- Name: purchases purchases_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_pkey PRIMARY KEY (id);


--
-- Name: sale_items sale_items_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sale_items
    ADD CONSTRAINT sale_items_pkey PRIMARY KEY (id);


--
-- Name: sales sales_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_pkey PRIMARY KEY (id);


--
-- Name: sales sales_sale_number_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_sale_number_unique UNIQUE (sale_number);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: idx_products_low_stock; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_low_stock ON public.products USING btree (stock) WHERE (stock <= min_stock);


--
-- Name: idx_products_name; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_name ON public.products USING gin (to_tsvector('spanish'::regconfig, (name)::text));


--
-- Name: idx_products_name_gin; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_name_gin ON public.products USING gin (to_tsvector('spanish'::regconfig, (name)::text));


--
-- Name: idx_purchase_items_product; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_purchase_items_product ON public.purchase_items USING btree (product_id);


--
-- Name: idx_purchase_items_purchase; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_purchase_items_purchase ON public.purchase_items USING btree (purchase_id);


--
-- Name: idx_purchases_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_purchases_date ON public.purchases USING btree (purchase_date);


--
-- Name: idx_purchases_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_purchases_user ON public.purchases USING btree (received_by, purchase_date);


--
-- Name: idx_sale_items_product; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sale_items_product ON public.sale_items USING btree (product_id);


--
-- Name: idx_sale_items_sale; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sale_items_sale ON public.sale_items USING btree (sale_id);


--
-- Name: idx_sale_items_sale_product; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sale_items_sale_product ON public.sale_items USING btree (sale_id, product_id);


--
-- Name: idx_sales_customer; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sales_customer ON public.sales USING btree (customer_name) WHERE (customer_name IS NOT NULL);


--
-- Name: idx_sales_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sales_date ON public.sales USING btree (sale_date);


--
-- Name: idx_sales_payment; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sales_payment ON public.sales USING btree (payment_method, sale_date);


--
-- Name: idx_sales_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sales_user ON public.sales USING btree (sold_by, sale_date);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: products_category_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX products_category_id_is_active_index ON public.products USING btree (category_id, is_active);


--
-- Name: products_name_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX products_name_index ON public.products USING btree (name);


--
-- Name: products_stock_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX products_stock_index ON public.products USING btree (stock);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: users_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_active_index ON public.users USING btree (active);


--
-- Name: users_active_role_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_active_role_index ON public.users USING btree (active, role);


--
-- Name: users_role_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_role_index ON public.users USING btree (role);


--
-- Name: purchase_items trg_calculate_purchase_item_line_total; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_calculate_purchase_item_line_total BEFORE INSERT OR UPDATE ON public.purchase_items FOR EACH ROW EXECUTE FUNCTION public.calculate_purchase_item_line_total();


--
-- Name: sale_items trg_calculate_sale_item_line_total; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_calculate_sale_item_line_total BEFORE INSERT OR UPDATE ON public.sale_items FOR EACH ROW EXECUTE FUNCTION public.calculate_sale_item_line_total();


--
-- Name: sales trg_set_sale_number; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_set_sale_number BEFORE INSERT ON public.sales FOR EACH ROW EXECUTE FUNCTION public.set_sale_number();


--
-- Name: purchase_items trg_update_product_stock_on_purchase; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_update_product_stock_on_purchase AFTER INSERT OR DELETE OR UPDATE ON public.purchase_items FOR EACH ROW EXECUTE FUNCTION public.update_product_stock_on_purchase();


--
-- Name: sale_items trg_update_product_stock_on_sale; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_update_product_stock_on_sale AFTER INSERT OR DELETE OR UPDATE ON public.sale_items FOR EACH ROW EXECUTE FUNCTION public.update_product_stock_on_sale();


--
-- Name: purchase_items trg_update_purchase_total_on_items; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_update_purchase_total_on_items AFTER INSERT OR DELETE OR UPDATE ON public.purchase_items FOR EACH ROW EXECUTE FUNCTION public.calculate_purchase_total();


--
-- Name: sale_items trg_update_sale_total; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_update_sale_total AFTER INSERT OR DELETE OR UPDATE ON public.sale_items FOR EACH ROW EXECUTE FUNCTION public.update_sale_total();


--
-- Name: products products_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE SET NULL;


--
-- Name: purchase_items purchase_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.purchase_items
    ADD CONSTRAINT purchase_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: purchase_items purchase_items_purchase_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.purchase_items
    ADD CONSTRAINT purchase_items_purchase_id_foreign FOREIGN KEY (purchase_id) REFERENCES public.purchases(id) ON DELETE CASCADE;


--
-- Name: purchases purchases_received_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_received_by_foreign FOREIGN KEY (received_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: sale_items sale_items_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sale_items
    ADD CONSTRAINT sale_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: sale_items sale_items_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sale_items
    ADD CONSTRAINT sale_items_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES public.sales(id) ON DELETE CASCADE;


--
-- Name: sales sales_sold_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_sold_by_foreign FOREIGN KEY (sold_by) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

