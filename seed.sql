-- =========================================================
-- HAAT! Demo Seed Data
-- Matches the mock data shown in the web app (app.js)
-- Password for ALL demo accounts: haat2026
-- Run this AFTER importing haat.sql schema
-- =========================================================

USE haat;

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM notifications;
DELETE FROM messages;
DELETE FROM product_reviews;
DELETE FROM delivery_tracking;
DELETE FROM payments;
DELETE FROM order_items;
DELETE FROM seller_orders;
DELETE FROM orders;
DELETE FROM riders;
DELETE FROM coupons;
DELETE FROM wishlists;
DELETE FROM cart;
DELETE FROM collections;
DELETE FROM inventory;
DELETE FROM products;
DELETE FROM brands;
DELETE FROM subcategories;
DELETE FROM categories;
DELETE FROM customer_addresses;
DELETE FROM stores;
DELETE FROM users;
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- 1. USERS (password: haat2026)
-- =========================================================
INSERT INTO users (id, name, email, password, phone, role, status) VALUES
(1, 'Rahim Sakib',                 'customer@haat.com.bd',        '$2y$10$KD5R1R.RASeXU2ATpAkHU.Oob3UGiqcFwvS1txtlQpA6DI3C0L2Ni', '01711223344', 'customer',  'active'),
(2, 'ABC Fashion Store',           'seller@abc-fashion.com',      '$2y$10$KD5R1R.RASeXU2ATpAkHU.Oob3UGiqcFwvS1txtlQpA6DI3C0L2Ni', '01811223344', 'seller',    'active'),
(3, 'XYZ Electronics & Gadgets',   'seller@xyz-electronics.com',  '$2y$10$KD5R1R.RASeXU2ATpAkHU.Oob3UGiqcFwvS1txtlQpA6DI3C0L2Ni', '01911223344', 'seller',    'active'),
(4, 'Admin',                       'admin@haat.com.bd',           '$2y$10$KD5R1R.RASeXU2ATpAkHU.Oob3UGiqcFwvS1txtlQpA6DI3C0L2Ni', '01511223344', 'admin',     'active'),
(5, 'HATEX',                       'logistics@hatex.com.bd',      '$2y$10$KD5R1R.RASeXU2ATpAkHU.Oob3UGiqcFwvS1txtlQpA6DI3C0L2Ni', '01611223344', 'logistics', 'active'),
(6, 'Tareq Ahmed',                 'tareq@hatex.com.bd',          '$2y$10$KD5R1R.RASeXU2ATpAkHU.Oob3UGiqcFwvS1txtlQpA6DI3C0L2Ni', '01822334455', 'logistics', 'active'),
(7, 'Sumon Mia',                   'sumon@hatex.com.bd',          '$2y$10$KD5R1R.RASeXU2ATpAkHU.Oob3UGiqcFwvS1txtlQpA6DI3C0L2Ni', '01933445566', 'logistics', 'active'),
(8, 'Fresh Harvest Haat',          'seller@fresh-harvest.com',    '$2y$10$KD5R1R.RASeXU2ATpAkHU.Oob3UGiqcFwvS1txtlQpA6DI3C0L2Ni', '01722334455', 'seller',    'active'),
(9, 'Dhamrai Bell Metal & Crafts', 'seller@dhamrai-crafts.com',   '$2y$10$KD5R1R.RASeXU2ATpAkHU.Oob3UGiqcFwvS1txtlQpA6DI3C0L2Ni', '01833445566', 'seller',    'active'),
-- Extra demo customers
(10, 'Tanzir Hasan',              'tanzir@example.com',           '$2y$10$KD5R1R.RASeXU2ATpAkHU.Oob3UGiqcFwvS1txtlQpA6DI3C0L2Ni', '01744556677', 'customer',  'active'),
(11, 'Nusrat Jahan',             'nusrat@example.com',            '$2y$10$KD5R1R.RASeXU2ATpAkHU.Oob3UGiqcFwvS1txtlQpA6DI3C0L2Ni', '01855667788', 'customer',  'active');

-- =========================================================
-- 2. STORES
-- =========================================================
INSERT INTO stores (id, user_id, store_name, store_slug, description, address, district, division,
                    postal_code, latitude, longitude, status, is_published) VALUES
(1, 2, 'ABC Fashion Store',           'abc-fashion',
   'Handcrafted Heritage Dhakai Jamdani, Premium Combed Cotton Panjabis & Artisan Wear.',
   'Shop 14, Noor Market, Farmgate', 'Dhaka', 'Dhaka', '1215', 23.757000, 90.389000, 'approved', 1),
(2, 3, 'XYZ Electronics & Gadgets',   'xyz-electronics',
   'Official flagship tech store: AMOLED smartwatches, ANC wireless earbuds & accessories.',
   'Plot 45, Wireless Gate, Mohakhali', 'Dhaka', 'Dhaka', '1212', 23.778000, 90.398000, 'approved', 1),
(3, 8, 'Fresh Harvest Haat',          'fresh-harvest',
   'Wild Sundarbans mangrove raw honey, Dinajpur aromatic Kalijira rice & organic cold-pressed oils.',
   'Bazar Road, Hemayetpur, Savar', 'Dhaka', 'Dhaka', '1340', 23.848000, 90.267000, 'approved', 1),
(4, 9, 'Dhamrai Bell Metal & Crafts', 'dhamrai-crafts',
   'Centuries-old lost-wax bell metal (Kasha) handicrafts, thali sets & brass decor.',
   'Rathkhola, Dhamrai Bazar', 'Dhaka', 'Dhaka', '1350', 23.918000, 90.210000, 'approved', 1);

-- =========================================================
-- 3. CUSTOMER ADDRESSES
-- =========================================================
INSERT INTO customer_addresses (id, user_id, label, name, phone, address, district, division,
                                 postal_code, latitude, longitude, is_default) VALUES
(1, 1, 'Home',   'Rahim Sakib', '01711223344', 'House 12, Road 5, Mirpur 10',
   'Dhaka', 'Dhaka', '1216', 23.806000, 90.368000, 1),
(2, 1, 'Office', 'Rahim Sakib', '01711223344', 'Level 5, Concord Tower, Gulshan 1',
   'Dhaka', 'Dhaka', '1212', 23.780000, 90.418000, 0);

-- =========================================================
-- 4. CATEGORIES
-- =========================================================
INSERT INTO categories (id, name, slug, description) VALUES
(1, 'Fashion & Apparel',            'fashion',     'Traditional and modern clothing, fabric and footwear'),
(2, 'Electronics & Gadgets',        'electronics', 'Consumer electronics, gadgets and accessories'),
(3, 'Groceries & Organic Food',     'groceries',   'Pure village harvests, organic groceries and spices'),
(4, 'Artisanal & Traditional Crafts','crafts',     'Heritage handicrafts, metalware and clay pottery');

-- =========================================================
-- 5. SUBCATEGORIES
-- =========================================================
INSERT INTO subcategories (id, category_id, name, slug) VALUES
(101, 1, "Men's Shirts & Panjabis",      'mens-shirts'),
(102, 1, "Women's Sarees & Kurtis",      'womens-sarees'),
(103, 1, 'Footwear & Sandals',           'footwear'),
(201, 2, 'Audio & Wireless Earbuds',     'audio'),
(202, 2, 'Smartwatches & Wearables',     'smartwatches'),
(203, 2, 'Computer Accessories',         'computer-accessories'),
(301, 3, 'Pure Honey & Organic Ghee',    'honey-ghee'),
(302, 3, 'Aromatic Rice & Pulses',       'rice-pulses'),
(303, 3, 'Village Spices & Dry Harvest', 'spices'),
(401, 4, 'Bell Metal & Brass Decor',     'bell-metal'),
(402, 4, 'Clay Pottery & Terracotta',    'clay-pottery');

-- =========================================================
-- 6. BRANDS
-- =========================================================
INSERT INTO brands (id, name, slug) VALUES
(1, 'Aarong Handloom',      'aarong'),
(2, 'Apex Footwear',        'apex'),
(3, 'TechZone Audio',       'techzone'),
(4, 'Sundarbans Natural',   'sundarbans'),
(5, 'Dhamrai Metal Heritage','dhamrai-metal');

-- =========================================================
-- 7. COLLECTIONS
-- =========================================================
INSERT INTO collections (id, store_id, name, slug) VALUES
(1, 1, 'Men''s Festive Heritage 2026', 'mens-festive'),
(2, 1, 'Dhakai Jamdani Elegance',     'jamdani-elegance'),
(3, 2, 'Next-Gen Wireless Sound',     'wireless-sound'),
(4, 3, 'Pure Forest Harvest',         'forest-harvest');

-- =========================================================
-- 8. PRODUCTS (image BLOBs left NULL — stored as URL in app.js)
-- =========================================================
INSERT INTO products (id, store_id, subcategory_id, brand_id, collection_id,
                      name, slug, sku, description, price, sale_price,
                      variant_1_name, variant_1_value,
                      variant_2_name, variant_2_value,
                      is_featured) VALUES
(1, 1, 101, 1, 1,
 'Premium Combed Cotton Semi-Fitting Panjabi', 'premium-cotton-panjabi', 'PAN-COT-01',
 '100% fine combed breathable cotton with subtle jacquard weave embroidery along the collar and placket. Perfect for Eid and celebrations.',
 2600.00, 2100.00, 'Color', 'Ivory White, Pastel Green', 'Size', 'M (40), L (42), XL (44)', 1),

(2, 1, 102, 1, 2,
 'Dhakai Muslin Jamdani Saree — Heritage Weave', 'dhakai-jamdani-saree', 'SAR-JAM-01',
 'Authentic handwoven Dhakai Jamdani — UNESCO Intangible Cultural Heritage. Premium 500-count thread density muslin saree with intricate floral motif.',
 8500.00, 7200.00, 'Color', 'Off White, Pale Gold', 'Blouse', 'Included (36–44 in)', 1),

(3, 2, 201, 3, 3,
 'TechZone ProBass ANC Wireless Earbuds', 'techzone-probass-anc', 'EAR-ANC-01',
 '40dB Active Noise Cancellation, 30-hour battery life, Hi-Res Audio certified LDAC codec. IP55 water resistant. Official Bangladesh warranty.',
 4500.00, 3800.00, 'Color', 'Midnight Black, Pearl White', NULL, NULL, 1),

(4, 2, 202, 3, NULL,
 'AMOLED Smart Fitness Watch Pro — 1.45" Display', 'amoled-smartwatch-pro', 'SWT-AMO-01',
 '1.45" Always-On AMOLED display, 200+ sport modes, GPS, SpO2, 7-day battery. Stainless steel body.',
 6800.00, 5500.00, 'Color', 'Graphite Black, Silver Steel', NULL, NULL, 1),

(5, 3, 301, 4, 4,
 'Pure Wild Sundarbans Mangrove Honey (500g Glass Jar)', 'sundarbans-mangrove-honey-500g', 'HON-SUN-01',
 '100% raw, unfiltered, unheated wild mangrove honey harvested by Sundarbans forest honey collectors. High antioxidant content.',
 850.00, NULL, 'Size', '500g Glass Jar', NULL, NULL, 1),

(6, 4, 401, 5, NULL,
 'Kasha Bell Metal Thali Dinner Set (6-Piece)', 'kasha-bell-metal-thali-set', 'MET-KAS-01',
 'Hand-forged traditional Kasha (bell metal) thali set from Dhamrai master artisans. Includes 1 large thali, 4 small bowls, 1 drinking glass.',
 4200.00, 3600.00, 'Finish', 'Natural Polish, Matte Antique', NULL, NULL, 1);

-- =========================================================
-- 9. INVENTORY
-- =========================================================
INSERT INTO inventory (product_id, quantity, reserved_quantity) VALUES
(1, 45, 2),
(2, 18, 1),
(3, 62, 3),
(4, 30, 1),
(5, 120, 2),
(6, 15, 0);

-- =========================================================
-- 10. COUPONS
-- =========================================================
INSERT INTO coupons (id, store_id, code, discount_type, discount_value,
                     min_order, max_discount, expiry_date, usage_limit, used_count, is_active) VALUES
(1, NULL, 'HAAT100',  'fixed',   100.00, 500.00,  NULL,    '2026-12-31', 500,  1, 1),
(2, NULL, 'TECH10',   'percent',  10.00,   0.00, 500.00,   '2026-12-31', 1000, 0, 1),
(3, 1,    'FASHION15','percent',  15.00, 1000.00, 800.00,  '2026-12-31', 200,  0, 1),
(4, 3,    'HONEY50',  'fixed',    50.00, 300.00,  NULL,    '2026-12-31', 300,  0, 1);

-- =========================================================
-- 13. ORDERS
-- =========================================================
INSERT INTO orders (id, order_number, user_id, coupon_id, total_amount, shipping_cost,
                    discount_amount, grand_total, order_status,
                    shipping_name, shipping_phone, shipping_address,
                    district, division, postal_code, notes, created_at) VALUES
(1, 'HAAT10234', 1, 1,
 4550.00, 130.00, 100.00, 4580.00, 'packaged',
 'Rahim Sakib', '01711223344', 'House 12, Road 5, Mirpur 10',
 'Dhaka', 'Dhaka', '1216', 'Please call before delivery.', '2026-09-29 10:15:00'),

(2, 'HAAT10198', 1, NULL,
 3800.00, 70.00, 0.00, 3870.00, 'delivered',
 'Rahim Sakib', '01711223344', 'Level 5, Concord Tower, Gulshan 1',
 'Dhaka', 'Dhaka', '1212', NULL, '2026-09-18 15:30:00'),

(3, 'HAAT10312', 1, NULL,
 2635.00, 0.00, 0.00, 2635.00, 'delivered',
 'Rahim Sakib', '01711223344', 'House 12, Road 5, Mirpur 10',
 'Dhaka', 'Dhaka', '1216', NULL, '2026-09-22 11:00:00');

-- =========================================================
-- 14. SELLER ORDERS
-- =========================================================
INSERT INTO seller_orders (id, order_id, store_id, seller_order_number,
                            subtotal, shipping_cost, discount_amount, seller_total, status) VALUES
(101, 1, 1, 'SO-101-ABC',  2100.00, 60.00, 100.00, 2060.00, 'packaged'),
(102, 1, 2, 'SO-102-XYZ',  2450.00, 70.00,   0.00, 2520.00, 'packaged'),
(103, 2, 2, 'SO-103-XYZ',  3800.00, 70.00,   0.00, 3870.00, 'delivered'),
(104, 3, 1, 'SO-104-ABC',  1785.00, 0.00,    0.00, 1785.00, 'delivered'),
(105, 3, 3, 'SO-105-FHH',   850.00, 0.00,    0.00,  850.00, 'delivered');

-- =========================================================
-- 15. ORDER ITEMS
-- =========================================================
INSERT INTO order_items (seller_order_id, product_id, product_name,
                          variant_name, variant_value, unit_price, quantity, subtotal) VALUES
(101, 1, 'Premium Combed Cotton Semi-Fitting Panjabi', 'Color: Ivory White, Size: L (42)', 'Ivory / 42', 2100.00, 1, 2100.00),
(102, 3, 'TechZone ProBass ANC Wireless Earbuds',      'Color: Midnight Black',            'Midnight Black', 2450.00, 1, 2450.00),
(103, 4, 'AMOLED Smart Fitness Watch Pro',              'Color: Graphite Black',            'Graphite Black', 3800.00, 1, 3800.00),
(104, 1, 'Premium Combed Cotton Semi-Fitting Panjabi', 'Ivory / 42',                       'Ivory / 42',     1785.00, 1, 1785.00),
(105, 5, 'Pure Wild Sundarbans Mangrove Honey (500g)', '500g Glass Jar',                   '500g',           850.00,  1, 850.00);

-- =========================================================
-- 16. PAYMENTS
-- =========================================================
INSERT INTO payments (order_id, method, transaction_reference, amount, status, paid_at) VALUES
(1, 'bkash',           'TRX819273648B',  4580.00, 'pending',  NULL),
(2, 'bkash',           'TRX726354819C',  3870.00, 'verified', '2026-09-18 16:10:00'),
(3, 'bkash',           'TRX918273645A',  2635.00, 'verified', '2026-09-22 14:05:00');

-- =========================================================
-- 17. RIDERS
-- =========================================================
INSERT INTO riders (id, user_id, status) VALUES
(1, 6, 'active'),
(2, 7, 'active');

-- =========================================================
-- 18. DELIVERY TRACKING
-- =========================================================
INSERT INTO delivery_tracking (seller_order_id, rider_id, status, location, latitude, longitude, note, updated_by) VALUES
(101, NULL, 'pending',          'HAAT Online Order Hub',          23.806, 90.368, 'Customer order placed successfully.',          1),
(101, NULL, 'order_accepted',   'ABC Fashion Store, Farmgate',    23.757, 90.389, 'Seller accepted order and verified stock.',     2),
(101, NULL, 'packaged',         'ABC Fashion Store, Farmgate',    23.757, 90.389, 'Seller packed parcel for HATEX pickup.',        2),
(103, 1,    'delivered',        'Gulshan 1, Customer Location',   23.780, 90.418, 'Package delivered to customer.',                6),
(104, 1,    'delivered',        'Mirpur 10, Customer Location',   23.806, 90.368, 'Package delivered to customer.',                6),
(105, 1,    'delivered',        'Mirpur 10, Customer Location',   23.806, 90.368, 'Package delivered. Honey received fresh.',      6);

-- =========================================================
-- 19. PRODUCT REVIEWS
-- =========================================================
INSERT INTO product_reviews (product_id, user_id, rating, comment, created_at) VALUES
(1, 1,  5, 'Superb fabric quality! The jacquard texture on the collar looks very sophisticated and pure cotton feels breathable.', '2026-09-20 10:00:00'),
(3, 10, 5, 'Noise cancellation is surprisingly good for this price segment. Bass response is punchy.', '2026-09-24 14:20:00'),
(5, 11, 5, 'Absolutely pure honey! No added sugar taste, thick consistency, great for mornings. Fast delivery too.', '2026-09-25 09:30:00'),
(2, 10, 4, 'Beautiful Jamdani weave — exactly as described. Delivery was well-packaged.', '2026-09-26 16:00:00'),
(4, 1,  5, 'AMOLED display is crisp and always-on feature is super useful. Battery lasts well over a week.', '2026-09-28 11:45:00'),
(6, 11, 4, 'Well-crafted set. The kasha metal has a beautiful ring when tapped. Authentic artisan work.', '2026-09-29 08:20:00');

-- =========================================================
-- 20. MESSAGES
-- =========================================================
INSERT INTO messages (order_id, sender_id, sender_role, receiver_id, receiver_role, message, is_read, created_at) VALUES
(1, 2, 'seller',   1, 'customer', 'Assalamu Alaikum! Welcome to ABC Fashion Store. Thank you for reaching out to us! How can we help you today?', 1, '2026-09-29 10:29:00'),
(1, 1, 'customer', 2, 'seller',   'Hello, when will my order SO-101-ABC ship?', 1, '2026-09-29 10:30:00'),
(1, 2, 'seller',   1, 'customer', 'Hello Rahim! Your Panjabi has been packaged and is ready for HATEX pickup. You can track it live in your dashboard.', 1, '2026-09-29 10:45:00'),
(1, 6, 'logistics',1, 'customer', 'Hello! I am Tareq Ahmed from HATEX. Once I pick up your parcel, I will head straight to Mirpur 10.', 1, '2026-09-29 12:00:00'),
(1, 4, 'admin',    2, 'seller',   'Hello ABC Fashion Store! Your store metrics look great this month. Please ensure all products have accurate stock counts.', 0, '2026-09-29 09:00:00');

-- =========================================================
-- 21. NOTIFICATIONS
-- =========================================================
INSERT INTO notifications (user_id, order_id, title, message, type, is_read, created_at) VALUES
(1, 1, 'Order Placed Successfully',  'Your order #HAAT10234 for ৳4,580 has been placed.',                             'order_placed',   1, '2026-09-29 10:15:00'),
(1, 1, 'Order Status Updated',       'Your order #SO-101-ABC is now: PACKAGED',                                       'order_status',   0, '2026-09-29 11:20:00'),
(2, 1, 'New Order Received',         'You have a new order #SO-101-ABC — ৳2,060.00',                                  'new_order',      1, '2026-09-29 10:15:00'),
(1, 2, 'Payment Verified',           'Your payment has been verified and your order is confirmed.',                    'payment_verified',1,'2026-09-18 16:10:00'),
(1, 3, 'Order Delivered',            'Your order #SO-105-FHH has been delivered. Thank you for shopping at HAAT!',    'order_status',   1, '2026-09-22 16:00:00'),
(10,NULL,'Welcome to HAAT!',         'Your account is ready. Start exploring thousands of products on HAAT.',          'system',         0, '2026-09-15 10:00:00');

-- =========================================================
-- CART (demo cart for customer)
-- =========================================================
INSERT INTO cart (user_id, product_id, quantity, variant_name, variant_value) VALUES
(1, 1, 1, 'Color', 'Ivory White'),
(1, 3, 1, 'Color', 'Midnight Black');

-- =========================================================
-- WISHLISTS (demo wishlist for customer)
-- =========================================================
INSERT INTO wishlists (user_id, product_id) VALUES
(1, 2),
(1, 5);

SELECT '✅ HAAT! demo seed data inserted successfully!' AS status;
SELECT CONCAT('Users: ', COUNT(*)) AS info FROM users
UNION ALL SELECT CONCAT('Stores: ', COUNT(*)) FROM stores
UNION ALL SELECT CONCAT('Products: ', COUNT(*)) FROM products
UNION ALL SELECT CONCAT('Orders: ', COUNT(*)) FROM orders
UNION ALL SELECT CONCAT('Reviews: ', COUNT(*)) FROM product_reviews;
