<?php
/**
 * Universal & Traditional Category and Product Seeder
 * HAAT - Global • Regional • Artisanal
 */

require_once __DIR__ . '/database.php';
$pdo = getDBConnection();

echo "1. Checking and updating schema...\n";
// Ensure department column exists in categories
try {
    $pdo->exec("ALTER TABLE `categories` ADD COLUMN `department` VARCHAR(50) DEFAULT 'Clothes' AFTER `name_bn`");
} catch (Exception $e) {
    // Column may already exist
}

// Ensure district_origin default or column is present in products
try {
    $pdo->exec("ALTER TABLE `products` ADD COLUMN `district_origin` VARCHAR(50) DEFAULT 'Dhaka' AFTER `gallery_images`");
} catch (Exception $e) {
    // Column already exists
}

echo "2. Setting up 15 Master Categories across 3 Departments...\n";

// Disable foreign key checks temporarily for clean category update
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("TRUNCATE TABLE `categories`;");
$pdo->exec("TRUNCATE TABLE `products`;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$categories = [
    // --- 1. CLOTHES ---
    [
        'id' => 1,
        'department' => 'Clothes',
        'name' => "Men's Modern & Casuals",
        'name_bn' => 'পুরুষদের আধুনিক ও ক্যাজুয়াল পোশাক',
        'slug' => 'mens-casuals',
        'icon' => 'bi-person-standing',
        'image' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=500&auto=format&fit=crop&q=80',
        'description' => 'Graphic T-shirts, premium polos, denim jeans, casual shirts, and jackets.'
    ],
    [
        'id' => 2,
        'department' => 'Clothes',
        'name' => "Men's Ethnic & Festive",
        'name_bn' => 'পুরুষদের ঐতিহ্যবাহী ও উৎসবের পোশাক',
        'slug' => 'mens-ethnic',
        'icon' => 'bi-person-badge',
        'image' => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=500&auto=format&fit=crop&q=80',
        'description' => 'Handcrafted silk & cotton panjabis, kabli suits, kurtas, and traditional lungi.'
    ],
    [
        'id' => 3,
        'department' => 'Clothes',
        'name' => "Women's Western & Fusion",
        'name_bn' => 'নারীদের ওয়েস্টার্ন ও ফিউশন পোশাক',
        'slug' => 'womens-western',
        'icon' => 'bi-person-hearts',
        'image' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=500&auto=format&fit=crop&q=80',
        'description' => 'Trendy tops, floral maxi dresses, denim trousers, cardigans, and fusion wear.'
    ],
    [
        'id' => 4,
        'department' => 'Clothes',
        'name' => 'Traditional Sarees & Ethnic',
        'name_bn' => 'ঐতিহ্যবাহী শাড়ি ও নকশি পোশাক',
        'slug' => 'sarees-ethnic',
        'icon' => 'bi-stars',
        'image' => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=500&auto=format&fit=crop&q=80',
        'description' => 'Handwoven Dhakai Jamdani, Rajshahi silk sarees, Tangail taant, and three-pieces.'
    ],
    [
        'id' => 5,
        'department' => 'Clothes',
        'name' => 'Kids & Baby Fashion',
        'name_bn' => 'শিশুদের পোশাক ও ফ্যাশন',
        'slug' => 'kids-baby',
        'icon' => 'bi-emoji-smile',
        'image' => 'https://images.unsplash.com/photo-1622290291468-a28f7a7dc6a8?w=500&auto=format&fit=crop&q=80',
        'description' => 'Soft cotton rompers, boys graphic tees & panjabis, girls festive frocks.'
    ],

    // --- 2. FOOD ---
    [
        'id' => 6,
        'department' => 'Food',
        'name' => 'Pantry Staples & Pure Organics',
        'name_bn' => 'খাঁটি অর্গানিক ও নিত্যপ্রয়োজনীয় খাবার',
        'slug' => 'pantry-organics',
        'icon' => 'bi-flower1',
        'image' => 'https://images.unsplash.com/photo-1589301760014-d929f3979dbc?w=500&auto=format&fit=crop&q=80',
        'description' => 'Raw Sundarbans honey, cold-pressed wood-ghani mustard oil, cow ghee, date jaggery.'
    ],
    [
        'id' => 7,
        'department' => 'Food',
        'name' => 'Tea, Coffee & Beverages',
        'name_bn' => 'চা, কফি ও পানীয়',
        'slug' => 'tea-coffee-beverages',
        'icon' => 'bi-cup-hot',
        'image' => 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=500&auto=format&fit=crop&q=80',
        'description' => 'Sylhet organic black tea, green tea, artisanal roasted coffee beans & espresso blends.'
    ],
    [
        'id' => 8,
        'department' => 'Food',
        'name' => 'Snacks, Cookies & Chocolates',
        'name_bn' => 'স্ন্যাকস, বিস্কুট ও চকলেট',
        'slug' => 'snacks-confectionery',
        'icon' => 'bi-basket2',
        'image' => 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=500&auto=format&fit=crop&q=80',
        'description' => 'Bakery butter cookies, dark chocolate bars, gourmet chips, and crunchy snacks.'
    ],
    [
        'id' => 9,
        'department' => 'Food',
        'name' => 'Traditional Delicacies & Sweets',
        'name_bn' => 'আঞ্চলিক মিষ্টান্ন ও ঐতিহ্যবাহী খাবার',
        'slug' => 'traditional-sweets',
        'icon' => 'bi-cake2',
        'image' => 'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?w=500&auto=format&fit=crop&q=80',
        'description' => 'Kushtia tiler khaja, homemade coconut naru, batasa, and sun-dried kumro bori.'
    ],
    [
        'id' => 10,
        'department' => 'Food',
        'name' => 'Dry Fruits, Nuts & Spices',
        'name_bn' => 'ড্রাই ফ্রুটস, বাদাম ও খাঁটি মশলা',
        'slug' => 'dryfruits-spices',
        'icon' => 'bi-egg-fried',
        'image' => 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=500&auto=format&fit=crop&q=80',
        'description' => 'Almonds, cashews, pistachios, dates, homemade spicy mango pickles, and whole spices.'
    ],

    // --- 3. ART & ACCESSORIES ---
    [
        'id' => 11,
        'department' => 'Art & Accessories',
        'name' => 'Bags, Wallets & Leather',
        'name_bn' => 'ব্যাগ, ওয়ালেট ও লেদার সামগ্রী',
        'slug' => 'bags-wallets-leather',
        'icon' => 'bi-bag-check',
        'image' => 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=500&auto=format&fit=crop&q=80',
        'description' => 'Full-grain leather wallets, canvas backpacks, stylish clutches, and eco jute totes.'
    ],
    [
        'id' => 12,
        'department' => 'Art & Accessories',
        'name' => 'Watches & Eyewear',
        'name_bn' => 'ঘড়ি ও সানগ্লাস',
        'slug' => 'watches-eyewear',
        'icon' => 'bi-watch',
        'image' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=500&auto=format&fit=crop&q=80',
        'description' => 'Classic minimalist analog watches, chronographs, polarized sunglasses, and UV frames.'
    ],
    [
        'id' => 13,
        'department' => 'Art & Accessories',
        'name' => 'Jewelry & Personal Styling',
        'name_bn' => 'হ্যান্ডমেড ও মডার্ন জুয়েলারি',
        'slug' => 'jewelry-styling',
        'icon' => 'bi-gem',
        'image' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=500&auto=format&fit=crop&q=80',
        'description' => 'Minimalist silver chains, handcrafted terracotta earrings, brass choker necklaces.'
    ],
    [
        'id' => 14,
        'department' => 'Art & Accessories',
        'name' => 'Wall Art, Paintings & Prints',
        'name_bn' => 'দেয়াল শিল্প, পেইন্টিং ও ফ্রেম',
        'slug' => 'wall-art-paintings',
        'icon' => 'bi-palette',
        'image' => 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?w=500&auto=format&fit=crop&q=80',
        'description' => 'Modern canvas abstracts, vibrant Dhaka rickshaw pop-art, and folk wall hangings.'
    ],
    [
        'id' => 15,
        'department' => 'Art & Accessories',
        'name' => 'Home Décor, Living & Ceramics',
        'name_bn' => 'হোম ডেকোর ও সিরামিক ক্রাফট',
        'slug' => 'home-decor-ceramics',
        'icon' => 'bi-house-heart',
        'image' => 'https://images.unsplash.com/photo-1578749556568-bc2c40e68b61?w=500&auto=format&fit=crop&q=80',
        'description' => 'Terracotta planters, ceramic coffee mugs, woven jute floor rugs, and bamboo accents.'
    ],
];

$catStmt = $pdo->prepare("INSERT INTO `categories` (`id`, `department`, `name`, `name_bn`, `slug`, `icon`, `image`, `description`, `is_featured`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
foreach ($categories as $c) {
    $catStmt->execute([
        $c['id'],
        $c['department'],
        $c['name'],
        $c['name_bn'],
        $c['slug'],
        $c['icon'],
        $c['image'],
        $c['description']
    ]);
}
echo "✓ 15 Categories seeded successfully!\n";

echo "3. Seeding Universal & Traditional Products across all categories...\n";

// We have 3 sellers:
// 1 = Sonargaon Jamdani Kutir (Clothes & Textiles)
// 2 = Bijoypur Terracotta & Clay Arts (Art, Living & Décor)
// 3 = Sundarbans Wild Organics & Pure Honey (Food & Pantry)

$products = [
    // --- CLOTHES PRODUCTS ---
    [
        'seller_id' => 1,
        'category_id' => 1, // Men's Modern & Casuals
        'name' => 'Premium Heavyweight Cotton Crewneck T-Shirt (Midnight Navy)',
        'name_bn' => 'প্রিমিয়াম কটন ক্রু-নেক টি-শার্ট (নেভি ব্লু)',
        'slug' => 'premium-cotton-crewneck-tshirt-navy',
        'price' => 750.00,
        'sale_price' => 650.00,
        'unit' => 'piece',
        'short_description' => '100% combed breathable cotton with modern tailored fit, anti-shrink pre-wash.',
        'description' => 'Crafted for daily effortless style, this heavy-weight combed cotton tee offers supreme breathability, durable double-needle stitching, and a clean minimalist aesthetic.',
        'featured_image' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Dhaka',
        'is_featured' => 1,
        'is_flash_deal' => 1
    ],
    [
        'seller_id' => 1,
        'category_id' => 2, // Men's Ethnic & Festive
        'name' => 'Handcrafted Silk-Cotton Festive Panjabi with Zari Work',
        'name_bn' => 'হাতে তৈরি জরি কাজের সিল্ক-কটন পাঞ্জাবি',
        'slug' => 'silk-cotton-festive-panjabi-zari',
        'price' => 3800.00,
        'sale_price' => 3400.00,
        'unit' => 'piece',
        'short_description' => 'Traditional tailored cut with subtle neck and cuff hand embroidery for celebrations.',
        'description' => 'An ode to classic celebratory attire. Blended mulberry silk and cotton fabric ensures cool comfort during long festive hours with hand-detailed Zari motifs on the placket.',
        'featured_image' => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Rajshahi',
        'is_featured' => 1,
        'is_flash_deal' => 0
    ],
    [
        'seller_id' => 1,
        'category_id' => 3, // Women's Western & Fusion
        'name' => 'Bohemian Tiered Maxi Dress with Floral Print',
        'name_bn' => 'বোহেমিয়ান ফ্লোরাল টিয়ার্ড ম্যাক্সি ড্রেস',
        'slug' => 'bohemian-tiered-maxi-dress-floral',
        'price' => 2450.00,
        'sale_price' => 2100.00,
        'unit' => 'piece',
        'short_description' => 'Airy breathable georgette blend with flowing silhouette and subtle belt accent.',
        'description' => 'Effortlessly feminine and universally flattering. Features delicate botanical prints, gentle tiered ruffles, and a soft inner lining perfect for warm-weather outings.',
        'featured_image' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Dhaka',
        'is_featured' => 1,
        'is_flash_deal' => 0
    ],
    [
        'seller_id' => 1,
        'category_id' => 4, // Traditional Sarees & Ethnic
        'name' => 'Original Sonargaon Handwoven Royal Dhakai Jamdani Saree (84 Count)',
        'name_bn' => 'আসল সোনারগাঁও রয়েল ঢাকাই জামদানি শাড়ি (৮৪ কাউন্ট)',
        'slug' => 'original-sonargaon-royal-dhakai-jamdani-saree',
        'price' => 16500.00,
        'sale_price' => 14500.00,
        'unit' => 'piece',
        'short_description' => 'UNESCO-recognized master handwoven Jamdani with intricate floral jaal motifs.',
        'description' => 'Woven by certified hereditary master artisans of Sonargaon over 45 painstaking days. Made from 84 count fine cotton yarn with traditional floral jaal geometric motifs in ivory and gold thread.',
        'featured_image' => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Narayanganj',
        'is_featured' => 1,
        'is_flash_deal' => 1
    ],
    [
        'seller_id' => 1,
        'category_id' => 5, // Kids & Baby Fashion
        'name' => 'Organic Soft Handloom Cotton Baby Romper & Cap Set',
        'name_bn' => 'অর্গানিক সফট হ্যান্ডলুম কটন বেবি রম্পার ও ক্যাপ',
        'slug' => 'organic-cotton-baby-romper-cap',
        'price' => 890.00,
        'sale_price' => 750.00,
        'unit' => 'set',
        'short_description' => 'Chemical-free natural dyed breathable cotton, snap buttons for easy dressing.',
        'description' => 'Gentle on delicate baby skin. Hand-stitched with ultra-soft unbleached organic cotton for cozy naps and playful days.',
        'featured_image' => 'https://images.unsplash.com/photo-1622290291468-a28f7a7dc6a8?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Cumilla',
        'is_featured' => 0,
        'is_flash_deal' => 0
    ],

    // --- FOOD PRODUCTS ---
    [
        'seller_id' => 3,
        'category_id' => 6, // Pantry Staples & Pure Organics
        'name' => 'Pure Raw Sundarbans Wild Mangrove Honey (Khalisha Flower 1000g)',
        'name_bn' => 'খাঁটি সুন্দরবনের খলিসা ফুলের মধু (১০০০ গ্রাম)',
        'slug' => 'pure-sundarbans-wild-honey-1000g',
        'price' => 1950.00,
        'sale_price' => 1750.00,
        'unit' => 'jar',
        'short_description' => '100% raw unpasteurized wild honeycomb nectar hand-harvested by traditional Mawalis.',
        'description' => 'Collected deep within the mangrove forests of the Sundarbans during peak Khalisha blossom season. Naturally antibiotic, rich in pollen enzymes, and free from any added syrup.',
        'featured_image' => 'https://images.unsplash.com/photo-1587049352846-4a222e784d38?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Satkhira',
        'is_featured' => 1,
        'is_flash_deal' => 1
    ],
    [
        'seller_id' => 3,
        'category_id' => 6, // Pantry Staples
        'name' => 'Cold-Pressed Traditional Wooden Ghani Mustard Oil (2 Liters)',
        'name_bn' => 'কাঠের ঘানিতে ভাঙা খাঁটি সরিষার তেল (২ লিটার)',
        'slug' => 'cold-pressed-wooden-ghani-mustard-oil-2l',
        'price' => 850.00,
        'sale_price' => 780.00,
        'unit' => 'bottle',
        'short_description' => 'Extracted slowly on wooden ghani without heat to preserve natural pungency and pungin.',
        'description' => 'Real village flavor. Sourced from local Maghi mustard seeds, cold-pressed at low temperatures to ensure authentic pungent aroma and heart-healthy antioxidants.',
        'featured_image' => 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Kushtia',
        'is_featured' => 1,
        'is_flash_deal' => 0
    ],
    [
        'seller_id' => 3,
        'category_id' => 7, // Tea, Coffee & Beverages
        'name' => 'Sreemangal Single-Estate Whole Leaf Organic Black Tea (400g Tin)',
        'name_bn' => 'শ্রীমঙ্গল সিঙ্গেল-এস্টেট ব্ল্যাক টি (৪০০ গ্রাম)',
        'slug' => 'sreemangal-single-estate-black-tea',
        'price' => 650.00,
        'sale_price' => 580.00,
        'unit' => 'tin',
        'short_description' => 'First-flush high-grown whole tea leaves with rich amber liquor and muscatel notes.',
        'description' => 'Handpicked from the lush misty slopes of Sreemangal, the tea capital of Bangladesh. Delivers an invigorating cup with bright clarity, rich body, and gentle sweetness.',
        'featured_image' => 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Moulvibazar',
        'is_featured' => 1,
        'is_flash_deal' => 0
    ],
    [
        'seller_id' => 3,
        'category_id' => 8, // Snacks, Cookies & Chocolates
        'name' => 'Artisanal 70% Dark Chocolate Bar with Sea Salt & Roasted Almonds',
        'name_bn' => 'আর্টিসানাল ৭০% ডার্ক চকলেট বার উইথ সি-সল্ট ও আমন্ড',
        'slug' => 'artisanal-dark-chocolate-sea-salt-almonds',
        'price' => 420.00,
        'sale_price' => 380.00,
        'unit' => 'piece',
        'short_description' => 'Small-batch bean-to-bar chocolate crafted with organic cocoa and crunchy nuts.',
        'description' => 'Velvety smooth, deeply satisfying dark chocolate balanced with a touch of mineral sea salt crystals and slow-roasted Californian almonds.',
        'featured_image' => 'https://images.unsplash.com/photo-1548907040-4baa42d10919?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Dhaka',
        'is_featured' => 0,
        'is_flash_deal' => 1
    ],
    [
        'seller_id' => 3,
        'category_id' => 9, // Traditional Delicacies & Sweets
        'name' => 'Kushtia Heritage Crispy Sesame Tiler Khaja (500g Gift Box)',
        'name_bn' => 'কুষ্টিয়ার ঐতিহ্যবাহী মুচমুচে তিলের খাজা (৫০০ গ্রাম)',
        'slug' => 'kushtia-crispy-tiler-khaja-500g',
        'price' => 380.00,
        'sale_price' => 320.00,
        'unit' => 'box',
        'short_description' => 'Authentic paper-thin layered sesame wafers infused with pure sugarcane molasses.',
        'description' => 'A century-old confection from Kushtia. Incredibly crisp, loaded with toasted white sesame seeds and hand-pulled molasses that melts in the mouth.',
        'featured_image' => 'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Kushtia',
        'is_featured' => 1,
        'is_flash_deal' => 0
    ],
    [
        'seller_id' => 3,
        'category_id' => 10, // Dry Fruits, Nuts & Spices
        'name' => 'Premium Roasted Jumbo Cashew Nuts & California Almonds Mix (500g)',
        'name_bn' => 'প্রিমিয়াম রোস্টেড কাজু ও কাঠবাদাম মিক্স (৫০০ গ্রাম)',
        'slug' => 'premium-roasted-cashew-almond-mix-500g',
        'price' => 980.00,
        'sale_price' => 890.00,
        'unit' => 'jar',
        'short_description' => 'Lightly salted slow-roasted whole nuts packed in airtight reusable glass jar.',
        'description' => 'Healthy daily energy boost. Premium W240 whole cashews and non-pareil almonds, oven-roasted to golden crunchiness without hydrogenated oils.',
        'featured_image' => 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Chattogram',
        'is_featured' => 0,
        'is_flash_deal' => 0
    ],

    // --- ART & ACCESSORIES PRODUCTS ---
    [
        'seller_id' => 2,
        'category_id' => 11, // Bags, Wallets & Leather
        'name' => 'Hand-Stitched Full-Grain Leather Bi-Fold Wallet (Vintage Tan)',
        'name_bn' => 'হাতে সেলাই করা জেনুইন লেদার ওয়ালেট (ট্যান)',
        'slug' => 'hand-stitched-leather-bifold-wallet-tan',
        'price' => 1650.00,
        'sale_price' => 1450.00,
        'unit' => 'piece',
        'short_description' => '100% genuine Bangladeshi vegetable-tanned cowhide with RFID blocking protection.',
        'description' => 'Built to age with an elegant patina. Features 8 card slots, dual cash compartments, and heavy waxed thread hand-stitching guaranteed for years of rugged use.',
        'featured_image' => 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Dhaka',
        'is_featured' => 1,
        'is_flash_deal' => 1
    ],
    [
        'seller_id' => 2,
        'category_id' => 12, // Watches & Eyewear
        'name' => 'Minimalist Bauhaus Slim Sapphire Watch with Leather Strap',
        'name_bn' => 'মিনিমালিস্ট স্লিম অ্যানালগ ঘড়ি (লেদার স্ট্র্যাপ)',
        'slug' => 'minimalist-bauhaus-slim-watch-leather',
        'price' => 3200.00,
        'sale_price' => 2850.00,
        'unit' => 'piece',
        'short_description' => 'Ultra-thin surgical stainless steel case with Japanese quartz movement and scratch-resistant glass.',
        'description' => 'Refined modern simplicity. Crisp white dial with ultra-slim hour markers, water-resistant to 30 meters, accompanied by genuine calfskin strap.',
        'featured_image' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Dhaka',
        'is_featured' => 1,
        'is_flash_deal' => 0
    ],
    [
        'seller_id' => 2,
        'category_id' => 13, // Jewelry & Personal Styling
        'name' => 'Bijoypur Hand-Painted Terracotta Choker & Earring Set',
        'name_bn' => 'বিজয়পুর হাতে আঁকা পোড়ামাটির চোকার ও কানের দুল',
        'slug' => 'bijoypur-handpainted-terracotta-jewelry-set',
        'price' => 1100.00,
        'sale_price' => 950.00,
        'unit' => 'set',
        'short_description' => 'Baked red clay beads with ethnic folk motifs and adjustable braided cord.',
        'description' => 'Created by female pottery artisans in Cumilla. Lightweight kiln-fired natural clay hand-painted in traditional folk colors, sealed with matte protective coating.',
        'featured_image' => 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Cumilla',
        'is_featured' => 1,
        'is_flash_deal' => 0
    ],
    [
        'seller_id' => 2,
        'category_id' => 14, // Wall Art, Paintings & Prints
        'name' => 'Framed Pop-Art Dhaka Rickshaw Painting Canvas (Peacock & Floral Motif)',
        'name_bn' => 'ফ্রেমড পপ-আর্ট ঢাকা রিকশা পেইন্টিং (ময়ূর ও ফুল)',
        'slug' => 'framed-dhaka-rickshaw-painting-canvas-peacock',
        'price' => 2400.00,
        'sale_price' => 2100.00,
        'unit' => 'piece',
        'short_description' => 'Hand-painted enamel by authentic old Dhaka Ustad rickshaw artists on solid wooden frame.',
        'description' => 'UNESCO-recognized heritage rickshaw art. Features the iconic vivid neon peacock and floral motifs with glossy lacquer finish, ready to hang and liven up any modern living room.',
        'featured_image' => 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Dhaka',
        'is_featured' => 1,
        'is_flash_deal' => 0
    ],
    [
        'seller_id' => 2,
        'category_id' => 15, // Home Décor, Living & Ceramics
        'name' => 'Artisanal Braided Golden Jute Area Floor Rug (4ft Round)',
        'name_bn' => 'হাতে বোনা সোনালী পাটের ফ্লোর রাগ (৪ ফুট গোলাকার)',
        'slug' => 'braided-golden-jute-round-rug-4ft',
        'price' => 2800.00,
        'sale_price' => 2450.00,
        'unit' => 'piece',
        'short_description' => '100% natural biodegradable golden jute fiber tightly hand-braided for modern boho homes.',
        'description' => 'Eco-friendly and durable. Handcrafted in Faridpur by village craft clusters, this circular rug adds warm natural texture, rustic warmth, and timeless charm to bedrooms or living spaces.',
        'featured_image' => 'https://images.unsplash.com/photo-1578749556568-bc2c40e68b61?w=600&auto=format&fit=crop&q=80',
        'district_origin' => 'Faridpur',
        'is_featured' => 1,
        'is_flash_deal' => 1
    ]
];

$prodStmt = $pdo->prepare("INSERT INTO `products` 
    (`seller_id`, `category_id`, `name`, `name_bn`, `slug`, `price`, `sale_price`, `stock_quantity`, `unit`, `short_description`, `description`, `featured_image`, `district_origin`, `is_featured`, `is_flash_deal`, `is_active`) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 25, ?, ?, ?, ?, ?, ?, ?, 1)");

foreach ($products as $p) {
    $prodStmt->execute([
        $p['seller_id'],
        $p['category_id'],
        $p['name'],
        $p['name_bn'],
        $p['slug'],
        $p['price'],
        $p['sale_price'],
        $p['unit'],
        $p['short_description'],
        $p['description'],
        $p['featured_image'],
        $p['district_origin'],
        $p['is_featured'],
        $p['is_flash_deal']
    ]);
}

echo "✓ 15 Universal & Traditional Products seeded successfully!\n";
echo "ALL DONE!\n";
