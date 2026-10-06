-- =========================================================
-- HAAT! E-Commerce Platform — Database Schema
-- =========================================================
-- Normalization Level  : 3NF (Third Normal Form) throughout
-- Indexing Strategy    : Covering indexes for all hot query paths
-- Engine               : InnoDB (ACID, FK support, row-level locking)
-- Charset              : utf8mb4_unicode_ci  (full Unicode + emoji)
-- =========================================================
--
-- NORMALIZATION NOTES
-- 1NF  Every column holds atomic values; no repeating groups.
-- 2NF  Every non-key attribute depends fully on the primary key.
-- 3NF  No transitive dependencies exist.
-- BCNF Every determinant is a candidate key.
--
-- INTENTIONAL DENORMALISATION (documented):
--   orders.shipping_* columns    : audit/legal snapshot at purchase time
--   order_items.product_name     : immutable sold-price/name record
--   order_items.unit_price       : price at time of sale
--   messages.sender_role etc.    : avoids JOIN to users on every read
--
-- INDEXING RULES:
--   Rule A  FK columns always indexed (avoids full scans on parent)
--   Rule B  ENUM/status columns used in WHERE get a dedicated index
--   Rule C  Time-sort queries use composite (fk_col, created_at) index
--   Rule D  Lookup columns (slug, sku, code, email) use UNIQUE index
--   Rule E  Composite covering indexes for the most frequent queries
-- =========================================================

CREATE DATABASE IF NOT EXISTS haat
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE haat;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS product_reviews;
DROP TABLE IF EXISTS delivery_tracking;
DROP TABLE IF EXISTS riders;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS seller_orders;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS collections;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS subcategories;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS customer_addresses;
DROP TABLE IF EXISTS stores;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;


-- =========================================================
-- 1. USERS
-- Normalization: Atomic columns; role/status are ENUM.
-- Q1 WHERE email = ?          -> UNIQUE(email)
-- Q2 WHERE role=? AND status=? -> idx_users_role_status
-- =========================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('customer','seller','admin','logistics') NOT NULL DEFAULT 'customer',
    status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role_status (role, status)         -- Rule B
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 2. STORES  (1:1 with users)
-- Normalization: Address attrs belong to store entity.
-- Q1 WHERE store_slug=?      -> UNIQUE(store_slug)
-- Q2 WHERE status=?          -> idx_stores_status
-- Q3 WHERE division=?        -> idx_stores_division
-- =========================================================
CREATE TABLE stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    store_name VARCHAR(150) NOT NULL UNIQUE,
    store_slug VARCHAR(160) NOT NULL UNIQUE,
    description TEXT,
    logo MEDIUMBLOB,
    logo_mime_type VARCHAR(50),
    banner MEDIUMBLOB,
    banner_mime_type VARCHAR(50),
    address VARCHAR(255),
    district VARCHAR(50),
    division VARCHAR(50),
    postal_code VARCHAR(10),
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    delivery_charge DECIMAL(10, 2) NOT NULL DEFAULT 60.00,
    free_delivery TINYINT(1) NOT NULL DEFAULT 0,
    auto_greeting TEXT DEFAULT NULL,
    verification_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
    verification_documents LONGTEXT DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stores_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_stores_status   (status, is_published),  -- Rule B
    INDEX idx_stores_division (division, status)       -- Rule E: geo store-finder
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 3. CUSTOMER ADDRESSES  (1:N with users)
-- Normalization: Separated to avoid repeating groups (1NF).
-- Q1 WHERE user_id=?                -> idx_addresses_user
-- Q2 WHERE user_id=? AND is_default=1 -> idx_addresses_user_default
-- =========================================================
CREATE TABLE customer_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(50) DEFAULT 'Home',
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    district VARCHAR(50) NOT NULL,
    division VARCHAR(50) NOT NULL,
    postal_code VARCHAR(10),
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_addresses_user         (user_id),             -- Rule A
    INDEX idx_addresses_user_default (user_id, is_default)  -- Rule E: checkout default
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 4. CATEGORIES
-- Normalization: Root taxonomy entity; fully atomic.
-- Q1 WHERE slug=? -> UNIQUE(slug)  Rule D
-- =========================================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(110) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 5. SUBCATEGORIES  (N:1 with categories)
-- Normalization: Slug uniqueness scoped to category.
-- Q1 WHERE category_id=?          -> idx_subcategories_category
-- Q2 WHERE category_id=? AND slug=? -> UNIQUE KEY
-- =========================================================
CREATE TABLE subcategories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(110) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subcategories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_category_subcategory (category_id, slug),
    INDEX idx_subcategories_category (category_id)  -- Rule A
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 6. BRANDS
-- Normalization: Independent entity; eliminates transitive
-- dependency brand_name->brand_logo inside products (3NF).
-- Q1 WHERE slug=? -> UNIQUE(slug)  Rule D
-- =========================================================
CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(110) NOT NULL UNIQUE,
    logo MEDIUMBLOB,
    logo_mime_type VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 7. COLLECTIONS  (seller-scoped product groupings)
-- Normalization: slug unique per store (composite unique).
-- Q1 WHERE store_id=?          -> idx_collections_store
-- Q2 WHERE store_id=? AND slug=? -> UNIQUE KEY
-- =========================================================
CREATE TABLE collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_collections_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    UNIQUE KEY unique_store_collection (store_id, slug),
    INDEX idx_collections_store (store_id)  -- Rule A
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 8. PRODUCTS
-- Normalization: Brand in brands table (removes transitive dep
-- brand_name->brand_logo from products = 3NF). Category
-- hierarchy in own tables. Collection is a FK. Images are
-- atomic BLOB columns (not a JSON array).
-- Variant columns are discrete atomic pairs; a separate
-- product_variants table is only needed if variants require
-- independent pricing/stock.
--
-- Q1  WHERE slug=?              -> UNIQUE(slug)   Rule D
-- Q2  WHERE sku=?               -> UNIQUE(sku)    Rule D
-- Q3  WHERE store_id=?          -> idx_products_store
-- Q4  WHERE subcategory_id=?    -> idx_products_subcategory
-- Q5  WHERE brand_id=?          -> idx_products_brand
-- Q6  WHERE collection_id=?     -> idx_products_collection
-- Q7  WHERE is_featured=1       -> idx_products_featured
-- Q8  WHERE store_id=? ORDER BY created_at -> idx_products_store_time
-- Q9  Full-text search          -> ft_products_search
-- Q10 Subcategory + price range -> idx_products_subcat_price
-- =========================================================
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    subcategory_id INT NOT NULL,
    brand_id INT DEFAULT NULL,
    collection_id INT DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    sku VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2) DEFAULT NULL,
    image_1 MEDIUMBLOB,
    image_1_mime_type VARCHAR(50),
    image_2 MEDIUMBLOB,
    image_2_mime_type VARCHAR(50),
    image_3 MEDIUMBLOB,
    image_3_mime_type VARCHAR(50),
    variant_1_name VARCHAR(100),
    variant_1_value VARCHAR(100),
    variant_2_name VARCHAR(100),
    variant_2_value VARCHAR(100),
    variant_3_name VARCHAR(100),
    variant_3_value VARCHAR(100),
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_store       FOREIGN KEY (store_id)       REFERENCES stores(id)        ON DELETE CASCADE,
    CONSTRAINT fk_products_subcategory FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_products_brand       FOREIGN KEY (brand_id)       REFERENCES brands(id)        ON DELETE SET NULL,
    CONSTRAINT fk_products_collection  FOREIGN KEY (collection_id)  REFERENCES collections(id)   ON DELETE SET NULL,
    CHECK (price >= 0),
    CHECK (sale_price IS NULL OR sale_price >= 0),
    CHECK (sale_price IS NULL OR sale_price <= price),
    INDEX idx_products_store        (store_id),          -- Rule A
    INDEX idx_products_subcategory  (subcategory_id),    -- Rule A
    INDEX idx_products_brand        (brand_id),          -- Rule A
    INDEX idx_products_collection   (collection_id),     -- Rule A
    INDEX idx_products_featured     (is_featured),       -- Rule B
    INDEX idx_products_store_time   (store_id, created_at),    -- Rule E: seller dashboard
    INDEX idx_products_subcat_price (subcategory_id, price),   -- Rule E: price filter
    FULLTEXT INDEX ft_products_search (name, description)      -- Rule E: search
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 9. INVENTORY  (1:1 with products)
-- Normalization: Separated because stock is a high-frequency
-- UPDATE hot-spot; avoids row-locking products on every sale.
-- Q1 WHERE product_id=?   -> UNIQUE(product_id)
-- Q2 WHERE quantity=0     -> idx_inventory_out_of_stock
-- =========================================================
CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL UNIQUE,
    quantity INT NOT NULL DEFAULT 0,
    reserved_quantity INT NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CHECK (quantity >= 0),
    CHECK (reserved_quantity >= 0),
    CHECK (reserved_quantity <= quantity),
    INDEX idx_inventory_out_of_stock (quantity, product_id)  -- Rule E
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 10. CART  (user <-> product junction with quantity)
-- Normalization: quantity is an attribute of the relationship.
-- Q1 WHERE user_id=?                    -> idx_cart_user
-- Q2 WHERE product_id=?                 -> idx_cart_product
-- Q3 WHERE user_id=? AND product_id=?   -> idx_cart_user_product
-- =========================================================
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    variant_name VARCHAR(100),
    variant_value VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CHECK (quantity > 0),
    INDEX idx_cart_user         (user_id),               -- Rule A
    INDEX idx_cart_product      (product_id),            -- Rule A
    INDEX idx_cart_user_product (user_id, product_id)    -- Rule E: existence check
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 11. WISHLIST  (pure junction: user <-> product)
-- Normalization: Composite UNIQUE enforces one row per pair.
-- Q1 WHERE user_id=?    -> UNIQUE KEY covers this
-- Q2 WHERE product_id=? -> idx_wishlists_product
-- =========================================================
CREATE TABLE wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_product (user_id, product_id),
    CONSTRAINT fk_wishlist_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_wishlists_product (product_id)  -- Rule A
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 12. COUPONS
-- Normalization: used_count is a denormalised counter for
-- performance (avoids COUNT(*) on orders at validation time).
-- Q1 WHERE code=?                     -> UNIQUE(code)  Rule D
-- Q2 WHERE store_id=?                 -> idx_coupons_store
-- Q3 WHERE is_active=1 AND expiry>NOW() -> idx_coupons_active
-- =========================================================
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT DEFAULT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    discount_type ENUM('percent','fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_order DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_discount DECIMAL(10,2) DEFAULT NULL,
    expiry_date DATETIME DEFAULT NULL,
    usage_limit INT DEFAULT NULL,
    used_count INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_coupons_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    CHECK (discount_value > 0),
    CHECK (min_order >= 0),
    CHECK (usage_limit IS NULL OR usage_limit > 0),
    CHECK (used_count >= 0),
    INDEX idx_coupons_store  (store_id),              -- Rule A
    INDEX idx_coupons_active (is_active, expiry_date) -- Rule E: coupon validation
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 13. ORDERS  (parent customer order)
-- Normalization: shipping_* columns = intentional
-- denormalisation for legal/audit snapshot. coupon_id is a FK
-- (not inline code). Financial totals stored for immutability.
-- Q1 WHERE order_number=?              -> UNIQUE(order_number)
-- Q2 WHERE user_id=?                   -> idx_orders_user
-- Q3 WHERE order_status=?              -> idx_orders_status
-- Q4 ORDER BY created_at DESC          -> idx_orders_created
-- Q5 WHERE user_id=? ORDER BY created_at -> idx_orders_user_time
-- Q6 Admin: status + time              -> idx_orders_status_time
-- =========================================================
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    coupon_id INT DEFAULT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    order_status ENUM('pending','confirmed','order_accepted','processing','packaged','ready_to_ship','partially_shipped','shipped','out_for_delivery','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
    shipping_name VARCHAR(100) NOT NULL,
    shipping_phone VARCHAR(20) NOT NULL,
    shipping_address TEXT NOT NULL,
    district VARCHAR(50) NOT NULL,
    division VARCHAR(50) NOT NULL,
    postal_code VARCHAR(10),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user   FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id)  ON DELETE SET NULL,
    CHECK (total_amount >= 0 AND shipping_cost >= 0 AND discount_amount >= 0 AND grand_total >= 0),
    INDEX idx_orders_user        (user_id),                    -- Rule A
    INDEX idx_orders_status      (order_status),               -- Rule B
    INDEX idx_orders_created     (created_at),                 -- Rule C
    INDEX idx_orders_user_time   (user_id, created_at),        -- Rule E: My Orders
    INDEX idx_orders_status_time (order_status, created_at)    -- Rule E: Admin dashboard
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 14. SELLER ORDERS  (per-store sub-orders)
-- Normalization: Without this table, per-seller financials
-- inside orders would need repeating groups (1NF violation).
-- Unique on (order_id, store_id).
-- Q1 WHERE order_id=?                     -> idx_seller_orders_order
-- Q2 WHERE store_id=? AND status=?        -> idx_seller_orders_store_status
-- Q3 WHERE assigned_rider_id=?            -> idx_seller_orders_rider
-- Q4 Seller dashboard newest              -> idx_seller_orders_store_time
-- =========================================================
CREATE TABLE seller_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    store_id INT NOT NULL,
    seller_order_number VARCHAR(40) NOT NULL UNIQUE,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    seller_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','confirmed','order_accepted','processing','packaged','ready_to_ship','shipped','out_for_delivery','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
    assigned_rider_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_order_store (order_id, store_id),
    CONSTRAINT fk_seller_orders_order FOREIGN KEY (order_id)  REFERENCES orders(id)  ON DELETE CASCADE,
    CONSTRAINT fk_seller_orders_store FOREIGN KEY (store_id)  REFERENCES stores(id)  ON DELETE RESTRICT,
    CHECK (subtotal >= 0 AND shipping_cost >= 0 AND discount_amount >= 0 AND seller_total >= 0),
    INDEX idx_seller_orders_order        (order_id),                    -- Rule A
    INDEX idx_seller_orders_store_status (store_id, status),            -- Rule B+E
    INDEX idx_seller_orders_rider        (assigned_rider_id, status),   -- Rule E: logistics
    INDEX idx_seller_orders_store_time   (store_id, created_at)         -- Rule E: dashboard
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 15. ORDER ITEMS  (line items within a seller sub-order)
-- Normalization: product_name & unit_price are snapshots
-- (intentional denormalisation — sold price/name immutable).
-- Q1 WHERE seller_order_id=?          -> idx_order_items_seller_order
-- Q2 WHERE product_id=?               -> idx_order_items_product
-- Q3 SUM(quantity) per product        -> idx_order_items_product_qty
-- =========================================================
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    variant_name VARCHAR(100),
    variant_value VARCHAR(100),
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_order_items_seller_order FOREIGN KEY (seller_order_id) REFERENCES seller_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product      FOREIGN KEY (product_id)      REFERENCES products(id)      ON DELETE RESTRICT,
    CHECK (quantity > 0),
    CHECK (unit_price >= 0 AND subtotal >= 0),
    INDEX idx_order_items_seller_order (seller_order_id),         -- Rule A
    INDEX idx_order_items_product      (product_id),              -- Rule A
    INDEX idx_order_items_product_qty  (product_id, quantity)     -- Rule E: sales analytics
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 16. PAYMENTS  (1:1 with orders)
-- Normalization: Payment attrs belong to payment entity.
-- Merging into orders would create transitive dependency
-- order_id -> transaction_reference -> payment_status (3NF violation).
-- Q1 WHERE order_id=?                -> UNIQUE(order_id)
-- Q2 WHERE transaction_reference=?   -> idx_payment_reference
-- Q3 WHERE status='pending'          -> idx_payment_status
-- =========================================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    method ENUM('cash_on_delivery','bkash','nagad','card','bank_transfer') NOT NULL,
    transaction_reference VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','submitted','verified','failed','refunded') NOT NULL DEFAULT 'pending',
    paid_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CHECK (amount >= 0),
    INDEX idx_payment_reference (transaction_reference),   -- Rule D
    INDEX idx_payment_status    (status, created_at)       -- Rule B+C
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 17. RIDERS  (1:1 extension of users for logistics role)
-- Normalization: Separated to avoid NULL columns in users
-- for non-riders — keeps users in 3NF.
-- Q1 WHERE user_id=?      -> UNIQUE(user_id)
-- Q2 WHERE status='active' -> idx_riders_status
-- =========================================================
CREATE TABLE riders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    status ENUM('active','offline','busy','suspended') NOT NULL DEFAULT 'active',
    CONSTRAINT fk_riders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_riders_status (status)  -- Rule B
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 18. DELIVERY TRACKING  (append-only event log)
-- Normalization: Each row is one status-change event.
-- rider_id and updated_by are separate FKs (updater may
-- differ from rider). No GPS — manual tracking by design.
-- Q1 WHERE seller_order_id=? ORDER BY created_at -> idx_delivery_order_time
-- Q2 WHERE rider_id=?                            -> idx_delivery_rider
-- Q3 WHERE status='in_transit'                   -> idx_delivery_status
-- =========================================================
CREATE TABLE delivery_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_order_id INT NOT NULL,
    rider_id INT DEFAULT NULL,
    status ENUM('order_placed','processing','picked_up','in_transit','out_for_delivery','delivered','failed','returned') NOT NULL,
    location VARCHAR(150),
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    note TEXT,
    updated_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_delivery_seller_order FOREIGN KEY (seller_order_id) REFERENCES seller_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_delivery_rider        FOREIGN KEY (rider_id)        REFERENCES riders(id)        ON DELETE SET NULL,
    CONSTRAINT fk_delivery_updated_by   FOREIGN KEY (updated_by)      REFERENCES users(id)         ON DELETE SET NULL,
    INDEX idx_delivery_order_time (seller_order_id, created_at),  -- Rule C: timeline
    INDEX idx_delivery_rider      (rider_id, created_at),         -- Rule A: workload
    INDEX idx_delivery_status     (status, created_at)            -- Rule B: admin filter
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 19. PRODUCT REVIEWS
-- Normalization: 1:N with products; UNIQUE enforces one
-- review per (user, product). order_item_id links to
-- verified purchase without embedding order data (3NF).
-- Q1 WHERE product_id=?   -> idx_reviews_product
-- Q2 WHERE user_id=?      -> idx_reviews_user
-- Q3 AVG(rating) per prod -> idx_reviews_product_rating
-- =========================================================
CREATE TABLE product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    order_item_id INT DEFAULT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_product    FOREIGN KEY (product_id)    REFERENCES products(id)    ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user       FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
    CONSTRAINT fk_reviews_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE SET NULL,
    CHECK (rating BETWEEN 1 AND 5),
    UNIQUE KEY unique_user_product_review (user_id, product_id),
    INDEX idx_reviews_product        (product_id),          -- Rule A
    INDEX idx_reviews_user           (user_id),             -- Rule A
    INDEX idx_reviews_product_rating (product_id, rating)   -- Rule E: AVG(rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 20. MESSAGES
-- Normalization: sender_role / receiver_role are ENUM columns
-- (bounded set). Denormalising role avoids a JOIN to users on
-- every read — justified given bounded role set. order_id is
-- optional FK for order-linked threads.
-- Q1 Inbox: WHERE receiver_id=? AND is_read=0 -> idx_messages_receiver_unread
-- Q2 Thread: WHERE sender_id=A AND receiver_id=B -> idx_messages_conversation
-- Q3 Order thread: WHERE order_id=?           -> idx_messages_order
-- =========================================================
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT DEFAULT NULL,
    sender_id INT NOT NULL,
    sender_role ENUM('customer','seller','admin','logistics') NOT NULL,
    receiver_id INT NOT NULL,
    receiver_role ENUM('customer','seller','admin','logistics') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_message_participants CHECK (sender_id <> receiver_id),
    CONSTRAINT fk_messages_order    FOREIGN KEY (order_id)    REFERENCES orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_messages_sender   FOREIGN KEY (sender_id)   REFERENCES users(id)  ON DELETE CASCADE,
    CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id)  ON DELETE CASCADE,
    INDEX idx_messages_conversation    (sender_id, receiver_id, created_at),    -- Rule E: thread
    INDEX idx_messages_receiver_unread (receiver_id, is_read, created_at),      -- Rule E: inbox
    INDEX idx_messages_order           (order_id)                               -- Rule A
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 21. NOTIFICATIONS
-- Normalization: Per-user event rows; title/message/type are
-- atomic. type is VARCHAR (not ENUM) to allow app-defined
-- types without schema migration — deliberate flexibility.
-- Q1 WHERE user_id=? AND is_read=0 ORDER BY created_at
--                                  -> idx_notifications_user_read
-- Q2 WHERE user_id=? AND type=?    -> idx_notifications_user_type
-- Q3 COUNT(*) unread (bell badge)  -> covered by Q1 index
-- =========================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT DEFAULT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(30) DEFAULT 'general',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    CONSTRAINT fk_notifications_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_notifications_user_read (user_id, is_read, created_at),    -- Rule C+E
    INDEX idx_notifications_user_type (user_id, type, created_at)        -- Rule E
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- 22. HAVERSINE DISTANCE FUNCTION (Option 2)
-- Calculates approximate geographic distance in km
-- between two (lat, lon) coordinates
-- =========================================================

DELIMITER $$

DROP FUNCTION IF EXISTS haversine_distance $$

CREATE FUNCTION haversine_distance(
    lat1 DECIMAL(10, 8),
    lon1 DECIMAL(11, 8),
    lat2 DECIMAL(10, 8),
    lon2 DECIMAL(11, 8)
)
RETURNS DECIMAL(10, 2)
DETERMINISTIC
BEGIN
    DECLARE earth_radius DECIMAL(10, 2) DEFAULT 6371.00;
    DECLARE dlat DOUBLE;
    DECLARE dlon DOUBLE;
    DECLARE a DOUBLE;
    DECLARE c DOUBLE;
    DECLARE distance DECIMAL(10, 2);

    IF lat1 IS NULL OR lon1 IS NULL OR lat2 IS NULL OR lon2 IS NULL THEN
        RETURN NULL;
    END IF;

    SET dlat = RADIANS(lat2 - lat1);
    SET dlon = RADIANS(lon2 - lon1);

    SET a = SIN(dlat / 2) * SIN(dlat / 2) +
            COS(RADIANS(lat1)) * COS(RADIANS(lat2)) *
            SIN(dlon / 2) * SIN(dlon / 2);

    SET c = 2 * ATAN2(SQRT(a), SQRT(1 - a));
    SET distance = earth_radius * c;

    RETURN distance;
END $$

DELIMITER ;
