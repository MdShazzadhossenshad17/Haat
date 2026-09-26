# 🛍️ HAAT — Global • Regional • Artisanal

> **HAAT** is a full-featured, multi-vendor artisanal e-commerce web platform celebrating Bangladeshi craft heritage. It directly connects rural master weavers, terracotta potters, organic wild honey harvesters, and indigenous craftspeople with modern national and global buyers.

---

## 🌟 Key Highlights & Architecture

- **Multi-Vendor Ecosystem**: Unified platform supporting three distinct user roles:
  - 👑 **Super Administrator**: Platform oversight, vendor management, categories/departments, orders, and coupons.
  - 🎨 **Artisan Sellers (Vendors)**: Workshop dashboard, inventory management, product listings, fulfillment tracking, payout setup, and buyer messaging.
  - 🛒 **Customers (Buyers)**: Departmental browsing, multi-attribute search, wishlists, cart & coupon discounts, courier parcel tracking, and artisan direct messaging.
- **Dynamic Live Messaging Suite**:
  - Split-screen real-time messenger connecting buyers and workshop artisans.
  - Real-time conversation polling (3.5s interval) without full page reloads.
  - Quick artisan reply templates (*Assalamu Alaikum*, *Dispatched via courier*, *Crafting in progress*, *Thank you*).
  - Unread badge counters, order reference tags, and online/offline status indicators.
- **Interactive Multi-Step Order Tracking**:
  - Live milestone timeline powered by `order_tracking_events`.
  - Visual status progression: *Pending → Processing / Crafting → Shipped (Courier Dispatch) → Delivered*.
  - Delivery courier partner integration metadata (e.g., Pathao Courier consignment tracking).
- **Categorized Department Hierarchy**:
  - **Clothes**: Men's Modern & Casuals, Men's Ethnic & Festive, Women's Western & Fusion, Traditional Sarees & Ethnic, Kids & Baby Fashion.
  - **Food**: Pantry Staples & Pure Organics, Tea, Coffee & Beverages, Snacks, Cookies & Chocolates, Traditional Delicacies & Sweets, Dry Fruits, Nuts & Spices.
  - **Art & Accessories**: Handmade Terracotta Pottery, Home Decor, Jewelry, Leather & Footwear, Traditional Brass, Metal & Woodcraft.
- **Robust Database Management (DBMS)**:
  - 100% portable, standard SQL schema in [`db.sql`](db.sql) with 13 relational tables, cascading foreign keys, indexes, and seeded records.
  - Pre-hashed passwords generated via PHP `password_hash()` for instant login upon import.

---

## 🛠️ Technology Stack

| Layer | Technology |
|---|---|
| **Frontend** | Vanilla HTML5, Semantic Elements, Modern CSS3 (Custom design system with artisanal color palette, flexbox/grid), Vanilla JavaScript (ES6+ Fetch API, Polling) |
| **Backend** | Native PHP (Procedural & Clean MVC patterns, Session Authentication, Secure Prepared Statements) |
| **Database** | MySQL / MariaDB (13 Relational Tables, UTF-8 MB4 Unicode) |
| **Icons & Media** | Bootstrap Icons (`cdn.jsdelivr.net`), Unsplash High-Resolution Artisanal Photography |
| **Server Environment** | Apache (XAMPP / WAMP / LAMP / Localhost) |

---

## 🗄️ Database Structure (`db.sql`)

The entire application runs on 13 interconnected tables defined in [`db.sql`](db.sql):

```
haat_db
├── users                     (Admin, Vendor, Customer accounts & credentials)
├── sellers                   (Artisan workshops, verification, payouts & ratings)
├── categories                (15 Categories across 3 Departments)
├── brands                    (Artisanal brands & manufacturer tags)
├── products                  (Inventory items, pricing, discounts, district origins)
├── product_reviews           (Customer star ratings and feedback comments)
├── orders                    (Customer orders, billing, shipping address, courier info)
├── order_items               (Per-vendor line items & individual fulfillment status)
├── order_tracking_events     (Real-time chronological courier & workshop timeline)
├── wishlists                 (Customer saved items)
├── coupons                   (Discount voucher codes, percent/flat rates, thresholds)
├── messages                  (Artisan-to-customer & customer-to-artisan live chat stream)
└── notifications             (System alerts, category broadcast announcements)
```

---

## 👥 Demo Accounts (Ready to Test)

All accounts are pre-seeded in [`db.sql`](db.sql). The passwords are ready out-of-the-box:

| Role | Account Name | Email | Password |
|---|---|---|---|
| **Admin** | HAAT Chief Administrator | `admin@haat.com.bd` | `Admin@123` |
| **Seller** | Sonargaon Jamdani Kutir | `jamdani@haat.com.bd` | `Seller@123` |
| **Seller** | Bijoypur Terracotta & Clay Arts | `pottery@haat.com.bd` | `Seller@123` |
| **Seller** | Sundarbans Wild Organics | `honey@haat.com.bd` | `Seller@123` |
| **Customer** | Tanvir Hossain | `customer@haat.com.bd` | `Customer@123` |

---

## 🚀 Installation & Local Setup

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (PHP 8.0+ and MySQL / MariaDB)
- Git

### Steps

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/<your-username>/haat-ecommerce.git
   ```
   *Or place the project inside your web server directory:*
   ```
   C:/xampp/htdocs/Haat/   (Windows)
   /var/www/html/Haat/      (Linux)
   ```

2. **Start Server**:
   - Open **XAMPP Control Panel**.
   - Start **Apache** and **MySQL**.

3. **Import Database**:
   - Open your browser and navigate to `http://localhost/phpmyadmin/`.
   - Create a database named `haat_db` (Optional — `db.sql` automatically creates it if missing).
   - Click **Import** → choose [`db.sql`](db.sql) → click **Import**.
   - *Or run via MySQL CLI:*
     ```bash
     mysql -u root -p < db.sql

  ### Migration: Add `allow_self_purchase` (if upgrading)

  If you are upgrading an existing database, run this non-destructive migration to add the per-seller setting:

  ```sql
  -- migrations/2026_09_25_add_allow_self_purchase.sql
  ALTER TABLE `sellers`
  ADD COLUMN `allow_self_purchase` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_verified`;
  ```

  Save the above SQL as `migrations/2026_09_25_add_allow_self_purchase.sql` and run it via `phpmyadmin` or MySQL CLI.
     ```

4. **Verify Configuration**:
   - Database connection settings are located in [`config/database.php`](config/database.php):
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'haat_db');
     ```

5. **Launch Application**:
   - Open your browser and visit:
     ```
     http://localhost/Haat/
     ```

---

## 📂 Project Directory Structure

```
Haat/
├── admin/                     # Super Administrator Portal
│   ├── index.php              # Admin overview & system metrics
│   ├── sellers.php            # Vendor workshop management
│   ├── orders.php             # Global order monitoring
│   └── categories.php         # Department & category controls
├── api/                       # Lightweight AJAX REST Endpoints
│   ├── cart.php               # Add, update, delete cart items
│   ├── messages.php           # Real-time live messaging (Buyer & Seller)
│   └── track.php              # Live order parcel tracking
├── assets/                    # Static Assets
│   ├── css/style.css          # Design system & responsive styles
│   ├── js/script.js           # Client UI interactions
│   └── images/                # Brand logos, avatars, craft graphics
├── config/                    # Database Configuration & Initializers
│   ├── db.php                 # PDO database connection
│   └── init_db.php            # Database seed script
├── customer/                  # Customer Account Portal
│   └── index.php              # Orders, profile, live chat with artisan
├── includes/                  # Reusable Layout Components
│   ├── functions.php          # Core helpers, authentication, session
│   ├── header.php             # Global navigation bar & search
│   ├── navbar.php             # Department category links
│   └── footer.php             # Footer & copyright
├── seller/                    # Artisan Seller / Vendor Dashboard
│   ├── index.php              # Products, inventory, orders, live chat
│   └── product-edit.php       # Product editor & media uploader
├── cart.php                   # Shopping cart page
├── checkout.php               # Multi-vendor checkout & bKash payment
├── db.sql                     # Full standalone database dump
├── index.php                  # Marketplace homepage
├── login.php                  # User & artisan authentication
├── logout.php                 # Session destroy & redirect
├── order-confirmation.php     # Post-purchase receipt & summary
├── product.php                # Product detail page & reviews
├── register.php               # Buyer & artisan seller registration
├── shop.php                   # Product catalog with filters & search
├── track-order.php            # Public order tracking timeline
└── vendor.php                 # Public artisan workshop storefront
```

---

## 📄 License & Heritage Note

This project is created to preserve, celebrate, and modernize traditional craftsmanship from districts across Bangladesh — from Narayanganj handloom Jamdani to Cumilla Bijoypur terracotta and Satkhira Sundarbans forest products.
