<?php
/**
 * Artisan Seller / Vendor Unified Dashboard
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/../includes/functions.php';
requireSeller();

$user = currentUser();
$seller = currentSeller();

if (!$seller) {
    echo "Seller profile not found. Please contact admin.";
    exit;
}

// Ensure session user name is synchronized
$_SESSION['user_name'] = $user['name'];

// ==========================================
// 0. GET ACTIONS: NOTIFICATIONS MANAGEMENT
// ==========================================
if (isset($_GET['mark_notif_read'])) {
    $nid = (int)$_GET['mark_notif_read'];
    $db->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `id` = ? AND (`seller_id` = ? OR `user_id` = ?)")->execute([$nid, $seller['id'], $user['id']]);
    $redirectTab = $_GET['tab'] ?? 'overview';
    header('Location: ' . BASE_URL . 'seller/#' . $redirectTab);
    exit;
}

if (isset($_GET['mark_all_read'])) {
    $db->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `seller_id` = ? OR (`seller_id` IS NULL AND `user_id` = ?)")->execute([$seller['id'], $user['id']]);
    $redirectTab = $_GET['tab'] ?? 'overview';
    header('Location: ' . BASE_URL . 'seller/#' . $redirectTab);
    exit;
}

// ==========================================
// 1. POST ACTION: UPDATE SHOP SETTINGS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_shop_settings'])) {
    $shopName = trim($_POST['shop_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $division = trim($_POST['division'] ?? '');
    $bkashNumber = trim($_POST['bkash_number'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $bankAcc = trim($_POST['bank_account_no'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (!empty($shopName)) {
        $up = $db->prepare("UPDATE `sellers` SET 
            `shop_name` = ?, `phone` = ?, `address` = ?, `district` = ?, 
            `division` = ?, `bkash_number` = ?, `bank_name` = ?, 
            `bank_account_no` = ?, `description` = ? 
            WHERE `id` = ?");
        $up->execute([
            $shopName, $phone, $address, $district, 
            $division, $bkashNumber, $bankName, 
            $bankAcc, $desc, $seller['id']
        ]);

        setFlash('success', 'Workshop settings and payout account updated successfully.');
        header('Location: ' . BASE_URL . 'seller/#settings');
        exit;
    }
}

// ==========================================
// 2. POST ACTION: UPDATE ORDER FULFILLMENT STATUS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_order_status'])) {
    $itemId = (int)$_POST['item_id'];
    $newStatus = $_POST['vendor_status'] ?? '';
    if (in_array($newStatus, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
        $up = $db->prepare("UPDATE `order_items` SET `vendor_status` = ? WHERE `id` = ? AND `seller_id` = ?");
        $up->execute([$newStatus, $itemId, $seller['id']]);

        // Automatically log tracking event
        $itemInfo = $db->query("SELECT oi.product_name, o.id as order_id, o.order_number, s.shop_name, s.district 
            FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id 
            JOIN sellers s ON oi.seller_id = s.id 
            WHERE oi.id = {$itemId}")->fetch();
        if ($itemInfo) {
            $statusLabels = [
                'processing' => 'Artisan Crafting & Workshop Packaging',
                'shipped' => 'Dispatched to Delivery Courier',
                'delivered' => 'Artisan Item Delivered to Customer',
                'cancelled' => 'Order Item Cancelled by Workshop'
            ];
            $title = ($statusLabels[$newStatus] ?? 'Order Item Status Updated') . ' - ' . $itemInfo['product_name'];
            $note = "Workshop {$itemInfo['shop_name']} updated item status to " . strtoupper($newStatus) . ".";
            $evIns = $db->prepare("INSERT INTO `order_tracking_events` (`order_id`, `order_number`, `title`, `actor`, `location`, `status_key`, `note`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $evIns->execute([
                $itemInfo['order_id'],
                $itemInfo['order_number'],
                $title,
                $itemInfo['shop_name'] . ' (Artisan)',
                $itemInfo['district'] . ' Workshop',
                $newStatus,
                $note
            ]);
            if ($newStatus === 'shipped') {
                $db->prepare("UPDATE `orders` SET `order_status` = 'shipped' WHERE `id` = ? AND `order_status` IN ('pending', 'processing')")->execute([$itemInfo['order_id']]);
            }
        }

        setFlash('success', 'Fulfillment status updated and pushed to live tracking.');
        header('Location: ' . BASE_URL . 'seller/#orders');
        exit;
    }
}

// ==========================================
// 3. POST ACTION: ADD NEW PRODUCT TO INVENTORY
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_product'])) {
    $name = trim($_POST['name'] ?? '');
    $nameBn = trim($_POST['name_bn'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $brandId = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $price = (float)($_POST['price'] ?? 0);
    $salePrice = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $stock = (int)($_POST['stock_quantity'] ?? 10);
    $unit = trim($_POST['unit'] ?? 'piece');
    $district = trim($_POST['district_origin'] ?? $seller['district']);
    $shortDesc = trim($_POST['short_description'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $imageUrl = trim($_POST['featured_image'] ?? '');
    $isFlash = isset($_POST['is_flash_deal']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

    // Check file upload or image URL
    if (!empty($_FILES['image_file']['name'])) {
        $targetDir = __DIR__ . '/../assets/uploads/products/';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $fileName = 'prod_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetDir . $fileName)) {
                $imageUrl = BASE_URL . 'assets/uploads/products/' . $fileName;
            }
        }
    }

    if (empty($imageUrl)) {
        $imageUrl = 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=700&q=80';
    }

    if (empty($name) || $categoryId <= 0 || $price <= 0) {
        setFlash('error', 'Please provide product name, category, and a valid base price.');
        header('Location: ' . BASE_URL . 'seller/#inventory');
        exit;
    } else {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)) . '-' . rand(1000, 9999);
        $sku = 'HAAT-' . strtoupper(substr(uniqid(), -5));

        $ins = $db->prepare("INSERT INTO `products` 
            (`seller_id`, `category_id`, `brand_id`, `name`, `name_bn`, `slug`, `sku`, `price`, `sale_price`, 
             `stock_quantity`, `unit`, `short_description`, `description`, `featured_image`, `district_origin`, 
             `is_featured`, `is_flash_deal`, `is_active`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

        $ins->execute([
            $seller['id'],
            $categoryId,
            $brandId,
            $name,
            $nameBn,
            $slug,
            $sku,
            $price,
            $salePrice,
            $stock,
            $unit,
            $shortDesc,
            $desc,
            $imageUrl,
            $district,
            $isFeatured,
            $isFlash
        ]);

        setFlash('success', 'Artisan product added directly into inventory successfully!');
        header('Location: ' . BASE_URL . 'seller/#inventory');
        exit;
    }
}

// ==========================================
// 4. POST ACTION: QUICK UPDATE STOCK (INVENTORY)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_stock'])) {
    $prodId = (int)($_POST['product_id'] ?? 0);
    $newStock = max(0, (int)($_POST['stock_quantity'] ?? 0));

    $up = $db->prepare("UPDATE `products` SET `stock_quantity` = ? WHERE `id` = ? AND `seller_id` = ?");
    $up->execute([$newStock, $prodId, $seller['id']]);

    if (!empty($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'new_stock' => $newStock]);
        exit;
    }

    setFlash('success', 'Stock level updated successfully.');
    header('Location: ' . BASE_URL . 'seller/#inventory');
    exit;
}

// ==========================================
// 5. GET ACTION: DELETE PRODUCT
// ==========================================
if (isset($_GET['delete_product'])) {
    $delId = (int)$_GET['delete_product'];
    $delStmt = $db->prepare("DELETE FROM `products` WHERE `id` = ? AND `seller_id` = ?");
    $delStmt->execute([$delId, $seller['id']]);
    setFlash('success', 'Product listing removed.');
    header('Location: ' . BASE_URL . 'seller/#products');
    exit;
}

// ==========================================
// 6. POST ACTION: TOGGLE STORE ACTIVE / ONLINE STATUS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_toggle_online'])) {
    $currentOnline = (int)($seller['is_online'] ?? 1);
    $newOnline = $currentOnline ? 0 : 1;
    $db->prepare("UPDATE `sellers` SET `is_online` = ? WHERE `id` = ?")->execute([$newOnline, $seller['id']]);
    $db->prepare("UPDATE `users` SET `is_online` = ? WHERE `id` = ?")->execute([$newOnline, $user['id']]);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'is_online' => $newOnline]);
    exit;
}

// ==========================================
// DATA QUERIES FOR DASHBOARD & TABS
// ==========================================

// Online state
$isOnline = (int)($seller['is_online'] ?? 1);

// Calculate live dynamic rating from verified product reviews, fallback to seller rating
$revStmt = $db->query("SELECT AVG(pr.rating) as avg_rating, COUNT(pr.id) as review_count 
    FROM product_reviews pr 
    JOIN products p ON pr.product_id = p.id 
    WHERE p.seller_id = {$seller['id']}");
$revData = $revStmt->fetch();
$liveRating = !empty($revData['avg_rating']) ? round((float)$revData['avg_rating'], 1) : round((float)$seller['rating'], 1);
$reviewCount = (int)($revData['review_count'] ?? 0);

// Product count & stats
$prodCount = $db->query("SELECT COUNT(*) FROM `products` WHERE `seller_id` = {$seller['id']}")->fetchColumn();

$orderStats = $db->query("SELECT COUNT(DISTINCT order_id) as total_vendor_orders, 
    COALESCE(SUM(subtotal), 0) as total_earnings 
    FROM `order_items` WHERE `seller_id` = {$seller['id']}")->fetch();

// Inventory metrics
$invStats = $db->query("SELECT 
    COUNT(*) as total_items,
    COALESCE(SUM(stock_quantity), 0) as total_units,
    COALESCE(SUM(stock_quantity * (CASE WHEN sale_price > 0 THEN sale_price ELSE price END)), 0) as total_valuation,
    COUNT(CASE WHEN stock_quantity <= 3 AND stock_quantity > 0 THEN 1 END) as low_stock_count,
    COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock_count
    FROM `products` WHERE `seller_id` = {$seller['id']}")->fetch();

$totalUnits = (int)($invStats['total_units'] ?? 0);
$totalValuation = (float)($invStats['total_valuation'] ?? 0);
$lowStockCount = (int)($invStats['low_stock_count'] ?? 0);
$outOfStockCount = (int)($invStats['out_of_stock_count'] ?? 0);

// Recent orders for Overview tab
$recentOrders = $db->query("SELECT oi.*, o.order_number, o.created_at, o.payment_method, o.order_status as overall_status 
    FROM `order_items` oi 
    JOIN `orders` o ON oi.order_id = o.id 
    WHERE oi.seller_id = {$seller['id']} 
    ORDER BY oi.id DESC LIMIT 5")->fetchAll();

// All products for "My Products" and "Inventory" tabs
$stmtProd = $db->prepare("SELECT p.*, c.name as category_name 
    FROM `products` p 
    JOIN `categories` c ON p.category_id = c.id 
    WHERE p.seller_id = ? 
    ORDER BY p.id DESC");
$stmtProd->execute([$seller['id']]);
$productsList = $stmtProd->fetchAll();

// All customer orders for "Customer Orders" tab
$stmtOrders = $db->prepare("SELECT oi.*, o.user_id as customer_user_id, o.order_number, o.created_at, o.payment_method, o.payment_status, 
    o.shipping_name, o.shipping_phone, o.shipping_address, o.district as ship_district, o.division as ship_division 
    FROM `order_items` oi 
    JOIN `orders` o ON oi.order_id = o.id 
    WHERE oi.seller_id = ? 
    ORDER BY oi.id DESC");
$stmtOrders->execute([$seller['id']]);
$allOrders = $stmtOrders->fetchAll();

// Customer Conversations for Live Messaging tab
$sellerId = (int)$seller['id'];
$sellerUserId = (int)$user['id'];
$convStmt = $db->query("
    SELECT u.id as customer_id, u.name as customer_name, u.phone as customer_phone, u.avatar as customer_avatar,
           COALESCE(u.is_online, 0) as is_online,
           (SELECT o.order_number FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = {$sellerId} AND o.user_id = u.id ORDER BY o.id DESC LIMIT 1) as order_number,
           (SELECT oi.product_name FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = {$sellerId} AND o.user_id = u.id ORDER BY o.id DESC LIMIT 1) as product_name,
           (SELECT p.featured_image FROM orders o JOIN order_items oi ON oi.order_id = o.id LEFT JOIN products p ON oi.product_id = p.id WHERE oi.seller_id = {$sellerId} AND o.user_id = u.id ORDER BY o.id DESC LIMIT 1) as featured_image,
           (SELECT message FROM messages WHERE seller_id = {$sellerId} AND ((sender_id = {$sellerUserId} AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = {$sellerUserId})) ORDER BY id DESC LIMIT 1) as last_message,
           (SELECT created_at FROM messages WHERE seller_id = {$sellerId} AND ((sender_id = {$sellerUserId} AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = {$sellerUserId})) ORDER BY id DESC LIMIT 1) as last_message_time,
           (SELECT COUNT(*) FROM messages WHERE seller_id = {$sellerId} AND sender_id = u.id AND receiver_id = {$sellerUserId} AND is_read = 0) as unread_count
    FROM users u
    WHERE u.role = 'customer' AND (
        u.id IN (SELECT o.user_id FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = {$sellerId})
        OR u.id IN (SELECT sender_id FROM messages WHERE seller_id = {$sellerId} AND receiver_id = {$sellerUserId})
        OR u.id IN (SELECT receiver_id FROM messages WHERE seller_id = {$sellerId} AND sender_id = {$sellerUserId})
    )
    ORDER BY (last_message_time IS NOT NULL) DESC, last_message_time DESC, u.id DESC
");
$customerConversations = $convStmt->fetchAll();

// Total unread messages for this seller
$unreadMessagesStmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE seller_id = ? AND receiver_id = ? AND is_read = 0");
$unreadMessagesStmt->execute([$sellerId, $sellerUserId]);
$totalUnreadMessages = (int)$unreadMessagesStmt->fetchColumn();

// Categories & Brands for "Add Product" inside Inventory tab
$categories = $db->query("SELECT * FROM `categories` ORDER BY department ASC, id ASC")->fetchAll();
$brands = $db->query("SELECT * FROM `brands` ORDER BY name ASC")->fetchAll();

// Dynamic Seller Notifications
$stmtNotifs = $db->prepare("SELECT * FROM `notifications` WHERE `seller_id` = ? OR (`seller_id` IS NULL AND `user_id` = ?) ORDER BY `id` DESC LIMIT 12");
$stmtNotifs->execute([$seller['id'], $user['id']]);
$notificationsList = $stmtNotifs->fetchAll();

$unreadNotifCount = 0;
foreach ($notificationsList as $nItem) {
    if (!$nItem['is_read']) {
        $unreadNotifCount++;
    }
}

// Preselected category if arrived from notification
$selectedCatId = (int)($_GET['category_id'] ?? 0);

// Header settings: Hide search, sub-navbar and cart for seller dashboard view
$hideSearch = true;
$hideNavbar = true;
$hideCart = true;
$pageTitle = 'Seller Dashboard — ' . $seller['shop_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
  .seller-tab-link {
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
  .seller-tab-link:hover {
    background: #f8fafc;
    color: var(--haat-clay);
  }
  .seller-tab-link.active {
    background: var(--haat-clay-light);
    color: var(--haat-clay);
    font-weight: 700;
  }
  .seller-panel {
    display: none;
    animation: fadeInTab 0.25s ease;
  }
  @keyframes fadeInTab {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .seller-conv-item:hover {
    background: #f2ede4 !important;
  }
  .seller-conv-item.active {
    background: #ffffff !important;
    border-left: 3px solid var(--haat-clay) !important;
  }
</style>

<div class="container" style="padding: 28px 20px 60px;">
  
  <!-- Dashboard Top Header -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:28px;">
    <div>
      <div style="display:flex; align-items:center; gap:10px;">
        <h1 style="font-size:1.8rem; color:var(--haat-green-dark); margin:0; font-weight:700;">
          <?= sanitize($seller['shop_name']) ?>
        </h1>
        <?php if ($seller['is_verified']): ?>
          <span class="badge badge-green"><i class="bi bi-patch-check-fill"></i> Verified</span>
        <?php else: ?>
          <span class="badge badge-gold">Pending Verification</span>
        <?php endif; ?>
      </div>
    </div>

    <div style="display:flex; align-items:center; gap:10px;">
      <!-- Seller Notifications Bell & Dropdown -->
      <div style="position:relative;">
        <button type="button" id="seller-notif-btn" style="position:relative; width:38px; height:38px; border-radius:var(--radius-sm); border:1px solid var(--haat-border); background:#fff; color:var(--haat-green-dark); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.15rem; transition:var(--transition); box-shadow:var(--shadow-sm);" title="Artisan Notifications">
          <i class="bi bi-bell"></i>
          <?php if ($unreadNotifCount > 0): ?>
            <span style="position:absolute; top:-4px; right:-4px; width:18px; height:18px; border-radius:50%; background:#d97008; color:#fff; font-size:0.7rem; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid #fff;">
              <?= $unreadNotifCount ?>
            </span>
          <?php endif; ?>
        </button>

        <!-- Dropdown panel -->
        <div id="seller-notif-dropdown" style="display:none; position:absolute; top:calc(100% + 8px); right:0; width:340px; background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); box-shadow:var(--shadow-lg); z-index:1050; overflow:hidden;">
          <div style="padding:12px 16px; border-bottom:1px solid var(--haat-border); display:flex; justify-content:space-between; align-items:center; background:#faf8f5;">
            <strong style="color:var(--haat-green-dark); font-size:0.9rem;">
              <i class="bi bi-bell-fill text-clay"></i> Artisan Notifications (<?= count($notificationsList) ?>)
            </strong>
            <?php if ($unreadNotifCount > 0): ?>
              <a href="<?= BASE_URL ?>seller/?mark_all_read=1" style="font-size:0.75rem; color:var(--haat-clay); font-weight:600; text-decoration:none;">Mark all read</a>
            <?php endif; ?>
          </div>
          <div style="max-height:320px; overflow-y:auto;">
            <?php if (empty($notificationsList)): ?>
              <p style="padding:20px; text-align:center; color:var(--text-muted); font-size:0.85rem; margin:0;">No notifications yet.</p>
            <?php else: ?>
              <?php foreach ($notificationsList as $nt): ?>
                <div style="padding:12px 16px; border-bottom:1px solid #f1f5f9; background:<?= $nt['is_read'] ? '#fff' : '#f0fdf4' ?>; transition:background 0.2s ease;">
                  <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                    <strong style="font-size:0.85rem; color:var(--haat-green-dark);"><?= sanitize($nt['title']) ?></strong>
                    <?php if (!$nt['is_read']): ?>
                      <span style="width:8px; height:8px; border-radius:50%; background:#2ecc71; flex-shrink:0; margin-top:4px;"></span>
                    <?php endif; ?>
                  </div>
                  <p style="font-size:0.8rem; color:#4a5568; margin:4px 0 8px; line-height:1.35;">
                    <?= sanitize($nt['message']) ?>
                  </p>
                  <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.72rem; color:var(--text-muted);">
                    <span><?= date('d M, h:i A', strtotime($nt['created_at'])) ?></span>
                    <div style="display:flex; gap:6px;">
                      <?php if ($nt['type'] === 'category_added'): ?>
                        <button type="button" onclick="openAddProductInInventory()" style="background:none; border:none; color:var(--haat-clay); font-weight:700; cursor:pointer; font-size:0.75rem; padding:0;">
                          Add Product &rarr;
                        </button>
                      <?php endif; ?>
                      <?php if (!$nt['is_read']): ?>
                        <a href="<?= BASE_URL ?>seller/?mark_notif_read=<?= $nt['id'] ?>" style="color:var(--text-muted); text-decoration:none;">Dismiss</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <a href="<?= BASE_URL ?>vendor.php?id=<?= $seller['id'] ?>" target="_blank" class="btn btn-sm btn-outline-green" style="display:inline-flex; align-items:center; gap:6px;">
        <i class="bi bi-eye"></i> View Public Store
      </a>
      <button type="button" onclick="openAddProductInInventory()" class="btn btn-sm btn-clay" style="display:inline-flex; align-items:center; gap:6px;">
        <i class="bi bi-plus-lg"></i> Add New Product
      </button>
    </div>
  </div>

  <!-- Unified Dashboard Layout: Left Sidebar + Right Side Dynamic Panels -->
  <div style="display:grid; grid-template-columns: 260px 1fr; gap:28px; align-items:flex-start;">
    
    <!-- PERSISTENT LEFT SIDEBAR -->
    <aside style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:20px; box-shadow:var(--shadow-sm); position:sticky; top:20px;">
      
      <!-- IMPROVED PREMIUM ARTISAN SELLER CARD -->
      <div style="background:linear-gradient(180deg, #ffffff 0%, #faf8f5 100%); border:1px solid rgba(27,61,34,0.14); border-radius:12px; padding:16px; margin-bottom:20px; box-shadow:0 3px 12px rgba(0,0,0,0.04); position:relative; overflow:hidden;">
        <!-- Top decorative brand stripe -->
        <div style="position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg, var(--haat-green), var(--haat-clay));"></div>
        
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
          <div style="width:42px; height:42px; border-radius:10px; background:linear-gradient(135deg, var(--haat-green-dark), var(--haat-green)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.25rem; flex-shrink:0; box-shadow:0 2px 6px rgba(27,61,34,0.25);">
            <i class="bi bi-shop"></i>
          </div>
          <div style="min-width:0; flex:1;">
            <div style="color:var(--haat-green-dark); font-size:0.98rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= sanitize($seller['shop_name']) ?>">
              <?= sanitize($seller['shop_name']) ?>
            </div>
            <!-- Live interactive store status toggle button -->
            <button type="button" id="seller-status-toggle-btn" onclick="toggleSellerOnline()" style="display:inline-flex; align-items:center; gap:5px; font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:12px; margin-top:2px; border:none; cursor:pointer; transition:all 0.2s ease; background:<?= $isOnline ? 'rgba(46,125,50,0.1)' : 'rgba(231,76,60,0.1)' ?>; color:<?= $isOnline ? '#2e7d32' : '#c0392b' ?>;" title="Click to toggle live Online / Away status">
              <span id="seller-status-dot" style="width:6px; height:6px; border-radius:50%; background:<?= $isOnline ? '#2ecc71' : '#e74c3c' ?>; display:inline-block; <?= $isOnline ? 'box-shadow:0 0 0 2px rgba(46,204,113,0.3);' : '' ?>"></span> 
              <span id="seller-status-text"><?= $isOnline ? 'Active Store' : 'Offline / Away' ?></span>
            </button>
          </div>
        </div>

        <div style="padding-top:10px; border-top:1px solid rgba(0,0,0,0.06); display:flex; justify-content:space-between; align-items:center; font-size:0.8rem;">
          <div style="display:flex; align-items:center; gap:6px; color:var(--text-main); font-weight:600;">
            <i class="bi bi-person-check-fill" style="color:var(--haat-clay); font-size:0.95rem;"></i>
            <span><?= sanitize($user['name']) ?></span>
          </div>
          <span style="font-size:0.75rem; color:#d97706; font-weight:700; background:#fef3c7; padding:2px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:3px;" title="<?= $reviewCount > 0 ? "Live rating based on {$reviewCount} verified reviews" : 'Artisan guild rating' ?>">
            ★ <?= number_format($liveRating, 1) ?>
            <?php if ($reviewCount > 0): ?>
              <span style="font-size:0.68rem; color:#92400e; font-weight:600;">(<?= $reviewCount ?>)</span>
            <?php endif; ?>
          </span>
        </div>
      </div>

      <!-- Navigation Tabs (Add Product is unified under Inventory) -->
      <nav style="display:flex; flex-direction:column; gap:4px;">
        <a href="#overview" class="seller-tab-link active" data-tab="overview">
          <i class="bi bi-speedometer2"></i>
          <span>Dashboard</span>
        </a>

        <a href="#products" class="seller-tab-link" data-tab="products">
          <i class="bi bi-boxes"></i>
          <span>My Products (<?= $prodCount ?>)</span>
        </a>

        <!-- Inventory tab (Housing Stock Levels and Add Product) -->
        <a href="#inventory" class="seller-tab-link" data-tab="inventory">
          <i class="bi bi-clipboard2-data"></i>
          <span>Inventory</span>
          <?php if ($lowStockCount + $outOfStockCount > 0): ?>
            <span style="margin-left:auto; font-size:0.68rem; font-weight:700; padding:2px 6px; border-radius:8px; background:<?= $outOfStockCount > 0 ? '#fee2e2; color:#b91c1c;' : '#fef3c7; color:#92400e;' ?>">
              <?= $lowStockCount + $outOfStockCount ?> Alert
            </span>
          <?php endif; ?>
        </a>

        <a href="#orders" class="seller-tab-link" data-tab="orders">
          <i class="bi bi-receipt"></i>
          <span>Customer Orders (<?= $orderStats['total_vendor_orders'] ?>)</span>
        </a>

        <a href="#messages" class="seller-tab-link" data-tab="messages">
          <i class="bi bi-chat-dots"></i>
          <span>Customer Messages</span>
          <?php if ($totalUnreadMessages > 0): ?>
            <span id="seller-nav-msg-badge" style="margin-left:auto; font-size:0.68rem; font-weight:700; padding:2px 7px; border-radius:10px; background:#b91c1c; color:#fff;">
              <?= $totalUnreadMessages ?>
            </span>
          <?php endif; ?>
        </a>

        <a href="#settings" class="seller-tab-link" data-tab="settings">
          <i class="bi bi-gear"></i>
          <span>Workshop Settings</span>
        </a>
      </nav>
    </aside>

    <!-- RIGHT SIDE CONTENT PANELS -->
    <main>
      
      <!-- Live Dynamic Announcement for New Categories -->
      <?php 
      $latestCatNotif = null;
      foreach ($notificationsList as $nItem) {
          if (!$nItem['is_read'] && $nItem['type'] === 'category_added') {
              $latestCatNotif = $nItem;
              break;
          }
      }
      if ($latestCatNotif): 
      ?>
      <div style="background:linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border:1px solid #c8e6c9; border-radius:12px; padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; box-shadow:0 3px 12px rgba(46,125,50,0.06);">
        <div style="display:flex; align-items:center; gap:14px;">
          <div style="width:42px; height:42px; border-radius:10px; background:linear-gradient(135deg, #2e7d32, #388e3c); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.35rem; flex-shrink:0; box-shadow:0 2px 6px rgba(46,125,50,0.25);">
            <i class="bi bi-broadcast"></i>
          </div>
          <div>
            <div style="font-weight:700; color:#1b3d22; font-size:0.96rem;"><?= sanitize($latestCatNotif['title']) ?></div>
            <div style="font-size:0.83rem; color:#374151; margin-top:2px; line-height:1.4;"><?= sanitize($latestCatNotif['message']) ?></div>
          </div>
        </div>
        <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
          <button type="button" onclick="openAddProductInInventory()" class="btn btn-sm btn-clay" style="font-size:0.82rem; white-space:nowrap; display:inline-flex; align-items:center; gap:6px;">
            <i class="bi bi-plus-circle"></i> Add Product in Category
          </button>
          <a href="<?= BASE_URL ?>seller/?mark_notif_read=<?= $latestCatNotif['id'] ?>" class="btn btn-sm btn-outline-green" style="font-size:0.8rem; padding:4px 10px;" title="Dismiss">
            <i class="bi bi-check2"></i> Got it
          </a>
        </div>
      </div>
      <?php endif; ?>

      <!-- ==========================================
           TAB 1: DASHBOARD OVERVIEW
           ========================================== -->
      <div id="panel-overview" class="seller-panel" style="display:block;">
        
        <!-- Performance Metrics Cards -->
        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; margin-bottom:28px;">
          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm);">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; margin-bottom:4px;">Total Sales Revenue</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--haat-green);"><?= formatPrice($orderStats['total_earnings'] ?: $seller['total_sales']) ?></div>
            <span style="font-size:0.73rem; color:var(--text-muted);">After 5% commission</span>
          </div>

          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm);">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; margin-bottom:4px;">Orders Received</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--haat-green-dark);"><?= $orderStats['total_vendor_orders'] ?></div>
            <span style="font-size:0.73rem; color:var(--text-muted);"><a href="javascript:void(0)" onclick="switchSellerTab('orders')" style="color:var(--haat-clay); font-weight:600;">Fulfill Orders &rarr;</a></span>
          </div>

          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm);">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; margin-bottom:4px;">Active Products</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--haat-clay);"><?= $prodCount ?> Items</div>
            <span style="font-size:0.73rem; color:var(--text-muted);">Rating: <?= number_format($seller['rating'], 1) ?> ★</span>
          </div>

          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <span style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Customer Messages</span>
              <?php if ($totalUnreadMessages > 0): ?>
                <span style="background:#fee2e2; color:#b91c1c; font-size:0.68rem; font-weight:700; padding:1px 6px; border-radius:10px;"><?= $totalUnreadMessages ?> New</span>
              <?php endif; ?>
            </div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--haat-green);"><?= count($customerConversations) ?> Chats</div>
            <span style="font-size:0.73rem; color:var(--text-muted);"><a href="javascript:void(0)" onclick="switchSellerTab('messages')" style="color:var(--haat-clay); font-weight:600;">Open Live Chat &rarr;</a></span>
          </div>
        </div>

        <!-- Recent Workshop Orders Table -->
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h3 style="font-size:1.2rem; color:var(--haat-green-dark); margin:0;">Recent Workshop Orders</h3>
            <button type="button" onclick="switchSellerTab('orders')" class="btn btn-sm btn-outline-green">
              Manage All Orders &rarr;
            </button>
          </div>

          <?php if (empty($recentOrders)): ?>
            <p style="color:var(--text-muted); text-align:center; padding:30px 0;">No orders for your workshop items yet.</p>
          <?php else: ?>
            <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
              <thead>
                <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
                  <th style="padding:10px 0;">Order #</th>
                  <th style="padding:10px 0;">Product Ordered</th>
                  <th style="padding:10px 0;">Qty</th>
                  <th style="padding:10px 0;">Subtotal</th>
                  <th style="padding:10px 0;">Fulfillment Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentOrders as $ro): ?>
                  <tr style="border-bottom:1px solid var(--haat-border);">
                    <td style="padding:14px 0; font-weight:700; color:var(--haat-green-dark);">
                      <?= sanitize($ro['order_number']) ?>
                    </td>
                    <td style="padding:14px 0;">
                      <?= sanitize($ro['product_name']) ?>
                    </td>
                    <td style="padding:14px 0;"><?= $ro['quantity'] ?></td>
                    <td style="padding:14px 0; font-weight:700; color:var(--haat-green);">
                      <?= formatPrice($ro['subtotal']) ?>
                    </td>
                    <td style="padding:14px 0;">
                      <span class="badge badge-<?= $ro['vendor_status'] === 'delivered' ? 'green' : 'gold' ?>">
                        <?= ucfirst($ro['vendor_status']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

      </div>

      <!-- ==========================================
           TAB 2: MY PRODUCTS
           ========================================== -->
      <div id="panel-products" class="seller-panel">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:26px; box-shadow:var(--shadow-sm);">
          
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; flex-wrap:wrap; gap:12px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0;">
                <i class="bi bi-boxes text-clay"></i> My Workshop Craft Listings
              </h2>
              <p style="color:var(--text-muted); font-size:0.85rem; margin:3px 0 0;">Manage your active products, inventory stock, and pricing</p>
            </div>
            <button type="button" onclick="openAddProductInInventory()" class="btn btn-sm btn-clay" style="display:inline-flex; align-items:center; gap:6px;">
              <i class="bi bi-plus-lg"></i> Add New Craft
            </button>
          </div>

          <?php if (empty($productsList)): ?>
            <div style="text-align:center; padding:40px 0;">
              <p style="color:var(--text-muted); margin-bottom:16px;">You haven't listed any products yet.</p>
              <button type="button" onclick="openAddProductInInventory()" class="btn btn-clay">Add Your First Product</button>
            </div>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
                <thead>
                  <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
                    <th style="padding:12px 10px;">Product</th>
                    <th style="padding:12px 10px;">Category</th>
                    <th style="padding:12px 10px;">Price</th>
                    <th style="padding:12px 10px;">Stock</th>
                    <th style="padding:12px 10px;">Status</th>
                    <th style="padding:12px 10px; text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($productsList as $p): ?>
                    <tr style="border-bottom:1px solid var(--haat-border);">
                      <td style="padding:14px 10px; display:flex; align-items:center; gap:14px;">
                        <img src="<?= sanitize($p['featured_image']) ?>" alt="thumb" style="width:48px; height:48px; border-radius:var(--radius-sm); object-fit:cover; border:1px solid var(--haat-border);">
                        <div>
                          <a href="<?= BASE_URL ?>product.php?id=<?= $p['id'] ?>" target="_blank" style="font-weight:700; color:var(--haat-green-dark); display:block; text-decoration:none;">
                            <?= sanitize($p['name']) ?>
                          </a>
                          <span style="font-size:0.75rem; color:var(--text-muted);">SKU: <?= sanitize($p['sku'] ?: 'HAAT-' . $p['id']) ?> • <?= sanitize($p['district_origin']) ?></span>
                        </div>
                      </td>
                      <td style="padding:14px 10px; color:var(--text-muted); font-size:0.88rem;">
                        <?= sanitize($p['category_name']) ?>
                      </td>
                      <td style="padding:14px 10px;">
                        <strong style="color:var(--haat-green); font-size:0.95rem;"><?= formatPrice($p['sale_price'] ?: $p['price']) ?></strong>
                        <?php if ($p['sale_price']): ?>
                          <div style="font-size:0.75rem; color:var(--text-light); text-decoration:line-through;"><?= formatPrice($p['price']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td style="padding:14px 10px;">
                        <?php if ($p['stock_quantity'] > 0): ?>
                          <span style="color:#2b8a3e; font-weight:700;"><?= $p['stock_quantity'] ?> <?= sanitize($p['unit']) ?>s</span>
                        <?php else: ?>
                          <span style="color:#c52828; font-weight:700;">Out of Stock</span>
                        <?php endif; ?>
                      </td>
                      <td style="padding:14px 10px;">
                        <span class="badge badge-<?= $p['is_active'] ? 'green' : 'gold' ?>">
                          <?= $p['is_active'] ? 'Active' : 'Draft' ?>
                        </span>
                        <?php if ($p['is_flash_deal']): ?>
                          <span class="badge badge-clay" style="font-size:0.65rem;">Flash</span>
                        <?php endif; ?>
                      </td>
                      <td style="padding:14px 10px; text-align:right; white-space:nowrap;">
                        <a href="<?= BASE_URL ?>seller/product-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-green" style="margin-right:6px; padding:4px 9px; font-size:0.82rem;">
                          <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="<?= BASE_URL ?>seller/?delete_product=<?= $p['id'] ?>" onclick="return confirm('Are you sure you want to remove this product?');" class="btn btn-sm" style="color:#c52828; border:1px solid #f8c8dc; padding:4px 9px; font-size:0.82rem;">
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
           TAB 3: INVENTORY (Houses Stock Levels & Add Product Form)
           ========================================== -->
      <div id="panel-inventory" class="seller-panel">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:26px; box-shadow:var(--shadow-sm);">
          
          <!-- Unified Header with Sub-view Switcher -->
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:14px; border-bottom:1px solid var(--haat-border); padding-bottom:18px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:8px;">
                <i class="bi bi-clipboard2-data text-clay"></i> Inventory & Stock Control
              </h2>
              <p style="color:var(--text-muted); font-size:0.85rem; margin:3px 0 0;">
                Monitor stock levels, track valuations, and add new products directly into inventory
              </p>
            </div>

            <!-- Clean Sub-Tabs: Stock Levels vs Add Product -->
            <div style="display:flex; gap:6px; background:#f1f5f9; padding:4px; border-radius:var(--radius-sm);">
              <button type="button" id="subtab-btn-stock" onclick="switchInventoryView('stock')" style="border:none; padding:8px 16px; border-radius:var(--radius-sm); font-size:0.85rem; font-weight:700; cursor:pointer; background:#fff; color:var(--haat-green-dark); box-shadow:var(--shadow-sm); display:inline-flex; align-items:center; gap:6px;">
                <i class="bi bi-stack"></i> Stock Levels (<?= $prodCount ?>)
              </button>
              <button type="button" id="subtab-btn-add" onclick="switchInventoryView('add')" style="border:none; padding:8px 16px; border-radius:var(--radius-sm); font-size:0.85rem; font-weight:600; cursor:pointer; background:transparent; color:var(--text-muted); display:inline-flex; align-items:center; gap:6px;">
                <i class="bi bi-plus-circle text-clay"></i> + Add Product to Inventory
              </button>
            </div>
          </div>

          <!-- SUB-VIEW 1: STOCK LEVELS & VALUATION -->
          <div id="inv-view-stock">
            <!-- Inventory Summary Stats Cards -->
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-bottom:24px;">
              <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md); padding:16px;">
                <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Total Units on Hand</div>
                <div style="font-size:1.6rem; font-weight:800; color:var(--haat-green-dark); margin-top:2px;">
                  <?= number_format($totalUnits) ?> <span style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">Units</span>
                </div>
                <span style="font-size:0.72rem; color:var(--text-muted);">Across <?= $prodCount ?> active craft listings</span>
              </div>

              <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md); padding:16px;">
                <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Inventory Valuation</div>
                <div style="font-size:1.6rem; font-weight:800; color:var(--haat-green); margin-top:2px;">
                  <?= formatPrice($totalValuation) ?>
                </div>
                <span style="font-size:0.72rem; color:var(--text-muted);">Current workshop retail value</span>
              </div>

              <div style="background:<?= ($lowStockCount + $outOfStockCount > 0) ? '#fff8eb' : '#f8fafc' ?>; border:1px solid <?= ($lowStockCount + $outOfStockCount > 0) ? '#fde68a' : '#e2e8f0' ?>; border-radius:var(--radius-md); padding:16px;">
                <div style="font-size:0.75rem; text-transform:uppercase; color:<?= ($lowStockCount + $outOfStockCount > 0) ? '#b45309' : 'var(--text-muted)' ?>; font-weight:700;">Stock Warnings</div>
                <div style="font-size:1.6rem; font-weight:800; color:<?= ($lowStockCount + $outOfStockCount > 0) ? '#b45309' : 'var(--haat-green-dark)' ?>; margin-top:2px;">
                  <?= $lowStockCount + $outOfStockCount ?> <span style="font-size:0.85rem; font-weight:600;">Items</span>
                </div>
                <span style="font-size:0.72rem; color:var(--text-muted);">
                  <?= $outOfStockCount ?> Out of Stock • <?= $lowStockCount ?> Low Stock (&le;3)
                </span>
              </div>
            </div>

            <!-- Inventory Table with Fast Quantity Adjuster -->
            <?php if (empty($productsList)): ?>
              <div style="text-align:center; padding:40px 0;">
                <p style="color:var(--text-muted); margin-bottom:14px;">No products in inventory yet.</p>
                <button type="button" onclick="switchInventoryView('add')" class="btn btn-clay">+ Add Product to Inventory</button>
              </div>
            <?php else: ?>
              <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
                  <thead>
                    <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
                      <th style="padding:12px 10px;">Item / Craft</th>
                      <th style="padding:12px 10px;">Unit Price</th>
                      <th style="padding:12px 10px;">Total Value</th>
                      <th style="padding:12px 10px;">Stock Status</th>
                      <th style="padding:12px 10px; text-align:right;">Adjust Quantity</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($productsList as $p): 
                      $pStock = (int)$p['stock_quantity'];
                      $pPrice = (float)($p['sale_price'] ?: $p['price']);
                      $itemVal = $pStock * $pPrice;
                    ?>
                      <tr style="border-bottom:1px solid var(--haat-border);" id="inv-row-<?= $p['id'] ?>">
                        <td style="padding:14px 10px; display:flex; align-items:center; gap:12px;">
                          <img src="<?= sanitize($p['featured_image']) ?>" alt="thumb" style="width:46px; height:46px; border-radius:var(--radius-sm); object-fit:cover; border:1px solid var(--haat-border);">
                          <div>
                            <strong style="color:var(--haat-green-dark); display:block; font-size:0.92rem;">
                              <?= sanitize($p['name']) ?>
                            </strong>
                            <span style="font-size:0.75rem; color:var(--text-muted);">
                              SKU: <?= sanitize($p['sku'] ?: 'HAAT-' . $p['id']) ?> • <?= sanitize($p['category_name']) ?>
                            </span>
                          </div>
                        </td>

                        <td style="padding:14px 10px;">
                          <strong style="color:var(--text-main); font-size:0.92rem;"><?= formatPrice($pPrice) ?></strong>
                        </td>

                        <td style="padding:14px 10px;">
                          <strong style="color:var(--haat-green); font-size:0.95rem;" id="inv-val-<?= $p['id'] ?>">
                            <?= formatPrice($itemVal) ?>
                          </strong>
                        </td>

                        <td style="padding:14px 10px;" id="inv-status-cell-<?= $p['id'] ?>">
                          <?php if ($pStock > 5): ?>
                            <span class="badge badge-green">In Stock (<?= $pStock ?>)</span>
                          <?php elseif ($pStock > 0): ?>
                            <span class="badge badge-gold">Low Stock (<?= $pStock ?>)</span>
                          <?php else: ?>
                            <span class="badge badge-red" style="background:#fee2e2; color:#b91c1c;">Out of Stock</span>
                          <?php endif; ?>
                        </td>

                        <td style="padding:14px 10px; text-align:right;">
                          <form method="POST" action="<?= BASE_URL ?>seller/" style="display:inline-flex; align-items:center; gap:6px; justify-content:flex-end;" onsubmit="handleQuickStock(event, <?= $p['id'] ?>, <?= $pPrice ?>)">
                            <input type="hidden" name="action_update_stock" value="1">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            
                            <div style="display:inline-flex; align-items:center; border:1px solid var(--haat-border); border-radius:var(--radius-sm); background:#fff; overflow:hidden;">
                              <button type="button" onclick="stepStock(<?= $p['id'] ?>, -1)" style="border:none; background:#f1f5f9; padding:5px 9px; cursor:pointer; color:var(--text-main); font-weight:700;">-</button>
                              <input type="number" name="stock_quantity" id="stock-input-<?= $p['id'] ?>" value="<?= $pStock ?>" min="0" style="width:52px; text-align:center; border:none; padding:4px; font-weight:700; font-size:0.88rem; outline:none;">
                              <button type="button" onclick="stepStock(<?= $p['id'] ?>, 1)" style="border:none; background:#f1f5f9; padding:5px 9px; cursor:pointer; color:var(--text-main); font-weight:700;">+</button>
                            </div>

                            <button type="submit" id="stock-btn-<?= $p['id'] ?>" class="btn btn-sm btn-outline-green" style="padding:5px 9px;" title="Update Quantity">
                              <i class="bi bi-check2"></i>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- SUB-VIEW 2: ADD PRODUCT DIRECTLY TO INVENTORY -->
          <div id="inv-view-add" style="display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
              <div>
                <h3 style="font-size:1.15rem; color:var(--haat-green-dark); margin:0;">
                  <i class="bi bi-plus-circle text-clay"></i> List a New Craft Product to Inventory
                </h3>
                <p style="color:var(--text-muted); font-size:0.82rem; margin:2px 0 0;">Fill out product specifications and starting stock count</p>
              </div>
              <button type="button" onclick="switchInventoryView('stock')" class="btn btn-sm btn-outline-green">
                &larr; Back to Stock List
              </button>
            </div>

            <form method="POST" action="<?= BASE_URL ?>seller/" enctype="multipart/form-data">
              <input type="hidden" name="action_add_product" value="1">

              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                  <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Product Name (English) *</label>
                  <input type="text" name="name" required placeholder="e.g. Tangail Handloom Cotton Saree" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
                </div>

                <div>
                  <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Product Name (Bengali)</label>
                  <input type="text" name="name_bn" placeholder="e.g. টাঙ্গাইল তাঁতের সুতি শাড়ি" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
                </div>
              </div>

              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                  <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Craft Category *</label>
                  <select name="category_id" required style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none; background:#fff;">
                    <option value="">Select Category</option>
                    <?php 
                    $currDept = '';
                    foreach ($categories as $cat): 
                        if ($cat['department'] !== $currDept):
                            if ($currDept !== '') echo '</optgroup>';
                            $currDept = $cat['department'];
                            echo '<optgroup label="' . htmlspecialchars($currDept) . '">';
                        endif;
                    ?>
                      <option value="<?= $cat['id'] ?>" <?= ($selectedCatId == $cat['id']) ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; if ($currDept !== '') echo '</optgroup>'; ?>
                  </select>
                </div>

                <div>
                  <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Brand / Artisan Guild</label>
                  <select name="brand_id" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none; background:#fff;">
                    <option value="">Independent Artisan / None</option>
                    <?php foreach ($brands as $b): ?>
                      <option value="<?= $b['id'] ?>"><?= sanitize($b['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap:14px; margin-bottom:16px;">
                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Base Price (৳) *</label>
                  <input type="number" step="0.01" name="price" required placeholder="0.00" style="width:100%; padding:10px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none;">
                </div>

                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Sale Price (Optional)</label>
                  <input type="number" step="0.01" name="sale_price" placeholder="0.00" style="width:100%; padding:10px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none;">
                </div>

                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Starting Stock *</label>
                  <input type="number" name="stock_quantity" value="10" min="0" required style="width:100%; padding:10px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none;">
                </div>

                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Unit</label>
                  <select name="unit" style="width:100%; padding:10px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none; background:#fff;">
                    <option value="piece">piece</option>
                    <option value="pair">pair</option>
                    <option value="set">set</option>
                    <option value="kg">kg</option>
                    <option value="meter">meter</option>
                  </select>
                </div>
              </div>

              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                  <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">District of Origin</label>
                  <input type="text" name="district_origin" value="<?= sanitize($seller['district']) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
                </div>

                <div>
                  <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Upload Photo / Image URL</label>
                  <input type="file" name="image_file" accept="image/*" style="width:100%; padding:8px 10px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.85rem; outline:none; background:#fff;">
                </div>
              </div>

              <div style="margin-bottom:16px;">
                <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Image URL (Alternative)</label>
                <input type="url" name="featured_image" placeholder="https://example.com/craft-photo.jpg" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
              </div>

              <div style="margin-bottom:16px;">
                <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Short Summary</label>
                <input type="text" name="short_description" placeholder="Brief 1-sentence craft summary" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
              </div>

              <div style="margin-bottom:18px;">
                <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Heritage & Crafting Details</label>
                <textarea name="description" rows="3" placeholder="Describe weaving technique, raw materials, clay source, or history..." style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;"></textarea>
              </div>

              <div style="display:flex; gap:24px; margin-bottom:24px;">
                <label style="display:flex; align-items:center; gap:8px; font-size:0.88rem; cursor:pointer;">
                  <input type="checkbox" name="is_flash_deal" value="1">
                  <span>Feature in <strong>Flash Deals</strong></span>
                </label>

                <label style="display:flex; align-items:center; gap:8px; font-size:0.88rem; cursor:pointer;">
                  <input type="checkbox" name="is_featured" value="1">
                  <span>Mark as <strong>Artisan Spotlight</strong></span>
                </label>
              </div>

              <button type="submit" class="btn btn-clay btn-lg" style="width:100%;">
                Publish Craft & Add to Inventory
              </button>
            </form>
          </div>

        </div>
      </div>

      <!-- ==========================================
           TAB 4: CUSTOMER ORDERS & FULFILLMENT
           ========================================== -->
      <div id="panel-orders" class="seller-panel">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:26px; box-shadow:var(--shadow-sm);">
          
          <div style="margin-bottom:22px;">
            <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0;">
              <i class="bi bi-receipt text-clay"></i> Workshop Orders & Fulfillment
            </h2>
            <p style="color:var(--text-muted); font-size:0.85rem; margin:3px 0 0;">Process customer orders, prepare artisan parcels, and update delivery status</p>
          </div>

          <?php if (empty($allOrders)): ?>
            <div style="text-align:center; padding:40px 0;">
              <i class="bi bi-box-seam text-muted" style="font-size:2.8rem; display:block; margin-bottom:10px;"></i>
              <h3>No Customer Orders Yet</h3>
              <p style="color:var(--text-muted); font-size:0.9rem;">As soon as buyers purchase your artisanal crafts, their orders will appear here for fulfillment.</p>
            </div>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
                <thead>
                  <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
                    <th style="padding:12px 10px;">Order #</th>
                    <th style="padding:12px 10px;">Ordered Item</th>
                    <th style="padding:12px 10px;">Recipient & Destination</th>
                    <th style="padding:12px 10px;">Subtotal</th>
                    <th style="padding:12px 10px;">Payment</th>
                    <th style="padding:12px 10px;">Fulfillment Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allOrders as $ord): ?>
                    <tr style="border-bottom:1px solid var(--haat-border);">
                      <td style="padding:16px 10px; font-weight:700; color:var(--haat-green-dark); white-space:nowrap;">
                        <?= sanitize($ord['order_number']) ?>
                        <div style="font-size:0.75rem; color:var(--text-muted); font-weight:400;"><?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?></div>
                      </td>
                      <td style="padding:16px 10px;">
                        <strong><?= sanitize($ord['product_name']) ?></strong>
                        <div style="font-size:0.8rem; color:var(--text-muted);"><?= $ord['quantity'] ?> × <?= formatPrice($ord['price']) ?></div>
                      </td>
                      <td style="padding:16px 10px; font-size:0.85rem;">
                        <strong><?= sanitize($ord['shipping_name']) ?></strong> (<?= sanitize($ord['shipping_phone']) ?>)<br>
                        <?= sanitize($ord['shipping_address']) ?>, <?= sanitize($ord['ship_district']) ?>
                      </td>
                      <td style="padding:16px 10px; font-weight:800; color:var(--haat-green); font-size:1.05rem; white-space:nowrap;">
                        <?= formatPrice($ord['subtotal']) ?>
                      </td>
                      <td style="padding:16px 10px; white-space:nowrap;">
                        <span class="pay-badge pay-<?= $ord['payment_method'] ?>" style="font-size:0.75rem;">
                          <?= strtoupper($ord['payment_method']) ?>
                        </span>
                        <span style="font-size:0.75rem; display:block; color:var(--text-muted); margin-top:2px;">
                          <?= ucfirst($ord['payment_status']) ?>
                        </span>
                      </td>
                      <td style="padding:16px 10px; white-space:nowrap;">
                        <div style="display:flex; align-items:center; gap:6px;">
                          <form method="POST" action="<?= BASE_URL ?>seller/" style="display:flex; align-items:center; gap:6px; margin:0;">
                            <input type="hidden" name="action_update_order_status" value="1">
                            <input type="hidden" name="item_id" value="<?= $ord['id'] ?>">
                            <select name="vendor_status" style="padding:6px 10px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.82rem; font-weight:600; outline:none; background:#fff;">
                              <option value="pending" <?= $ord['vendor_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                              <option value="processing" <?= $ord['vendor_status'] === 'processing' ? 'selected' : '' ?>>Processing / Crafting</option>
                              <option value="shipped" <?= $ord['vendor_status'] === 'shipped' ? 'selected' : '' ?>>Dispatched / Shipped</option>
                              <option value="delivered" <?= $ord['vendor_status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                              <option value="cancelled" <?= $ord['vendor_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-green" style="padding:4px 8px;" title="Save Status">
                              <i class="bi bi-check-lg"></i>
                            </button>
                          </form>
                          <?php if (!empty($ord['customer_user_id'])): ?>
                            <button type="button" onclick="openChatWithCustomer(<?= (int)$ord['customer_user_id'] ?>, '<?= sanitize($ord['order_number']) ?>', '<?= sanitize($ord['product_name']) ?>')" class="btn btn-sm btn-outline-clay" style="padding:5px 9px; font-size:0.8rem; display:inline-flex; align-items:center; gap:4px;" title="Chat with Buyer">
                              <i class="bi bi-chat-dots-fill"></i> Chat
                            </button>
                          <?php endif; ?>
                        </div>
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
           TAB 5: CUSTOMER MESSAGES (LIVE MESSAGING SYSTEM)
           ========================================== -->
      <div id="panel-messages" class="seller-panel" style="display:none;">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); overflow:hidden; box-shadow:var(--shadow-sm);">
          
          <!-- Header Bar -->
          <div style="padding:16px 24px; border-bottom:1px solid var(--haat-border); background:#ffffff; display:flex; align-items:center;">
            <h2 style="font-size:1.3rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:10px;">
              <i class="bi bi-chat-dots-fill text-clay"></i> Artisan-Customer Live Messenger
            </h2>
          </div>

          <!-- Split-Screen Messenger Workspace -->
          <div style="display:grid; grid-template-columns: 320px 1fr; min-height: 600px;">
            
            <!-- Left Column: Customer Conversations List -->
            <div style="border-right:1px solid var(--haat-border); background:#fcfbfa; display:flex; flex-direction:column;">
              
              <!-- Search Customers / Orders -->
              <div style="padding:14px; border-bottom:1px solid var(--haat-border); background:#ffffff;">
                <div style="position:relative;">
                  <i class="bi bi-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.85rem;"></i>
                  <input type="text" id="seller-search-conv" oninput="filterSellerConversations()" placeholder="Search buyer or order #..." style="width:100%; padding:8px 12px 8px 34px; border:1px solid var(--haat-border); border-radius:20px; font-size:0.85rem; outline:none; background:#f9fafb;">
                </div>
              </div>

              <!-- Conversation Threads List -->
              <div id="seller-conv-list" style="flex:1; overflow-y:auto; max-height:550px;">
                <?php if (empty($customerConversations)): ?>
                  <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
                    <i class="bi bi-chat-heart text-clay" style="font-size:2.4rem; display:block; margin-bottom:10px;"></i>
                    <strong style="color:var(--haat-green-dark); font-size:0.95rem; display:block;">No customer chats yet</strong>
                    <p style="font-size:0.8rem; margin-top:4px;">When buyers place orders or ask questions about your artisanal products, conversations will appear here.</p>
                  </div>
                <?php else: ?>
                  <?php foreach ($customerConversations as $idx => $conv): ?>
                    <div class="seller-conv-item <?= $idx === 0 ? 'active' : '' ?>" 
                         data-customer-id="<?= $conv['customer_id'] ?>"
                         data-order-num="<?= sanitize($conv['order_number'] ?? '') ?>"
                         data-product-name="<?= sanitize($conv['product_name'] ?? '') ?>"
                         data-customer-name="<?= sanitize($conv['customer_name']) ?>"
                         data-customer-phone="<?= sanitize($conv['customer_phone'] ?? '') ?>"
                         data-is-online="<?= $conv['is_online'] ? 1 : 0 ?>"
                         onclick="selectCustomerConversation(<?= $conv['customer_id'] ?>, '<?= sanitize($conv['order_number'] ?? '') ?>', '<?= sanitize($conv['product_name'] ?? '') ?>', <?= $conv['is_online'] ? 1 : 0 ?>, '<?= sanitize($conv['customer_name']) ?>', '<?= sanitize($conv['customer_phone'] ?? '') ?>')"
                         style="padding:12px 14px; border-bottom:1px solid #f0ebe4; cursor:pointer; display:flex; gap:12px; align-items:center; transition:background 0.2s; position:relative; background:<?= $idx === 0 ? '#ffffff' : 'transparent' ?>; <?= $idx === 0 ? 'border-left:3px solid var(--haat-clay);' : '' ?>">
                      
                      <!-- Customer Avatar with Online Dot -->
                      <div style="position:relative; flex-shrink:0;">
                        <div style="width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg, #f7efe6, #ebd9c8); color:var(--haat-clay); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.05rem; border:1px solid #e2d5c5;">
                          <?= strtoupper(substr($conv['customer_name'], 0, 1)) ?>
                        </div>
                        <span class="seller-conv-dot" style="position:absolute; bottom:0; right:0; width:11px; height:11px; background:<?= $conv['is_online'] ? '#2ecc71' : '#cbd5e1' ?>; border-radius:50%; border:2px solid #fff;" title="<?= $conv['is_online'] ? 'Active Now' : 'Offline' ?>"></span>
                      </div>

                      <!-- Details -->
                      <div style="flex:1; min-width:0;">
                        <div style="display:flex; justify-content:space-between; align-items:baseline; gap:6px;">
                          <strong style="font-size:0.88rem; color:var(--haat-green-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;">
                            <?= sanitize($conv['customer_name']) ?>
                          </strong>
                          <?php if (!empty($conv['last_message_time'])): ?>
                            <span style="font-size:0.68rem; color:var(--text-muted); flex-shrink:0;">
                              <?= date('h:i A', strtotime($conv['last_message_time'])) ?>
                            </span>
                          <?php endif; ?>
                        </div>

                        <?php if (!empty($conv['order_number'])): ?>
                          <div style="font-size:0.73rem; color:var(--haat-clay); font-weight:600; display:flex; align-items:center; gap:4px; margin:2px 0;">
                            <i class="bi bi-bag-check" style="font-size:0.72rem;"></i>
                            <span>#<?= sanitize($conv['order_number']) ?></span>
                            <?php if (!empty($conv['product_name'])): ?>
                              <span style="color:var(--text-muted); font-weight:400; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:120px;">• <?= sanitize($conv['product_name']) ?></span>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>

                        <div style="display:flex; align-items:center; justify-content:space-between; gap:6px;">
                          <div style="font-size:0.74rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;">
                            <?= sanitize($conv['last_message'] ?? 'Click to chat...') ?>
                          </div>
                          <?php if ($conv['unread_count'] > 0): ?>
                            <span class="conv-unread-pill" style="font-size:0.65rem; background:var(--haat-clay); color:#fff; font-weight:700; padding:1px 6px; border-radius:10px;">
                              <?= $conv['unread_count'] ?>
                            </span>
                          <?php endif; ?>
                        </div>
                      </div>

                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

            </div>

            <!-- Right Column: Active Live Chat Window -->
            <div style="display:flex; flex-direction:column; background:#ffffff;">
              
              <!-- Chat Header -->
              <div id="seller-chat-header" style="padding:14px 20px; border-bottom:1px solid var(--haat-border); display:flex; justify-content:space-between; align-items:center; background:#ffffff;">
                <div style="display:flex; align-items:center; gap:12px;">
                  <div style="position:relative;">
                    <div id="seller-chat-avatar" style="width:42px; height:42px; border-radius:50%; background:var(--haat-sand); display:flex; align-items:center; justify-content:center; color:var(--haat-green); font-size:1.15rem; font-weight:700;">
                      <?= !empty($customerConversations) ? strtoupper(substr($customerConversations[0]['customer_name'], 0, 1)) : 'C' ?>
                    </div>
                    <span id="seller-chat-avatar-status" style="position:absolute; bottom:0; right:0; width:11px; height:11px; background:<?= (!empty($customerConversations) && $customerConversations[0]['is_online']) ? '#2ecc71' : '#cbd5e1' ?>; border-radius:50%; border:2px solid #fff;"></span>
                  </div>

                  <div>
                    <strong id="seller-chat-title" style="color:var(--haat-green-dark); font-size:0.98rem; display:block;">
                      <?= !empty($customerConversations) ? sanitize($customerConversations[0]['customer_name']) : 'Select a Customer' ?>
                    </strong>
                    <span id="seller-chat-subtitle" style="font-size:0.75rem; color:var(--text-muted); display:flex; align-items:center; gap:6px;">
                      <?= !empty($customerConversations) && !empty($customerConversations[0]['customer_phone']) ? '<i class="bi bi-telephone"></i> ' . sanitize($customerConversations[0]['customer_phone']) : 'Buyer Account' ?>
                    </span>
                  </div>
                </div>

                <!-- Order Reference Tag -->
                <div id="seller-chat-order-tag">
                  <span id="seller-chat-order-label" class="badge" style="background:#f7efe6; color:var(--haat-clay); font-size:0.84rem; font-weight:700; padding:6px 12px; border-radius:6px; letter-spacing:0.3px; <?= (empty($customerConversations) || empty($customerConversations[0]['order_number'])) ? 'display:none;' : '' ?>">
                    <?php if (!empty($customerConversations) && !empty($customerConversations[0]['order_number'])): ?>
                      #<?= sanitize($customerConversations[0]['order_number']) ?>
                    <?php endif; ?>
                  </span>
                </div>
              </div>

              <!-- Message Stream Bubbles -->
              <div id="seller-chat-stream" style="flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:12px; background:#f9f9f9; min-height:380px;">
                <div style="text-align:center; padding:40px; color:var(--text-muted); font-size:0.9rem;">
                  <i class="bi bi-chat-heart text-clay" style="font-size:2.4rem; display:block; margin-bottom:10px;"></i>
                  Loading customer conversation...
                </div>
              </div>

              <!-- Chat Input Bar -->
              <div style="border-top:1px solid var(--haat-border); padding:12px 18px; background:#ffffff;">
                
                <!-- Quick Suggestion Tags for Artisan -->
                <div style="display:flex; gap:6px; margin-bottom:10px; overflow-x:auto; padding-bottom:4px;">
                  <button type="button" onclick="setSellerQuickMsg('Assalamu Alaikum! How can we assist you with our craft?')" class="badge" style="background:#f4f4f4; color:var(--text-main); border:1px solid var(--haat-border); cursor:pointer; font-weight:500; font-size:0.75rem; white-space:nowrap;">
                    👋 Assalamu Alaikum
                  </button>
                  <button type="button" onclick="setSellerQuickMsg('Your parcel is carefully packed and scheduled for courier pickup.')" class="badge" style="background:#f4f4f4; color:var(--text-main); border:1px solid var(--haat-border); cursor:pointer; font-weight:500; font-size:0.75rem; white-space:nowrap;">
                    🚚 Dispatched via courier
                  </button>
                  <button type="button" onclick="setSellerQuickMsg('We are handcrafting your item in our workshop with authentic materials.')" class="badge" style="background:#f4f4f4; color:var(--text-main); border:1px solid var(--haat-border); cursor:pointer; font-weight:500; font-size:0.75rem; white-space:nowrap;">
                    🧵 Handcrafting now
                  </button>
                  <button type="button" onclick="setSellerQuickMsg('Thank you for supporting authentic Bangladeshi artisanal heritage!')" class="badge" style="background:#f4f4f4; color:var(--text-main); border:1px solid var(--haat-border); cursor:pointer; font-weight:500; font-size:0.75rem; white-space:nowrap;">
                    ✨ Thank you for supporting
                  </button>
                </div>

                <!-- Input Form -->
                <form id="seller-chat-form" onsubmit="sendSellerMessage(event)" style="display:flex; gap:10px; align-items:center;">
                  <input type="hidden" id="seller-chat-customer-id" value="<?= !empty($customerConversations) ? (int)$customerConversations[0]['customer_id'] : 0 ?>">
                  <input type="text" id="seller-chat-input" placeholder="Type a message to buyer..." required autocomplete="off" style="flex:1; padding:10px 16px; border:1px solid var(--haat-border); border-radius:24px; font-size:0.9rem; outline:none; transition:var(--transition);" onfocus="this.style.borderColor='var(--haat-clay)';" onblur="this.style.borderColor='var(--haat-border)';">
                  <button type="submit" id="seller-chat-send-btn" class="btn btn-clay" style="width:42px; height:42px; border-radius:50%; padding:0; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i class="bi bi-send-fill" style="margin-left:2px;"></i>
                  </button>
                </form>

              </div>

            </div>

          </div>

        </div>
      </div>

      <!-- ==========================================
           TAB 6: WORKSHOP SETTINGS
           ========================================== -->
      <div id="panel-settings" class="seller-panel">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:30px; box-shadow:var(--shadow-sm);">
          
          <div style="margin-bottom:22px;">
            <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0;">
              <i class="bi bi-gear text-clay"></i> Workshop & Payout Profile
            </h2>
            <p style="color:var(--text-muted); font-size:0.85rem; margin:3px 0 0;">Manage your public artisan store branding and bKash payout account</p>
          </div>

          <form method="POST" action="<?= BASE_URL ?>seller/">
            <input type="hidden" name="action_shop_settings" value="1">
            
            <div style="margin-bottom:16px;">
              <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Shop / Guild Name *</label>
              <input type="text" name="shop_name" required value="<?= sanitize($seller['shop_name']) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
              <div>
                <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Division</label>
                <select name="division" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none; background:#fff;">
                  <?php 
                    $divs = ['Dhaka', 'Chittagong', 'Rajshahi', 'Sylhet', 'Khulna', 'Barisal', 'Rangpur', 'Mymensingh'];
                    foreach ($divs as $d):
                  ?>
                    <option value="<?= $d ?>" <?= $seller['division'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">District Origin *</label>
                <input type="text" name="district" required value="<?= sanitize($seller['district']) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
              </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
              <div>
                <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Workshop Phone</label>
                <input type="text" name="phone" value="<?= sanitize($seller['phone'] ?? '') ?>" placeholder="01XXXXXXXXX" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
              </div>

              <div>
                <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Workshop Physical Location</label>
                <input type="text" name="address" value="<?= sanitize($seller['address']) ?>" placeholder="e.g. Rupganj, Narayanganj" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
              </div>
            </div>

            <!-- Payout Details Box -->
            <div style="background:var(--haat-sand); border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:20px; margin-bottom:20px;">
              <h4 style="color:var(--haat-green-dark); font-size:1rem; margin-bottom:12px;">
                <i class="bi bi-wallet2 text-clay"></i> Sales Payout Information
              </h4>

              <div style="margin-bottom:14px;">
                <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">bKash Payout Number (Primary)</label>
                <input type="text" name="bkash_number" value="<?= sanitize($seller['bkash_number']) ?>" placeholder="01XXXXXXXXX" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; background:#fff; outline:none;">
              </div>

              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Bank Name (Optional)</label>
                  <input type="text" name="bank_name" value="<?= sanitize($seller['bank_name']) ?>" placeholder="e.g. Dutch-Bangla Bank" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; background:#fff; outline:none;">
                </div>
                <div>
                  <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Bank Account Number</label>
                  <input type="text" name="bank_account_no" value="<?= sanitize($seller['bank_account_no']) ?>" placeholder="Account Number" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; background:#fff; outline:none;">
                </div>
              </div>
            </div>

            <div style="margin-bottom:22px;">
              <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">About Your Craft & Heritage Story</label>
              <textarea name="description" rows="4" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;"><?= sanitize($seller['description']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-clay btn-lg" style="width:100%;">
              Save Workshop Settings
            </button>
          </form>

        </div>
      </div>

    </main>
  </div>
</div>

<script>
  // Unified Seller Dashboard Tab Switching
  let activeCustomerId = <?= !empty($customerConversations) ? (int)$customerConversations[0]['customer_id'] : 0 ?>;
  let sellerChatPollTimer = null;

  // Unified Seller Dashboard Tab Switching
  function switchSellerTab(tabId) {
    if (tabId === 'add-product') {
      switchSellerTab('inventory');
      switchInventoryView('add');
      return;
    }

    const validTabs = ['overview', 'products', 'inventory', 'orders', 'messages', 'settings'];
    if (!validTabs.includes(tabId)) {
      tabId = 'overview';
    }

    // Hide all panels
    document.querySelectorAll('.seller-panel').forEach(panel => {
      panel.style.display = 'none';
    });

    // Remove active state from all links
    document.querySelectorAll('.seller-tab-link').forEach(link => {
      link.classList.remove('active');
    });

    // Show target panel
    const targetPanel = document.getElementById(`panel-${tabId}`);
    if (targetPanel) {
      targetPanel.style.display = 'block';
    }

    // Activate corresponding link
    const targetLink = document.querySelector(`.seller-tab-link[data-tab="${tabId}"]`);
    if (targetLink) {
      targetLink.classList.add('active');
    }

    // Update URL hash without causing full page reload
    window.location.hash = tabId;

    if (tabId === 'messages') {
      if (activeCustomerId) {
        loadSellerMessages(activeCustomerId);
      }
      startSellerChatPolling();
    } else {
      stopSellerChatPolling();
    }
  }

  // Inventory Sub-View Switcher (Stock Levels vs Add Product)
  function switchInventoryView(view) {
    const stockView = document.getElementById('inv-view-stock');
    const addView = document.getElementById('inv-view-add');
    const btnStock = document.getElementById('subtab-btn-stock');
    const btnAdd = document.getElementById('subtab-btn-add');

    if (view === 'add') {
      if (stockView) stockView.style.display = 'none';
      if (addView) addView.style.display = 'block';
      if (btnStock) {
        btnStock.style.background = 'transparent';
        btnStock.style.color = 'var(--text-muted)';
        btnStock.style.fontWeight = '600';
        btnStock.style.boxShadow = 'none';
      }
      if (btnAdd) {
        btnAdd.style.background = '#fff';
        btnAdd.style.color = 'var(--haat-green-dark)';
        btnAdd.style.fontWeight = '700';
        btnAdd.style.boxShadow = 'var(--shadow-sm)';
      }
    } else {
      if (stockView) stockView.style.display = 'block';
      if (addView) addView.style.display = 'none';
      if (btnStock) {
        btnStock.style.background = '#fff';
        btnStock.style.color = 'var(--haat-green-dark)';
        btnStock.style.fontWeight = '700';
        btnStock.style.boxShadow = 'var(--shadow-sm)';
      }
      if (btnAdd) {
        btnAdd.style.background = 'transparent';
        btnAdd.style.color = 'var(--text-muted)';
        btnAdd.style.fontWeight = '600';
        btnAdd.style.boxShadow = 'none';
      }
    }
  }

  function openAddProductInInventory() {
    switchSellerTab('inventory');
    switchInventoryView('add');
  }

  // Handle Hash on Page Load
  window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#', '');
    if (hash === 'add-product') {
      switchSellerTab('inventory');
      switchInventoryView('add');
    } else if (hash && ['overview', 'products', 'inventory', 'orders', 'messages', 'settings'].includes(hash)) {
      switchSellerTab(hash);
    } else {
      switchSellerTab('overview');
    }

    // Click handler for tab links
    document.querySelectorAll('.seller-tab-link').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const tab = link.getAttribute('data-tab');
        switchSellerTab(tab);
      });
    });
  });

  // Handle browser back/forward buttons
  window.addEventListener('hashchange', () => {
    const hash = window.location.hash.replace('#', '');
    if (hash === 'add-product') {
      switchSellerTab('inventory');
      switchInventoryView('add');
    } else if (hash && ['overview', 'products', 'inventory', 'orders', 'messages', 'settings'].includes(hash)) {
      switchSellerTab(hash);
    }
  });

  // ==========================================
  // ARTISAN LIVE MESSAGING SYSTEM LOGIC
  // ==========================================
  function selectCustomerConversation(customerId, orderNum, productName, isOnline, customerName, customerPhone) {
    activeCustomerId = customerId;
    document.querySelectorAll('.seller-conv-item').forEach(item => {
      const match = parseInt(item.getAttribute('data-customer-id')) === customerId;
      if (match) {
        item.classList.add('active');
        item.style.background = '#ffffff';
        item.style.borderLeft = '3px solid var(--haat-clay)';
        const unreadPill = item.querySelector('.conv-unread-pill');
        if (unreadPill) unreadPill.remove();
      } else {
        item.classList.remove('active');
        item.style.background = 'transparent';
        item.style.borderLeft = 'none';
      }
    });

    if (customerName) {
      document.getElementById('seller-chat-title').innerText = customerName;
      document.getElementById('seller-chat-avatar').innerText = customerName.charAt(0).toUpperCase();
    }
    if (customerPhone) {
      document.getElementById('seller-chat-subtitle').innerHTML = `<i class="bi bi-telephone"></i> ${escapeHtml(customerPhone)}`;
    }
    const orderTag = document.getElementById('seller-chat-order-label');
    if (orderTag) {
      orderTag.innerHTML = orderNum ? `#${escapeHtml(orderNum)}` : '';
      orderTag.style.display = orderNum ? 'inline-block' : 'none';
    }
    const statusDot = document.getElementById('seller-chat-avatar-status');
    if (statusDot) {
      statusDot.style.background = isOnline ? '#2ecc71' : '#cbd5e1';
      statusDot.title = isOnline ? 'Active Now' : 'Offline';
    }

    loadSellerMessages(customerId);
  }

  function setSellerQuickMsg(text) {
    const input = document.getElementById('seller-chat-input');
    input.value = text;
    input.focus();
  }

  function loadSellerMessages(customerId) {
    if (!customerId) return;
    const stream = document.getElementById('seller-chat-stream');
    document.getElementById('seller-chat-customer-id').value = customerId;

    fetch(`<?= BASE_URL ?>api/messages.php?action=get&customer_id=${customerId}`)
      .then(res => res.json())
      .then(data => {
        if (!data.success) return;

        if (data.customer) {
          document.getElementById('seller-chat-title').innerText = data.customer.name;
          document.getElementById('seller-chat-avatar').innerText = data.customer.name.charAt(0).toUpperCase();
          const statusDot = document.getElementById('seller-chat-avatar-status');
          if (statusDot) {
            statusDot.style.background = data.customer.is_online ? '#2ecc71' : '#cbd5e1';
            statusDot.title = data.customer.is_online ? 'Active Now' : 'Offline';
          }
          if (data.customer.phone) {
            document.getElementById('seller-chat-subtitle').innerHTML = `<i class="bi bi-telephone"></i> ${escapeHtml(data.customer.phone)}`;
          }
        }

        if (data.messages.length === 0) {
          stream.innerHTML = `
            <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
              <i class="bi bi-chat-heart" style="font-size:2.5rem; color:var(--haat-clay); display:block; margin-bottom:8px;"></i>
              <strong style="color:var(--haat-green-dark); font-size:1rem; display:block;">Conversation with ${data.customer ? escapeHtml(data.customer.name) : 'Customer'}</strong>
              <p style="font-size:0.85rem; margin-top:4px;">No messages exchanged yet. Send a greeting or order update to the buyer.</p>
            </div>
          `;
          return;
        }

        let html = '';
        data.messages.forEach(m => {
          if (m.is_me) {
            // Seller outgoing message (Right)
            html += `
              <div style="display:flex; justify-content:flex-end; margin-bottom:6px;">
                <div style="max-width:70%;">
                  <div style="background:var(--haat-green); color:#ffffff; padding:10px 16px; border-radius:18px 18px 4px 18px; font-size:0.9rem; line-height:1.45; box-shadow:0 2px 6px rgba(0,0,0,0.08);">
                    ${escapeHtml(m.message)}
                  </div>
                  <div style="font-size:0.7rem; color:var(--text-muted); text-align:right; margin-top:3px; display:flex; justify-content:flex-end; align-items:center; gap:4px;">
                    <span>${m.time}</span> • <span>${m.date}</span> <i class="bi bi-check2-all" style="color:var(--haat-green);"></i>
                  </div>
                </div>
              </div>
            `;
          } else {
            // Customer incoming message (Left)
            html += `
              <div style="display:flex; gap:10px; align-items:flex-end; margin-bottom:6px;">
                <div style="width:32px; height:32px; border-radius:50%; background:var(--haat-sand); display:flex; align-items:center; justify-content:center; color:var(--haat-green); font-size:0.85rem; font-weight:700; flex-shrink:0;">
                  ${m.sender_name.charAt(0).toUpperCase()}
                </div>
                <div style="max-width:70%;">
                  <div style="background:#ffffff; color:var(--text-main); border:1px solid var(--haat-border); padding:10px 16px; border-radius:18px 18px 18px 4px; font-size:0.9rem; line-height:1.45; box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                    ${escapeHtml(m.message)}
                  </div>
                  <div style="font-size:0.7rem; color:var(--text-muted); margin-top:3px; margin-left:4px;">
                    <span>${m.time}</span> • <span>${m.date}</span>
                  </div>
                </div>
              </div>
            `;
          }
        });

        stream.innerHTML = html;
        stream.scrollTop = stream.scrollHeight;
      })
      .catch(err => console.error(err));
  }

  function sendSellerMessage(e) {
    e.preventDefault();
    const input = document.getElementById('seller-chat-input');
    const msg = input.value.trim();
    if (!msg || !activeCustomerId) return;

    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('customer_id', activeCustomerId);
    fd.append('message', msg);

    input.value = '';
    const sendBtn = document.getElementById('seller-chat-send-btn');
    sendBtn.disabled = true;

    fetch('<?= BASE_URL ?>api/messages.php', {
      method: 'POST',
      body: fd
    })
      .then(res => res.json())
      .then(data => {
        sendBtn.disabled = false;
        if (data.success) {
          loadSellerMessages(activeCustomerId);
        }
      })
      .catch(err => {
        sendBtn.disabled = false;
        console.error(err);
      });
  }

  function openChatWithCustomer(customerId, orderNum, productName) {
    switchSellerTab('messages');
    selectCustomerConversation(customerId, orderNum, productName, 0, '', '');
    if (orderNum) {
      document.getElementById('seller-chat-input').placeholder = `Type message regarding Order #${orderNum}...`;
    }
  }

  function startSellerChatPolling() {
    stopSellerChatPolling();
    sellerChatPollTimer = setInterval(() => {
      const messagesPanel = document.getElementById('panel-messages');
      if (messagesPanel && messagesPanel.style.display !== 'none' && activeCustomerId) {
        loadSellerMessages(activeCustomerId);
      }
    }, 3500);
  }

  function stopSellerChatPolling() {
    if (sellerChatPollTimer) {
      clearInterval(sellerChatPollTimer);
      sellerChatPollTimer = null;
    }
  }

  function filterSellerConversations() {
    const q = (document.getElementById('seller-search-conv').value || '').toLowerCase();
    document.querySelectorAll('.seller-conv-item').forEach(item => {
      const name = (item.getAttribute('data-customer-name') || '').toLowerCase();
      const order = (item.getAttribute('data-order-num') || '').toLowerCase();
      const prod = (item.getAttribute('data-product-name') || '').toLowerCase();
      if (name.includes(q) || order.includes(q) || prod.includes(q)) {
        item.style.display = 'flex';
      } else {
        item.style.display = 'none';
      }
    });
  }

  function escapeHtml(text) {
    if (!text) return '';
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
  }

  // Inventory Quick Stock Helpers
  function stepStock(productId, delta) {
    const input = document.getElementById(`stock-input-${productId}`);
    if (input) {
      let val = parseInt(input.value) || 0;
      val = Math.max(0, val + delta);
      input.value = val;
    }
  }

  function handleQuickStock(event, productId, unitPrice) {
    event.preventDefault();
    const input = document.getElementById(`stock-input-${productId}`);
    const btn = document.getElementById(`stock-btn-${productId}`);
    const newStock = Math.max(0, parseInt(input.value) || 0);
    
    if (btn) btn.disabled = true;

    const fd = new FormData();
    fd.append('action_update_stock', '1');
    fd.append('product_id', productId);
    fd.append('stock_quantity', newStock);
    fd.append('ajax', '1');

    fetch('<?= BASE_URL ?>seller/', {
      method: 'POST',
      body: fd
    })
    .then(res => res.json())
    .then(data => {
      if (btn) btn.disabled = false;
      if (data.success) {
        // Update total value
        const valCell = document.getElementById(`inv-val-${productId}`);
        if (valCell) {
          valCell.innerText = '৳ ' + (newStock * unitPrice).toLocaleString();
        }
        // Update status badge
        const statusCell = document.getElementById(`inv-status-cell-${productId}`);
        if (statusCell) {
          if (newStock > 5) {
            statusCell.innerHTML = `<span class="badge badge-green">In Stock (${newStock})</span>`;
          } else if (newStock > 0) {
            statusCell.innerHTML = `<span class="badge badge-gold">Low Stock (${newStock})</span>`;
          } else {
            statusCell.innerHTML = `<span class="badge badge-red" style="background:#fee2e2; color:#b91c1c;">Out of Stock</span>`;
          }
        }
        // Visual indicator on input
        input.style.background = '#e8f5e9';
        setTimeout(() => { input.style.background = '#fff'; }, 800);
      }
    })
    .catch(err => {
      if (btn) btn.disabled = false;
      event.target.submit();
    });
  }

  function toggleSellerOnline() {
      const btn = document.getElementById('seller-status-toggle-btn');
      const dot = document.getElementById('seller-status-dot');
      const text = document.getElementById('seller-status-text');

      if (btn) btn.disabled = true;

      const fd = new FormData();
      fd.append('action_toggle_online', '1');

      fetch('<?= BASE_URL ?>seller/', {
        method: 'POST',
        body: fd
      })
      .then(res => res.json())
      .then(data => {
        if (btn) btn.disabled = false;
        if (data.success) {
          if (data.is_online) {
            btn.style.background = 'rgba(46,125,50,0.1)';
            btn.style.color = '#2e7d32';
            dot.style.background = '#2ecc71';
            dot.style.boxShadow = '0 0 0 2px rgba(46,204,113,0.3)';
            text.innerText = 'Active Store';
          } else {
            btn.style.background = 'rgba(231,76,60,0.1)';
            btn.style.color = '#c0392b';
            dot.style.background = '#e74c3c';
            dot.style.boxShadow = 'none';
            text.innerText = 'Offline / Away';
          }
        }
      })
      .catch(err => {
        if (btn) btn.disabled = false;
        console.error(err);
      });
    }

    // Toggle Notifications Dropdown
    const notifBtn = document.getElementById('seller-notif-btn');
    const notifDropdown = document.getElementById('seller-notif-dropdown');
    if (notifBtn && notifDropdown) {
      notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notifDropdown.style.display = (notifDropdown.style.display === 'none' || !notifDropdown.style.display) ? 'block' : 'none';
      });
      document.addEventListener('click', (e) => {
        if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
          notifDropdown.style.display = 'none';
        }
      });
    }
  </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
