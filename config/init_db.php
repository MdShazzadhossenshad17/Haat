<?php
/**
 * Database Initialization & Seed Script
 * HAAT - Bangladeshi Multi-Vendor E-Commerce Platform
 */

require_once __DIR__ . '/database.php';

$pdo = getDBConnection();

// Schema Creation
$schema = "
-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `role` ENUM('admin', 'seller', 'customer') DEFAULT 'customer',
    `avatar` VARCHAR(255) DEFAULT 'assets/images/default-avatar.png',
    `status` ENUM('active', 'pending', 'suspended') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sellers (Vendors) Table
CREATE TABLE IF NOT EXISTS `sellers` (
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
        `allow_self_purchase` TINYINT(1) DEFAULT 0,
    `status` ENUM('active', 'pending', 'suspended') DEFAULT 'active',
    `rating` DECIMAL(3,2) DEFAULT 4.80,
    `total_sales` DECIMAL(12,2) DEFAULT 0.00,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `name_bn` VARCHAR(100) DEFAULT NULL,
    `slug` VARCHAR(110) NOT NULL UNIQUE,
    `icon` VARCHAR(50) DEFAULT 'bi-tag',
    `image` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `is_featured` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Brands Table
CREATE TABLE IF NOT EXISTS `brands` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(110) NOT NULL UNIQUE,
    `logo` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products Table
CREATE TABLE IF NOT EXISTS `products` (
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
    `description` LONGTEXT DEFAULT NULL,
    `featured_image` VARCHAR(255) NOT NULL,
    `gallery_images` TEXT DEFAULT NULL,
    `district_origin` VARCHAR(50) DEFAULT 'Dhaka',
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

-- Product Reviews Table
CREATE TABLE IF NOT EXISTS `product_reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `rating` INT NOT NULL DEFAULT 5,
    `comment` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(30) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `shipping_cost` DECIMAL(10,2) DEFAULT 60.00,
    `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
    `grand_total` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('bkash', 'nagad', 'rocket', 'cod') NOT NULL DEFAULT 'cod',
    `payment_status` ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
    `transaction_id` VARCHAR(100) DEFAULT NULL,
    `order_status` ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    `shipping_name` VARCHAR(100) NOT NULL,
    `shipping_phone` VARCHAR(20) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `district` VARCHAR(50) NOT NULL DEFAULT 'Dhaka',
    `division` VARCHAR(50) NOT NULL DEFAULT 'Dhaka',
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items Table
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `seller_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `vendor_status` ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`seller_id`) REFERENCES `sellers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wishlist Table
CREATE TABLE IF NOT EXISTS `wishlists` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `user_prod_unique` (`user_id`, `product_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coupons Table
CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `discount_percent` INT NOT NULL DEFAULT 10,
    `min_order` DECIMAL(10,2) DEFAULT 500.00,
    `max_discount` DECIMAL(10,2) DEFAULT 500.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$pdo->exec($schema);

// Seed Users if not exists
$stmt = $pdo->query("SELECT COUNT(*) FROM `users`");
if ($stmt->fetchColumn() == 0) {
    // Passwords
    $adminPass = password_hash('Admin@123', PASSWORD_DEFAULT);
    $sellerPass = password_hash('Seller@123', PASSWORD_DEFAULT);
    $customerPass = password_hash('Customer@123', PASSWORD_DEFAULT);

    $userInsert = $pdo->prepare("INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`) VALUES (?, ?, ?, ?, ?, ?)");
    
    // 1. Admin
    $userInsert->execute(['HAAT Chief Administrator', 'admin@haat.com.bd', $adminPass, '01711000001', 'admin', 'active']);
    $adminId = $pdo->lastInsertId();

    // 2. Seller 1 (Jamdani)
    $userInsert->execute(['Al-Amin Mia (Master Weaver)', 'jamdani@haat.com.bd', $sellerPass, '01811223344', 'seller', 'active']);
    $seller1UserId = $pdo->lastInsertId();

    // 3. Seller 2 (Pottery)
    $userInsert->execute(['Gouranga Pal (Master Potter)', 'pottery@haat.com.bd', $sellerPass, '01911334455', 'seller', 'active']);
    $seller2UserId = $pdo->lastInsertId();

    // 4. Seller 3 (Sundarbans Organics)
    $userInsert->execute(['Mizanur Rahman (Honey Harvester)', 'honey@haat.com.bd', $sellerPass, '01611445566', 'seller', 'active']);
    $seller3UserId = $pdo->lastInsertId();

    // 5. Customer
    $userInsert->execute(['Tanvir Hossain', 'customer@haat.com.bd', $customerPass, '01511556677', 'customer', 'active']);
    $customerId = $pdo->lastInsertId();

    // Seed Sellers Profiles
    $sellerInsert = $pdo->prepare("INSERT INTO `sellers` 
        (`user_id`, `shop_name`, `shop_slug`, `description`, `phone`, `address`, `district`, `division`, `bkash_number`, `is_verified`, `rating`, `total_sales`) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $sellerInsert->execute([
        $seller1UserId,
        'Sonargaon Jamdani Kutir',
        'sonargaon-jamdani-kutir',
        'Authentic handloom Jamdani & Tangail cotton sarees crafted by generations of traditional Bangladeshi weavers using pure organic yarn.',
        '01811223344',
        'Rupganj, Sonargaon',
        'Narayanganj',
        'Dhaka',
        '01811223344',
        1,
        0,
        4.95,
        185000.00
    ]);
    $seller1Id = $pdo->lastInsertId();

    $sellerInsert->execute([
        $seller2UserId,
        'Bijoypur Terracotta & Clay Arts',
        'bijoypur-terracotta-arts',
        'Heritage terracotta pottery, clay kitchenware, decorative home pieces, and water pitchers molded by Cumilla traditional artisans.',
        '01911334455',
        'Bijoypur Pottery Village',
        'Cumilla',
        'Chittagong',
        '01911334455',
        1,
        0,
        4.85,
        92000.00
    ]);
    $seller2Id = $pdo->lastInsertId();

    $sellerInsert->execute([
        $seller3UserId,
        'Sundarbans Wild Organics & Pure Honey',
        'sundarbans-wild-organics',
        'Raw Khalisha & Goran flower deep mangrove forest honey, cold-pressed mustard oil, and naturally sourced mangrove sea salt from Satkhira.',
        '01611445566',
        'Shyamnagar, Sundarbans Rim',
        'Satkhira',
        'Khulna',
        '01611445566',
        1,
        0,
        4.90,
        142000.00
    ]);
    $seller3Id = $pdo->lastInsertId();

    // Seed Categories
    $catInsert = $pdo->prepare("INSERT INTO `categories` (`name`, `name_bn`, `slug`, `icon`, `image`, `description`, `is_featured`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $categories = [
        ['Jamdani & Handloom', 'জামদানি ও তাঁতবস্ত্র', 'jamdani-handloom', 'bi-palette2', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=600&q=80', 'Traditional handwoven Jamdani, Tangail silk, and Khadi fabrics', 1],
        ['Clay & Terracotta Pottery', 'মৃৎশিল্প ও টেরাকোটা', 'clay-terracotta-pottery', 'bi-cup-hot', 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?auto=format&fit=crop&w=600&q=80', 'Handmade clay tableware, decorative pots, terracotta lamps', 1],
        ['Sundarbans Pure Organics', 'সুন্দরবনের খাঁটি মধু ও তেল', 'sundarbans-pure-organics', 'bi-droplet-half', 'https://images.unsplash.com/photo-1587049352846-4a222e784d38?auto=format&fit=crop&w=600&q=80', 'Raw unfiltered wild mangrove honey, cold-pressed oils', 1],
        ['Jute & Eco Handicrafts', 'পাট ও পরিবেশবান্ধব শিল্প', 'jute-eco-crafts', 'bi-basket', 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=600&q=80', 'Natural golden fibre jute rugs, baskets, handmade bags', 1],
        ['Brass & Bell Metal', 'পিতল ও কাঁসা শিল্প', 'brass-bell-metal', 'bi-shield-shaded', 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=600&q=80', 'Dhamrai heritage brass statues, bell-metal plates & bells', 1],
        ['Rajshahi Pure Silk', 'রাজশাহী খাঁটি রেশম', 'rajshahi-pure-silk', 'bi-stars', 'https://images.unsplash.com/photo-1607344645866-009c320c5ab8?auto=format&fit=crop&w=600&q=80', 'Luxurious mulberry silk sarees, panjabis and scarves', 1],
        ['Nakshi Kantha Embroidery', 'নকশী কাঁথা', 'nakshi-kantha', 'bi-flower1', 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=600&q=80', 'Intricate hand-stitched folk quilts and decorative wall mats', 1],
        ['Pure Leather Goods', 'চামড়াজাত পণ্য', 'leather-goods', 'bi-bag', 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?auto=format&fit=crop&w=600&q=80', 'Handcrafted genuine leather wallets, shoes, and messengers', 1],
    ];

    foreach ($categories as $cat) {
        $catInsert->execute($cat);
    }

    // Seed Brands
    $brandInsert = $pdo->prepare("INSERT INTO `brands` (`name`, `slug`) VALUES (?, ?)");
    $brandInsert->execute(['Heritage Sonargaon', 'heritage-sonargaon']);
    $b1 = $pdo->lastInsertId();
    $brandInsert->execute(['Bijoypur Clay Guild', 'bijoypur-clay-guild']);
    $b2 = $pdo->lastInsertId();
    $brandInsert->execute(['Sundarbans Honey Collective', 'sundarbans-honey-collective']);
    $b3 = $pdo->lastInsertId();
    $brandInsert->execute(['Tangail Tanti Shamiti', 'tangail-tanti-shamiti']);
    $b4 = $pdo->lastInsertId();

    // Seed Products
    $prodInsert = $pdo->prepare("INSERT INTO `products` 
        (`seller_id`, `category_id`, `brand_id`, `name`, `name_bn`, `slug`, `sku`, `price`, `sale_price`, `stock_quantity`, `unit`, `short_description`, `description`, `featured_image`, `district_origin`, `is_featured`, `is_flash_deal`, `is_active`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $products = [
        [
            $seller1Id, 1, $b1,
            'Handwoven Royal Dhakai Jamdani Saree (84 Count)',
            'হাতে বোনা রাজকীয় ঢাকাই জামদানি শাড়ি',
            'handwoven-royal-dhakai-jamdani-saree-84-count',
            'JMD-84-01',
            14500.00, 12900.00, 15, 'piece',
            'Exquisite 84-count pure cotton thread Jamdani with intricate floral jaal motif handwoven in Sonargaon.',
            'This magnificent Jamdani saree is crafted over 45 days by two master artisans on traditional pit looms in Rupganj, Narayanganj. Utilizing ancient Mughal motifs (Panna Hazar & Moyurkonthi), the fabric breathes naturally and drapes with majestic grace. 100% authentic geographical indication (GI) certified handloom piece.',
            'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=700&q=80',
            'Narayanganj', 1, 1, 1
        ],
        [
            $seller2Id, 2, $b2,
            'Traditional Bijoypur Glazed Clay Water Pitcher (Matka)',
            'বিজয়পুরের ঐতিহ্যবাহী পোড়ামাটির পানির কলসি',
            'traditional-bijoypur-glazed-clay-water-pitcher',
            'POT-MAT-02',
            1200.00, 950.00, 30, 'piece',
            'Naturally cooling terracotta clay water pitcher with floral hand engravings from Cumilla.',
            'Hand-turned on potter wheels in Bijoypur, Cumilla. Keeps drinking water chilled naturally by 4-6 degrees Celsius through micro-porous evaporative cooling. Mineral-rich, lead-free clay firing that balances water pH level. Includes clay lid and pour coaster.',
            'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?auto=format&fit=crop&w=700&q=80',
            'Cumilla', 1, 1, 1
        ],
        [
            $seller3Id, 3, $b3,
            'Pure Sundarbans Khalisha Flower Wild Honey (1000g)',
            'সুন্দরবনের খাঁটি খলিশা ফুলের প্রাকৃতিক মধু (১ কেজি)',
            'pure-sundarbans-khalisha-flower-wild-honey-1kg',
            'HON-SUN-1K',
            1850.00, 1650.00, 50, 'kg',
            '100% raw, unpasteurized natural honey collected from deep mangrove forests by Mawalis.',
            'Collected sustainably from deep within the Sundarbans mangrove forest during the spring blooming season of Khalisha blossom. Naturally rich in enzymes, antioxidants, and a delicate floral aroma. Lab tested and certified 0% added sugar or adulteration.',
            'https://images.unsplash.com/photo-1587049352846-4a222e784d38?auto=format&fit=crop&w=700&q=80',
            'Satkhira', 1, 1, 1
        ],
        [
            $seller1Id, 7, $b4,
            'Heritage Hand-Stitched Silk Nakshi Kantha Quilt',
            'রেশম সুতার ঐতিহ্যবাহী নকশী কাঁথা',
            'heritage-hand-stitched-silk-nakshi-kantha-quilt',
            'KAN-SLK-04',
            8500.00, 7400.00, 12, 'piece',
            'Masterpiece rural folk tale motifs hand-stitched by village women artisans of Jamalpur.',
            'Every square inch of this Nakshi Kantha features stories of village life, riverboats, dancing peacocks, and blooming lotus ponds. Hand-embroidered using silk and cotton thread on layers of soft vintage-feel fabric. Perfect as a lightweight luxury throw or heirloom bedspread.',
            'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=700&q=80',
            'Jamalpur', 1, 0, 1
        ],
        [
            $seller2Id, 4, $b2,
            'Artisanal Braided Golden Jute Storage Basket Set',
            'হাতে বোনা সোনালী পাটের ঝুড়ি সেট (৩টি)',
            'artisanal-braided-golden-jute-storage-basket-set',
            'JUT-BSK-05',
            2200.00, 1850.00, 25, 'set',
            'Set of 3 eco-friendly nesting storage baskets made of premium Bangladeshi natural jute fiber.',
            'Braided by hand from organic golden jute fiber harvested in Faridpur. Sturdy handles, 100% biodegradable and chemical-free. Multi-purpose use for living room storage, planters, pantry organizers, and laundry.',
            'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=700&q=80',
            'Faridpur', 1, 1, 1
        ],
        [
            $seller1Id, 6, $b1,
            'Rajshahi Mulberry Silk Formal Panjabi (Embroidered)',
            'রাজশাহী খাঁটি সিল্কের কারুকাজ করা পাঞ্জাবি',
            'rajshahi-mulberry-silk-formal-panjabi',
            'PAN-SLK-06',
            5800.00, 4950.00, 20, 'piece',
            'Authentic Rajshahi pure silk panjabi with subtle hand zari embroidery along collar and placket.',
            'Woven from the finest mulberry cocoons of the Bholahat silk clusters in Rajshahi. Light, ultra-soft sheen, and breathable comfort for festive and formal occasions. Tailored with mother-of-pearl buttons.',
            'https://images.unsplash.com/photo-1607344645866-009c320c5ab8?auto=format&fit=crop&w=700&q=80',
            'Rajshahi', 1, 0, 1
        ],
        [
            $seller3Id, 3, $b3,
            'Cold-Pressed Traditional Wooden Ghani Mustard Oil (2L)',
            'কাঠের ঘানির খাঁটি সরিষার তেল (২ লিটার)',
            'cold-pressed-wooden-ghani-mustard-oil-2l',
            'OIL-MUS-2L',
            780.00, 690.00, 40, 'bottle',
            'Pungent, authentic virgin mustard oil extracted in ancient wooden mortar (Ghani).',
            'Extracted from select indigenous deshi Maghi mustard seeds without heat processing or chemical solvents. Preserves the intense aroma, natural vitamin E, and heart-healthy omega fats that define traditional Bengali cooking.',
            'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?auto=format&fit=crop&w=700&q=80',
            'Kushtia', 0, 1, 1
        ],
        [
            $seller2Id, 5, $b2,
            'Hand-Engraved Dhamrai Heritage Brass Kasha Thali Set',
            'ধামরাইয়ের প্রাচীন ঐতিহ্যবাহী কাঁসার থালা বাটি সেট',
            'hand-engraved-dhamrai-heritage-brass-kasha-set',
            'BRS-THA-08',
            4500.00, 3900.00, 10, 'set',
            'Authentic bell-metal (Kasha) eating plate, bowls, and water glass made by Dhamrai artisans.',
            'Crafted through centuries-old lost-wax casting in Dhamrai, Dhaka. Traditional Ayurvedic belief maintains that dining on bell metal boosts digestion, purifies food, and promotes vitality. Includes 1 dinner thali, 2 curry bowls, and 1 tall bell-metal tumbler.',
            'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=700&q=80',
            'Dhaka', 1, 1, 1
        ]
    ];

    $prodIds = [];
    foreach ($products as $p) {
        $prodInsert->execute($p);
        $prodIds[] = $pdo->lastInsertId();
    }

    // Seed Sample Reviews
    $revInsert = $pdo->prepare("INSERT INTO `product_reviews` (`product_id`, `user_id`, `rating`, `comment`) VALUES (?, ?, ?, ?)");
    $revInsert->execute([$prodIds[0], $customerId, 5, 'The Jamdani saree exceeded my expectations! The craftsmanship from Sonargaon is stunning, and the fabric is so soft. Delivered to Dhanmondi in 2 days.']);
    $revInsert->execute([$prodIds[2], $customerId, 5, 'Pure Sundarbans honey with that distinct aroma. Verified by crystallization and heat test. 100% authentic Haat product!']);
    $revInsert->execute([$prodIds[1], $customerId, 5, 'The terracotta matka keeps water icy cold naturally in Dhaka summer heat. Beautiful finish.']);

    // Seed Sample Orders
    $orderInsert = $pdo->prepare("INSERT INTO `orders` 
        (`order_number`, `user_id`, `total_amount`, `shipping_cost`, `discount_amount`, `grand_total`, `payment_method`, `payment_status`, `transaction_id`, `order_status`, `shipping_name`, `shipping_phone`, `shipping_address`, `district`, `division`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $orderInsert->execute([
        'HAAT-2026-90412',
        $customerId,
        14550.00,
        100.00,
        500.00,
        14150.00,
        'bkash',
        'paid',
        'BK9A72189X',
        'processing',
        'Tanvir Hossain',
        '01511556677',
        'House 42, Road 11, Banani',
        'Dhaka',
        'Dhaka'
    ]);
    $order1Id = $pdo->lastInsertId();

    $itemInsert = $pdo->prepare("INSERT INTO `order_items` (`order_id`, `seller_id`, `product_id`, `product_name`, `price`, `quantity`, `subtotal`, `vendor_status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $itemInsert->execute([$order1Id, $seller1Id, $prodIds[0], 'Handwoven Royal Dhakai Jamdani Saree (84 Count)', 12900.00, 1, 12900.00, 'processing']);
    $itemInsert->execute([$order1Id, $seller3Id, $prodIds[2], 'Pure Sundarbans Khalisha Flower Wild Honey (1000g)', 1650.00, 1, 1650.00, 'processing']);

    // Seed Active Coupon
    $pdo->exec("INSERT INTO `coupons` (`code`, `discount_percent`, `min_order`, `max_discount`, `is_active`) VALUES ('HAAT10', 10, 500, 500, 1)");
}

echo "HAAT Database Initialized Successfully with Tables, Categories, Artisans, Products, and Demo Accounts!\n";
