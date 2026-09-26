-- ========================================================
-- HAAT Multi-Vendor E-Commerce & Logistics Platform
-- Complete Database Schema & Seed Data
-- Generated: 2026-09-26 08:24:33
-- ========================================================

CREATE DATABASE IF NOT EXISTS `haat_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `haat_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table structure for table `brands`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(110) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `brands` (4 rows)
INSERT INTO `brands` VALUES
(1, 'Heritage Sonargaon', 'heritage-sonargaon', NULL, '2026-09-24 17:58:43'),
(2, 'Bijoypur Clay Guild', 'bijoypur-clay-guild', NULL, '2026-09-24 17:58:43'),
(3, 'Sundarbans Honey Collective', 'sundarbans-honey-collective', NULL, '2026-09-24 17:58:43'),
(4, 'Tangail Tanti Shamiti', 'tangail-tanti-shamiti', NULL, '2026-09-24 17:58:43');

-- --------------------------------------------------------
-- Table structure for table `categories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `name_bn` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT 'Clothes',
  `slug` varchar(110) NOT NULL,
  `icon` varchar(50) DEFAULT 'bi-tag',
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `categories` (15 rows)
INSERT INTO `categories` VALUES
(1, 'Men\'s Modern & Casuals', 'পুরুষদের আধুনিক ও ক্যাজুয়াল পোশাক', 'Clothes', 'mens-casuals', 'bi-person-standing', 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=500&auto=format&fit=crop&q=80', 'Graphic T-shirts, premium polos, denim jeans, casual shirts, and jackets.', 1, '2026-09-24 17:58:43'),
(2, 'Men\'s Ethnic & Festive', 'পুরুষদের ঐতিহ্যবাহী ও উৎসবের পোশাক', 'Clothes', 'mens-ethnic', 'bi-person-badge', 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=500&auto=format&fit=crop&q=80', 'Handcrafted silk & cotton panjabis, kabli suits, kurtas, and traditional lungi.', 1, '2026-09-24 17:58:43'),
(3, 'Women\'s Western & Fusion', 'নারীদের ওয়েস্টার্ন ও ফিউশন পোশাক', 'Clothes', 'womens-western', 'bi-person-hearts', 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=500&auto=format&fit=crop&q=80', 'Trendy tops, floral maxi dresses, denim trousers, cardigans, and fusion wear.', 1, '2026-09-24 17:58:43'),
(4, 'Traditional Sarees & Ethnic', 'ঐতিহ্যবাহী শাড়ি ও নকশি পোশাক', 'Clothes', 'sarees-ethnic', 'bi-stars', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=500&auto=format&fit=crop&q=80', 'Handwoven Dhakai Jamdani, Rajshahi silk sarees, Tangail taant, and three-pieces.', 1, '2026-09-24 17:58:43'),
(5, 'Kids & Baby Fashion', 'শিশুদের পোশাক ও ফ্যাশন', 'Clothes', 'kids-baby', 'bi-emoji-smile', 'https://images.unsplash.com/photo-1622290291468-a28f7a7dc6a8?w=500&auto=format&fit=crop&q=80', 'Soft cotton rompers, boys graphic tees & panjabis, girls festive frocks.', 1, '2026-09-24 17:58:43'),
(6, 'Pantry Staples & Pure Organics', 'খাঁটি অর্গানিক ও নিত্যপ্রয়োজনীয় খাবার', 'Food', 'pantry-organics', 'bi-flower1', 'https://images.unsplash.com/photo-1589301760014-d929f3979dbc?w=500&auto=format&fit=crop&q=80', 'Raw Sundarbans honey, cold-pressed wood-ghani mustard oil, cow ghee, date jaggery.', 1, '2026-09-24 17:58:43'),
(7, 'Tea, Coffee & Beverages', 'চা, কফি ও পানীয়', 'Food', 'tea-coffee-beverages', 'bi-cup-hot', 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=500&auto=format&fit=crop&q=80', 'Sylhet organic black tea, green tea, artisanal roasted coffee beans & espresso blends.', 1, '2026-09-24 17:58:43'),
(8, 'Snacks, Cookies & Chocolates', 'স্ন্যাকস, বিস্কুট ও চকলেট', 'Food', 'snacks-confectionery', 'bi-basket2', 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=500&auto=format&fit=crop&q=80', 'Bakery butter cookies, dark chocolate bars, gourmet chips, and crunchy snacks.', 1, '2026-09-24 17:58:43'),
(9, 'Traditional Delicacies & Sweets', 'আঞ্চলিক মিষ্টান্ন ও ঐতিহ্যবাহী খাবার', 'Food', 'traditional-sweets', 'bi-cake2', 'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?w=500&auto=format&fit=crop&q=80', 'Kushtia tiler khaja, homemade coconut naru, batasa, and sun-dried kumro bori.', 1, '2026-09-24 17:58:43'),
(10, 'Dry Fruits, Nuts & Spices', 'ড্রাই ফ্রুটস, বাদাম ও খাঁটি মশলা', 'Food', 'dryfruits-spices', 'bi-egg-fried', 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=500&auto=format&fit=crop&q=80', 'Almonds, cashews, pistachios, dates, homemade spicy mango pickles, and whole spices.', 1, '2026-09-24 17:58:43'),
(11, 'Bags, Wallets & Leather', 'ব্যাগ, ওয়ালেট ও লেদার সামগ্রী', 'Art & Accessories', 'bags-wallets-leather', 'bi-bag-check', 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=500&auto=format&fit=crop&q=80', 'Full-grain leather wallets, canvas backpacks, stylish clutches, and eco jute totes.', 1, '2026-09-24 17:58:43'),
(12, 'Watches & Eyewear', 'ঘড়ি ও সানগ্লাস', 'Art & Accessories', 'watches-eyewear', 'bi-watch', 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=500&auto=format&fit=crop&q=80', 'Classic minimalist analog watches, chronographs, polarized sunglasses, and UV frames.', 1, '2026-09-24 17:58:43'),
(13, 'Jewelry & Personal Styling', 'হ্যান্ডমেড ও মডার্ন জুয়েলারি', 'Art & Accessories', 'jewelry-styling', 'bi-gem', 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=500&auto=format&fit=crop&q=80', 'Minimalist silver chains, handcrafted terracotta earrings, brass choker necklaces.', 1, '2026-09-24 17:58:43'),
(14, 'Wall Art, Paintings & Prints', 'দেয়াল শিল্প, পেইন্টিং ও ফ্রেম', 'Art & Accessories', 'wall-art-paintings', 'bi-palette', 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?w=500&auto=format&fit=crop&q=80', 'Modern canvas abstracts, vibrant Dhaka rickshaw pop-art, and folk wall hangings.', 1, '2026-09-24 17:58:43'),
(15, 'Home Decor, Living & Ceramics', 'হোম ডেকোর ও সিরামিক ক্রাফট', 'Art & Accessories', 'home-decor-ceramics', 'bi-house-heart', 'https://images.unsplash.com/photo-1578749556568-bc2c40e68b61?w=500&auto=format&fit=crop&q=80', 'Terracotta planters, ceramic coffee mugs, woven jute floor rugs, and bamboo accents.', 1, '2026-09-24 17:58:43');

-- --------------------------------------------------------
-- Table structure for table `coupons`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) DEFAULT NULL,
  `code` varchar(30) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `discount_type` varchar(20) DEFAULT 'percent',
  `discount_value` decimal(10,2) DEFAULT 10.00,
  `discount_percent` int(11) NOT NULL DEFAULT 10,
  `min_order` decimal(10,2) DEFAULT 500.00,
  `max_discount` decimal(10,2) DEFAULT 500.00,
  `expiry_date` date DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `seller_id` (`seller_id`),
  CONSTRAINT `coupons_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `coupons` (1 rows)
INSERT INTO `coupons` VALUES
(1, NULL, 'HAAT10', 'Grand Inaugural 10% Off', 'percent', '10.00', 10, '500.00', '500.00', '2026-12-31', 1, 1, '2026-09-24 17:58:44');

-- --------------------------------------------------------
-- Table structure for table `messages`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `seller_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `seller_id` (`seller_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `messages` (20 rows)
INSERT INTO `messages` VALUES
(1, 1, 2, 5, 1, 'Assalamu Alaikum brother Tanvir! We have received your order #HAAT-2026-90412 for the Royal Dhakai Jamdani Saree. Our master weavers are inspecting the zari embroidery before packaging.', 1, '2026-09-24 10:30:00'),
(2, 1, 5, 2, 1, 'Walaikum Assalam! Thank you Al-Amin bhai. Please ensure water-resistant packaging as it will be delivered outside Dhaka.', 1, '2026-09-24 11:00:00'),
(3, 1, 2, 5, 1, 'Absolutely! We always use double bubble wrap and genuine traditional muslin protection. It has been handed over to courier dispatch.', 1, '2026-09-24 11:45:00'),
(4, 1, 4, 5, 3, 'Hello Tanvir! Your Pure Sundarbans Wild Honey (1000g) is packed in an airtight glass jar with sealed protective carton. Freshly harvested from the forest!', 1, '2026-09-24 09:15:00'),
(5, 1, 5, 4, 3, 'Great, looking forward to tasting authentic Sundarbans honey. Thank you!', 1, '2026-09-24 09:45:00'),
(7, NULL, 5, 4, 3, 'Assalamu Alaikum, has my order been dispatched?', 0, '2026-09-25 15:41:20'),
(8, NULL, 2, 5, 1, 'Assalamu Alaikum! How can we assist you with our craft?', 1, '2026-09-25 15:51:37'),
(9, NULL, 5, 2, 1, 'Hello artisan! Is the authentic Jamdani available for urgent delivery? [1790331065]', 1, '2026-09-25 16:11:05'),
(10, NULL, 5, 2, 1, 'Hello artisan! Is the authentic Jamdani available for urgent delivery? [1790331091]', 1, '2026-09-25 16:11:31'),
(11, NULL, 2, 5, 1, 'Yes Tanvir, we have authentic 84-count Dhakai Jamdani ready in our workshop! [1790331091]', 1, '2026-09-25 16:11:31'),
(12, 1, 5, 2, 1, 'Can I customize the border of this Jamdani sari?', 1, '2026-09-25 16:17:39'),
(13, 1, 2, 5, 1, 'Yes, we can handweave custom zardosi borders in 3 days.', 1, '2026-09-25 16:23:13'),
(14, 1, 2, 5, 1, 'Your parcel is carefully packed and scheduled for courier pickup.', 1, '2026-09-25 16:27:13'),
(15, 1, 2, 5, 1, 'Your handcrafted order #HAAT-2026-90412 has been handed to delivery rider.', 1, '2026-09-25 16:42:11'),
(16, NULL, 5, 7, NULL, 'Test message from Customer to HAATEX Logistics', 1, '2026-09-26 00:11:51'),
(17, NULL, 5, 7, NULL, 'Hello HAATEX, when will my Jamdani parcel arrive?', 1, '2026-09-26 00:14:42'),
(18, NULL, 7, 5, NULL, 'Your package is currently with rider Mahfuzur and on the way!', 1, '2026-09-26 00:18:49'),
(19, NULL, 5, 7, NULL, 'When will my package be delivered?', 1, '2026-09-26 00:29:15'),
(20, NULL, 2, 7, 1, 'Assalamu Alaikum HAATEX Hub, our handicraft order is packed and ready for pickup at workshop!', 1, '2026-09-26 01:07:20'),
(21, NULL, 7, 2, 1, '🏍️ HAATEX delivery rider is dispatched to your workshop for parcel pickup.', 1, '2026-09-26 01:29:36');

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `seller_id` int(11) DEFAULT NULL,
  `order_number` varchar(60) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'general',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_order_number` (`order_number`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `notifications` (1 rows)
INSERT INTO `notifications` VALUES
(4, 2, NULL, 'HAAT-20260925-16E61', 'Order Confirmed: #HAAT-20260925-16E61', 'Your artisanal order #HAAT-20260925-16E61 has been placed successfully and routed to master workshops for handcrafted fulfillment.', 'order_confirmed', 'http://localhost/Haat/track-order.php?order=HAAT-20260925-16E61', 1, '2026-09-25 18:40:59');

-- --------------------------------------------------------
-- Table structure for table `order_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL,
  `vendor_status` varchar(30) DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `seller_id` (`seller_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `order_items` (3 rows)
INSERT INTO `order_items` VALUES
(1, 1, 1, 4, 'Original Sonargaon Handwoven Royal Dhakai Jamdani Saree (84 Count)', '14500.00', 1, '14500.00', 'processing'),
(2, 1, 3, 6, 'Pure Raw Sundarbans Wild Mangrove Honey (Khalisha Flower 1000g)', '1750.00', 1, '1750.00', 'processing'),
(3, 2, 3, 9, 'Artisanal 70% Dark Chocolate Bar with Sea Salt & Roasted Almonds', '380.00', 1, '380.00', 'pending');

-- --------------------------------------------------------
-- Table structure for table `order_tracking_events`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `order_tracking_events`;
CREATE TABLE `order_tracking_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `order_number` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `actor` varchar(150) NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `status_key` varchar(50) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `order_number` (`order_number`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `order_tracking_events` (6 rows)
INSERT INTO `order_tracking_events` VALUES
(1, 1, 'HAAT-2026-90412', 'Order Placed & Payment Confirmed', 'HAAT Customer Checkout', 'Online (Dhaka)', 'pending', 'Payment verified via bKash Merchant Gateway (Trx ID: BKASH-98234120). Order dispatched to respective artisan workshops.', '2026-09-23 20:30:00'),
(2, 1, 'HAAT-2026-90412', 'Artisan Crafting & Workshop Preparation', 'Sonargaon Jamdani Kutir', 'Narayanganj Workshop', 'processing', 'Master weaver inspected 84-count Jamdani handloom embroidery. Protected with moisture-barrier packaging.', '2026-09-24 08:30:00'),
(3, 1, 'HAAT-2026-90412', 'Organic Jar Sealing & Quality Assurance', 'Sundarbans Wild Organics', 'Satkhira Facility', 'processing', 'Khalisha wild honey bottled in sealed airtight glass jar with security seal applied.', '2026-09-24 11:30:00'),
(4, 1, 'HAAT-2026-90412', 'Handed Over to Delivery Partner', 'HAATEX Logistics Hub', 'Narayanganj Regional Sorting Hub', 'shipped', 'Consignment #PTH-8849201 received by Pathao courier agent. Departed Narayanganj hub en route to Dhaka Central.', '2026-09-24 13:45:00'),
(5, 2, 'HAAT-20260925-16E61', 'Order Received & Placed', 'HAAT Marketplace System', 'Dhaka, Dhaka', 'pending', 'Customer order received and assigned to respective artisan guilds.', '2026-09-25 18:40:59'),
(6, 2, 'HAAT-20260925-16E61', 'Package Received at HAATEX Sorting Hub', 'HAATEX Logistics Hub', 'HAATEX Central Hub (Dhaka)', 'processing', 'Package safely received from artisan workshop at HAATEX regional fulfillment center.', '2026-09-25 23:57:59');

-- --------------------------------------------------------
-- Table structure for table `orders`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(30) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) DEFAULT 60.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL,
  `payment_method` varchar(30) NOT NULL DEFAULT 'cod',
  `payment_status` varchar(30) DEFAULT 'unpaid',
  `transaction_id` varchar(100) DEFAULT NULL,
  `order_status` varchar(30) DEFAULT 'pending',
  `logistics_status` varchar(50) DEFAULT 'pending',
  `shipping_name` varchar(100) NOT NULL,
  `shipping_phone` varchar(20) NOT NULL,
  `shipping_address` text NOT NULL,
  `district` varchar(50) NOT NULL DEFAULT 'Dhaka',
  `division` varchar(50) NOT NULL DEFAULT 'Dhaka',
  `notes` text DEFAULT NULL,
  `courier_partner` varchar(100) DEFAULT 'Pathao Courier',
  `assigned_rider_id` int(11) DEFAULT NULL,
  `assigned_rider_name` varchar(150) DEFAULT NULL,
  `assigned_rider_phone` varchar(50) DEFAULT NULL,
  `tracking_code` varchar(100) DEFAULT NULL,
  `estimated_delivery` varchar(100) DEFAULT NULL,
  `pickup_requested_at` datetime DEFAULT NULL,
  `logistics_accepted_at` datetime DEFAULT NULL,
  `out_for_delivery_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `orders` (2 rows)
INSERT INTO `orders` VALUES
(1, 'HAAT-2026-90412', 5, '14550.00', '100.00', '500.00', '14150.00', 'bkash', 'paid', 'BK9A72189X', 'shipped', 'out_for_delivery', 'Tanvir Hossain', '01511556677', 'House 42, Road 11, Banani', 'Dhaka', 'Dhaka', NULL, 'HAATEX (HAAT Express Logistics)', NULL, 'Tanvir Rahman (HAATEX Rider)', '01819234567', 'HTX-267527', '25-27 Sep 2026', NULL, NULL, NULL, NULL, '2026-09-24 17:58:44', '2026-09-25 23:35:56'),
(2, 'HAAT-20260925-16E61', 2, '380.00', '80.00', '0.00', '460.00', 'cod', 'unpaid', '', 'shipped', 'hub_received', 'Al-Amin Mia (Master Weaver)', '01811223344', '123 Jamdani Kutir, Sonargaon, Narayanganj', 'Dhaka', 'Dhaka', '', 'HAATEX (HAAT Express Logistics)', NULL, 'Tanvir Rahman (HAATEX Rider)', '01819234567', 'HTX-814614', NULL, NULL, '2026-09-25 23:57:59', NULL, NULL, '2026-09-25 18:40:59', '2026-09-25 23:57:59');

-- --------------------------------------------------------
-- Table structure for table `product_reviews`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `product_reviews`;
CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL DEFAULT 5,
  `comment` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `product_reviews` (6 rows)
INSERT INTO `product_reviews` VALUES
(1, 4, 5, 5, 'The Jamdani saree exceeded my expectations! The craftsmanship from Sonargaon is stunning, and the fabric is so soft. Delivered to Dhanmondi in 2 days.', '2026-09-24 17:58:44'),
(2, 6, 5, 5, 'Pure Sundarbans honey with that distinct aroma. Verified by crystallization and heat test. 100% authentic Haat product!', '2026-09-24 17:58:44'),
(3, 1, 5, 5, 'Excellent quality cotton T-shirt. The fit is perfect and the fabric breathes well in Dhaka heat.', '2026-09-24 17:58:44'),
(5, 20, 2, 5, 'very good product', '2026-09-25 21:28:50'),
(6, 20, 5, 5, 'perfect product', '2026-09-25 21:29:26'),
(8, 4, 5, 4, 'nice', '2026-09-25 23:12:54');

-- --------------------------------------------------------
-- Table structure for table `products`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `name_bn` varchar(200) DEFAULT NULL,
  `slug` varchar(220) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 10,
  `unit` varchar(20) DEFAULT 'piece',
  `short_description` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `featured_image` varchar(255) NOT NULL,
  `gallery_images` text DEFAULT NULL,
  `district_origin` varchar(50) DEFAULT 'Dhaka',
  `keywords` text DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_flash_deal` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `views` int(11) DEFAULT 0,
  `rating` decimal(3,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `seller_id` (`seller_id`),
  KEY `category_id` (`category_id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_ibfk_3` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `products` (18 rows)
INSERT INTO `products` VALUES
(1, 1, 1, NULL, 'Premium Heavyweight Cotton Crewneck T-Shirt (Midnight Navy)', 'প্রিমিয়াম কটন ক্রু-নেক টি-শার্ট (নেভি ব্লু)', 'premium-cotton-crewneck-tshirt-navy', NULL, '750.00', '650.00', 25, 'piece', '100% combed breathable cotton with modern tailored fit, anti-shrink pre-wash.', 'Crafted for daily effortless style, this heavy-weight combed cotton tee offers supreme breathability, durable double-needle stitching, and a clean minimalist aesthetic.', 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80', NULL, 'Dhaka', NULL, 1, 1, 1, 4, '5.00', '2026-09-24 17:58:43', '2026-09-25 23:02:25'),
(2, 1, 2, NULL, 'Handcrafted Silk-Cotton Festive Panjabi with Zari Work', 'হাতে তৈরি জরি কাজের সিল্ক-কটন পাঞ্জাবি', 'silk-cotton-festive-panjabi-zari', NULL, '3800.00', '3400.00', 25, 'piece', 'Traditional tailored cut with subtle neck and cuff hand embroidery for celebrations.', 'An ode to classic celebratory attire. Blended mulberry silk and cotton fabric ensures cool comfort during long festive hours with hand-detailed Zari motifs on the placket.', 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=600&auto=format&fit=crop&q=80', NULL, 'Rajshahi', NULL, 1, 0, 1, 0, '0.00', '2026-09-24 17:58:43', '2026-09-25 23:07:17'),
(3, 1, 3, NULL, 'Bohemian Tiered Maxi Dress with Floral Print', 'বোহেমিয়ান ফ্লোরাল টিয়ার্ড ম্যাক্সি ড্রেস', 'bohemian-tiered-maxi-dress-floral', NULL, '2450.00', '2100.00', 25, 'piece', 'Airy breathable georgette blend with flowing silhouette and subtle belt accent.', 'Effortlessly feminine and universally flattering. Features delicate botanical prints, gentle tiered ruffles, and a soft inner lining perfect for warm-weather outings.', 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=600&auto=format&fit=crop&q=80', NULL, 'Dhaka', NULL, 1, 0, 1, 0, '0.00', '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(4, 1, 4, NULL, 'Original Sonargaon Handwoven Royal Dhakai Jamdani Saree (84 Count)', 'আসল সোনারগাঁও রয়েল ঢাকাই জামদানি শাড়ি (৮৪ কাউন্ট)', 'original-sonargaon-royal-dhakai-jamdani-saree', NULL, '16500.00', '14500.00', 25, 'piece', 'UNESCO-recognized master handwoven Jamdani with intricate floral jaal motifs.', 'Woven by certified hereditary master artisans of Sonargaon over 45 painstaking days. Made from 84 count fine cotton yarn with traditional floral jaal geometric motifs in ivory and gold thread.', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=600&auto=format&fit=crop&q=80', NULL, 'Narayanganj', NULL, 1, 1, 1, 5, '4.50', '2026-09-24 17:58:43', '2026-09-25 23:12:54'),
(5, 1, 5, NULL, 'Organic Soft Handloom Cotton Baby Romper & Cap Set', 'অর্গানিক সফট হ্যান্ডলুম কটন বেবি রম্পার ও ক্যাপ', 'organic-cotton-baby-romper-cap', NULL, '890.00', '750.00', 25, 'set', 'Chemical-free natural dyed breathable cotton, snap buttons for easy dressing.', 'Gentle on delicate baby skin. Hand-stitched with ultra-soft unbleached organic cotton for cozy naps and playful days.', 'https://images.unsplash.com/photo-1622290291468-a28f7a7dc6a8?w=600&auto=format&fit=crop&q=80', NULL, 'Cumilla', NULL, 0, 0, 1, 0, '0.00', '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(6, 3, 6, NULL, 'Pure Raw Sundarbans Wild Mangrove Honey (Khalisha Flower 1000g)', 'খাঁটি সুন্দরবনের খলিসা ফুলের মধু (১০০০ গ্রাম)', 'pure-sundarbans-wild-honey-1000g', NULL, '1950.00', '1750.00', 25, 'jar', '100% raw unpasteurized wild honeycomb nectar hand-harvested by traditional Mawalis.', 'Collected deep within the mangrove forests of the Sundarbans during peak Khalisha blossom season. Naturally antibiotic, rich in pollen enzymes, and free from any added syrup.', 'https://images.unsplash.com/photo-1587049352846-4a222e784d38?w=600&auto=format&fit=crop&q=80', NULL, 'Satkhira', NULL, 1, 1, 1, 0, '5.00', '2026-09-24 17:58:43', '2026-09-25 23:02:25'),
(7, 3, 6, NULL, 'Cold-Pressed Traditional Wooden Ghani Mustard Oil (2 Liters)', 'কাঠের ঘানিতে ভাঙা খাঁটি সরিষার তেল (২ লিটার)', 'cold-pressed-wooden-ghani-mustard-oil-2l', NULL, '850.00', '780.00', 25, 'bottle', 'Extracted slowly on wooden ghani without heat to preserve natural pungency and pungin.', 'Real village flavor. Sourced from local Maghi mustard seeds, cold-pressed at low temperatures to ensure authentic pungent aroma and heart-healthy antioxidants.', 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=600&auto=format&fit=crop&q=80', NULL, 'Kushtia', NULL, 1, 0, 1, 0, '0.00', '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(8, 3, 7, NULL, 'Sreemangal Single-Estate Whole Leaf Organic Black Tea (400g Tin)', 'শ্রীমঙ্গল সিঙ্গেল-এস্টেট ব্ল্যাক টি (৪০০ গ্রাম)', 'sreemangal-single-estate-black-tea', NULL, '650.00', '580.00', 25, 'tin', 'First-flush high-grown whole tea leaves with rich amber liquor and muscatel notes.', 'Handpicked from the lush misty slopes of Sreemangal, the tea capital of Bangladesh. Delivers an invigorating cup with bright clarity, rich body, and gentle sweetness.', 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80', NULL, 'Moulvibazar', NULL, 1, 0, 1, 0, '0.00', '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(9, 3, 8, NULL, 'Artisanal 70% Dark Chocolate Bar with Sea Salt & Roasted Almonds', 'আর্টিসানাল ৭০% ডার্ক চকলেট বার উইথ সি-সল্ট ও আমন্ড', 'artisanal-dark-chocolate-sea-salt-almonds', NULL, '420.00', '380.00', 24, 'piece', 'Small-batch bean-to-bar chocolate crafted with organic cocoa and crunchy nuts.', 'Velvety smooth, deeply satisfying dark chocolate balanced with a touch of mineral sea salt crystals and slow-roasted Californian almonds.', 'https://images.unsplash.com/photo-1548907040-4baa42d10919?w=600&auto=format&fit=crop&q=80', NULL, 'Dhaka', NULL, 0, 1, 1, 1, '0.00', '2026-09-24 17:58:43', '2026-09-25 18:40:59'),
(10, 3, 9, NULL, 'Kushtia Heritage Crispy Sesame Tiler Khaja (500g Gift Box)', 'কুষ্টিয়ার ঐতিহ্যবাহী মুচমুচে তিলের খাজা (৫০০ গ্রাম)', 'kushtia-crispy-tiler-khaja-500g', NULL, '380.00', '320.00', 25, 'box', 'Authentic paper-thin layered sesame wafers infused with pure sugarcane molasses.', 'A century-old confection from Kushtia. Incredibly crisp, loaded with toasted white sesame seeds and hand-pulled molasses that melts in the mouth.', 'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?w=600&auto=format&fit=crop&q=80', NULL, 'Kushtia', NULL, 1, 0, 1, 0, '0.00', '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(11, 3, 10, NULL, 'Premium Roasted Jumbo Cashew Nuts & California Almonds Mix (500g)', 'প্রিমিয়াম রোস্টেড কাজু ও কাঠবাদাম মিক্স (৫০০ গ্রাম)', 'premium-roasted-cashew-almond-mix-500g', NULL, '980.00', '890.00', 25, 'jar', 'Lightly salted slow-roasted whole nuts packed in airtight reusable glass jar.', 'Healthy daily energy boost. Premium W240 whole cashews and non-pareil almonds, oven-roasted to golden crunchiness without hydrogenated oils.', 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=600&auto=format&fit=crop&q=80', NULL, 'Chattogram', NULL, 0, 0, 1, 0, '0.00', '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(12, 2, 11, NULL, 'Hand-Stitched Full-Grain Leather Bi-Fold Wallet (Vintage Tan)', 'হাতে সেলাই করা জেনুইন লেদার ওয়ালেট (ট্যান)', 'hand-stitched-leather-bifold-wallet-tan', NULL, '1650.00', '1450.00', 25, 'piece', '100% genuine Bangladeshi vegetable-tanned cowhide with RFID blocking protection.', 'Built to age with an elegant patina. Features 8 card slots, dual cash compartments, and heavy waxed thread hand-stitching guaranteed for years of rugged use.', 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=600&auto=format&fit=crop&q=80', NULL, 'Dhaka', NULL, 1, 1, 1, 3, '0.00', '2026-09-24 17:58:43', '2026-09-25 21:24:47'),
(13, 2, 12, NULL, 'Minimalist Bauhaus Slim Sapphire Watch with Leather Strap', 'মিনিমালিস্ট স্লিম অ্যানালগ ঘড়ি (লেদার স্ট্র্যাপ)', 'minimalist-bauhaus-slim-watch-leather', NULL, '3200.00', '2850.00', 25, 'piece', 'Ultra-thin surgical stainless steel case with Japanese quartz movement and scratch-resistant glass.', 'Refined modern simplicity. Crisp white dial with ultra-slim hour markers, water-resistant to 30 meters, accompanied by genuine calfskin strap.', 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=600&auto=format&fit=crop&q=80', NULL, 'Dhaka', NULL, 1, 0, 1, 0, '0.00', '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(14, 2, 13, NULL, 'Bijoypur Hand-Painted Terracotta Choker & Earring Set', 'বিজয়পুর হাতে আঁকা পোড়ামাটির চোকার ও কানের দুল', 'bijoypur-handpainted-terracotta-jewelry-set', NULL, '1100.00', '950.00', 25, 'set', 'Baked red clay beads with ethnic folk motifs and adjustable braided cord.', 'Created by female pottery artisans in Cumilla. Lightweight kiln-fired natural clay hand-painted in traditional folk colors, sealed with matte protective coating.', 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&auto=format&fit=crop&q=80', NULL, 'Cumilla', NULL, 1, 0, 1, 0, '0.00', '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(15, 2, 14, NULL, 'Framed Pop-Art Dhaka Rickshaw Painting Canvas (Peacock & Floral Motif)', 'ফ্রেমড পপ-আর্ট ঢাকা রিকশা পেইন্টিং (ময়ূর ও ফুল)', 'framed-dhaka-rickshaw-painting-canvas-peacock', NULL, '2400.00', '2100.00', 25, 'piece', 'Hand-painted enamel by authentic old Dhaka Ustad rickshaw artists on solid wooden frame.', 'UNESCO-recognized heritage rickshaw art. Features the iconic vivid neon peacock and floral motifs with glossy lacquer finish, ready to hang and liven up any modern living room.', 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?w=600&auto=format&fit=crop&q=80', NULL, 'Dhaka', NULL, 1, 0, 1, 0, '0.00', '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(16, 2, 15, NULL, 'Artisanal Braided Golden Jute Area Floor Rug (4ft Round)', 'হাতে বোনা সোনালী পাটের ফ্লোর রাগ (৪ ফুট গোলাকার)', 'braided-golden-jute-round-rug-4ft', NULL, '2800.00', '2450.00', 25, 'piece', '100% natural biodegradable golden jute fiber tightly hand-braided for modern boho homes.', 'Eco-friendly and durable. Handcrafted in Faridpur by village craft clusters, this circular rug adds warm natural texture, rustic warmth, and timeless charm to bedrooms or living spaces.', 'https://images.unsplash.com/photo-1578749556568-bc2c40e68b61?w=600&auto=format&fit=crop&q=80', NULL, 'Faridpur', NULL, 1, 1, 1, 5, '0.00', '2026-09-24 17:58:43', '2026-09-25 23:12:39'),
(18, 1, 1, 1, 'Full Slive Shirt', '', 'full-slive-shirt-7113', 'HAAT-E07D6', '500.00', '450.00', 10, 'piece', 'Besst full sleve cotton shirt', 'build for mens', 'http://localhost/Haat/assets/uploads/products/prod_1790252096_298.jpeg', NULL, 'Narayanganj', NULL, 0, 0, 1, 1, '0.00', '2026-09-24 18:14:56', '2026-09-24 19:24:24'),
(20, 1, 1, 1, 'Heritage Simple Hoodie', '', 'heritage-simple-hoodie-4548', 'HAAT-425A6', '650.00', '599.00', 100, 'piece', 'A **classic black full-zip hoodie** with a simple, minimalist design. It features a drawstring hood, front zipper, two side pockets, and ribbed cuffs and hem. Perfect for **casual, everyday wear**.', 'Rooted in timeless streetwear style, this hoodie combines **clean craftsmanship with everyday comfort**. Its classic full-zip construction, sturdy stitching, adjustable drawstring hood, ribbed cuffs, and practical front pockets create a durable piece designed for **long-lasting, effortless wear**.', 'http://localhost/Haat/assets/uploads/products/prod_1790350073_921.jpeg', NULL, 'Narayanganj', NULL, 1, 0, 1, 8, '5.00', '2026-09-25 21:27:53', '2026-09-25 23:02:25');

-- --------------------------------------------------------
-- Table structure for table `riders`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `riders`;
CREATE TABLE `riders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `hub_zone` varchar(100) NOT NULL,
  `vehicle_type` varchar(50) DEFAULT 'Motorbike',
  `status` varchar(50) DEFAULT 'active',
  `active_deliveries` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `riders` (4 rows)
INSERT INTO `riders` VALUES
(1, 'Tanvir Rahman (Lead Courier)', '01819234567', 'Banani & Gulshan Hub (Dhaka North)', 'Motorbike', 'active', 0, '2026-09-25 23:35:56'),
(2, 'Rakibul Hasan (Express Rider)', '01712345678', 'Dhanmondi & Mirpur Hub (Dhaka South)', 'Motorbike', 'active', 0, '2026-09-25 23:35:56'),
(3, 'Mehedi Hasan (City Delivery)', '01912345679', 'Uttara & Airport Zone (Dhaka North)', 'Motorbike', 'active', 0, '2026-09-25 23:35:56'),
(4, 'Shakil Ahmed (Artisan Hub Rider)', '01612345680', 'Narayanganj & Sonargaon Craft Hub', 'Covered Van', 'active', 0, '2026-09-25 23:35:56');

-- --------------------------------------------------------
-- Table structure for table `sellers`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sellers`;
CREATE TABLE `sellers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `shop_name` varchar(150) NOT NULL,
  `shop_slug` varchar(160) NOT NULL,
  `shop_logo` varchar(255) DEFAULT 'assets/images/default-shop.png',
  `shop_banner` varchar(255) DEFAULT 'assets/images/default-banner.jpg',
  `description` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `district` varchar(50) DEFAULT 'Dhaka',
  `division` varchar(50) DEFAULT 'Dhaka',
  `nid_number` varchar(50) DEFAULT NULL,
  `trade_license` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_no` varchar(50) DEFAULT NULL,
  `bkash_number` varchar(20) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT 5.00,
  `is_verified` tinyint(1) DEFAULT 1,
  `status` varchar(30) DEFAULT 'active',
  `rating` decimal(3,2) DEFAULT 4.80,
  `total_sales` decimal(12,2) DEFAULT 0.00,
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `shop_slug` (`shop_slug`),
  CONSTRAINT `sellers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `sellers` (3 rows)
INSERT INTO `sellers` VALUES
(1, 2, 'Sonargaon Jamdani Kutir', 'sonargaon-jamdani-kutir', 'assets/images/default-shop.png', 'assets/images/default-banner.jpg', 'Authentic handloom Jamdani & Tangail cotton sarees crafted by generations of traditional Bangladeshi weavers using pure organic yarn.', '01811223344', 'Rupganj, Sonargaon', 'Narayanganj', 'Dhaka', NULL, NULL, NULL, NULL, '01811223344', '5.00', 1, 'active', '4.80', '185000.00', 1, '2026-09-24 17:58:43'),
(2, 3, 'Bijoypur Terracotta & Clay Arts', 'bijoypur-terracotta-arts', 'assets/images/default-shop.png', 'assets/images/default-banner.jpg', 'Heritage terracotta pottery, clay kitchenware, decorative home pieces, and water pitchers molded by Cumilla traditional artisans.', '01911334455', 'Bijoypur Pottery Village', 'Cumilla', 'Chittagong', NULL, NULL, NULL, NULL, '01911334455', '5.00', 1, 'active', '0.00', '92000.00', 1, '2026-09-24 17:58:43'),
(3, 4, 'Sundarbans Wild Organics & Pure Honey', 'sundarbans-wild-organics', 'assets/images/default-shop.png', 'assets/images/default-banner.jpg', 'Raw Khalisha & Goran flower deep mangrove forest honey, cold-pressed mustard oil, and naturally sourced mangrove sea salt from Satkhira.', '01611445566', 'Shyamnagar, Sundarbans Rim', 'Satkhira', 'Khulna', NULL, NULL, NULL, NULL, '01611445566', '5.00', 1, 'active', '5.00', '142380.00', 1, '2026-09-24 17:58:43');

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(30) DEFAULT 'customer',
  `avatar` varchar(255) DEFAULT 'assets/images/default-avatar.png',
  `status` varchar(30) DEFAULT 'active',
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `users` (6 rows)
INSERT INTO `users` VALUES
(1, 'HAAT Chief Administrator', 'admin@haat.com.bd', '$2y$10$C3Rwm4F9KEMTzL.y4No4peX8Gv6jsAv76CY/P6kGf0sTAKNlKknla', '01711000001', 'admin', 'assets/images/default-avatar.png', 'active', 1, '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(2, 'Al-Amin Mia (Master Weaver)', 'jamdani@haat.com.bd', '$2y$10$hcvMvBi.fDy5kMd0BwUXaunE14pQ2HenvwTv5H4xFn/itzr90SiEK', '01811223344', 'seller', 'assets/images/default-avatar.png', 'active', 1, '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(3, 'Gouranga Pal (Master Potter)', 'pottery@haat.com.bd', '$2y$10$hcvMvBi.fDy5kMd0BwUXaunE14pQ2HenvwTv5H4xFn/itzr90SiEK', '01911334455', 'seller', 'assets/images/default-avatar.png', 'active', 1, '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(4, 'Mizanur Rahman (Honey Harvester)', 'honey@haat.com.bd', '$2y$10$hcvMvBi.fDy5kMd0BwUXaunE14pQ2HenvwTv5H4xFn/itzr90SiEK', '01611445566', 'seller', 'assets/images/default-avatar.png', 'active', 1, '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(5, 'Tanvir Hossain', 'customer@haat.com.bd', '$2y$10$GxpFfxDlSPdoVmT5OlvAguz5aN/HahNsPP6s45yeVcriUV4MObjmC', '01511556677', 'customer', 'assets/images/default-avatar.png', 'active', 0, '2026-09-24 17:58:43', '2026-09-24 17:58:43'),
(7, 'HAATEX Logistics Command Hub', 'logistics@haat.com.bd', '$2y$10$FKUkdjblDnabty.zKCQA1eZr.h7nUnRvf2P5q7t.0BH7xnuyyPQ6m', '01711009988', 'logistics', 'assets/images/default-avatar.png', 'active', 1, '2026-09-25 23:35:56', '2026-09-25 23:35:56');

-- --------------------------------------------------------
-- Table structure for table `wishlists`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `wishlists`;
CREATE TABLE `wishlists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_prod_unique` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `wishlists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlists_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `wishlists` (1 rows)
INSERT INTO `wishlists` VALUES
(3, 2, 9, '2026-09-24 19:26:15');

SET FOREIGN_KEY_CHECKS = 1;
