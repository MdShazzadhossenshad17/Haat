<?php
/**
 * Superadmin Control Portal Dashboard — Unified Dynamic Single-Page Control Center
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$user = currentUser();

// ==========================================
// 1. POST ACTION: CREATE PROMOTION / COUPON
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_coupon'])) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $title = trim($_POST['title'] ?? '');
    $sellerId = !empty($_POST['seller_id']) ? (int)$_POST['seller_id'] : null;
    $discType = $_POST['discount_type'] ?? 'percent';
    $discVal = (float)($_POST['discount_value'] ?? 10);
    $discPercent = ($discType === 'percent') ? (int)$discVal : 0;
    $minOrder = (float)($_POST['min_order'] ?? 0);
    $maxDiscount = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : 500.00;
    $expiry = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    if (!empty($code) && $discVal > 0) {
        $ins = $db->prepare("INSERT INTO `coupons` 
            (`seller_id`, `code`, `title`, `discount_type`, `discount_value`, `discount_percent`, `min_order`, `max_discount`, `expiry_date`, `is_active`, `created_at`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE 
            `seller_id` = VALUES(`seller_id`), `title` = VALUES(`title`), `discount_type` = VALUES(`discount_type`), 
            `discount_value` = VALUES(`discount_value`), `discount_percent` = VALUES(`discount_percent`), 
            `min_order` = VALUES(`min_order`), `max_discount` = VALUES(`max_discount`), `expiry_date` = VALUES(`expiry_date`), `is_active` = 1");
        $ins->execute([$sellerId, $code, $title, $discType, $discVal, $discPercent, $minOrder, $maxDiscount, $expiry]);

        setFlash('success', 'Promotion offer ' . htmlspecialchars($code) . ' created successfully.');
        header('Location: ' . BASE_URL . 'admin/#promotions');
        exit;
    } else {
        setFlash('error', 'Please provide a valid coupon code and discount value.');
        header('Location: ' . BASE_URL . 'admin/#promotions');
        exit;
    }
}

// ==========================================
// 2. GET ACTION: TOGGLE COUPON STATUS
// ==========================================
if (isset($_GET['toggle_coupon'])) {
    $cid = (int)$_GET['toggle_coupon'];
    $db->prepare("UPDATE `coupons` SET `is_active` = NOT `is_active` WHERE `id` = ?")->execute([$cid]);
    setFlash('success', 'Promotion status updated.');
    header('Location: ' . BASE_URL . 'admin/#promotions');
    exit;
}

// ==========================================
// 3. GET ACTION: DELETE COUPON
// ==========================================
if (isset($_GET['delete_coupon'])) {
    $cid = (int)$_GET['delete_coupon'];
    $db->prepare("DELETE FROM `coupons` WHERE `id` = ?")->execute([$cid]);
    setFlash('success', 'Promotion removed.');
    header('Location: ' . BASE_URL . 'admin/#promotions');
    exit;
}

// ==========================================
// 4. GET ACTION: TOGGLE SELLER VERIFICATION
// ==========================================
if (isset($_GET['toggle_verify'])) {
    $sid = (int)$_GET['toggle_verify'];
    $db->prepare("UPDATE `sellers` SET `is_verified` = NOT `is_verified` WHERE `id` = ?")->execute([$sid]);
    setFlash('success', 'Artisan workshop GI verification badge updated.');
    header('Location: ' . BASE_URL . 'admin/#sellers');
    exit;
}

// ==========================================
// 5. GET ACTION: TOGGLE SELLER ACCOUNT STATUS
// ==========================================
if (isset($_GET['toggle_status'])) {
    $sid = (int)$_GET['toggle_status'];
    $currentStatus = $db->query("SELECT status FROM `sellers` WHERE `id` = {$sid}")->fetchColumn();
    $newStatus = ($currentStatus === 'active') ? 'suspended' : 'active';
    $db->prepare("UPDATE `sellers` SET `status` = ? WHERE `id` = ?")->execute([$newStatus, $sid]);
    setFlash('success', 'Artisan seller status updated to ' . ucfirst($newStatus) . '.');
    header('Location: ' . BASE_URL . 'admin/#sellers');
    exit;
}

// ==========================================
// 6. GET ACTION: TOGGLE PRODUCT FEATURED
// ==========================================
if (isset($_GET['toggle_featured'])) {
    $pid = (int)$_GET['toggle_featured'];
    $db->prepare("UPDATE `products` SET `is_featured` = NOT `is_featured` WHERE `id` = ?")->execute([$pid]);
    setFlash('success', 'Featured craft highlight updated.');
    header('Location: ' . BASE_URL . 'admin/#products');
    exit;
}

// ==========================================
// 7. GET ACTION: TOGGLE PRODUCT FLASH DEAL
// ==========================================
if (isset($_GET['toggle_flash'])) {
    $pid = (int)$_GET['toggle_flash'];
    $db->prepare("UPDATE `products` SET `is_flash_deal` = NOT `is_flash_deal` WHERE `id` = ?")->execute([$pid]);
    setFlash('success', 'Flash deal listing status updated.');
    header('Location: ' . BASE_URL . 'admin/#products');
    exit;
}

// ==========================================
// 8. GET ACTION: DELETE PRODUCT
// ==========================================
if (isset($_GET['delete_product'])) {
    $pid = (int)$_GET['delete_product'];
    $db->prepare("DELETE FROM `products` WHERE `id` = ?")->execute([$pid]);
    setFlash('success', 'Product permanently removed from catalog.');
    header('Location: ' . BASE_URL . 'admin/#products');
    exit;
}

// ==========================================
// 9. POST ACTION: CREATE NEW CATEGORY & BROADCAST TO ALL SELLERS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_category'])) {
    $department = trim($_POST['department'] ?? 'Clothes');
    $name = trim($_POST['name'] ?? '');
    $nameBn = trim($_POST['name_bn'] ?? '');
    $icon = trim($_POST['icon'] ?? 'bi-stars');
    $image = trim($_POST['image'] ?? 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=600&q=80');
    $desc = trim($_POST['description'] ?? '');

    // Support category image upload
    if (!empty($_FILES['category_file']['name'])) {
        $targetDir = __DIR__ . '/../assets/uploads/categories/';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['category_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'svg'])) {
            $fileName = 'cat_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['category_file']['tmp_name'], $targetDir . $fileName)) {
                $image = BASE_URL . 'assets/uploads/categories/' . $fileName;
            }
        }
    }

    if (!empty($name)) {
        $cleanSlug = trim(preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($name)), '-');
        if (empty($cleanSlug)) {
            $cleanSlug = 'craft-category';
        }
        $slug = $cleanSlug . '-' . rand(100, 999);
        $ins = $db->prepare("INSERT INTO `categories` (`department`, `name`, `name_bn`, `slug`, `icon`, `image`, `description`, `is_featured`) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $ins->execute([$department, $name, $nameBn, $slug, $icon, $image, $desc]);
        $newCatId = $db->lastInsertId();

        // Broadcast notification to all active sellers
        $allSellers = $db->query("SELECT id, user_id, shop_name FROM `sellers`")->fetchAll();
        $notifStmt = $db->prepare("INSERT INTO `notifications` (`user_id`, `seller_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, 'category_added', ?, 0, NOW())");
        
        $notifTitle = "New Category Launched: " . $name;
        $notifMsg = "Admin has launched a new craft category: \"{$name}\" ({$department}). You can now list and add your handcrafted products under this category in your Inventory.";
        $notifLink = "seller/#inventory";

        foreach ($allSellers as $sRow) {
            $notifStmt->execute([
                $sRow['user_id'],
                $sRow['id'],
                $notifTitle,
                $notifMsg,
                $notifLink
            ]);
        }

        setFlash('success', 'New craft category "' . htmlspecialchars($name) . '" created and broadcasted to all ' . count($allSellers) . ' seller workshops!');
        header('Location: ' . BASE_URL . 'admin/#categories');
        exit;
    } else {
        setFlash('error', 'Please enter a valid category name.');
        header('Location: ' . BASE_URL . 'admin/#categories');
        exit;
    }
}

// ==========================================
// 9b. GET ACTION: DELETE CATEGORY
// ==========================================
if (isset($_GET['delete_category'])) {
    $catId = (int)$_GET['delete_category'];
    $db->prepare("DELETE FROM `categories` WHERE `id` = ?")->execute([$catId]);
    setFlash('success', 'Craft category removed successfully.');
    header('Location: ' . BASE_URL . 'admin/#categories');
    exit;
}

// ==========================================
// 9c. POST ACTION: ADMIN UPDATE ORDER & DISPATCH
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_admin_update_order'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $orderStatus = trim($_POST['order_status'] ?? 'pending');
    $paymentStatus = trim($_POST['payment_status'] ?? 'unpaid');

    $up = $db->prepare("UPDATE `orders` SET `order_status` = ?, `payment_status` = ? WHERE `id` = ?");
    $up->execute([$orderStatus, $paymentStatus, $orderId]);

    // Push tracking event
    $ordInfo = $db->query("SELECT order_number, courier_partner FROM `orders` WHERE `id` = {$orderId}")->fetch();
    if ($ordInfo) {
        $titles = [
            'pending' => 'Order Placed & Awaiting Fulfillment',
            'processing' => 'Order Processing at Artisan Guild Hub',
            'shipped' => 'Handed over to Delivery Courier',
            'delivered' => 'Package Delivered to Recipient',
            'cancelled' => 'Order Cancelled'
        ];
        $evIns = $db->prepare("INSERT INTO `order_tracking_events` (`order_id`, `order_number`, `title`, `actor`, `location`, `status_key`, `note`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $evIns->execute([
            $orderId,
            $ordInfo['order_number'],
            $titles[$orderStatus] ?? 'Order Status Update',
            $ordInfo['courier_partner'] ?? 'Central Logistics Hub',
            'Central Logistics Hub (Dhaka)',
            $orderStatus,
            "Platform updated overall delivery status to " . strtoupper($orderStatus) . "."
        ]);
        if ($orderStatus === 'delivered' || $orderStatus === 'shipped') {
            $db->prepare("UPDATE `order_items` SET `vendor_status` = ? WHERE `order_id` = ?")->execute([$orderStatus, $orderId]);
        }
    }

    setFlash('success', 'Order status and tracking updated successfully.');
    header('Location: ' . BASE_URL . 'admin/#orders');
    exit;
}

// ==========================================
// 10. GET ACTION: TOGGLE USER STATUS
// ==========================================
if (isset($_GET['toggle_user_status'])) {
    $uid = (int)$_GET['toggle_user_status'];
    $uStatus = $db->query("SELECT status FROM `users` WHERE `id` = {$uid}")->fetchColumn();
    $newUStatus = ($uStatus === 'active') ? 'suspended' : 'active';
    $db->prepare("UPDATE `users` SET `status` = ? WHERE `id` = ?")->execute([$newUStatus, $uid]);
    setFlash('success', 'User account status changed to ' . ucfirst($newUStatus) . '.');
    header('Location: ' . BASE_URL . 'admin/#users');
    exit;
}

// ==========================================
// DATA QUERIES
// ==========================================

// Global Marketplace Analytics
$totalRevenue = (float)$db->query("SELECT COALESCE(SUM(grand_total), 0) FROM `orders` WHERE `payment_status` = 'paid' OR `order_status` != 'cancelled'")->fetchColumn();
$commissionEarned = $totalRevenue * 0.05; // 5% platform fee
$artisanDisbursements = $totalRevenue * 0.95; // 95% net payout to artisans

$totalOrders = (int)$db->query("SELECT COUNT(*) FROM `orders`")->fetchColumn();
$pendingOrders = (int)$db->query("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'pending'")->fetchColumn();

$totalSellers = (int)$db->query("SELECT COUNT(*) FROM `sellers`")->fetchColumn();
$totalProducts = (int)$db->query("SELECT COUNT(*) FROM `products`")->fetchColumn();
$totalCategories = (int)$db->query("SELECT COUNT(*) FROM `categories`")->fetchColumn();
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM `users`")->fetchColumn();

// Recent Platform Orders for Overview
$recentOrders = $db->query("SELECT * FROM `orders` ORDER BY id DESC LIMIT 6")->fetchAll();
$allOrdersList = $db->query("SELECT * FROM `orders` ORDER BY id DESC")->fetchAll();

// Store-by-Store Analytics Breakdown
$storeAnalytics = $db->query("SELECT s.id, s.shop_name, s.district, s.division, s.is_verified, s.status,
    COUNT(DISTINCT oi.order_id) as order_count,
    COALESCE(SUM(oi.subtotal), 0) as gross_income,
    COALESCE(SUM(oi.subtotal * 0.05), 0) as commission_income,
    COALESCE(SUM(oi.subtotal * 0.95), 0) as artisan_net_income,
    (SELECT COUNT(*) FROM products WHERE seller_id = s.id) as product_count,
    COALESCE(SUM(oi.quantity), 0) as total_units_sold
    FROM sellers s
    LEFT JOIN order_items oi ON s.id = oi.seller_id
    GROUP BY s.id
    ORDER BY gross_income DESC")->fetchAll();

$topStore = $storeAnalytics[0] ?? null;

// Promotions & Discounts List
$couponsList = $db->query("SELECT c.*, s.shop_name 
    FROM coupons c 
    LEFT JOIN sellers s ON c.seller_id = s.id 
    ORDER BY c.id DESC")->fetchAll();

// Active Stores for Promotions Dropdown
$activeSellersList = $db->query("SELECT id, shop_name, district FROM sellers WHERE status = 'active' ORDER BY shop_name ASC")->fetchAll();

// All Sellers for "Manage Sellers" Tab
$sellersList = $db->query("SELECT s.*, u.name as owner_name, u.email as owner_email, 
    (SELECT COUNT(*) FROM `products` WHERE seller_id = s.id) as product_count 
    FROM `sellers` s 
    JOIN `users` u ON s.user_id = u.id 
    ORDER BY s.id DESC")->fetchAll();

// All Products for "All Products" Tab
$productsList = $db->query("SELECT p.*, s.shop_name, s.district as seller_district, c.name as category_name 
    FROM `products` p 
    JOIN `sellers` s ON p.seller_id = s.id 
    JOIN `categories` c ON p.category_id = c.id 
    ORDER BY p.id DESC")->fetchAll();

// All Categories for "Categories" Tab
$categoriesList = $db->query("SELECT c.*, COUNT(p.id) as product_count 
    FROM `categories` c 
    LEFT JOIN `products` p ON c.id = p.category_id 
    GROUP BY c.id ORDER BY c.id ASC")->fetchAll();

// All Users for "Users & Buyers" Tab
$usersList = $db->query("SELECT u.*, 
    (SELECT COUNT(*) FROM `orders` WHERE user_id = u.id) as order_count 
    FROM `users` u 
    ORDER BY u.id DESC")->fetchAll();

// Header settings: Hide search, sub-navbar, and cart for clean admin view
$hideNavbar = true;
$hideCart = true;
$hideSearch = true;
$pageTitle = 'Admin Portal — HAAT';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
  .admin-tab-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius-sm);
    color: var(--text-main);
    font-size: 0.92rem;
    font-weight: 600;
    text-decoration: none;
    transition: var(--transition);
    cursor: pointer;
  }
  .admin-tab-link:hover {
    background: #f8fafc;
    color: var(--haat-green);
  }
  .admin-tab-link.active {
    background: var(--haat-sand);
    color: var(--haat-green);
    font-weight: 700;
  }
  .admin-panel {
    display: none;
    animation: fadeInTab 0.25s ease;
  }
  @keyframes fadeInTab {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
  }
</style>

<div class="container" style="padding: 28px 20px 60px;">
  
  <!-- Dashboard Top Header (Clean: Title + Quick Action Badges) -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:28px;">
    <div>
      <h1 style="font-size:1.8rem; color:var(--haat-green-dark); margin:0; font-weight:700;">
        HAAT Marketplace Control Center
      </h1>
    </div>

    <div style="display:flex; gap:10px;">
      <button type="button" onclick="switchAdminTab('sellers')" class="btn btn-sm btn-outline-green" style="display:inline-flex; align-items:center; gap:6px;">
        <i class="bi bi-shop"></i> Verify Artisans
      </button>
      <button type="button" onclick="switchAdminTab('analytics')" class="btn btn-sm btn-clay" style="display:inline-flex; align-items:center; gap:6px;">
        <i class="bi bi-graph-up"></i> Store Analytics
      </button>
      <button type="button" onclick="switchAdminTab('categories')" class="btn btn-sm btn-outline-green" style="display:inline-flex; align-items:center; gap:6px;">
        <i class="bi bi-tags"></i> Categories
      </button>
    </div>
  </div>

  <!-- Unified Dashboard Layout: Left Sidebar + Right Side Dynamic Panels -->
  <div style="display:grid; grid-template-columns: 260px 1fr; gap:28px; align-items:flex-start;">
    
    <!-- PERSISTENT LEFT SIDEBAR -->
    <aside style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:20px; box-shadow:var(--shadow-sm); position:sticky; top:20px;">
      
      <!-- RECTANGULAR ADMIN PROFILE CARD -->
      <div style="background:linear-gradient(180deg, #ffffff 0%, #faf8f5 100%); border:1px solid rgba(27,61,34,0.14); border-radius:12px; padding:16px; margin-bottom:20px; box-shadow:0 3px 12px rgba(0,0,0,0.04); position:relative; overflow:hidden;">
        <div style="position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg, var(--haat-green), var(--haat-clay));"></div>
        
        <div style="display:flex; align-items:center; gap:12px;">
          <div style="width:42px; height:42px; border-radius:10px; background:linear-gradient(135deg, var(--haat-green-dark), var(--haat-green)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.25rem; flex-shrink:0; box-shadow:0 2px 6px rgba(27,61,34,0.25);">
            <i class="bi bi-shield-lock"></i>
          </div>
          <div>
            <strong style="color:var(--haat-green-dark); font-size:0.98rem; display:block; font-weight:700;">HAAT Administrator</strong>
            <span style="font-size:0.72rem; color:var(--text-muted); display:inline-flex; align-items:center; gap:5px; margin-top:3px; font-weight:600;">
              <span style="width:6px; height:6px; border-radius:50%; background:#2ecc71; display:inline-block;"></span> Platform Owner
            </span>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs: ALL 7 TABS SWITCH DYNAMICALLY TO THE RIGHT SIDE -->
      <nav style="display:flex; flex-direction:column; gap:4px;">
        <a href="#overview" class="admin-tab-link active" data-tab="overview">
          <i class="bi bi-speedometer2"></i>
          <span>Dashboard Overview</span>
        </a>

        <!-- Platform Orders -->
        <a href="#orders" class="admin-tab-link" data-tab="orders">
          <i class="bi bi-receipt"></i>
          <span>Platform Orders (<?= $totalOrders ?>)</span>
        </a>

        <!-- Store Analytics -->
        <a href="#analytics" class="admin-tab-link" data-tab="analytics">
          <i class="bi bi-graph-up-arrow"></i>
          <span>Store Analytics</span>
        </a>

        <!-- Promotions & Discounts -->
        <a href="#promotions" class="admin-tab-link" data-tab="promotions">
          <i class="bi bi-percent"></i>
          <span>Promotions & Offers</span>
        </a>

        <!-- Manage Sellers (IN-PAGE RIGHT SIDE) -->
        <a href="#sellers" class="admin-tab-link" data-tab="sellers">
          <i class="bi bi-shop"></i>
          <span>Manage Sellers (<?= $totalSellers ?>)</span>
        </a>

        <!-- All Products (IN-PAGE RIGHT SIDE) -->
        <a href="#products" class="admin-tab-link" data-tab="products">
          <i class="bi bi-boxes"></i>
          <span>All Products (<?= $totalProducts ?>)</span>
        </a>

        <!-- Categories (IN-PAGE RIGHT SIDE) -->
        <a href="#categories" class="admin-tab-link" data-tab="categories">
          <i class="bi bi-tags"></i>
          <span>Categories (<?= $totalCategories ?>)</span>
        </a>

        <!-- Users & Buyers (IN-PAGE RIGHT SIDE) -->
        <a href="#users" class="admin-tab-link" data-tab="users">
          <i class="bi bi-people"></i>
          <span>Users & Buyers (<?= $totalUsers ?>)</span>
        </a>
      </nav>
    </aside>

    <!-- RIGHT SIDE CONTENT PANELS -->
    <main>
      
      <!-- ==========================================
           TAB 1: DASHBOARD OVERVIEW
           ========================================== -->
      <div id="panel-overview" class="admin-panel" style="display:block;">
        
        <!-- Metrics Row -->
        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; margin-bottom:24px;">
          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm);">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px; font-weight:700;">Gross GMV Sales</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--haat-green);"><?= formatPrice($totalRevenue) ?></div>
            <span style="font-size:0.72rem; color:var(--text-muted);">Across 64 districts</span>
          </div>

          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm);">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px; font-weight:700;">Platform Commission</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--haat-clay);"><?= formatPrice($commissionEarned) ?></div>
            <span style="font-size:0.72rem; color:var(--text-muted);">5% marketplace fee</span>
          </div>

          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm);">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px; font-weight:700;">Total Orders</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--haat-green-dark);"><?= $totalOrders ?></div>
            <span style="font-size:0.72rem; color:#d97008; font-weight:600;"><?= $pendingOrders ?> Pending</span>
          </div>

          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm);">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px; font-weight:700;">Artisan Sellers</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--haat-green-dark);"><?= $totalSellers ?></div>
            <span style="font-size:0.72rem; color:var(--text-muted);"><?= $totalProducts ?> Total Items</span>
          </div>
        </div>

        <!-- Clean Status Bar -->
        <div style="background:var(--haat-cream); border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
          <strong style="color:var(--haat-green-dark); font-size:0.95rem;">Artisan Marketplace Status: 🟢 Fully Operational</strong>
          <div style="display:flex; gap:10px;">
            <button type="button" onclick="switchAdminTab('sellers')" class="btn btn-sm btn-outline-green">Review Sellers</button>
          </div>
        </div>

        <!-- Latest Orders on Platform -->
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h3 style="font-size:1.2rem; color:var(--haat-green-dark); margin:0;">Latest Marketplace Orders</h3>
            <button type="button" onclick="switchAdminTab('analytics')" class="btn btn-sm btn-outline-green">
              View Store Revenue &rarr;
            </button>
          </div>

          <?php if (empty($recentOrders)): ?>
            <p style="color:var(--text-muted); text-align:center; padding:30px 0;">No marketplace orders logged yet.</p>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
                <thead>
                  <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
                    <th style="padding:10px 0;">Order #</th>
                    <th style="padding:10px 0;">Customer</th>
                    <th style="padding:10px 0;">Grand Total</th>
                    <th style="padding:10px 0;">Payment</th>
                    <th style="padding:10px 0;">Status</th>
                    <th style="padding:10px 0; text-align:right;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentOrders as $ro): ?>
                    <tr style="border-bottom:1px solid var(--haat-border);">
                      <td style="padding:14px 0; font-weight:700; color:var(--haat-green-dark);">
                        <?= sanitize($ro['order_number']) ?>
                        <div style="font-size:0.75rem; color:var(--text-muted); font-weight:400;"><?= date('d M Y, h:i A', strtotime($ro['created_at'])) ?></div>
                      </td>
                      <td style="padding:14px 0;">
                        <strong><?= sanitize($ro['shipping_name']) ?></strong>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($ro['district']) ?></div>
                      </td>
                      <td style="padding:14px 0; font-weight:800; color:var(--haat-green);">
                        <?= formatPrice($ro['grand_total']) ?>
                      </td>
                      <td style="padding:14px 0;">
                        <span class="pay-badge pay-<?= $ro['payment_method'] ?>" style="font-size:0.75rem;">
                          <?= strtoupper($ro['payment_method']) ?>
                        </span>
                        <span style="font-size:0.75rem; display:block; color:var(--text-muted); margin-top:2px;">
                          <?= ucfirst($ro['payment_status']) ?>
                        </span>
                      </td>
                      <td style="padding:14px 0;">
                        <span class="badge badge-<?= $ro['order_status'] === 'delivered' ? 'green' : ($ro['order_status'] === 'pending' ? 'gold' : 'clay') ?>">
                          <?= ucfirst($ro['order_status']) ?>
                        </span>
                      </td>
                      <td style="padding:14px 0; text-align:right;">
                        <a href="<?= BASE_URL ?>track-order.php?order=<?= urlencode($ro['order_number']) ?>" target="_blank" class="btn btn-sm btn-outline-green" style="padding:4px 10px; font-size:0.8rem;">
                          Track
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- ==========================================
           TAB: PLATFORM ORDERS MANAGEMENT
           ========================================== -->
      <div id="panel-orders" class="admin-panel" style="display:none;">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:26px; box-shadow:var(--shadow-sm);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0;">
                <i class="bi bi-receipt text-clay"></i> Platform Orders & Live Tracking (<?= count($allOrdersList) ?>)
              </h2>
              <p style="color:var(--text-muted); font-size:0.85rem; margin:3px 0 0;">Update order delivery and payment status directly in database and push live tracking events</p>
            </div>
          </div>

          <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
              <thead>
                <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.8rem; text-transform:uppercase;">
                  <th style="padding:10px 8px;">Order #</th>
                  <th style="padding:10px 8px;">Customer</th>
                  <th style="padding:10px 8px;">Amount</th>
                  <th style="padding:10px 8px;">Payment</th>
                  <th style="padding:10px 8px;">Fulfillment Status</th>
                  <th style="padding:10px 8px; text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($allOrdersList)): ?>
                  <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No orders found.</td></tr>
                <?php else: ?>
                  <?php foreach ($allOrdersList as $ord): ?>
                    <tr style="border-bottom:1px solid var(--haat-border);">
                      <td style="padding:12px 8px;">
                        <strong style="color:var(--haat-green-dark);"><?= sanitize($ord['order_number']) ?></strong>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?></div>
                      </td>
                      <td style="padding:12px 8px;">
                        <strong><?= sanitize($ord['shipping_name']) ?></strong>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($ord['shipping_phone']) ?> • <?= sanitize($ord['district']) ?></div>
                      </td>
                      <td style="padding:12px 8px; font-weight:800; color:var(--haat-green);">
                        <?= formatPrice($ord['grand_total']) ?>
                      </td>
                      <td style="padding:12px 8px;">
                        <form method="POST" action="<?= BASE_URL ?>admin/" style="display:inline-flex; align-items:center; gap:6px;">
                          <input type="hidden" name="action_admin_update_order" value="1">
                          <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                          <input type="hidden" name="order_status" value="<?= $ord['order_status'] ?>">
                          <select name="payment_status" onchange="this.form.submit()" style="padding:4px 8px; border-radius:4px; font-size:0.78rem; font-weight:600; border:1px solid var(--haat-border); background:#fff;">
                            <option value="unpaid" <?= $ord['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            <option value="paid" <?= $ord['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="refunded" <?= $ord['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                          </select>
                        </form>
                      </td>
                      <td style="padding:12px 8px;">
                        <form method="POST" action="<?= BASE_URL ?>admin/" style="display:inline-flex; align-items:center; gap:6px;">
                          <input type="hidden" name="action_admin_update_order" value="1">
                          <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                          <input type="hidden" name="payment_status" value="<?= $ord['payment_status'] ?>">
                          <select name="order_status" onchange="this.form.submit()" style="padding:4px 8px; border-radius:4px; font-size:0.78rem; font-weight:600; border:1px solid var(--haat-border); background:#fff;">
                            <option value="pending" <?= $ord['order_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= $ord['order_status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="shipped" <?= $ord['order_status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="delivered" <?= $ord['order_status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $ord['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                          </select>
                        </form>
                      </td>
                      <td style="padding:12px 8px; text-align:right; white-space:nowrap;">
                        <a href="<?= BASE_URL ?>track-order.php?order=<?= urlencode($ord['order_number']) ?>" target="_blank" class="btn btn-sm btn-outline-green" style="padding:4px 8px; font-size:0.78rem;">
                          Track
                        </a>
                        <a href="<?= BASE_URL ?>order-confirmation.php?order=<?= urlencode($ord['order_number']) ?>" target="_blank" class="btn btn-sm btn-clay" style="padding:4px 8px; font-size:0.78rem; margin-left:4px;">
                          Invoice
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ==========================================
           TAB 2: STORE ANALYTICS & REVENUE TELEMETRY
           ========================================== -->
      <div id="panel-analytics" class="admin-panel">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:26px; box-shadow:var(--shadow-sm);">
          
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; border-bottom:1px solid var(--haat-border); padding-bottom:18px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:8px;">
                <i class="bi bi-graph-up-arrow text-clay"></i> Store Analytics & Revenue Intelligence
              </h2>
              <p style="color:var(--text-muted); font-size:0.85rem; margin:3px 0 0;">
                Live multi-vendor financial telemetry: order counts, gross store revenue, and platform commissions.
              </p>
            </div>
            <button type="button" onclick="switchAdminTab('promotions')" class="btn btn-sm btn-clay" style="display:inline-flex; align-items:center; gap:6px;">
              <i class="bi bi-percent"></i> Create Promotion
            </button>
          </div>

          <!-- Analytics KPI Summary Cards -->
          <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; margin-bottom:26px;">
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md); padding:16px;">
              <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Top Earning Store</div>
              <div style="font-size:1.15rem; font-weight:800; color:var(--haat-green-dark); margin-top:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                <?= $topStore ? sanitize($topStore['shop_name']) : 'N/A' ?>
              </div>
              <span style="font-size:0.72rem; color:var(--haat-green); font-weight:700;">
                <?= $topStore ? formatPrice($topStore['gross_income']) : '৳ 0' ?> GMV
              </span>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md); padding:16px;">
              <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Gross Store Income</div>
              <div style="font-size:1.45rem; font-weight:800; color:var(--haat-green); margin-top:4px;">
                <?= formatPrice($totalRevenue) ?>
              </div>
              <span style="font-size:0.72rem; color:var(--text-muted);">Across all artisanal guilds</span>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md); padding:16px;">
              <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Platform Commission (5%)</div>
              <div style="font-size:1.45rem; font-weight:800; color:var(--haat-clay); margin-top:4px;">
                <?= formatPrice($commissionEarned) ?>
              </div>
              <span style="font-size:0.72rem; color:var(--text-muted);">Net marketplace operating profit</span>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md); padding:16px;">
              <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Artisan Disbursements (95%)</div>
              <div style="font-size:1.45rem; font-weight:800; color:var(--haat-green-dark); margin-top:4px;">
                <?= formatPrice($artisanDisbursements) ?>
              </div>
              <span style="font-size:0.72rem; color:var(--text-muted);">Total payout to village artisans</span>
            </div>
          </div>

          <!-- Store-by-Store Analytics Table -->
          <div style="margin-bottom:12px;">
            <h3 style="font-size:1.1rem; color:var(--haat-green-dark); margin-bottom:4px;">Artisan Workshop Performance Breakdown</h3>
            <p style="font-size:0.82rem; color:var(--text-muted); margin:0;">Detailed financial receipts and order fulfillment per active store</p>
          </div>

          <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
              <thead>
                <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
                  <th style="padding:12px 10px;">Store / Guild</th>
                  <th style="padding:12px 10px;">Location</th>
                  <th style="padding:12px 10px;">Listings</th>
                  <th style="padding:12px 10px;">Orders</th>
                  <th style="padding:12px 10px;">Units Sold</th>
                  <th style="padding:12px 10px;">Gross GMV Income</th>
                  <th style="padding:12px 10px;">Platform 5%</th>
                  <th style="padding:12px 10px; text-align:right;">Artisan 95%</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($storeAnalytics as $sa): ?>
                  <tr style="border-bottom:1px solid var(--haat-border);">
                    <td style="padding:14px 10px;">
                      <div style="font-weight:700; color:var(--haat-green-dark);">
                        <?= sanitize($sa['shop_name']) ?>
                      </div>
                      <?php if ($sa['is_verified']): ?>
                        <span style="font-size:0.7rem; color:#2e7d32; font-weight:600;"><i class="bi bi-patch-check-fill"></i> Verified Guild</span>
                      <?php endif; ?>
                    </td>

                    <td style="padding:14px 10px; font-size:0.85rem; color:var(--text-muted);">
                      <?= sanitize($sa['district']) ?>, <?= sanitize($sa['division']) ?>
                    </td>

                    <td style="padding:14px 10px;">
                      <span style="font-weight:600;"><?= $sa['product_count'] ?></span>
                    </td>

                    <td style="padding:14px 10px;">
                      <span class="badge badge-<?= $sa['order_count'] > 0 ? 'green' : 'gold' ?>">
                        <?= $sa['order_count'] ?> Orders
                      </span>
                    </td>

                    <td style="padding:14px 10px; font-weight:600;">
                      <?= $sa['total_units_sold'] ?>
                    </td>

                    <td style="padding:14px 10px; font-weight:800; color:var(--haat-green);">
                      <?= formatPrice($sa['gross_income']) ?>
                    </td>

                    <td style="padding:14px 10px; font-weight:700; color:var(--haat-clay);">
                      <?= formatPrice($sa['commission_income']) ?>
                    </td>

                    <td style="padding:14px 10px; text-align:right; font-weight:800; color:var(--haat-green-dark);">
                      <?= formatPrice($sa['artisan_net_income']) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>

      <!-- ==========================================
           TAB 3: PROMOTIONS & DISCOUNT OFFERS
           ========================================== -->
      <div id="panel-promotions" class="admin-panel">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:26px; box-shadow:var(--shadow-sm);">
          
          <div style="margin-bottom:22px; border-bottom:1px solid var(--haat-border); padding-bottom:18px;">
            <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:8px;">
              <i class="bi bi-percent text-clay"></i> Promotions & Discount Offers
            </h2>
            <p style="color:var(--text-muted); font-size:0.85rem; margin:3px 0 0;">
              Create platform discounts, coupon codes, and promotional campaigns for specific artisan stores or marketplace-wide.
            </p>
          </div>

          <!-- Create New Promotion Form -->
          <div style="background:#fcfbf9; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:22px; margin-bottom:28px;">
            <h3 style="font-size:1.1rem; color:var(--haat-green-dark); margin-bottom:16px;">
              <i class="bi bi-plus-circle text-clay"></i> Add New Promotion / Coupon Code
            </h3>

            <form method="POST" action="<?= BASE_URL ?>admin/">
              <input type="hidden" name="action_create_coupon" value="1">

              <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Promo Coupon Code *</label>
                  <input type="text" name="code" required placeholder="e.g. EID2026, HAAT15" style="width:100%; padding:10px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; text-transform:uppercase; font-weight:700; color:var(--haat-green-dark); outline:none;">
                </div>

                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Campaign / Offer Title</label>
                  <input type="text" name="title" placeholder="e.g. Eid Handloom Festival 15% Off" style="width:100%; padding:10px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
                </div>

                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Applicable Store</label>
                  <select name="seller_id" style="width:100%; padding:10px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none; background:#fff;">
                    <option value="">🌐 All Stores (Marketplace-wide)</option>
                    <?php foreach ($activeSellersList as $s): ?>
                      <option value="<?= $s['id'] ?>"><?= sanitize($s['shop_name']) ?> (<?= sanitize($s['district']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap:14px; margin-bottom:20px;">
                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Discount Type</label>
                  <select name="discount_type" style="width:100%; padding:10px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none; background:#fff;">
                    <option value="percent">Percentage (%)</option>
                    <option value="fixed">Fixed Amount (৳)</option>
                  </select>
                </div>

                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Discount Value *</label>
                  <input type="number" step="0.01" name="discount_value" required value="10" min="1" style="width:100%; padding:10px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
                </div>

                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Min Order Requirement (৳)</label>
                  <input type="number" step="0.01" name="min_order" value="500" min="0" style="width:100%; padding:10px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
                </div>

                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Expiry Date (Optional)</label>
                  <input type="date" name="expiry_date" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none; background:#fff;">
                </div>
              </div>

              <button type="submit" class="btn btn-clay" style="height:44px; display:inline-flex; align-items:center; gap:8px;">
                <i class="bi bi-check2-circle"></i> Create Promotion Offer
              </button>
            </form>
          </div>

          <!-- Existing Active Promotions Table -->
          <div style="margin-bottom:12px;">
            <h3 style="font-size:1.1rem; color:var(--haat-green-dark); margin-bottom:4px;">Active Promotions & Discount Offers</h3>
            <p style="font-size:0.82rem; color:var(--text-muted); margin:0;">Currently deployed promotions applicable at checkout</p>
          </div>

          <?php if (empty($couponsList)): ?>
            <p style="color:var(--text-muted); text-align:center; padding:30px 0;">No active promotions created yet.</p>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
                <thead>
                  <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
                    <th style="padding:12px 10px;">Promo Code & Title</th>
                    <th style="padding:12px 10px;">Target Store</th>
                    <th style="padding:12px 10px;">Discount</th>
                    <th style="padding:12px 10px;">Min Order</th>
                    <th style="padding:12px 10px;">Expiry</th>
                    <th style="padding:12px 10px;">Status</th>
                    <th style="padding:12px 10px; text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($couponsList as $cp): ?>
                    <tr style="border-bottom:1px solid var(--haat-border);">
                      <td style="padding:14px 10px;">
                        <strong style="color:var(--haat-green-dark); font-size:1rem; letter-spacing:0.5px;">
                          <?= sanitize($cp['code']) ?>
                        </strong>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($cp['title'] ?: 'Standard Promotion') ?></div>
                      </td>

                      <td style="padding:14px 10px;">
                        <?php if (!empty($cp['shop_name'])): ?>
                          <span class="badge badge-clay" style="font-size:0.75rem;"><?= sanitize($cp['shop_name']) ?></span>
                        <?php else: ?>
                          <span class="badge badge-green" style="font-size:0.75rem;">🌐 All Stores</span>
                        <?php endif; ?>
                      </td>

                      <td style="padding:14px 10px;">
                        <strong style="color:var(--haat-clay);">
                          <?= ($cp['discount_type'] === 'fixed') ? formatPrice($cp['discount_value']) : ($cp['discount_percent'] ?: $cp['discount_value']) . '% OFF' ?>
                        </strong>
                      </td>

                      <td style="padding:14px 10px; color:var(--text-muted); font-size:0.85rem;">
                        <?= formatPrice($cp['min_order']) ?>
                      </td>

                      <td style="padding:14px 10px; font-size:0.85rem; color:var(--text-muted);">
                        <?= !empty($cp['expiry_date']) ? date('d M Y', strtotime($cp['expiry_date'])) : 'No Expiry' ?>
                      </td>

                      <td style="padding:14px 10px;">
                        <span class="badge badge-<?= $cp['is_active'] ? 'green' : 'gold' ?>">
                          <?= $cp['is_active'] ? 'Active' : 'Paused' ?>
                        </span>
                      </td>

                      <td style="padding:14px 10px; text-align:right; white-space:nowrap;">
                        <a href="<?= BASE_URL ?>admin/?toggle_coupon=<?= $cp['id'] ?>" class="btn btn-sm btn-outline-green" style="padding:4px 8px; font-size:0.8rem; margin-right:4px;" title="Toggle Active">
                          <?= $cp['is_active'] ? 'Pause' : 'Activate' ?>
                        </a>
                        <a href="<?= BASE_URL ?>admin/?delete_coupon=<?= $cp['id'] ?>" onclick="return confirm('Delete this promotion offer?');" class="btn btn-sm" style="color:#c52828; border:1px solid #f8c8dc; padding:4px 8px; font-size:0.8rem;" title="Remove">
                          <i class="bi bi-trash"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- ==========================================
           TAB 4: MANAGE SELLERS (IN-PAGE RIGHT PANEL)
           ========================================== -->
      <div id="panel-sellers" class="admin-panel">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:26px; box-shadow:var(--shadow-sm);">
          
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; border-bottom:1px solid var(--haat-border); padding-bottom:18px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:8px;">
                <i class="bi bi-shop text-clay"></i> Manage Artisan Guilds & Sellers
              </h2>
              <p style="color:var(--text-muted); font-size:0.85rem; margin:3px 0 0;">
                Review workshop credentials, grant verified GI badges, and oversee vendor account statuses.
              </p>
            </div>
          </div>

          <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
              <thead>
                <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
                  <th style="padding:12px 10px;">Artisan Workshop</th>
                  <th style="padding:12px 10px;">Owner Contact</th>
                  <th style="padding:12px 10px;">Origin District</th>
                  <th style="padding:12px 10px;">bKash Payout</th>
                  <th style="padding:12px 10px;">Total Sales</th>
                  <th style="padding:12px 10px;">GI Verified</th>
                  <th style="padding:12px 10px;">Status</th>
                  <th style="padding:12px 10px; text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sellersList as $s): ?>
                  <tr style="border-bottom:1px solid var(--haat-border);">
                    <td style="padding:14px 10px;">
                      <a href="<?= BASE_URL ?>vendor.php?id=<?= $s['id'] ?>" target="_blank" style="font-weight:700; color:var(--haat-green-dark); display:block;">
                        <?= sanitize($s['shop_name']) ?>
                      </a>
                      <span style="font-size:0.75rem; color:var(--text-muted);"><?= $s['product_count'] ?> Products • <?= number_format($s['rating'], 1) ?> ★</span>
                    </td>
                    <td style="padding:14px 10px; font-size:0.85rem;">
                      <strong><?= sanitize($s['owner_name']) ?></strong><br>
                      <span style="color:var(--text-muted);"><?= sanitize($s['owner_email']) ?></span><br>
                      <span><?= sanitize($s['phone']) ?></span>
                    </td>
                    <td style="padding:14px 10px;">
                      <span class="district-tag"><?= sanitize($s['district']) ?></span>
                      <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;"><?= sanitize($s['division']) ?></div>
                    </td>
                    <td style="padding:14px 10px; font-size:0.85rem; font-weight:600; color:#e2136e;">
                      <?= sanitize($s['bkash_number'] ?: 'Not added') ?>
                    </td>
                    <td style="padding:14px 10px; font-weight:700; color:var(--haat-green);">
                      <?= formatPrice($s['total_sales']) ?>
                    </td>
                    <td style="padding:14px 10px;">
                      <a href="<?= BASE_URL ?>admin/?toggle_verify=<?= $s['id'] ?>" title="Click to toggle verification">
                        <?php if ($s['is_verified']): ?>
                          <span class="badge badge-green"><i class="bi bi-patch-check-fill"></i> Verified</span>
                        <?php else: ?>
                          <span class="badge badge-gold">Unverified</span>
                        <?php endif; ?>
                      </a>
                    </td>
                    <td style="padding:14px 10px;">
                      <span class="badge badge-<?= $s['status'] === 'active' ? 'green' : 'clay' ?>">
                        <?= ucfirst($s['status']) ?>
                      </span>
                    </td>
                    <td style="padding:14px 10px; text-align:right; white-space:nowrap;">
                      <a href="<?= BASE_URL ?>vendor.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-outline-green" style="margin-right:4px;">
                        View
                      </a>
                      <a href="<?= BASE_URL ?>admin/?toggle_status=<?= $s['id'] ?>" class="btn btn-sm <?= $s['status'] === 'active' ? 'btn-outline-clay' : 'btn-clay' ?>">
                        <?= $s['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>

      <!-- ==========================================
           TAB 5: ALL PRODUCTS (IN-PAGE RIGHT PANEL)
           ========================================== -->
      <div id="panel-products" class="admin-panel">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:26px; box-shadow:var(--shadow-sm);">
          
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; border-bottom:1px solid var(--haat-border); padding-bottom:18px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:8px;">
                <i class="bi bi-boxes text-clay"></i> Marketplace Products Catalog (<?= count($productsList) ?>)
              </h2>
              <p style="color:var(--text-muted); font-size:0.85rem; margin:3px 0 0;">
                Review vendor craft listings, toggle featured highlights, and moderate inventory.
              </p>
            </div>
          </div>

          <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
              <thead>
                <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
                  <th style="padding:12px 10px;">Craft Item</th>
                  <th style="padding:12px 10px;">Artisan Workshop</th>
                  <th style="padding:12px 10px;">Category</th>
                  <th style="padding:12px 10px;">Price</th>
                  <th style="padding:12px 10px;">Stock</th>
                  <th style="padding:12px 10px;">Featured</th>
                  <th style="padding:12px 10px;">Flash Deal</th>
                  <th style="padding:12px 10px; text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($productsList as $p): ?>
                  <tr style="border-bottom:1px solid var(--haat-border);">
                    <td style="padding:14px 10px; display:flex; align-items:center; gap:12px;">
                      <img src="<?= sanitize($p['featured_image']) ?>" alt="thumb" style="width:48px; height:48px; border-radius:var(--radius-sm); object-fit:cover; border:1px solid var(--haat-border);">
                      <div>
                        <a href="<?= BASE_URL ?>product.php?id=<?= $p['id'] ?>" target="_blank" style="font-weight:700; color:var(--haat-green-dark); display:block;">
                          <?= sanitize($p['name']) ?>
                        </a>
                        <span style="font-size:0.75rem; color:var(--text-muted);">SKU: <?= sanitize($p['sku'] ?: 'HAAT-' . $p['id']) ?></span>
                      </div>
                    </td>
                    <td style="padding:14px 10px;">
                      <a href="<?= BASE_URL ?>vendor.php?id=<?= $p['seller_id'] ?>" target="_blank" style="color:var(--haat-clay); font-weight:600; font-size:0.85rem;">
                        <?= sanitize($p['shop_name']) ?>
                      </a>
                      <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($p['district_origin']) ?></div>
                    </td>
                    <td style="padding:14px 10px; color:var(--text-muted); font-size:0.85rem;">
                      <?= sanitize($p['category_name']) ?>
                    </td>
                    <td style="padding:14px 10px;">
                      <strong style="color:var(--haat-green);"><?= formatPrice($p['sale_price'] ?: $p['price']) ?></strong>
                    </td>
                    <td style="padding:14px 10px; font-weight:600;">
                      <?= $p['stock_quantity'] ?> <?= sanitize($p['unit']) ?>
                    </td>
                    <td style="padding:14px 10px;">
                      <a href="<?= BASE_URL ?>admin/?toggle_featured=<?= $p['id'] ?>" title="Toggle Featured">
                        <span class="badge badge-<?= $p['is_featured'] ? 'green' : 'gold' ?>">
                          <?= $p['is_featured'] ? 'Yes' : 'No' ?>
                        </span>
                      </a>
                    </td>
                    <td style="padding:14px 10px;">
                      <a href="<?= BASE_URL ?>admin/?toggle_flash=<?= $p['id'] ?>" title="Toggle Flash Deal">
                        <span class="badge badge-<?= $p['is_flash_deal'] ? 'clay' : 'gold' ?>">
                          <?= $p['is_flash_deal'] ? 'Flash' : 'Standard' ?>
                        </span>
                      </a>
                    </td>
                    <td style="padding:14px 10px; text-align:right; white-space:nowrap;">
                      <a href="<?= BASE_URL ?>product.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-green" style="margin-right:4px;">
                        View
                      </a>
                      <a href="<?= BASE_URL ?>admin/?delete_product=<?= $p['id'] ?>" onclick="return confirm('Delete this product permanently?');" class="btn btn-sm" style="color:#c52828; border:1px solid #f8c8dc;">
                        <i class="bi bi-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>

      <!-- ==========================================
           TAB 6: CATEGORIES & CREATOR (IN-PAGE RIGHT PANEL)
           ========================================== -->
      <div id="panel-categories" class="admin-panel">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:26px; box-shadow:var(--shadow-sm);">
          
          <div style="margin-bottom:22px; border-bottom:1px solid var(--haat-border); padding-bottom:18px;">
            <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:8px;">
              <i class="bi bi-tags text-clay"></i> Artisanal Craft Categories & Expansion
            </h2>
            <p style="color:var(--text-muted); font-size:0.85rem; margin:3px 0 0;">
              Organize heritage craft sectors. Creating a category automatically notifies all seller workshops so they can immediately add corresponding products.
            </p>
          </div>

          <div style="display:grid; grid-template-columns: 1fr 340px; gap:28px; align-items:flex-start;">
            
            <!-- Existing Categories List -->
            <div>
              <div style="margin-bottom:12px;">
                <h3 style="font-size:1.1rem; color:var(--haat-green-dark); margin:0;">Active Marketplace Categories (<?= count($categoriesList) ?>)</h3>
              </div>

              <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
                  <thead>
                    <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
                      <th style="padding:10px 8px;">Image</th>
                      <th style="padding:10px 8px;">Category Name</th>
                      <th style="padding:10px 8px;">Department</th>
                      <th style="padding:10px 8px;">Bengali</th>
                      <th style="padding:10px 8px;">Crafts</th>
                      <th style="padding:10px 8px; text-align:right;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($categoriesList as $cat): ?>
                      <tr style="border-bottom:1px solid var(--haat-border);">
                        <td style="padding:12px 8px;">
                          <img src="<?= sanitize($cat['image']) ?>" alt="thumb" style="width:40px; height:40px; border-radius:var(--radius-sm); object-fit:cover; border:1px solid var(--haat-border);">
                        </td>
                        <td style="padding:12px 8px; font-weight:700; color:var(--haat-green-dark);">
                          <i class="bi <?= sanitize($cat['icon']) ?>" style="color:var(--haat-clay); margin-right:6px;"></i>
                          <?= sanitize($cat['name']) ?>
                        </td>
                        <td style="padding:12px 8px;">
                          <span class="badge badge-clay"><?= sanitize($cat['department'] ?? 'Clothes') ?></span>
                        </td>
                        <td style="padding:12px 8px; color:var(--text-muted); font-size:0.85rem;">
                          <?= sanitize($cat['name_bn']) ?>
                        </td>
                        <td style="padding:12px 8px;">
                          <span class="badge badge-green"><?= $cat['product_count'] ?></span>
                        </td>
                        <td style="padding:12px 8px; text-align:right; white-space:nowrap;">
                          <a href="<?= BASE_URL ?>shop.php?category=<?= sanitize($cat['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-green" style="padding:3px 8px; font-size:0.8rem;">
                            View
                          </a>
                          <a href="<?= BASE_URL ?>admin/?delete_category=<?= $cat['id'] ?>" onclick="return confirm('Are you sure you want to delete this category?');" class="btn btn-sm" style="padding:3px 8px; font-size:0.8rem; color:#c52828; border:1px solid #f8c8dc; margin-left:4px;" title="Delete Category">
                            <i class="bi bi-trash"></i>
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Add Category Form with Live Seller Broadcast Notification -->
            <div style="background:#fcfbf9; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:20px;">
              <h3 style="font-size:1.1rem; color:var(--haat-green-dark); margin-bottom:14px; display:flex; align-items:center; gap:8px;">
                <i class="bi bi-plus-circle text-clay"></i> Start New Category
              </h3>

              <div style="background:#e8f5e9; border:1px solid #c8e6c9; border-radius:8px; padding:10px 12px; margin-bottom:16px; font-size:0.8rem; color:#1b3d22; line-height:1.4;">
                <i class="bi bi-broadcast" style="color:#2e7d32; font-size:1rem; margin-right:4px;"></i>
                <strong>Dynamic Seller Alert:</strong> Creating a category will automatically notify all registered artisan sellers so they can add crafts under this category.
              </div>

              <form method="POST" action="<?= BASE_URL ?>admin/" enctype="multipart/form-data">
                <input type="hidden" name="action_add_category" value="1">

                <div style="margin-bottom:12px;">
                  <label style="font-weight:600; font-size:0.83rem; display:block; margin-bottom:4px;">Department *</label>
                  <select name="department" required style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none; background:#fff;">
                    <option value="Clothes">👕 Clothes</option>
                    <option value="Food">🍲 Food & Grocery</option>
                    <option value="Art & Accessories">🎨 Art & Accessories</option>
                  </select>
                </div>

                <div style="margin-bottom:12px;">
                  <label style="font-weight:600; font-size:0.83rem; display:block; margin-bottom:4px;">Category Name (English) *</label>
                  <input type="text" name="name" required placeholder="e.g. Silk & Handspun Khadi" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none;">
                </div>

                <div style="margin-bottom:12px;">
                  <label style="font-weight:600; font-size:0.83rem; display:block; margin-bottom:4px;">Category Name (Bengali)</label>
                  <input type="text" name="name_bn" placeholder="e.g. রেশম ও খাদি বস্ত্র" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none;">
                </div>

                <div style="margin-bottom:12px;">
                  <label style="font-weight:600; font-size:0.83rem; display:block; margin-bottom:4px;">Bootstrap Icon</label>
                  <input type="text" name="icon" value="bi-stars" placeholder="bi-tag, bi-palette2" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none;">
                </div>

                <div style="margin-bottom:12px;">
                  <label style="font-weight:600; font-size:0.83rem; display:block; margin-bottom:4px;">Upload Cover Image File</label>
                  <input type="file" name="category_file" accept="image/*" style="width:100%; padding:7px 10px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.82rem; background:#fff; outline:none;">
                </div>

                <div style="margin-bottom:12px;">
                  <label style="font-weight:600; font-size:0.83rem; display:block; margin-bottom:4px;">Or Cover Image URL</label>
                  <input type="url" name="image" value="https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=600&q=80" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none;">
                </div>

                <div style="margin-bottom:16px;">
                  <label style="font-weight:600; font-size:0.83rem; display:block; margin-bottom:4px;">Short Description</label>
                  <textarea name="description" rows="2" placeholder="Brief note on craft cluster..." style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none;"></textarea>
                </div>

                <button type="submit" class="btn btn-clay btn-lg" style="width:100%; display:inline-flex; align-items:center; justify-content:center; gap:8px;">
                  <i class="bi bi-broadcast"></i> Launch & Notify Sellers
                </button>
              </form>
            </div>

          </div>

        </div>
      </div>

      <!-- ==========================================
           TAB 7: USERS & BUYERS (IN-PAGE RIGHT PANEL)
           ========================================== -->
      <div id="panel-users" class="admin-panel">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:26px; box-shadow:var(--shadow-sm);">
          
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; border-bottom:1px solid var(--haat-border); padding-bottom:18px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:8px;">
                <i class="bi bi-people text-clay"></i> Platform Users & Buyers (<?= count($usersList) ?>)
              </h2>
              <p style="color:var(--text-muted); font-size:0.85rem; margin:3px 0 0;">
                Overview of registered patrons, artisan guild accounts, and platform staff.
              </p>
            </div>
          </div>

          <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
              <thead>
                <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
                  <th style="padding:12px 10px;">User Name</th>
                  <th style="padding:12px 10px;">Email</th>
                  <th style="padding:12px 10px;">Phone</th>
                  <th style="padding:12px 10px;">Role</th>
                  <th style="padding:12px 10px;">Orders</th>
                  <th style="padding:12px 10px;">Registered</th>
                  <th style="padding:12px 10px;">Status</th>
                  <th style="padding:12px 10px; text-align:right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($usersList as $u): ?>
                  <tr style="border-bottom:1px solid var(--haat-border);">
                    <td style="padding:14px 10px; font-weight:700; color:var(--haat-green-dark);">
                      <?= sanitize($u['name']) ?>
                    </td>
                    <td style="padding:14px 10px; color:var(--text-muted);">
                      <?= sanitize($u['email']) ?>
                    </td>
                    <td style="padding:14px 10px;">
                      <?= sanitize($u['phone'] ?: 'N/A') ?>
                    </td>
                    <td style="padding:14px 10px;">
                      <span class="badge badge-<?= $u['role'] === 'admin' ? 'clay' : ($u['role'] === 'seller' ? 'green' : 'gold') ?>">
                        <?= strtoupper($u['role']) ?>
                      </span>
                    </td>
                    <td style="padding:14px 10px; font-weight:600;">
                      <?= $u['order_count'] ?> Orders
                    </td>
                    <td style="padding:14px 10px; color:var(--text-muted); font-size:0.85rem;">
                      <?= date('d M Y', strtotime($u['created_at'])) ?>
                    </td>
                    <td style="padding:14px 10px;">
                      <span class="badge badge-<?= $u['status'] === 'active' ? 'green' : 'clay' ?>">
                        <?= ucfirst($u['status']) ?>
                      </span>
                    </td>
                    <td style="padding:14px 10px; text-align:right;">
                      <?php if ($u['id'] !== $user['id']): ?>
                        <a href="<?= BASE_URL ?>admin/?toggle_user_status=<?= $u['id'] ?>" class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-outline-clay' : 'btn-clay' ?>" style="padding:4px 8px; font-size:0.8rem;">
                          <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                        </a>
                      <?php else: ?>
                        <span style="font-size:0.75rem; color:var(--text-muted);">Current</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>

    </main>
  </div>
</div>

<script>
  // Unified Admin Tab Switching
  function switchAdminTab(tabId) {
    const validTabs = ['overview', 'orders', 'analytics', 'promotions', 'sellers', 'products', 'categories', 'users'];
    if (!validTabs.includes(tabId)) {
      tabId = 'overview';
    }

    // Hide all panels
    document.querySelectorAll('.admin-panel').forEach(panel => {
      panel.style.display = 'none';
    });

    // Remove active state from all links
    document.querySelectorAll('.admin-tab-link').forEach(link => {
      link.classList.remove('active');
    });

    // Show target panel
    const targetPanel = document.getElementById(`panel-${tabId}`);
    if (targetPanel) {
      targetPanel.style.display = 'block';
    }

    // Activate corresponding link
    const targetLink = document.querySelector(`.admin-tab-link[data-tab="${tabId}"]`);
    if (targetLink) {
      targetLink.classList.add('active');
    }

    // Update URL hash
    window.location.hash = tabId;
  }

  // Handle Hash on Page Load
  window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#', '');
    const validTabs = ['overview', 'orders', 'analytics', 'promotions', 'sellers', 'products', 'categories', 'users'];
    if (hash && validTabs.includes(hash)) {
      switchAdminTab(hash);
    } else {
      switchAdminTab('overview');
    }

    // Click handler for tab links
    document.querySelectorAll('.admin-tab-link[data-tab]').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const tab = link.getAttribute('data-tab');
        switchAdminTab(tab);
      });
    });
  });

  // Handle browser back/forward buttons
  window.addEventListener('hashchange', () => {
    const hash = window.location.hash.replace('#', '');
    const validTabs = ['overview', 'orders', 'analytics', 'promotions', 'sellers', 'products', 'categories', 'users'];
    if (hash && validTabs.includes(hash)) {
      switchAdminTab(hash);
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
