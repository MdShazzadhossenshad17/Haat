-- ================================================================
-- HAAT — Global • Regional • Artisanal
-- Bangladeshi Multi-Vendor E-Commerce Platform
-- Complete Database Schema + Seed Data
-- ================================================================
-- 
-- HOW TO USE:
-- 1. Open phpMyAdmin or MySQL CLI
-- 2. Import this entire file OR run:
--    mysql -u root -p < db.sql
-- 3. This will create the database `haat_db` and all tables with
--    sample data ready for testing.
-- ================================================================

-- Create Database
CREATE DATABASE IF NOT EXISTS `haat_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `haat_db`;

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;


-- ================================================================
-- 1. USERS TABLE
-- ================================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `role` VARCHAR(30) DEFAULT 'customer',
    `avatar` VARCHAR(255) DEFAULT 'assets/images/default-avatar.png',
    `status` VARCHAR(30) DEFAULT 'active',
    `is_online` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 2. SELLERS (VENDORS) TABLE
-- ================================================================
DROP TABLE IF EXISTS `sellers`;
CREATE TABLE `sellers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `shop_name` VARCHAR(150) NOT NULL,
    `shop_slug` VARCHAR(160) NOT NULL UNIQUE,
    `shop_logo` VARCHAR(255) DEFAULT 'assets/images/default-shop.png',
    `shop_banner` VARCHAR(255) DEFAULT 'assets/images/default-banner.jpg',
    `description` TEXT DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` VARCHAR(255) DEFAULT NULL,
    `district` VARCHAR(50) DEFAULT 'Dhaka',
    `division` VARCHAR(50) DEFAULT 'Dhaka',
    `nid_number` VARCHAR(50) DEFAULT NULL,
    `trade_license` VARCHAR(50) DEFAULT NULL,
    `bank_name` VARCHAR(100) DEFAULT NULL,
    `bank_account_no` VARCHAR(50) DEFAULT NULL,
    `bkash_number` VARCHAR(20) DEFAULT NULL,
    `commission_rate` DECIMAL(5,2) DEFAULT 5.00,
    `is_verified` TINYINT(1) DEFAULT 1,
    `status` VARCHAR(30) DEFAULT 'active',
    `rating` DECIMAL(3,2) DEFAULT 4.80,
    `total_sales` DECIMAL(12,2) DEFAULT 0.00,
    `is_online` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 3. CATEGORIES TABLE (with department column)
-- ================================================================
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `name_bn` VARCHAR(100) DEFAULT NULL,
    `department` VARCHAR(50) DEFAULT 'Clothes',
    `slug` VARCHAR(110) NOT NULL UNIQUE,
    `icon` VARCHAR(50) DEFAULT 'bi-tag',
    `image` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `is_featured` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 4. BRANDS TABLE
-- ================================================================
DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(110) NOT NULL UNIQUE,
    `logo` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 5. PRODUCTS TABLE (with keywords & district_origin)
-- ================================================================
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `seller_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `brand_id` INT DEFAULT NULL,
    `name` VARCHAR(200) NOT NULL,
    `name_bn` VARCHAR(200) DEFAULT NULL,
    `slug` VARCHAR(220) NOT NULL UNIQUE,
    `sku` VARCHAR(50) DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `sale_price` DECIMAL(10,2) DEFAULT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 10,
    `unit` VARCHAR(20) DEFAULT 'piece',
    `short_description` TEXT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `featured_image` VARCHAR(255) NOT NULL,
    `gallery_images` TEXT DEFAULT NULL,
    `district_origin` VARCHAR(50) DEFAULT 'Dhaka',
    `keywords` TEXT DEFAULT NULL,
    `is_featured` TINYINT(1) DEFAULT 0,
    `is_flash_deal` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `views` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`seller_id`) REFERENCES `sellers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 6. PRODUCT REVIEWS TABLE
-- ================================================================
DROP TABLE IF EXISTS `product_reviews`;
CREATE TABLE `product_reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `rating` INT NOT NULL DEFAULT 5,
    `comment` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 7. ORDERS TABLE (with courier tracking columns)
-- ================================================================
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(30) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `shipping_cost` DECIMAL(10,2) DEFAULT 60.00,
    `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
    `grand_total` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(30) NOT NULL DEFAULT 'cod',
    `payment_status` VARCHAR(30) DEFAULT 'unpaid',
    `transaction_id` VARCHAR(100) DEFAULT NULL,
    `order_status` VARCHAR(30) DEFAULT 'pending',
    `shipping_name` VARCHAR(100) NOT NULL,
    `shipping_phone` VARCHAR(20) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `district` VARCHAR(50) NOT NULL DEFAULT 'Dhaka',
    `division` VARCHAR(50) NOT NULL DEFAULT 'Dhaka',
    `notes` TEXT DEFAULT NULL,
    `courier_partner` VARCHAR(100) DEFAULT 'Pathao Courier',
    `tracking_code` VARCHAR(100) DEFAULT NULL,
    `estimated_delivery` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 8. ORDER ITEMS TABLE
-- ================================================================
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `seller_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `vendor_status` VARCHAR(30) DEFAULT 'pending',
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`seller_id`) REFERENCES `sellers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 9. ORDER TRACKING EVENTS TABLE
-- ================================================================
DROP TABLE IF EXISTS `order_tracking_events`;
CREATE TABLE `order_tracking_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `order_number` VARCHAR(100) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `actor` VARCHAR(150) NOT NULL,
    `location` VARCHAR(150) NULL,
    `status_key` VARCHAR(50) NOT NULL,
    `note` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (`order_id`),
    INDEX (`order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 10. WISHLIST TABLE
-- ================================================================
DROP TABLE IF EXISTS `wishlists`;
CREATE TABLE `wishlists` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `user_prod_unique` (`user_id`, `product_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 11. COUPONS TABLE
-- ================================================================
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `seller_id` INT DEFAULT NULL,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `title` VARCHAR(150) DEFAULT NULL,
    `discount_type` VARCHAR(20) DEFAULT 'percent',
    `discount_value` DECIMAL(10,2) DEFAULT 10.00,
    `discount_percent` INT NOT NULL DEFAULT 10,
    `min_order` DECIMAL(10,2) DEFAULT 500.00,
    `max_discount` DECIMAL(10,2) DEFAULT 500.00,
    `expiry_date` DATE DEFAULT NULL,
    `usage_count` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`seller_id`) REFERENCES `sellers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 12. MESSAGES TABLE (Live Chat)
-- ================================================================
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT DEFAULT NULL,
    `sender_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `seller_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`seller_id`) REFERENCES `sellers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- 13. NOTIFICATIONS TABLE (Artisan Broadcasts)
-- ================================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `seller_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) DEFAULT 'general',
    `link` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;


-- ================================================================
-- ================================================================
--                  SEED DATA — DEMO ACCOUNTS
-- ================================================================
-- ================================================================
-- 
-- LOGIN CREDENTIALS:
-- +-----------------------------+------------------------+---------------+
-- | Role                        | Email                  | Password      |
-- +-----------------------------+------------------------+---------------+
-- | Admin                       | admin@haat.com.bd      | Admin@123     |
-- | Seller (Jamdani)            | jamdani@haat.com.bd    | Seller@123    |
-- | Seller (Pottery)            | pottery@haat.com.bd    | Seller@123    |
-- | Seller (Organics)           | honey@haat.com.bd      | Seller@123    |
-- | Customer                    | customer@haat.com.bd   | Customer@123  |
-- +-----------------------------+------------------------+---------------+
-- 
-- NOTE: The password hashes below are generated by PHP password_hash().
--       They ONLY work when verified via password_verify() in PHP.
--       To regenerate correct hashes, run: php config/init_db.php
-- ================================================================


-- ================================================================
-- SEED: Users
-- ================================================================
INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `role`, `status`, `is_online`) VALUES
(1, 'HAAT Chief Administrator',         'admin@haat.com.bd',     '$2y$10$C3Rwm4F9KEMTzL.y4No4peX8Gv6jsAv76CY/P6kGf0sTAKNlKknla', '01711000001', 'admin',    'active', 1),
(2, 'Al-Amin Mia (Master Weaver)',      'jamdani@haat.com.bd',   '$2y$10$hcvMvBi.fDy5kMd0BwUXaunE14pQ2HenvwTv5H4xFn/itzr90SiEK', '01811223344', 'seller',   'active', 1),
(3, 'Gouranga Pal (Master Potter)',      'pottery@haat.com.bd',   '$2y$10$hcvMvBi.fDy5kMd0BwUXaunE14pQ2HenvwTv5H4xFn/itzr90SiEK', '01911334455', 'seller',   'active', 1),
(4, 'Mizanur Rahman (Honey Harvester)', 'honey@haat.com.bd',     '$2y$10$hcvMvBi.fDy5kMd0BwUXaunE14pQ2HenvwTv5H4xFn/itzr90SiEK', '01611445566', 'seller',   'active', 1),
(5, 'Tanvir Hossain',                   'customer@haat.com.bd',  '$2y$10$GxpFfxDlSPdoVmT5OlvAguz5aN/HahNsPP6s45yeVcriUV4MObjmC', '01511556677', 'customer', 'active', 0);

-- Password hashes generated by PHP password_hash() — ready for direct import.


-- ================================================================
-- SEED: Seller Profiles
-- ================================================================
INSERT INTO `sellers` (`id`, `user_id`, `shop_name`, `shop_slug`, `description`, `phone`, `address`, `district`, `division`, `bkash_number`, `is_verified`, `rating`, `total_sales`, `is_online`) VALUES
(1, 2, 'Sonargaon Jamdani Kutir', 'sonargaon-jamdani-kutir',
 'Authentic handloom Jamdani & Tangail cotton sarees crafted by generations of traditional Bangladeshi weavers using pure organic yarn.',
 '01811223344', 'Rupganj, Sonargaon', 'Narayanganj', 'Dhaka', '01811223344', 1, 4.95, 185000.00, 1),

(2, 3, 'Bijoypur Terracotta & Clay Arts', 'bijoypur-terracotta-arts',
 'Heritage terracotta pottery, clay kitchenware, decorative home pieces, and water pitchers molded by Cumilla traditional artisans.',
 '01911334455', 'Bijoypur Pottery Village', 'Cumilla', 'Chittagong', '01911334455', 1, 4.85, 92000.00, 1),

(3, 4, 'Sundarbans Wild Organics & Pure Honey', 'sundarbans-wild-organics',
 'Raw Khalisha & Goran flower deep mangrove forest honey, cold-pressed mustard oil, and naturally sourced mangrove sea salt from Satkhira.',
 '01611445566', 'Shyamnagar, Sundarbans Rim', 'Satkhira', 'Khulna', '01611445566', 1, 4.90, 142000.00, 1);


-- ================================================================
-- SEED: 15 Categories (3 Departments)
-- ================================================================
INSERT INTO `categories` (`id`, `department`, `name`, `name_bn`, `slug`, `icon`, `image`, `description`, `is_featured`) VALUES
-- CLOTHES (5)
(1,  'Clothes',            'Men''s Modern & Casuals',        'পুরুষদের আধুনিক ও ক্যাজুয়াল পোশাক',       'mens-casuals',       'bi-person-standing', 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=500&auto=format&fit=crop&q=80', 'Graphic T-shirts, premium polos, denim jeans, casual shirts, and jackets.', 1),
(2,  'Clothes',            'Men''s Ethnic & Festive',        'পুরুষদের ঐতিহ্যবাহী ও উৎসবের পোশাক',       'mens-ethnic',        'bi-person-badge',    'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=500&auto=format&fit=crop&q=80', 'Handcrafted silk & cotton panjabis, kabli suits, kurtas, and traditional lungi.', 1),
(3,  'Clothes',            'Women''s Western & Fusion',      'নারীদের ওয়েস্টার্ন ও ফিউশন পোশাক',         'womens-western',     'bi-person-hearts',   'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=500&auto=format&fit=crop&q=80', 'Trendy tops, floral maxi dresses, denim trousers, cardigans, and fusion wear.', 1),
(4,  'Clothes',            'Traditional Sarees & Ethnic',    'ঐতিহ্যবাহী শাড়ি ও নকশি পোশাক',             'sarees-ethnic',      'bi-stars',           'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=500&auto=format&fit=crop&q=80', 'Handwoven Dhakai Jamdani, Rajshahi silk sarees, Tangail taant, and three-pieces.', 1),
(5,  'Clothes',            'Kids & Baby Fashion',            'শিশুদের পোশাক ও ফ্যাশন',                     'kids-baby',          'bi-emoji-smile',     'https://images.unsplash.com/photo-1622290291468-a28f7a7dc6a8?w=500&auto=format&fit=crop&q=80', 'Soft cotton rompers, boys graphic tees & panjabis, girls festive frocks.', 1),

-- FOOD (5)
(6,  'Food',               'Pantry Staples & Pure Organics', 'খাঁটি অর্গানিক ও নিত্যপ্রয়োজনীয় খাবার',    'pantry-organics',    'bi-flower1',         'https://images.unsplash.com/photo-1589301760014-d929f3979dbc?w=500&auto=format&fit=crop&q=80', 'Raw Sundarbans honey, cold-pressed wood-ghani mustard oil, cow ghee, date jaggery.', 1),
(7,  'Food',               'Tea, Coffee & Beverages',        'চা, কফি ও পানীয়',                           'tea-coffee-beverages','bi-cup-hot',         'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=500&auto=format&fit=crop&q=80', 'Sylhet organic black tea, green tea, artisanal roasted coffee beans & espresso blends.', 1),
(8,  'Food',               'Snacks, Cookies & Chocolates',   'স্ন্যাকস, বিস্কুট ও চকলেট',                  'snacks-confectionery','bi-basket2',         'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=500&auto=format&fit=crop&q=80', 'Bakery butter cookies, dark chocolate bars, gourmet chips, and crunchy snacks.', 1),
(9,  'Food',               'Traditional Delicacies & Sweets','আঞ্চলিক মিষ্টান্ন ও ঐতিহ্যবাহী খাবার',      'traditional-sweets', 'bi-cake2',           'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?w=500&auto=format&fit=crop&q=80', 'Kushtia tiler khaja, homemade coconut naru, batasa, and sun-dried kumro bori.', 1),
(10, 'Food',               'Dry Fruits, Nuts & Spices',      'ড্রাই ফ্রুটস, বাদাম ও খাঁটি মশলা',           'dryfruits-spices',   'bi-egg-fried',       'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=500&auto=format&fit=crop&q=80', 'Almonds, cashews, pistachios, dates, homemade spicy mango pickles, and whole spices.', 1),

-- ART & ACCESSORIES (5)
(11, 'Art & Accessories',  'Bags, Wallets & Leather',        'ব্যাগ, ওয়ালেট ও লেদার সামগ্রী',             'bags-wallets-leather','bi-bag-check',       'https://images.unsplash.com/photo-1627123424574-724758594e93?w=500&auto=format&fit=crop&q=80', 'Full-grain leather wallets, canvas backpacks, stylish clutches, and eco jute totes.', 1),
(12, 'Art & Accessories',  'Watches & Eyewear',              'ঘড়ি ও সানগ্লাস',                            'watches-eyewear',    'bi-watch',           'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=500&auto=format&fit=crop&q=80', 'Classic minimalist analog watches, chronographs, polarized sunglasses, and UV frames.', 1),
(13, 'Art & Accessories',  'Jewelry & Personal Styling',     'হ্যান্ডমেড ও মডার্ন জুয়েলারি',               'jewelry-styling',    'bi-gem',             'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=500&auto=format&fit=crop&q=80', 'Minimalist silver chains, handcrafted terracotta earrings, brass choker necklaces.', 1),
(14, 'Art & Accessories',  'Wall Art, Paintings & Prints',   'দেয়াল শিল্প, পেইন্টিং ও ফ্রেম',              'wall-art-paintings',  'bi-palette',        'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?w=500&auto=format&fit=crop&q=80', 'Modern canvas abstracts, vibrant Dhaka rickshaw pop-art, and folk wall hangings.', 1),
(15, 'Art & Accessories',  'Home Decor, Living & Ceramics',  'হোম ডেকোর ও সিরামিক ক্রাফট',                 'home-decor-ceramics','bi-house-heart',     'https://images.unsplash.com/photo-1578749556568-bc2c40e68b61?w=500&auto=format&fit=crop&q=80', 'Terracotta planters, ceramic coffee mugs, woven jute floor rugs, and bamboo accents.', 1);


-- ================================================================
-- SEED: Brands
-- ================================================================
INSERT INTO `brands` (`id`, `name`, `slug`) VALUES
(1, 'Heritage Sonargaon',          'heritage-sonargaon'),
(2, 'Bijoypur Clay Guild',         'bijoypur-clay-guild'),
(3, 'Sundarbans Honey Collective', 'sundarbans-honey-collective'),
(4, 'Tangail Tanti Shamiti',       'tangail-tanti-shamiti');


-- ================================================================
-- SEED: 15 Products (Across all 15 categories)
-- ================================================================
INSERT INTO `products` (`seller_id`, `category_id`, `name`, `name_bn`, `slug`, `price`, `sale_price`, `stock_quantity`, `unit`, `short_description`, `description`, `featured_image`, `district_origin`, `is_featured`, `is_flash_deal`, `is_active`) VALUES

-- CLOTHES (5 products)
(1, 1, 'Premium Heavyweight Cotton Crewneck T-Shirt (Midnight Navy)',
 'প্রিমিয়াম কটন ক্রু-নেক টি-শার্ট (নেভি ব্লু)',
 'premium-cotton-crewneck-tshirt-navy',
 750.00, 650.00, 25, 'piece',
 '100% combed breathable cotton with modern tailored fit, anti-shrink pre-wash.',
 'Crafted for daily effortless style, this heavy-weight combed cotton tee offers supreme breathability, durable double-needle stitching, and a clean minimalist aesthetic.',
 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80',
 'Dhaka', 1, 1, 1),

(1, 2, 'Handcrafted Silk-Cotton Festive Panjabi with Zari Work',
 'হাতে তৈরি জরি কাজের সিল্ক-কটন পাঞ্জাবি',
 'silk-cotton-festive-panjabi-zari',
 3800.00, 3400.00, 25, 'piece',
 'Traditional tailored cut with subtle neck and cuff hand embroidery for celebrations.',
 'An ode to classic celebratory attire. Blended mulberry silk and cotton fabric ensures cool comfort during long festive hours with hand-detailed Zari motifs on the placket.',
 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=600&auto=format&fit=crop&q=80',
 'Rajshahi', 1, 0, 1),

(1, 3, 'Bohemian Tiered Maxi Dress with Floral Print',
 'বোহেমিয়ান ফ্লোরাল টিয়ার্ড ম্যাক্সি ড্রেস',
 'bohemian-tiered-maxi-dress-floral',
 2450.00, 2100.00, 25, 'piece',
 'Airy breathable georgette blend with flowing silhouette and subtle belt accent.',
 'Effortlessly feminine and universally flattering. Features delicate botanical prints, gentle tiered ruffles, and a soft inner lining perfect for warm-weather outings.',
 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=600&auto=format&fit=crop&q=80',
 'Dhaka', 1, 0, 1),

(1, 4, 'Original Sonargaon Handwoven Royal Dhakai Jamdani Saree (84 Count)',
 'আসল সোনারগাঁও রয়েল ঢাকাই জামদানি শাড়ি (৮৪ কাউন্ট)',
 'original-sonargaon-royal-dhakai-jamdani-saree',
 16500.00, 14500.00, 25, 'piece',
 'UNESCO-recognized master handwoven Jamdani with intricate floral jaal motifs.',
 'Woven by certified hereditary master artisans of Sonargaon over 45 painstaking days. Made from 84 count fine cotton yarn with traditional floral jaal geometric motifs in ivory and gold thread.',
 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=600&auto=format&fit=crop&q=80',
 'Narayanganj', 1, 1, 1),

(1, 5, 'Organic Soft Handloom Cotton Baby Romper & Cap Set',
 'অর্গানিক সফট হ্যান্ডলুম কটন বেবি রম্পার ও ক্যাপ',
 'organic-cotton-baby-romper-cap',
 890.00, 750.00, 25, 'set',
 'Chemical-free natural dyed breathable cotton, snap buttons for easy dressing.',
 'Gentle on delicate baby skin. Hand-stitched with ultra-soft unbleached organic cotton for cozy naps and playful days.',
 'https://images.unsplash.com/photo-1622290291468-a28f7a7dc6a8?w=600&auto=format&fit=crop&q=80',
 'Cumilla', 0, 0, 1),

-- FOOD (6 products)
(3, 6, 'Pure Raw Sundarbans Wild Mangrove Honey (Khalisha Flower 1000g)',
 'খাঁটি সুন্দরবনের খলিসা ফুলের মধু (১০০০ গ্রাম)',
 'pure-sundarbans-wild-honey-1000g',
 1950.00, 1750.00, 25, 'jar',
 '100% raw unpasteurized wild honeycomb nectar hand-harvested by traditional Mawalis.',
 'Collected deep within the mangrove forests of the Sundarbans during peak Khalisha blossom season. Naturally antibiotic, rich in pollen enzymes, and free from any added syrup.',
 'https://images.unsplash.com/photo-1587049352846-4a222e784d38?w=600&auto=format&fit=crop&q=80',
 'Satkhira', 1, 1, 1),

(3, 6, 'Cold-Pressed Traditional Wooden Ghani Mustard Oil (2 Liters)',
 'কাঠের ঘানিতে ভাঙা খাঁটি সরিষার তেল (২ লিটার)',
 'cold-pressed-wooden-ghani-mustard-oil-2l',
 850.00, 780.00, 25, 'bottle',
 'Extracted slowly on wooden ghani without heat to preserve natural pungency and pungin.',
 'Real village flavor. Sourced from local Maghi mustard seeds, cold-pressed at low temperatures to ensure authentic pungent aroma and heart-healthy antioxidants.',
 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=600&auto=format&fit=crop&q=80',
 'Kushtia', 1, 0, 1),

(3, 7, 'Sreemangal Single-Estate Whole Leaf Organic Black Tea (400g Tin)',
 'শ্রীমঙ্গল সিঙ্গেল-এস্টেট ব্ল্যাক টি (৪০০ গ্রাম)',
 'sreemangal-single-estate-black-tea',
 650.00, 580.00, 25, 'tin',
 'First-flush high-grown whole tea leaves with rich amber liquor and muscatel notes.',
 'Handpicked from the lush misty slopes of Sreemangal, the tea capital of Bangladesh. Delivers an invigorating cup with bright clarity, rich body, and gentle sweetness.',
 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80',
 'Moulvibazar', 1, 0, 1),

(3, 8, 'Artisanal 70% Dark Chocolate Bar with Sea Salt & Roasted Almonds',
 'আর্টিসানাল ৭০% ডার্ক চকলেট বার উইথ সি-সল্ট ও আমন্ড',
 'artisanal-dark-chocolate-sea-salt-almonds',
 420.00, 380.00, 25, 'piece',
 'Small-batch bean-to-bar chocolate crafted with organic cocoa and crunchy nuts.',
 'Velvety smooth, deeply satisfying dark chocolate balanced with a touch of mineral sea salt crystals and slow-roasted Californian almonds.',
 'https://images.unsplash.com/photo-1548907040-4baa42d10919?w=600&auto=format&fit=crop&q=80',
 'Dhaka', 0, 1, 1),

(3, 9, 'Kushtia Heritage Crispy Sesame Tiler Khaja (500g Gift Box)',
 'কুষ্টিয়ার ঐতিহ্যবাহী মুচমুচে তিলের খাজা (৫০০ গ্রাম)',
 'kushtia-crispy-tiler-khaja-500g',
 380.00, 320.00, 25, 'box',
 'Authentic paper-thin layered sesame wafers infused with pure sugarcane molasses.',
 'A century-old confection from Kushtia. Incredibly crisp, loaded with toasted white sesame seeds and hand-pulled molasses that melts in the mouth.',
 'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?w=600&auto=format&fit=crop&q=80',
 'Kushtia', 1, 0, 1),

(3, 10, 'Premium Roasted Jumbo Cashew Nuts & California Almonds Mix (500g)',
 'প্রিমিয়াম রোস্টেড কাজু ও কাঠবাদাম মিক্স (৫০০ গ্রাম)',
 'premium-roasted-cashew-almond-mix-500g',
 980.00, 890.00, 25, 'jar',
 'Lightly salted slow-roasted whole nuts packed in airtight reusable glass jar.',
 'Healthy daily energy boost. Premium W240 whole cashews and non-pareil almonds, oven-roasted to golden crunchiness without hydrogenated oils.',
 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=600&auto=format&fit=crop&q=80',
 'Chattogram', 0, 0, 1),

-- ART & ACCESSORIES (4 products)
(2, 11, 'Hand-Stitched Full-Grain Leather Bi-Fold Wallet (Vintage Tan)',
 'হাতে সেলাই করা জেনুইন লেদার ওয়ালেট (ট্যান)',
 'hand-stitched-leather-bifold-wallet-tan',
 1650.00, 1450.00, 25, 'piece',
 '100% genuine Bangladeshi vegetable-tanned cowhide with RFID blocking protection.',
 'Built to age with an elegant patina. Features 8 card slots, dual cash compartments, and heavy waxed thread hand-stitching guaranteed for years of rugged use.',
 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=600&auto=format&fit=crop&q=80',
 'Dhaka', 1, 1, 1),

(2, 12, 'Minimalist Bauhaus Slim Sapphire Watch with Leather Strap',
 'মিনিমালিস্ট স্লিম অ্যানালগ ঘড়ি (লেদার স্ট্র্যাপ)',
 'minimalist-bauhaus-slim-watch-leather',
 3200.00, 2850.00, 25, 'piece',
 'Ultra-thin surgical stainless steel case with Japanese quartz movement and scratch-resistant glass.',
 'Refined modern simplicity. Crisp white dial with ultra-slim hour markers, water-resistant to 30 meters, accompanied by genuine calfskin strap.',
 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=600&auto=format&fit=crop&q=80',
 'Dhaka', 1, 0, 1),

(2, 13, 'Bijoypur Hand-Painted Terracotta Choker & Earring Set',
 'বিজয়পুর হাতে আঁকা পোড়ামাটির চোকার ও কানের দুল',
 'bijoypur-handpainted-terracotta-jewelry-set',
 1100.00, 950.00, 25, 'set',
 'Baked red clay beads with ethnic folk motifs and adjustable braided cord.',
 'Created by female pottery artisans in Cumilla. Lightweight kiln-fired natural clay hand-painted in traditional folk colors, sealed with matte protective coating.',
 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&auto=format&fit=crop&q=80',
 'Cumilla', 1, 0, 1),

(2, 14, 'Framed Pop-Art Dhaka Rickshaw Painting Canvas (Peacock & Floral Motif)',
 'ফ্রেমড পপ-আর্ট ঢাকা রিকশা পেইন্টিং (ময়ূর ও ফুল)',
 'framed-dhaka-rickshaw-painting-canvas-peacock',
 2400.00, 2100.00, 25, 'piece',
 'Hand-painted enamel by authentic old Dhaka Ustad rickshaw artists on solid wooden frame.',
 'UNESCO-recognized heritage rickshaw art. Features the iconic vivid neon peacock and floral motifs with glossy lacquer finish, ready to hang and liven up any modern living room.',
 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?w=600&auto=format&fit=crop&q=80',
 'Dhaka', 1, 0, 1),

(2, 15, 'Artisanal Braided Golden Jute Area Floor Rug (4ft Round)',
 'হাতে বোনা সোনালী পাটের ফ্লোর রাগ (৪ ফুট গোলাকার)',
 'braided-golden-jute-round-rug-4ft',
 2800.00, 2450.00, 25, 'piece',
 '100% natural biodegradable golden jute fiber tightly hand-braided for modern boho homes.',
 'Eco-friendly and durable. Handcrafted in Faridpur by village craft clusters, this circular rug adds warm natural texture, rustic warmth, and timeless charm to bedrooms or living spaces.',
 'https://images.unsplash.com/photo-1578749556568-bc2c40e68b61?w=600&auto=format&fit=crop&q=80',
 'Faridpur', 1, 1, 1);


-- ================================================================
-- SEED: Sample Product Reviews
-- ================================================================
INSERT INTO `product_reviews` (`product_id`, `user_id`, `rating`, `comment`) VALUES
(4, 5, 5, 'The Jamdani saree exceeded my expectations! The craftsmanship from Sonargaon is stunning, and the fabric is so soft. Delivered to Dhanmondi in 2 days.'),
(6, 5, 5, 'Pure Sundarbans honey with that distinct aroma. Verified by crystallization and heat test. 100% authentic Haat product!'),
(1, 5, 5, 'Excellent quality cotton T-shirt. The fit is perfect and the fabric breathes well in Dhaka heat.');


-- ================================================================
-- SEED: Sample Order
-- ================================================================
INSERT INTO `orders` (`id`, `order_number`, `user_id`, `total_amount`, `shipping_cost`, `discount_amount`, `grand_total`, `payment_method`, `payment_status`, `transaction_id`, `order_status`, `shipping_name`, `shipping_phone`, `shipping_address`, `district`, `division`, `courier_partner`, `tracking_code`, `estimated_delivery`) VALUES
(1, 'HAAT-2026-90412', 5, 14550.00, 100.00, 500.00, 14150.00, 'bkash', 'paid', 'BK9A72189X', 'shipped', 'Tanvir Hossain', '01511556677', 'House 42, Road 11, Banani', 'Dhaka', 'Dhaka', 'Pathao Courier', 'PTH-8849201', '25-27 Sep 2026');


-- ================================================================
-- SEED: Order Items
-- ================================================================
INSERT INTO `order_items` (`order_id`, `seller_id`, `product_id`, `product_name`, `price`, `quantity`, `subtotal`, `vendor_status`) VALUES
(1, 1, 4, 'Original Sonargaon Handwoven Royal Dhakai Jamdani Saree (84 Count)', 14500.00, 1, 14500.00, 'processing'),
(1, 3, 6, 'Pure Raw Sundarbans Wild Mangrove Honey (Khalisha Flower 1000g)', 1750.00, 1, 1750.00, 'processing');


-- ================================================================
-- SEED: Order Tracking Events
-- ================================================================
INSERT INTO `order_tracking_events` (`order_id`, `order_number`, `title`, `actor`, `location`, `status_key`, `note`, `created_at`) VALUES
(1, 'HAAT-2026-90412', 'Order Placed & Payment Confirmed', 'HAAT Customer Checkout', 'Online (Dhaka)', 'pending',
 'Payment verified via bKash Merchant Gateway (Trx ID: BKASH-98234120). Order dispatched to respective artisan workshops.',
 '2026-09-23 20:30:00'),

(1, 'HAAT-2026-90412', 'Artisan Crafting & Workshop Preparation', 'Sonargaon Jamdani Kutir', 'Narayanganj Workshop', 'processing',
 'Master weaver inspected 84-count Jamdani handloom embroidery. Protected with moisture-barrier packaging.',
 '2026-09-24 08:30:00'),

(1, 'HAAT-2026-90412', 'Organic Jar Sealing & Quality Assurance', 'Sundarbans Wild Organics', 'Satkhira Facility', 'processing',
 'Khalisha wild honey bottled in sealed airtight glass jar with security seal applied.',
 '2026-09-24 11:30:00'),

(1, 'HAAT-2026-90412', 'Handed Over to Delivery Partner', 'Pathao Courier Logistics', 'Narayanganj Regional Sorting Hub', 'shipped',
 'Consignment #PTH-8849201 received by Pathao courier agent. Departed Narayanganj hub en route to Dhaka Central.',
 '2026-09-24 13:45:00');


-- ================================================================
-- SEED: Sample Messages (Live Chat)
-- ================================================================
INSERT INTO `messages` (`order_id`, `sender_id`, `receiver_id`, `seller_id`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 5, 1,
 'Assalamu Alaikum brother Tanvir! We have received your order #HAAT-2026-90412 for the Royal Dhakai Jamdani Saree. Our master weavers are inspecting the zari embroidery before packaging.',
 1, '2026-09-24 10:30:00'),

(1, 5, 2, 1,
 'Walaikum Assalam! Thank you Al-Amin bhai. Please ensure water-resistant packaging as it will be delivered outside Dhaka.',
 1, '2026-09-24 11:00:00'),

(1, 2, 5, 1,
 'Absolutely! We always use double bubble wrap and genuine traditional muslin protection. It has been handed over to courier dispatch.',
 0, '2026-09-24 11:45:00'),

(1, 4, 5, 3,
 'Hello Tanvir! Your Pure Sundarbans Wild Honey (1000g) is packed in an airtight glass jar with sealed protective carton. Freshly harvested from the forest!',
 1, '2026-09-24 09:15:00'),

(1, 5, 4, 3,
 'Great, looking forward to tasting authentic Sundarbans honey. Thank you!',
 1, '2026-09-24 09:45:00');


-- ================================================================
-- SEED: Active Coupon
-- ================================================================
INSERT INTO `coupons` (`id`, `seller_id`, `code`, `title`, `discount_type`, `discount_value`, `discount_percent`, `min_order`, `max_discount`, `expiry_date`, `usage_count`, `is_active`) VALUES
(1, NULL, 'HAAT10', 'Grand Inaugural 10% Off', 'percent', 10.00, 10, 500.00, 500.00, '2026-12-31', 1, 1);


-- ================================================================
-- DONE! All 13 tables created and seeded with demo data.
-- ================================================================
-- 
-- TABLE SUMMARY:
-- +---------------------------+---------------------------------------+
-- | Table                     | Purpose                               |
-- +---------------------------+---------------------------------------+
-- | users                     | All accounts (admin/seller/customer)  |
-- | sellers                   | Vendor shop profiles                  |
-- | categories                | Product categories (3 departments)    |
-- | brands                    | Product brands                        |
-- | products                  | Product listings                      |
-- | product_reviews           | Customer ratings & reviews            |
-- | orders                    | Customer orders                       |
-- | order_items               | Individual items per order            |
-- | order_tracking_events     | Live tracking timeline                |
-- | wishlists                 | Customer saved products               |
-- | coupons                   | Discount codes                        |
-- | messages                  | Buyer-Seller live chat                |
-- | notifications             | System & artisan broadcast alerts     |
-- +---------------------------+---------------------------------------+
-- ================================================================
