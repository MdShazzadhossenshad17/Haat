<?php
/**
 * Customer Account Unified Dashboard
 * HAAT - Bangladeshi Multi-Vendor E-Commerce Platform
 */
require_once __DIR__ . '/../includes/functions.php';
requireCustomer();

$user = currentUser();

// Handle Profile Updates inside Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!empty($name)) {
        $up = $db->prepare("UPDATE `users` SET `name` = ?, `phone` = ? WHERE `id` = ?");
        $up->execute([$name, $phone, $user['id']]);
        $_SESSION['user_name'] = $name;
        setFlash('success', 'Profile information updated successfully.');
        header('Location: ' . BASE_URL . 'customer/#profile');
        exit;
    }
}

// Handle Password Change inside Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    $uStmt = $db->prepare("SELECT password FROM `users` WHERE `id` = ?");
    $uStmt->execute([$user['id']]);
    $hashed = $uStmt->fetchColumn();

    if (!password_verify($currentPass, $hashed)) {
        setFlash('error', 'Current password is incorrect.');
        header('Location: ' . BASE_URL . 'customer/#profile');
        exit;
    } elseif (strlen($newPass) < 6) {
        setFlash('error', 'New password must be at least 6 characters.');
        header('Location: ' . BASE_URL . 'customer/#profile');
        exit;
    } elseif ($newPass !== $confirmPass) {
        setFlash('error', 'New passwords do not match.');
        header('Location: ' . BASE_URL . 'customer/#profile');
        exit;
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $pwUp = $db->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
        $pwUp->execute([$newHash, $user['id']]);
        setFlash('success', 'Password updated successfully.');
        header('Location: ' . BASE_URL . 'customer/#profile');
        exit;
    }
}

// Fetch all customer orders with item count and delivered date
$ordStmt = $db->prepare("SELECT o.*, 
    (SELECT COUNT(*) FROM `order_items` WHERE `order_id` = o.id) as item_count,
    (SELECT MAX(created_at) FROM `order_tracking_events` WHERE `order_id` = o.id AND `status_key` = 'delivered') as delivered_date
    FROM `orders` o 
    WHERE o.`user_id` = ? 
    ORDER BY o.id DESC");
$ordStmt->execute([$user['id']]);
$orders = $ordStmt->fetchAll();

// Total spent & order count
$spentStmt = $db->prepare("SELECT COUNT(*) as total_orders, COALESCE(SUM(grand_total), 0) as total_spent FROM `orders` WHERE `user_id` = ?");
$spentStmt->execute([$user['id']]);
$stats = $spentStmt->fetch();

// Active/pending orders count (orders that are NOT delivered or cancelled)
$activeOrdStmt = $db->prepare("SELECT COUNT(*) FROM `orders` WHERE `user_id` = ? AND `order_status` NOT IN ('delivered', 'cancelled')");
$activeOrdStmt->execute([$user['id']]);
$activeOrdersCount = (int)$activeOrdStmt->fetchColumn();

// Fetch Ordered Products & Sellers for Meta Messenger Live Chat
$uid = (int)$user['id'];
$targetSellerId = !empty($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;
$sellerWhere = "WHERE s.id IN (SELECT oi.seller_id FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE o.user_id = {$uid})
   OR s.id IN (SELECT seller_id FROM messages WHERE sender_id = {$uid} OR receiver_id = {$uid})";
if ($targetSellerId > 0) {
    $sellerWhere .= " OR s.id = {$targetSellerId}";
}

$sellerStmt = $db->query("
    SELECT s.id as seller_id, s.shop_name, s.shop_logo, s.district,
           COALESCE(s.is_online, u.is_online, 0) as is_online,
           u.id as seller_user_id, u.name as artisan_name,
           (SELECT o.order_number FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = s.id AND o.user_id = {$uid} ORDER BY o.id DESC LIMIT 1) as order_number,
           (SELECT oi.product_name FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = s.id AND o.user_id = {$uid} ORDER BY o.id DESC LIMIT 1) as product_name,
           (SELECT p.featured_image FROM orders o JOIN order_items oi ON oi.order_id = o.id LEFT JOIN products p ON oi.product_id = p.id WHERE oi.seller_id = s.id AND o.user_id = {$uid} ORDER BY o.id DESC LIMIT 1) as featured_image,
           (SELECT message FROM messages WHERE seller_id = s.id AND ((sender_id = {$uid} AND receiver_id = s.user_id) OR (sender_id = s.user_id AND receiver_id = {$uid})) ORDER BY id DESC LIMIT 1) as last_message,
           (SELECT created_at FROM messages WHERE seller_id = s.id AND ((sender_id = {$uid} AND receiver_id = s.user_id) OR (sender_id = s.user_id AND receiver_id = {$uid})) ORDER BY id DESC LIMIT 1) as last_message_time,
           (SELECT COUNT(*) FROM messages WHERE seller_id = s.id AND sender_id = s.user_id AND receiver_id = {$uid} AND is_read = 0) as unread_count
    FROM sellers s
    JOIN users u ON s.user_id = u.id
    {$sellerWhere}
    ORDER BY " . ($targetSellerId > 0 ? "(s.id = {$targetSellerId}) DESC, " : "") . "(last_message_time IS NOT NULL) DESC, last_message_time DESC, s.id ASC
");
$orderedProducts = ($sellerStmt) ? $sellerStmt->fetchAll() : [];
$orderedSellers = is_array($orderedProducts) ? $orderedProducts : [];

// Fetch Logistics Conversation Metadata for Customer
$logisticsUser = $db->query("SELECT id, name, email, phone, avatar, COALESCE(is_online, 1) as is_online FROM `users` WHERE `role` = 'logistics' LIMIT 1")->fetch();
$logisticsId = $logisticsUser ? (int)$logisticsUser['id'] : 1;

$logMsgStmt = $db->prepare("SELECT message, created_at FROM `messages` WHERE (seller_id IS NULL OR seller_id = 0) AND (sender_id = ? OR receiver_id = ?) ORDER BY id DESC LIMIT 1");
$logMsgStmt->execute([$uid, $uid]);
$lastLogMsg = $logMsgStmt->fetch();

$logUnreadStmt = $db->prepare("SELECT COUNT(*) FROM `messages` WHERE (seller_id IS NULL OR seller_id = 0) AND receiver_id = ? AND is_read = 0");
$logUnreadStmt->execute([$uid]);
$logisticsUnreadCount = (int)$logUnreadStmt->fetchColumn();

// Total unread messages across all sellers and logistics
$unreadTotalStmt = $db->prepare("SELECT COUNT(*) FROM `messages` WHERE `receiver_id` = ? AND `is_read` = 0");
$unreadTotalStmt->execute([$user['id']]);
$totalUnreadMessages = (int)$unreadTotalStmt->fetchColumn();

// Customer Saved Wishlist
$wishlistProducts = getUserWishlistProducts($user['id']);
$wishlistCount = count($wishlistProducts);

// Customer Notifications for Live Tracking & Order Updates
$recentCustomerNotifs = getUserNotifications($user['id'], 6);

$pageTitle = 'Buyer Dashboard — HAAT';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 28px 20px 60px;">
  
  <!-- Dashboard Top Title Bar -->
  <div style="margin-bottom:24px;">
    <h1 style="font-size:1.75rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:10px;">
      <i class="bi bi-speedometer2 text-clay"></i> Customer Dashboard
    </h1>
  </div>

  <!-- Dashboard Grid with Persistent Left Sidebar -->
  <div style="display:grid; grid-template-columns: 260px 1fr; gap:28px; align-items:flex-start;">
    
    <!-- PERSISTENT LEFT SIDEBAR -->
    <aside style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:20px; box-shadow:var(--shadow-sm); position:sticky; top:20px;">
      
      <!-- IMPROVED PREMIUM CUSTOMER BRAND CARD -->
      <div style="background:linear-gradient(180deg, #ffffff 0%, #faf8f5 100%); border:1px solid rgba(27,61,34,0.14); border-radius:12px; padding:16px; margin-bottom:20px; box-shadow:0 3px 12px rgba(0,0,0,0.04); position:relative; overflow:hidden;">
        <!-- Top decorative brand stripe -->
        <div style="position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg, var(--haat-green), var(--haat-clay));"></div>
        
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
          <div style="width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg, var(--haat-green-dark), var(--haat-green)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.15rem; font-weight:700; flex-shrink:0; box-shadow:0 2px 6px rgba(27,61,34,0.2);">
            <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
          </div>
          <div style="min-width:0; flex:1;">
            <div style="color:var(--haat-green-dark); font-size:0.98rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= sanitize($user['name']) ?>">
              <?= sanitize($user['name']) ?>
            </div>
            <div style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
              <?= sanitize($user['email']) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Unified Sidebar Navigation Tabs -->
      <ul class="dashboard-nav" style="display:flex; flex-direction:column; gap:6px; list-style:none; padding:0; margin:0; font-size:0.92rem; font-weight:600;">
        <li>
          <a href="#overview" class="dash-tab-link active" data-tab="overview" onclick="switchTab('overview')">
            <i class="bi bi-speedometer2"></i>
            <span>Overview</span>
            </a>
        </li>
        <li>
          <a href="#orders" class="dash-tab-link" data-tab="orders" onclick="switchTab('orders')">
            <i class="bi bi-bag-check"></i>
            <span>My Orders</span>
            <span class="badge orders-count-badge" style="background:var(--haat-sand); color:var(--haat-green-dark); font-weight:700; font-size:0.75rem; padding:2px 8px; border-radius:12px; margin-left:auto; border:1px solid rgba(27,61,34,0.1); <?= $activeOrdersCount > 0 ? '' : 'display:none;' ?>"><?= $activeOrdersCount ?></span>
          </a>
        </li>
        <li>
          <a href="#messages" class="dash-tab-link" data-tab="messages" onclick="switchTab('messages')">
            <i class="bi bi-chat-dots-fill"></i>
            <span>Seller Messages</span>
            <span id="cust-nav-msg-badge" class="badge" style="background:var(--haat-clay); color:#fff; font-size:0.72rem; font-weight:700; padding:2px 7px; border-radius:12px; margin-left:auto; <?= $totalUnreadMessages > 0 ? '' : 'display:none;' ?>"><?= $totalUnreadMessages ?></span>
          </a>
        </li>
        <li>
          <a href="#wishlist" class="dash-tab-link" data-tab="wishlist" onclick="switchTab('wishlist')">
            <i class="bi bi-heart"></i>
            <span>Saved Wishlist</span>
            <span class="badge wishlist-count-badge" style="font-weight:700; font-size:0.75rem; padding:2px 8px; border-radius:12px; margin-left:auto; <?= $wishlistCount > 0 ? '' : 'display:none;' ?>"><?= $wishlistCount ?></span>
          </a>
        </li>
        <li>
          <a href="#profile" class="dash-tab-link" data-tab="profile" onclick="switchTab('profile')">
            <i class="bi bi-person-gear"></i>
            <span>Account Profile</span>
          </a>
        </li>
      </ul>
    </aside>

    <!-- RIGHT SIDE DYNAMIC CONTENT PANELS -->
    <main style="min-width:0;">
      
      <!-- ==========================================
           TAB 1: OVERVIEW PANEL
           ========================================== -->
      <div id="tab-overview" class="dash-panel active">
        
        <!-- Stats Cards with Saved Wishlist -->
        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:14px; margin-bottom:24px;">
          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm); cursor:pointer;" onclick="switchTab('orders')">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; margin-bottom:4px;">Total Orders</div>
            <div style="font-size:1.7rem; font-weight:800; color:var(--haat-green-dark);"><?= $stats['total_orders'] ?></div>
          </div>

          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm);">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; margin-bottom:4px;">Total Purchases</div>
            <div style="font-size:1.7rem; font-weight:800; color:var(--haat-green);"><?= formatPrice($stats['total_spent']) ?></div>
          </div>

          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm); cursor:pointer;" onclick="switchTab('messages')">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; margin-bottom:4px;">Conversations</div>
            <div style="font-size:1.7rem; font-weight:800; color:var(--haat-clay); display:flex; align-items:center; gap:8px;">
              <span><?= count($orderedSellers) ?></span>
              <span style="font-size:0.72rem; font-weight:600; color:var(--text-muted);">Artisans</span>
            </div>
          </div>

          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm); cursor:pointer;" onclick="switchTab('wishlist')">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; margin-bottom:4px;">Saved Wishlist</div>
            <div style="font-size:1.7rem; font-weight:800; color:var(--haat-clay); display:flex; align-items:center; gap:8px;">
              <span class="wishlist-count-badge"><?= $wishlistCount ?></span>
              <span style="font-size:0.72rem; font-weight:600; color:var(--text-muted);">Crafts</span>
            </div>
          </div>
        </div>

        <?php if (!empty($recentCustomerNotifs)): ?>
        <!-- Live Order Tracking Alerts Card (Visible during Order Confirmation & Crafting before Shipped) -->
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm); margin-bottom:24px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div style="display:flex; align-items:center; gap:10px;">
              <div style="width:38px; height:38px; border-radius:10px; background:var(--haat-sand); color:var(--haat-clay); display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                <i class="bi bi-broadcast"></i>
              </div>
              <div>
                <h3 style="font-size:1.15rem; color:var(--haat-green-dark); margin:0;">Live Order Tracking Alerts</h3>
                <p style="font-size:0.8rem; color:var(--text-muted); margin:2px 0 0;">Real-time workshop crafting and fulfillment updates</p>
              </div>
            </div>
            <button type="button" onclick="switchTab('orders')" style="background:none; border:none; color:var(--haat-clay); font-size:0.85rem; font-weight:700; cursor:pointer;">
              Track All Orders &rarr;
            </button>
          </div>

          <div style="display:flex; flex-direction:column; gap:10px;">
            <?php foreach ($recentCustomerNotifs as $cn): 
              $iconClass = 'bi-bell-fill';
              $iconBg = 'var(--haat-sand)';
              $iconColor = 'var(--haat-green-dark)';
              if (strpos($cn['type'], 'confirmed') !== false || strpos($cn['type'], 'order_placed') !== false) {
                  $iconClass = 'bi-box-seam-fill';
                  $iconBg = '#e8efe9';
                  $iconColor = 'var(--haat-green)';
              } elseif (strpos($cn['type'], 'processing') !== false) {
                  $iconClass = 'bi-gear-wide-connected';
                  $iconBg = 'var(--haat-clay-light)';
                  $iconColor = 'var(--haat-clay)';
              } elseif (strpos($cn['type'], 'shipped') !== false || strpos($cn['type'], 'dispatched') !== false) {
                  $iconClass = 'bi-truck';
                  $iconBg = '#fbf0e4';
                  $iconColor = '#b45309';
              } elseif (strpos($cn['type'], 'delivered') !== false) {
                  $iconClass = 'bi-check2-circle';
                  $iconBg = '#e2ede5';
                  $iconColor = 'var(--haat-green)';
              } elseif (strpos($cn['type'], 'message') !== false) {
                  $iconClass = 'bi-chat-dots-fill';
                  $iconBg = 'var(--haat-clay-light)';
                  $iconColor = 'var(--haat-clay)';
              }
            ?>
              <div style="display:flex; align-items:flex-start; gap:14px; padding:12px 16px; border-radius:10px; background:#faf8f5; border:1px solid rgba(0,0,0,0.04);">
                <div style="width:36px; height:36px; border-radius:8px; background:<?= $iconBg ?>; color:<?= $iconColor ?>; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; margin-top:2px;">
                  <i class="bi <?= $iconClass ?>"></i>
                </div>
                <div style="flex:1; min-width:0;">
                  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px; flex-wrap:wrap; gap:4px;">
                    <span style="font-weight:700; color:var(--haat-green-dark); font-size:0.9rem;"><?= sanitize($cn['title']) ?></span>
                    <span style="font-size:0.75rem; color:var(--text-muted);"><?= date('d M, h:i A', strtotime($cn['created_at'])) ?></span>
                  </div>
                  <p style="margin:0 0 6px; font-size:0.83rem; color:var(--text-main); line-height:1.4;"><?= sanitize($cn['message']) ?></p>
                  <?php if (!empty($cn['link'])): ?>
                    <a href="<?= sanitize($cn['link']) ?>" style="font-size:0.78rem; font-weight:700; color:var(--haat-clay); text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                      <span>View Live Status & Timeline</span> &rarr;
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Recent Orders Section -->
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm); margin-bottom:24px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h3 style="font-size:1.2rem; color:var(--haat-green-dark); margin:0;">Recent Orders</h3>
            <button type="button" onclick="switchTab('orders')" style="background:none; border:none; color:var(--haat-clay); font-size:0.88rem; font-weight:700; cursor:pointer;">
              View All Orders (<?= count($orders) ?>) &rarr;
            </button>
          </div>

          <?php if (empty($orders)): ?>
            <div style="text-align:center; padding:36px 0;">
              <i class="bi bi-box-seam text-muted" style="font-size:2.8rem; display:block; margin-bottom:10px;"></i>
              <h4 style="margin:0 0 6px;">No Orders Placed Yet</h4>
              <p style="color:var(--text-muted); font-size:0.88rem; margin-bottom:16px;">Explore authentic handloom sarees, pottery, and pure village harvests.</p>
              <a href="<?= BASE_URL ?>shop.php" class="btn btn-clay btn-sm">Start Shopping</a>
            </div>
          <?php else: ?>
            <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
              <thead>
                <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.8rem; text-transform:uppercase;">
                  <th style="padding:10px 0;">Order #</th>
                  <th style="padding:10px 0;">Date</th>
                  <th style="padding:10px 0;">Amount</th>
                  <th style="padding:10px 0;">Payment</th>
                  <th style="padding:10px 0;">Status</th>
                  <th style="padding:10px 0; text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($orders, 0, 5) as $ord): ?>
                  <tr style="border-bottom:1px solid var(--haat-border);">
                    <td style="padding:14px 0; font-weight:700; color:var(--haat-green-dark);">
                      <?= sanitize($ord['order_number']) ?>
                    </td>
                    <td style="padding:14px 0; color:var(--text-muted); font-size:0.85rem;">
                      <?= date('d M Y', strtotime($ord['created_at'])) ?>
                    </td>
                    <td style="padding:14px 0; font-weight:700; color:var(--haat-green);">
                      <?= formatPrice($ord['grand_total']) ?>
                    </td>
                    <td style="padding:14px 0;">
                      <span class="pay-badge pay-<?= $ord['payment_method'] ?>" style="font-size:0.75rem;">
                        <?= strtoupper($ord['payment_method']) ?>
                      </span>
                    </td>
                    <td style="padding:14px 0;">
                      <span class="badge badge-<?= $ord['order_status'] === 'delivered' ? 'green' : 'gold' ?>" style="font-size:0.75rem;">
                        <?= $ord['order_status'] === 'delivered' ? '<i class="bi bi-check2-circle"></i> Completed' : ucfirst($ord['order_status']) ?>
                      </span>
                    </td>
                    <td style="padding:14px 0; text-align:right;">
                      <a href="<?= BASE_URL ?>invoice.php?order=<?= urlencode($ord['order_number']) ?>" target="_blank" class="btn btn-sm btn-outline-green" style="padding:4px 10px; font-size:0.8rem;">
                        <i class="bi bi-printer"></i> Invoice
                      </a>
                      <a href="<?= BASE_URL ?>track-order.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-clay" style="padding:4px 10px; font-size:0.8rem; margin-left:4px;">
                        Track
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

      </div>

      <!-- ==========================================
           TAB 2: MY ORDERS PANEL (Clean Table with Expandable Details)
           ========================================== -->
      <div id="tab-orders" class="dash-panel" style="display:none;">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0;">Complete Order History</h2>
              <p style="font-size:0.85rem; color:var(--text-muted); margin:3px 0 0;">Click on any order row to see received details, full tracking timeline, and invoice downloads.</p>
            </div>
            <span class="badge badge-green"><?= count($orders) ?> Total Orders</span>
          </div>

          <?php if (empty($orders)): ?>
            <div style="text-align:center; padding:50px 0;">
              <i class="bi bi-bag-x text-muted" style="font-size:3rem; display:block; margin-bottom:12px;"></i>
              <h3 style="margin-bottom:8px;">No Orders Placed Yet</h3>
              <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">Explore authentic handloom sarees, pottery, and pure village harvests.</p>
              <a href="<?= BASE_URL ?>shop.php" class="btn btn-clay btn-sm">Explore Haat Crafts</a>
            </div>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                <thead>
                  <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.8rem; text-transform:uppercase; background:#faf7f2;">
                    <th style="padding:12px 14px; border-top-left-radius:6px;">Order #</th>
                    <th style="padding:12px 14px;">Date Placed</th>
                    <th style="padding:12px 14px;">Items</th>
                    <th style="padding:12px 14px;">Total Amount</th>
                    <th style="padding:12px 14px;">Payment</th>
                    <th style="padding:12px 14px;">Order Status</th>
                    <th style="padding:12px 14px; text-align:right; border-top-right-radius:6px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orders as $ord): 
                    // Fetch items for this order
                    $itemStmt = $db->prepare("SELECT oi.*, s.id as seller_id, s.shop_name, p.featured_image 
                        FROM `order_items` oi 
                        JOIN `sellers` s ON oi.seller_id = s.id 
                        LEFT JOIN `products` p ON oi.product_id = p.id 
                        WHERE oi.order_id = ?");
                    $itemStmt->execute([$ord['id']]);
                    $orderItems = $itemStmt->fetchAll();

                    $isDelivered = ($ord['order_status'] === 'delivered');
                    $isShipped = ($ord['order_status'] === 'shipped');
                    $isProcessing = ($ord['order_status'] === 'processing');
                    $isPending = ($ord['order_status'] === 'pending');
                    $isCancelled = ($ord['order_status'] === 'cancelled');

                    $statusBadgeClass = 'badge-clay';
                    $statusLabel = 'Confirmed';
                    if ($isDelivered) {
                        $statusBadgeClass = 'badge-green';
                        $statusLabel = '<i class="bi bi-check2-circle"></i> Completed';
                    } elseif ($isShipped) {
                        $statusBadgeClass = 'badge-gold';
                        $statusLabel = '<i class="bi bi-truck"></i> Shipped';
                    } elseif ($isProcessing) {
                        $statusBadgeClass = 'badge-clay';
                        $statusLabel = '<i class="bi bi-gear-wide-connected"></i> Crafting';
                    } elseif ($isCancelled) {
                        $statusBadgeClass = 'badge-danger';
                        $statusLabel = '<i class="bi bi-x-circle"></i> Cancelled';
                    }
                  ?>
                    <!-- Main Order Summary Row (Clickable) -->
                    <tr style="border-bottom:1px solid var(--haat-border); cursor:pointer; transition:background 0.15s ease;" 
                        onmouseover="this.style.background='#faf8f5';" 
                        onmouseout="this.style.background='#ffffff';"
                        onclick="toggleOrderRowDetails(<?= (int)$ord['id'] ?>)">
                      <td style="padding:14px; font-weight:700; color:var(--haat-green-dark); white-space:nowrap;">
                        <div style="display:flex; align-items:center; gap:8px;">
                          <i class="bi bi-chevron-right text-clay" id="order-chevron-<?= $ord['id'] ?>" style="transition:transform 0.2s ease; font-size:0.8rem;"></i>
                          <span><?= sanitize($ord['order_number']) ?></span>
                        </div>
                      </td>
                      <td style="padding:14px; color:var(--text-muted); font-size:0.85rem; white-space:nowrap;">
                        <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
                      </td>
                      <td style="padding:14px; color:var(--text-main); font-size:0.88rem; white-space:nowrap;">
                        <span class="badge badge-green" style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; font-size:0.75rem;">
                          <?= count($orderItems) ?> <?= count($orderItems) === 1 ? 'Craft' : 'Crafts' ?>
                        </span>
                      </td>
                      <td style="padding:14px; font-weight:800; color:var(--haat-green); font-size:0.95rem; white-space:nowrap;">
                        <?= formatPrice($ord['grand_total']) ?>
                      </td>
                      <td style="padding:14px; white-space:nowrap;">
                        <span class="pay-badge pay-<?= $ord['payment_method'] ?>" style="font-size:0.75rem;">
                          <?= strtoupper($ord['payment_method']) ?>
                        </span>
                      </td>
                      <td style="padding:14px; white-space:nowrap;">
                        <span class="badge <?= $statusBadgeClass ?>" style="font-size:0.78rem; padding:4px 10px; font-weight:700;">
                          <?= $statusLabel ?>
                        </span>
                      </td>
                      <td style="padding:14px; text-align:right; white-space:nowrap;" onclick="event.stopPropagation();">
                        <button type="button" class="btn btn-sm btn-outline-clay" onclick="toggleOrderRowDetails(<?= (int)$ord['id'] ?>)" style="padding:3px 8px; font-size:0.78rem;">
                          <i class="bi bi-eye"></i> Details
                        </button>
                        <a href="<?= BASE_URL ?>invoice.php?order=<?= urlencode($ord['order_number']) ?>" target="_blank" class="btn btn-sm btn-outline-green" style="padding:3px 8px; font-size:0.78rem; margin-left:3px;" title="Print / Download Tax Invoice">
                          <i class="bi bi-printer"></i> Invoice
                        </a>
                        <a href="<?= BASE_URL ?>track-order.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-clay" style="padding:3px 8px; font-size:0.78rem; margin-left:3px;" title="Live Courier Tracking">
                          <i class="bi bi-truck"></i> Track
                        </a>
                      </td>
                    </tr>

                    <!-- Expandable Order Full Details Row -->
                    <tr id="order-details-drawer-<?= $ord['id'] ?>" style="display:none; background:#fcfbf9; border-bottom:2px solid var(--haat-border);">
                      <td colspan="7" style="padding:18px 22px;">
                        <div style="background:#ffffff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
                          
                          <!-- Order Status / Delivered Banner -->
                          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding-bottom:14px; border-bottom:1px solid var(--haat-border); margin-bottom:16px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                              <div style="width:36px; height:36px; border-radius:8px; background:<?= $isDelivered ? '#e8f5e9' : 'var(--haat-sand)' ?>; color:<?= $isDelivered ? 'var(--haat-green)' : 'var(--haat-clay)' ?>; display:flex; align-items:center; justify-content:center; font-size:1.15rem;">
                                <i class="bi <?= $isDelivered ? 'bi-check-circle-fill' : 'bi-truck' ?>"></i>
                              </div>
                              <div>
                                <strong style="color:var(--haat-green-dark); font-size:0.92rem;">
                                  <?= $isDelivered ? 'Order Received & Completed' : 'Order in Progress (' . ucfirst($ord['order_status']) . ')' ?>
                                </strong>
                                <div style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">
                                  <?php if ($isDelivered && !empty($ord['delivered_date'])): ?>
                                    Delivered on: <strong><?= date('d M Y, h:i A', strtotime($ord['delivered_date'])) ?></strong>
                                  <?php else: ?>
                                    Order Number: <strong><?= sanitize($ord['order_number']) ?></strong> • Placed on <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
                                  <?php endif; ?>
                                </div>
                              </div>
                            </div>

                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                              <button type="button" onclick="openChatWithLogistics('<?= sanitize($ord['order_number']) ?>')" class="btn btn-sm btn-clay" style="font-size:0.8rem; display:inline-flex; align-items:center; gap:5px;">
                                <i class="bi bi-chat-dots-fill"></i> Message HAATEX Logistics
                              </button>
                              <a href="<?= BASE_URL ?>invoice.php?order=<?= urlencode($ord['order_number']) ?>" target="_blank" class="btn btn-sm btn-outline-green" style="font-size:0.8rem; display:inline-flex; align-items:center; gap:5px;">
                                <i class="bi bi-file-earmark-pdf"></i> Download Invoice
                              </a>
                              <a href="<?= BASE_URL ?>track-order.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-outline-clay" style="font-size:0.8rem; display:inline-flex; align-items:center; gap:5px;">
                                <i class="bi bi-geo-alt-fill"></i> Live Tracking
                              </a>
                            </div>
                          </div>

                          <!-- HAATEX Logistics Consignment Info Box -->
                          <div style="background:#faf8f5; border:1px solid var(--haat-border); border-radius:8px; padding:10px 14px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; font-size:0.82rem;">
                            <div style="display:flex; align-items:center; gap:8px;">
                              <span style="font-weight:700; color:var(--haat-clay); display:flex; align-items:center; gap:5px;">
                                <i class="bi bi-truck"></i> HAATEX Express:
                              </span>
                              <code style="background:#fff; border:1px solid #cbd5e1; padding:2px 7px; border-radius:4px; font-weight:700; color:var(--haat-green-dark);">
                                <?= sanitize($ord['tracking_code'] ?: 'HTX-' . $ord['id']) ?>
                              </code>
                              <?php if (!empty($ord['assigned_rider_name'])): ?>
                                <span style="color:var(--haat-green); font-weight:600;">
                                  • Delivery Rider: <strong><?= sanitize($ord['assigned_rider_name']) ?></strong> (<?= sanitize($ord['assigned_rider_phone'] ?? '') ?>)
                                </span>
                              <?php endif; ?>
                            </div>

                            <span class="badge" style="background:var(--haat-sand); color:var(--haat-green-dark); font-weight:700; font-size:0.75rem;">
                              <?= !empty($ord['logistics_status']) ? ucwords(str_replace('_', ' ', $ord['logistics_status'])) : 'Pending Dispatch' ?>
                            </span>
                          </div>

                          <!-- Itemized Products List -->
                          <div style="margin-bottom:16px;">
                            <div style="font-size:0.82rem; font-weight:700; color:var(--haat-green-dark); text-transform:uppercase; margin-bottom:10px;">
                              Ordered Craft Items (<?= count($orderItems) ?>)
                            </div>
                            <div style="display:flex; flex-direction:column; gap:10px;">
                              <?php foreach ($orderItems as $it): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; background:#fdfbf8; border:1px solid var(--haat-border); border-radius:var(--radius-sm); flex-wrap:wrap; gap:10px;">
                                  <div style="display:flex; align-items:center; gap:12px;">
                                    <?php if (!empty($it['featured_image'])): ?>
                                      <img src="<?= sanitize($it['featured_image']) ?>" alt="thumb" style="width:44px; height:44px; border-radius:var(--radius-sm); object-fit:cover; border:1px solid var(--haat-border);">
                                    <?php else: ?>
                                      <div style="width:44px; height:44px; border-radius:var(--radius-sm); background:var(--haat-sand); display:flex; align-items:center; justify-content:center; color:var(--haat-clay);">
                                        <i class="bi bi-box"></i>
                                      </div>
                                    <?php endif; ?>
                                    <div>
                                      <a href="<?= BASE_URL ?>product.php?id=<?= $it['product_id'] ?>" style="font-size:0.9rem; font-weight:700; color:var(--haat-green-dark); text-decoration:none;">
                                        <?= sanitize($it['product_name']) ?>
                                      </a>
                                      <div style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">
                                        Artisan Workshop: <strong style="color:var(--haat-clay);"><?= sanitize($it['shop_name']) ?></strong> • Qty: <?= $it['quantity'] ?> × <?= formatPrice($it['price']) ?>
                                      </div>
                                    </div>
                                  </div>

                                  <div style="display:flex; align-items:center; gap:12px;">
                                    <span style="font-size:0.95rem; font-weight:800; color:var(--haat-green);">
                                      <?= formatPrice($it['price'] * $it['quantity']) ?>
                                    </span>
                                    <div style="display:flex; gap:5px;">
                                      <?php if (!empty($it['product_id'])): ?>
                                        <a href="<?= BASE_URL ?>product.php?id=<?= $it['product_id'] ?>#reviews" class="btn btn-sm btn-outline-green" style="padding:3px 9px; font-size:0.75rem; display:inline-flex; align-items:center; gap:4px;">
                                          <i class="bi bi-star-fill text-gold"></i> Rate / Review
                                        </a>
                                      <?php endif; ?>
                                      <button type="button" onclick="openChatWithSeller(<?= $it['seller_id'] ?>, '<?= sanitize($ord['order_number']) ?>', '<?= addslashes(sanitize($it['product_name'])) ?>')" class="btn btn-sm btn-outline-clay" style="padding:3px 9px; font-size:0.75rem; display:inline-flex; align-items:center; gap:4px;">
                                        <i class="bi bi-chat-dots"></i> Message Seller
                                      </button>
                                    </div>
                                  </div>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          </div>

                          <!-- Delivery Address & Financial Breakdown -->
                          <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap:16px; padding-top:12px; border-top:1px dashed var(--haat-border); font-size:0.83rem;">
                            <div>
                              <div style="font-weight:700; color:var(--haat-green-dark); margin-bottom:3px;">Recipient & Delivery Address:</div>
                              <div style="color:var(--text-muted); line-height:1.4;">
                                <strong><?= sanitize($ord['shipping_name']) ?></strong> (Phone: <?= sanitize($ord['shipping_phone']) ?>)<br>
                                <?= sanitize($ord['shipping_address']) ?>, <?= sanitize($ord['district']) ?>, <?= sanitize($ord['division']) ?>
                              </div>
                            </div>
                            <div style="background:#faf8f5; border:1px solid var(--haat-border); border-radius:6px; padding:10px 14px;">
                              <div style="display:flex; justify-content:space-between; margin-bottom:4px; color:var(--text-muted);">
                                <span>Subtotal:</span>
                                <span><?= formatPrice($ord['total_amount']) ?></span>
                              </div>
                              <?php if ($ord['discount_amount'] > 0): ?>
                              <div style="display:flex; justify-content:space-between; margin-bottom:4px; color:var(--haat-clay);">
                                <span>Coupon Savings:</span>
                                <span>- <?= formatPrice($ord['discount_amount']) ?></span>
                              </div>
                              <?php endif; ?>
                              <div style="display:flex; justify-content:space-between; margin-bottom:6px; color:var(--text-muted);">
                                <span>Delivery Courier:</span>
                                <span><?= $ord['shipping_cost'] > 0 ? formatPrice($ord['shipping_cost']) : 'FREE' ?></span>
                              </div>
                              <div style="display:flex; justify-content:space-between; font-weight:800; font-size:0.95rem; color:var(--haat-green-dark); border-top:1px solid var(--haat-border); padding-top:6px;">
                                <span>Grand Total:</span>
                                <span><?= formatPrice($ord['grand_total']) ?></span>
                              </div>
                            </div>
                          </div>

                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <script>
              function toggleOrderRowDetails(orderId) {
                const drawer = document.getElementById('order-details-drawer-' + orderId);
                const chevron = document.getElementById('order-chevron-' + orderId);
                if (!drawer) return;
                const isHidden = (drawer.style.display === 'none' || !drawer.style.display);
                drawer.style.display = isHidden ? 'table-row' : 'none';
                if (chevron) {
                  chevron.style.transform = isHidden ? 'rotate(90deg)' : 'rotate(0deg)';
                }
              }
            </script>
          <?php endif; ?>
        </div>
      </div>

      <!-- ==========================================
           TAB 3: META MESSENGER LIVE CHAT PANEL
           ========================================== -->
      <div id="tab-messages" class="dash-panel" style="display:none;">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); box-shadow:var(--shadow-sm); overflow:hidden;">
          
          <!-- Messenger Component Container -->
          <div style="display:grid; grid-template-columns: 340px 1fr; height: 600px; max-height:600px;">
            
            <!-- Left Pane: Conversations / Ordered Products & Sellers -->
            <div style="border-right:1px solid var(--haat-border); display:flex; flex-direction:column; height:600px; min-height:0; max-height:600px; background:#faf7f2; overflow:hidden;">
              
              <div style="padding:14px 18px; border-bottom:1px solid var(--haat-border); display:flex; justify-content:space-between; align-items:center; flex-shrink:0; background:#fff;">
                <h3 style="font-size:0.95rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:8px; font-weight:800;">
                  <i class="bi bi-chat-left-dots text-clay"></i> Messages & Support Desk
                </h3>
              </div>

              <!-- Conversation List -->
              <div id="conversation-list" style="flex:1 1 0%; min-height:0; overflow-y:auto; padding:6px 0;">
                
                <!-- 1. Dedicated HAATEX Logistics Delivery Partner Channel -->
                <div class="conv-item conv-logistics" 
                     id="conv-item-logistics"
                     onclick="selectLogisticsConversation()" 
                     style="padding:12px 14px; border-bottom:1px solid rgba(0,0,0,0.06); cursor:pointer; display:flex; gap:12px; align-items:center; transition:var(--transition); background:transparent;">
                  
                  <!-- Logistics Avatar with Active Dot -->
                  <div style="position:relative; flex-shrink:0;">
                    <div style="width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg, #1b3d22 0%, #2d5a36 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.3rem; box-shadow:0 2px 6px rgba(27,61,34,0.25);">
                      <i class="bi bi-truck"></i>
                    </div>
                    <span class="status-dot-indicator" 
                          style="position:absolute; bottom:0; right:0; width:11px; height:11px; background:#2ecc71; border-radius:50%; border:2px solid #fff;" 
                          title="Active Now"></span>
                  </div>

                  <!-- Logistics Text Info -->
                  <div style="flex:1; min-width:0;">
                    <div style="display:flex; justify-content:space-between; align-items:baseline; gap:6px;">
                      <strong style="font-size:0.88rem; color:var(--haat-green-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;">
                        HAATEX
                      </strong>
                      <span class="conv-time-preview" id="logistics-last-time" style="font-size:0.68rem; color:var(--text-muted); flex-shrink:0;">
                        <?= !empty($lastLogMsg['created_at']) ? date('h:i A', strtotime($lastLogMsg['created_at'])) : '' ?>
                      </span>
                    </div>

                    <div style="display:flex; align-items:center; gap:6px; font-size:0.73rem; color:var(--text-muted); margin-top:2px;">
                      <span class="last-msg-preview" id="logistics-last-msg" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;">
                        <?= sanitize($lastLogMsg['message'] ?? 'Direct courier and rider dispatch desk...') ?>
                      </span>
                    </div>
                  </div>

                  <span class="unread-pill" id="logistics-unread-pill" style="font-size:0.65rem; background:var(--haat-clay); color:#fff; font-weight:700; padding:1px 6px; border-radius:10px; flex-shrink:0; <?= $logisticsUnreadCount > 0 ? '' : 'display:none;' ?>">
                    <?= $logisticsUnreadCount ?>
                  </span>
                </div>

                <!-- Section Divider: Artisan Sellers -->
                <div style="padding:10px 14px 4px; font-size:0.72rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; border-top:1px dashed #e2ded8; margin-top:4px;">
                  Artisan Workshop Sellers
                </div>

                <?php if (empty($orderedProducts)): ?>
                  <div style="padding:24px 18px; text-align:center; color:var(--text-muted); font-size:0.82rem;">
                    <i class="bi bi-shop" style="font-size:1.8rem; display:block; margin-bottom:6px; opacity:0.6;"></i>
                    When you order handcrafted crafts, artisan chat channels will appear here.
                  </div>
                <?php else: ?>
                  <?php foreach ($orderedProducts as $idx => $s): 
                    $isSelected = ((int)$s['seller_id'] === (int)($targetSellerId > 0 ? $targetSellerId : 0));
                  ?>
                    <div class="conv-item conv-seller <?= $isSelected ? 'active' : '' ?>" 
                         data-seller-id="<?= $s['seller_id'] ?>" 
                         data-order-number="<?= sanitize($s['order_number'] ?? '') ?>"
                         data-product-name="<?= sanitize($s['product_name'] ?? '') ?>"
                         data-shop-name="<?= sanitize($s['shop_name']) ?>"
                         data-is-online="<?= $s['is_online'] ? '1' : '0' ?>"
                         onclick="selectConversation(<?= $s['seller_id'] ?>, '<?= addslashes(sanitize($s['order_number'] ?? '')) ?>', '<?= addslashes(sanitize($s['product_name'] ?? '')) ?>', <?= $s['is_online'] ? '1' : '0' ?>, '<?= addslashes(sanitize($s['shop_name'])) ?>')" 
                         style="padding:12px 14px; border-bottom:1px solid rgba(0,0,0,0.05); cursor:pointer; display:flex; gap:12px; align-items:center; transition:var(--transition); background:<?= $isSelected ? '#ffffff' : 'transparent' ?>; <?= $isSelected ? 'border-left:3px solid var(--haat-clay);' : '' ?>">
                      
                      <!-- Product Image Thumbnail / Shop Avatar with Live Status Dot -->
                      <div style="position:relative; flex-shrink:0;">
                        <?php if (!empty($s['featured_image'])): ?>
                          <img src="<?= sanitize($s['featured_image']) ?>" 
                                alt="<?= sanitize($s['shop_name']) ?>" 
                                style="width:44px; height:44px; border-radius:50%; object-fit:cover; border:1px solid var(--haat-border); background:#fff;">
                        <?php else: ?>
                          <div style="width:44px; height:44px; border-radius:50%; background:var(--haat-sand); display:flex; align-items:center; justify-content:center; color:var(--haat-green); font-size:1.1rem; font-weight:700; border:1px solid var(--haat-border);">
                            <?= strtoupper(substr($s['shop_name'], 0, 1)) ?>
                          </div>
                        <?php endif; ?>
                        <!-- Active Dot -->
                        <span class="status-dot-indicator" 
                              style="position:absolute; bottom:0; right:0; width:11px; height:11px; background:<?= $s['is_online'] ? '#2ecc71' : '#cbd5e1' ?>; border-radius:50%; border:2px solid #fff;" 
                              title="<?= $s['is_online'] ? 'Active Now' : 'Offline' ?>"></span>
                      </div>

                      <!-- Shop and Order Info -->
                      <div style="flex:1; min-width:0;">
                        <div style="display:flex; justify-content:space-between; align-items:baseline; gap:6px;">
                          <strong style="font-size:0.88rem; color:var(--haat-green-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;">
                            <?= sanitize($s['shop_name']) ?>
                          </strong>
                          <?php if (!empty($s['last_message_time'])): ?>
                            <span class="conv-time-preview" style="font-size:0.68rem; color:var(--text-muted); flex-shrink:0;">
                              <?= date('h:i A', strtotime($s['last_message_time'])) ?>
                            </span>
                          <?php endif; ?>
                        </div>

                        <!-- Order Number and Last Message Preview -->
                        <div style="display:flex; align-items:center; gap:6px; font-size:0.73rem; color:var(--text-muted); margin-top:2px;">
                          <?php if (!empty($s['order_number'])): ?>
                            <span class="badge" style="background:#f1eee9; color:#6b5847; padding:1px 5px; font-size:0.68rem; border-radius:4px; flex-shrink:0;">
                              #<?= sanitize($s['order_number']) ?>
                            </span>
                          <?php endif; ?>
                          <span class="last-msg-preview" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;">
                            <?= sanitize($s['last_message'] ?? 'Click to chat...') ?>
                          </span>
                        </div>
                      </div>

                      <?php if ($s['unread_count'] > 0): ?>
                        <span class="unread-pill" style="font-size:0.65rem; background:var(--haat-clay); color:#fff; font-weight:700; padding:1px 6px; border-radius:10px; flex-shrink:0;">
                          <?= $s['unread_count'] ?>
                        </span>
                      <?php endif; ?>

                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

            </div>

            <!-- Right Pane: Active Live Chat Window -->
            <div style="display:flex; flex-direction:column; height:600px; min-height:0; max-height:600px; background:#ffffff; overflow:hidden;">
              
              <!-- Chat Header -->
              <div id="chat-header" style="padding:14px 20px; border-bottom:1px solid var(--haat-border); display:flex; justify-content:space-between; align-items:center; background:#ffffff; flex-shrink:0;">
                <div style="display:flex; align-items:center; gap:12px;">
                  <div style="position:relative;">
                    <div id="active-chat-avatar" style="width:42px; height:42px; border-radius:50%; background:var(--haat-sand); display:flex; align-items:center; justify-content:center; color:var(--haat-green); font-size:1.2rem; font-weight:700;">
                      <i class="bi bi-truck"></i>
                    </div>
                    <!-- Workable Active/Offline Dot -->
                    <span id="active-chat-avatar-status" 
                          style="position:absolute; bottom:0; right:0; width:11px; height:11px; background:#2ecc71; border-radius:50%; border:2px solid #fff;"></span>
                  </div>

                  <div>
                    <strong id="active-chat-title" style="color:var(--haat-green-dark); font-size:0.98rem; display:block;">
                      HAATEX
                    </strong>
                    <span id="active-chat-subtitle" style="font-size:0.75rem; color:var(--text-muted); display:flex; align-items:center; gap:4px; margin-top:1px;">
                      Official Delivery Desk
                    </span>
                  </div>
                </div>

                <!-- Order Reference Tag -->
                <div id="active-chat-order-tag">
                  <span id="active-chat-product-label" class="badge" style="background:#f7efe6; color:var(--haat-clay); font-size:0.84rem; font-weight:700; padding:6px 12px; border-radius:6px; letter-spacing:0.3px; display:none;">
                  </span>
                </div>
              </div>

              <!-- Message Stream Bubbles (Pinned Scrolling Container) -->
              <div id="chat-stream" style="flex:1 1 0%; min-height:0; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:12px; background:#f9f9f9;">
                <div style="text-align:center; padding:40px; color:var(--text-muted); font-size:0.9rem;">
                  <i class="bi bi-chat-heart text-clay" style="font-size:2.4rem; display:block; margin-bottom:10px;"></i>
                  Loading conversation...
                </div>
              </div>

              <!-- Chat Input Bar (ALWAYS Visible at Bottom) -->
              <div style="border-top:1px solid var(--haat-border); padding:12px 18px; background:#ffffff; flex-shrink:0;">
                
                <!-- Quick Suggestion Tags Container -->
                <div id="chat-quick-suggestions" style="display:flex; gap:6px; margin-bottom:10px; overflow-x:auto; padding-bottom:4px;">
                  <!-- Injected dynamically via JS -->
                </div>

                <!-- Input Form -->
                <form id="chat-form" onsubmit="sendMessage(event)" style="display:flex; gap:10px; align-items:center;">
                  <input type="hidden" id="chat-seller-id" value="0">
                  <input type="text" id="chat-input" placeholder="Type a message to HAATEX Logistics Delivery Desk..." required autocomplete="off" style="flex:1; padding:10px 16px; border:1px solid var(--haat-border); border-radius:24px; font-size:0.9rem; outline:none; transition:var(--transition);" onfocus="this.style.borderColor='var(--haat-clay)';" onblur="this.style.borderColor='var(--haat-border)';">
                  <button type="submit" id="chat-send-btn" class="btn btn-clay" style="width:42px; height:42px; border-radius:50%; padding:0; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i class="bi bi-send-fill" style="margin-left:2px;"></i>
                  </button>
                </form>

              </div>

            </div>

          </div>

        </div>
      </div>

      <!-- ==========================================
           TAB: SAVED CRAFTS & WISHLIST
           ========================================== -->
      <div id="tab-wishlist" class="dash-panel" style="display:none;">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:26px; box-shadow:var(--shadow-sm);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--haat-border); padding-bottom:14px; flex-wrap:wrap; gap:10px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:8px;">
                <i class="bi bi-heart-fill text-clay"></i> Saved Wishlist Crafts (<?= $wishlistCount ?>)
              </h2>
              <p style="color:var(--text-muted); font-size:0.85rem; margin:2px 0 0;">Artisanal creations and products you have bookmarked for purchase</p>
            </div>
            <a href="<?= BASE_URL ?>shop.php" class="btn btn-sm btn-outline-green">
              <i class="bi bi-plus-lg"></i> Browse More Crafts
            </a>
          </div>

          <?php if (empty($wishlistProducts)): ?>
            <div style="text-align:center; padding:45px 20px;">
              <i class="bi bi-heart text-muted" style="font-size:3rem; color:var(--haat-clay); display:block; margin-bottom:10px;"></i>
              <h3 style="margin:0 0 6px; font-size:1.2rem;">Your Saved Wishlist is Empty</h3>
              <p style="color:var(--text-muted); font-size:0.88rem; max-width:400px; margin:0 auto 18px;">
                Click the heart icon on any handloom saree, terracotta craft, or organic harvest to save it here.
              </p>
              <a href="<?= BASE_URL ?>shop.php" class="btn btn-clay btn-sm">Explore Haat Bazaar</a>
            </div>
          <?php else: ?>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:18px;">
              <?php foreach ($wishlistProducts as $wItem): ?>
                <div id="dash-wishlist-item-<?= $wItem['id'] ?>" style="border:1px solid var(--haat-border); border-radius:var(--radius-md); overflow:hidden; background:#fff; display:flex; flex-direction:column; position:relative; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                  <div style="aspect-ratio:1/1; overflow:hidden; background:#f9f9f9; position:relative;">
                    <a href="<?= BASE_URL ?>product.php?id=<?= $wItem['id'] ?>">
                      <img src="<?= sanitize($wItem['featured_image']) ?>" alt="thumb" style="width:100%; height:100%; object-fit:cover;">
                    </a>
                    <button type="button" class="product-wishlist-btn active" data-product-id="<?= $wItem['id'] ?>" title="Remove" style="top:8px; right:8px; width:30px; height:30px;">
                      <i class="bi bi-heart-fill" style="color:#e63946; font-size:0.85rem;"></i>
                    </button>
                  </div>
                  <div style="padding:12px; display:flex; flex-direction:column; flex:1;">
                    <div style="font-size:0.72rem; color:var(--text-muted); margin-bottom:4px; font-weight:600;">
                      <?= sanitize($wItem['shop_name']) ?>
                    </div>
                    <a href="<?= BASE_URL ?>product.php?id=<?= $wItem['id'] ?>" style="font-size:0.88rem; font-weight:700; color:var(--haat-green-dark); text-decoration:none; margin-bottom:8px; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                      <?= sanitize($wItem['name']) ?>
                    </a>
                    <div style="margin-top:auto; padding-top:10px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                      <span style="font-weight:800; color:var(--haat-green); font-size:1.05rem;">
                        <?= formatPrice($wItem['sale_price'] ?: $wItem['price']) ?>
                      </span>
                      <button type="button" class="btn btn-sm btn-clay btn-add-cart btn-add-cart-action" data-product-id="<?= $wItem['id'] ?>" style="background:var(--haat-clay) !important; color:#ffffff !important; border:none; padding:6px 14px; font-size:0.82rem; font-weight:700; border-radius:var(--radius-sm); display:inline-flex; align-items:center; gap:6px; cursor:pointer; box-shadow:0 2px 6px rgba(194,97,45,0.25); width:auto !important; height:auto !important; transition:all 0.2s ease;">
                        <i class="bi bi-bag-plus-fill"></i> Add to Cart
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ==========================================
           TAB 4: ACCOUNT PROFILE & SETTINGS PANEL
           ========================================== -->
      <div id="tab-profile" class="dash-panel" style="display:none;">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px;">
          
          <!-- Edit Personal Info -->
          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
            <h3 style="font-size:1.15rem; color:var(--haat-green-dark); margin:0 0 4px; display:flex; align-items:center; gap:8px;">
              <i class="bi bi-person text-clay"></i> Personal Information
            </h3>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:18px;">Update your display name and contact phone number</p>

            <form method="POST" action="<?= BASE_URL ?>customer/index.php">
              <input type="hidden" name="update_profile" value="1">

              <div style="margin-bottom:14px;">
                <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Full Name *</label>
                <input type="text" name="name" required value="<?= sanitize($user['name']) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
              </div>

              <div style="margin-bottom:14px;">
                <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Email Address (Primary Account)</label>
                <input type="email" disabled value="<?= sanitize($user['email']) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; background:#f5f5f5; color:#777;">
              </div>

              <div style="margin-bottom:20px;">
                <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Phone Number (Bangladeshi)</label>
                <input type="text" name="phone" value="<?= sanitize($user['phone'] ?? '') ?>" placeholder="01XXXXXXXXX" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
              </div>

              <button type="submit" class="btn btn-clay btn-sm">Save Changes</button>
            </form>
          </div>

          <!-- Change Security Password -->
          <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
            <h3 style="font-size:1.15rem; color:var(--haat-green-dark); margin:0 0 4px; display:flex; align-items:center; gap:8px;">
              <i class="bi bi-shield-lock text-clay"></i> Security & Password
            </h3>
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:18px;">Ensure your account uses a secure password</p>

            <form method="POST" action="<?= BASE_URL ?>customer/index.php">
              <input type="hidden" name="change_password" value="1">

              <div style="margin-bottom:14px;">
                <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Current Password *</label>
                <input type="password" name="current_password" required placeholder="••••••••" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
              </div>

              <div style="margin-bottom:14px;">
                <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">New Password * (Min 6 chars)</label>
                <input type="password" name="new_password" required placeholder="••••••••" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
              </div>

              <div style="margin-bottom:20px;">
                <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Confirm New Password *</label>
                <input type="password" name="confirm_password" required placeholder="••••••••" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
              </div>

              <button type="submit" class="btn btn-outline-green btn-sm">Update Password</button>
            </form>
          </div>

        </div>
      </div>

    </main>
  </div>
</div>

<style>
  /* Dashboard persistent navigation styles */
  .dash-tab-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 16px;
    border-radius: var(--radius-sm);
    color: var(--text-main);
    font-size: 0.92rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1px solid transparent;
  }
  .dash-tab-link:hover {
    background: #faf7f2;
    color: var(--haat-clay);
  }
  .dash-tab-link.active {
    background: var(--haat-clay-light);
    color: var(--haat-clay);
    font-weight: 700;
    border-color: rgba(194, 97, 45, 0.18);
  }
  .dash-tab-link .badge {
    transition: all 0.2s ease;
  }
  .dash-tab-link:not(.active) .wishlist-count-badge {
    background: var(--haat-sand) !important;
    color: var(--haat-green-dark) !important;
    border: 1px solid rgba(27, 61, 34, 0.1) !important;
  }
  .dash-tab-link.active .badge {
    background: var(--haat-clay) !important;
    color: #ffffff !important;
    border: none !important;
    box-shadow: 0 1px 5px rgba(194, 97, 45, 0.4) !important;
  }
  .btn-add-cart-action:hover {
    background: #a34e20 !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(194, 97, 45, 0.35) !important;
  }
  .conv-item:hover {
    background: #f2ede4 !important;
  }
  .conv-item.active {
    background: #ffffff !important;
    border-left: 3px solid var(--haat-clay);
  }
</style>

<script>
  let activeConversationType = '<?= $targetSellerId > 0 ? "seller" : "logistics" ?>';
  let activeSellerId = <?= $targetSellerId > 0 ? $targetSellerId : (!empty($orderedSellers) ? (int)$orderedSellers[0]['seller_id'] : 0) ?>;
  let activeOrderNumber = '<?= !empty($orderedSellers) ? addslashes(sanitize($orderedSellers[0]['order_number'] ?? '')) : '' ?>';
  let chatPollTimer = null;
  let lastMessageCount = -1;

  // Quick suggestions for Logistics vs Seller
  const LOGISTICS_SUGGESTIONS = [
    { icon: '🚚', text: 'When will my package be delivered?' },
    { icon: '📍', text: 'Where is the delivery rider now?' },
    { icon: '📞', text: 'Please call me before doorstep delivery.' },
    { icon: '📦', text: 'Please confirm my delivery address.' }
  ];

  const SELLER_SUGGESTIONS = [
    { icon: '🚚', text: 'Assalamu Alaikum, has my order been dispatched?' },
    { icon: '📦', text: 'Please ensure water-proof protective packaging.' },
    { icon: '📅', text: 'Could you confirm expected crafting completion date?' }
  ];

  function renderQuickSuggestions(type) {
    const box = document.getElementById('chat-quick-suggestions');
    if (!box) return;
    const items = (type === 'logistics') ? LOGISTICS_SUGGESTIONS : SELLER_SUGGESTIONS;
    box.innerHTML = items.map(s => `
      <button type="button" onclick="setQuickMsg('${escapeHtml(s.text)}')" class="badge" style="background:#f4f4f4; color:var(--text-main); border:1px solid var(--haat-border); cursor:pointer; font-weight:500; font-size:0.75rem; white-space:nowrap; padding:4px 9px;">
        ${s.icon} ${escapeHtml(s.text)}
      </button>
    `).join('');
  }

  // Tab switching logic
  function switchTab(tabId) {
    document.querySelectorAll('.dash-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.dash-tab-link').forEach(l => l.classList.remove('active'));

    const targetPanel = document.getElementById('tab-' + tabId);
    const targetLink = document.querySelector(`.dash-tab-link[data-tab="${tabId}"]`);

    if (targetPanel) {
      targetPanel.style.display = 'block';
    }
    if (targetLink) {
      targetLink.classList.add('active');
    }
    window.location.hash = tabId;

    if (tabId === 'messages') {
      if (activeConversationType === 'logistics') {
        selectLogisticsConversation(activeOrderNumber);
      } else if (activeSellerId) {
        loadMessages(activeSellerId);
      }
      pollCustomerConversations();
    }
  }

  // Toggle Order Row Expandable Details Drawer
  function toggleOrderRowDetails(orderId) {
    const drawer = document.getElementById('order-details-drawer-' + orderId);
    const chevron = document.getElementById('order-chevron-' + orderId);
    if (!drawer) return;
    
    if (drawer.style.display === 'none' || drawer.style.display === '') {
      drawer.style.display = 'table-row';
      if (chevron) {
        chevron.style.transform = 'rotate(90deg)';
      }
    } else {
      drawer.style.display = 'none';
      if (chevron) {
        chevron.style.transform = 'rotate(0deg)';
      }
    }
  }

  // ==========================================
  // LOGISTICS CONVERSATION HANDLERS
  // ==========================================
  function selectLogisticsConversation(orderNum) {
    activeConversationType = 'logistics';
    if (orderNum) activeOrderNumber = orderNum;
    lastMessageCount = -1;

    // Highlight logistics item in left list
    document.querySelectorAll('.conv-item').forEach(item => {
      item.classList.remove('active');
      item.style.background = 'transparent';
      item.style.borderLeft = 'none';
    });
    const logItem = document.getElementById('conv-item-logistics');
    if (logItem) {
      logItem.classList.add('active');
      logItem.style.background = '#ffffff';
      logItem.style.borderLeft = '3px solid var(--haat-clay)';
      const unreadPill = document.getElementById('logistics-unread-pill');
      if (unreadPill) unreadPill.style.display = 'none';
    }

    // Update Header for Logistics
    const headerTitle = document.getElementById('active-chat-title');
    const headerSub = document.getElementById('active-chat-subtitle');
    const headerAvatar = document.getElementById('active-chat-avatar');
    const tag = document.getElementById('active-chat-product-label');
    const input = document.getElementById('chat-input');

    if (headerTitle) headerTitle.innerText = 'HAATEX';
    if (headerSub) headerSub.innerText = 'Official Delivery & Rider Dispatch Desk';
    if (headerAvatar) {
      headerAvatar.style.background = 'linear-gradient(135deg, #1b3d22 0%, #2d5a36 100%)';
      headerAvatar.style.color = '#ffffff';
      headerAvatar.innerHTML = '<i class="bi bi-truck"></i>';
    }
    applyOnlineStatus(true);

    if (tag) {
      if (activeOrderNumber) {
        tag.innerHTML = `#${escapeHtml(activeOrderNumber)}`;
        tag.style.display = 'inline-block';
      } else {
        tag.style.display = 'none';
      }
    }

    if (input) {
      input.placeholder = activeOrderNumber ? `Type a message regarding Order #${activeOrderNumber}...` : 'Type a message to HAATEX Logistics Delivery Desk...';
    }

    renderQuickSuggestions('logistics');
    loadLogisticsMessages();
  }

  function openChatWithLogistics(orderNum) {
    activeOrderNumber = orderNum || '';
    switchTab('messages');
    selectLogisticsConversation(orderNum);
  }

  function loadLogisticsMessages(isPolling = false) {
    const stream = document.getElementById('chat-stream');
    if (!stream) return;

    fetch('<?= BASE_URL ?>api/messages.php?action=get_logistics_chat')
      .then(res => res.json())
      .then(data => {
        if (!data.success) return;

        if (isPolling && data.messages.length === lastMessageCount) {
          return;
        }
        lastMessageCount = data.messages.length;

        if (data.messages.length === 0) {
          stream.innerHTML = `
            <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
              <div style="width:60px; height:60px; border-radius:50%; background:var(--haat-sand); color:var(--haat-green-dark); display:inline-flex; align-items:center; justify-content:center; font-size:1.8rem; margin-bottom:12px;">
                <i class="bi bi-truck"></i>
              </div>
              <strong style="color:var(--haat-green-dark); font-size:1.05rem; display:block;">HAATEX Live Delivery Support Desk</strong>
              <p style="font-size:0.85rem; margin:6px auto 0; max-width:380px;">Send a message to our logistics coordination hub for live package updates, rider contact, or delivery schedule inquiries.</p>
            </div>
          `;
          return;
        }

        let html = '';
        data.messages.forEach(m => {
          if (m.is_me) {
            // Customer outgoing bubble (Right)
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
            // HAATEX Logistics incoming bubble (Left)
            html += `
              <div style="display:flex; gap:10px; align-items:flex-end; margin-bottom:6px;">
                <div style="width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg, #1b3d22 0%, #2d5a36 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.95rem; flex-shrink:0;">
                  <i class="bi bi-truck"></i>
                </div>
                <div style="max-width:70%;">
                  <div style="background:#ffffff; color:var(--text-main); border:1px solid var(--haat-border); padding:10px 16px; border-radius:18px 18px 18px 4px; font-size:0.9rem; line-height:1.45; box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                    <div style="font-size:0.72rem; font-weight:700; color:var(--haat-clay); margin-bottom:3px;">HAATEX Dispatch Team</div>
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
      .catch(console.error);
  }

  // ==========================================
  // ARTISAN SELLER CONVERSATION HANDLERS
  // ==========================================
  function selectConversation(sellerId, orderNum, productName, isOnline, shopName) {
    activeConversationType = 'seller';
    activeSellerId = sellerId;
    activeOrderNumber = orderNum || '';
    lastMessageCount = -1;

    // Reset left list highlight
    document.querySelectorAll('.conv-item').forEach(item => {
      const match = parseInt(item.getAttribute('data-seller-id')) === sellerId;
      if (match) {
        item.classList.add('active');
        item.style.background = '#ffffff';
        item.style.borderLeft = '3px solid var(--haat-clay)';
        const unreadPill = item.querySelector('.unread-pill');
        if (unreadPill) unreadPill.remove();
      } else {
        item.classList.remove('active');
        item.style.background = 'transparent';
        item.style.borderLeft = 'none';
      }
    });

    const headerAvatar = document.getElementById('active-chat-avatar');
    if (headerAvatar) {
      headerAvatar.style.background = 'var(--haat-sand)';
      headerAvatar.style.color = 'var(--haat-green)';
      headerAvatar.innerText = shopName ? shopName.charAt(0).toUpperCase() : 'S';
    }

    const tag = document.getElementById('active-chat-product-label');
    if (tag) {
      if (orderNum) {
        tag.innerHTML = `#${escapeHtml(orderNum)}`;
        tag.style.display = 'inline-block';
      } else {
        tag.style.display = 'none';
      }
    }

    if (shopName) {
      document.getElementById('active-chat-title').innerText = shopName;
    }

    if (typeof isOnline !== 'undefined') {
      applyOnlineStatus(Boolean(isOnline));
    }

    const input = document.getElementById('chat-input');
    if (input) {
      input.placeholder = orderNum ? `Type message regarding Order #${orderNum}...` : 'Type a message to the artisan seller...';
    }

    renderQuickSuggestions('seller');
    loadMessages(sellerId);
  }

  function applyOnlineStatus(isOnline) {
    const statusDot = document.getElementById('active-chat-avatar-status');
    if (statusDot) {
      statusDot.style.background = isOnline ? '#2ecc71' : '#cbd5e1';
      statusDot.title = isOnline ? 'Active Now' : 'Offline';
    }
  }

  function openChatWithSeller(sellerId, orderNum, productName, isOnline, shopName) {
    activeOrderNumber = orderNum || '';
    switchTab('messages');
    selectConversation(sellerId, orderNum, productName, isOnline, shopName);
  }

  function setQuickMsg(text) {
    const input = document.getElementById('chat-input');
    input.value = text;
    input.focus();
  }

  function loadMessages(sellerId, isPolling = false) {
    if (!sellerId) return;
    const stream = document.getElementById('chat-stream');
    const hiddenInput = document.getElementById('chat-seller-id');
    if (hiddenInput) hiddenInput.value = sellerId;

    fetch(`<?= BASE_URL ?>api/messages.php?action=get&seller_id=${sellerId}`)
      .then(res => res.json())
      .then(data => {
        if (!data.success) return;

        // Immediately update sidebar unread badge
        if (typeof data.unread_total !== 'undefined') {
          const navBadge = document.getElementById('cust-nav-msg-badge') || document.querySelector('.dash-tab-link[data-tab="messages"] .badge');
          if (navBadge) {
            if (data.unread_total > 0) {
              navBadge.innerText = data.unread_total;
              navBadge.style.display = 'inline-block';
            } else {
              navBadge.style.display = 'none';
            }
          }
        }

        // Remove unread pill on active conversation item
        const activeItem = document.querySelector(`.conv-item[data-seller-id="${sellerId}"]`);
        if (activeItem) {
          const unreadPill = activeItem.querySelector('.unread-pill');
          if (unreadPill) unreadPill.remove();
        }

        if (isPolling && data.messages.length === lastMessageCount) {
          return;
        }
        lastMessageCount = data.messages.length;

        // Update active header
        if (data.seller) {
          document.getElementById('active-chat-title').innerText = data.seller.shop_name;
          const subtitle = document.getElementById('active-chat-subtitle');
          if (subtitle && data.seller.artisan_name) {
            subtitle.innerText = `${data.seller.artisan_name} (${data.seller.district || 'Artisan'})`;
          }
          applyOnlineStatus(Boolean(data.seller.is_online));
        }

        // Render message bubbles
        if (data.messages.length === 0) {
          stream.innerHTML = `
            <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
              <i class="bi bi-chat-heart" style="font-size:2.5rem; color:var(--haat-clay); display:block; margin-bottom:8px;"></i>
              <strong style="color:var(--haat-green-dark); font-size:1rem; display:block;">Start conversation with ${data.seller ? escapeHtml(data.seller.shop_name) : 'Artisan'}</strong>
              <p style="font-size:0.85rem; margin-top:4px;">Ask questions regarding handloom materials, sizing, packaging or dispatch times.</p>
            </div>
          `;
          return;
        }

        let html = '';
        data.messages.forEach(m => {
          if (m.is_me) {
            // Customer outgoing bubble (Right)
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
            // Seller incoming bubble (Left)
            html += `
              <div style="display:flex; gap:10px; align-items:flex-end; margin-bottom:6px;">
                <div style="width:32px; height:32px; border-radius:50%; background:var(--haat-sand); display:flex; align-items:center; justify-content:center; color:var(--haat-green); font-size:0.85rem; font-weight:700; flex-shrink:0;">
                  ${data.seller ? data.seller.shop_name.charAt(0).toUpperCase() : 'S'}
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
      .catch(console.error);
  }

  // Unified Message Sender
  function sendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if (!msg) return;

    const sendBtn = document.getElementById('chat-send-btn');
    sendBtn.disabled = true;

    if (activeConversationType === 'logistics') {
      // SEND TO HAATEX LOGISTICS
      const fd = new FormData();
      fd.append('action', 'send_logistics_chat');
      fd.append('message', msg);

      fetch('<?= BASE_URL ?>api/messages.php', {
        method: 'POST',
        body: fd
      })
        .then(res => res.json())
        .then(data => {
          sendBtn.disabled = false;
          if (data.success) {
            input.value = '';
            lastMessageCount = -1;
            loadLogisticsMessages();
            pollCustomerConversations();
          } else {
            alert(data.error || 'Failed to send message to HAATEX Logistics');
          }
        })
        .catch(err => {
          sendBtn.disabled = false;
          console.error(err);
        });

    } else {
      // SEND TO ARTISAN SELLER
      if (!activeSellerId) {
        sendBtn.disabled = false;
        return;
      }
      const fd = new FormData();
      fd.append('action', 'send');
      fd.append('seller_id', activeSellerId);
      fd.append('message', msg);
      if (activeOrderNumber) {
        fd.append('order_number', activeOrderNumber);
      }

      fetch('<?= BASE_URL ?>api/messages.php', {
        method: 'POST',
        body: fd
      })
        .then(res => res.json())
        .then(data => {
          sendBtn.disabled = false;
          if (data.success) {
            input.value = '';
            lastMessageCount = -1;
            loadMessages(activeSellerId);
            pollCustomerConversations();
          } else {
            alert(data.error || 'Failed to send message to seller');
          }
        })
        .catch(err => {
          sendBtn.disabled = false;
          console.error(err);
        });
    }
  }

  function pollCustomerConversations() {
    fetch('<?= BASE_URL ?>api/messages.php?action=customer_conversations')
      .then(res => res.json())
      .then(data => {
        if (!data.success) return;

        // Update sidebar unread badge
        const badge = document.getElementById('cust-nav-msg-badge') || document.querySelector('.dash-tab-link[data-tab="messages"] .badge');
        if (badge) {
          if (data.unread_total > 0) {
            badge.innerText = data.unread_total;
            badge.style.display = 'inline-block';
          } else {
            badge.style.display = 'none';
          }
        }

        // Update Logistics Conversation preview & unread
        if (data.logistics) {
          const logMsgPreview = document.getElementById('logistics-last-msg');
          const logTimePreview = document.getElementById('logistics-last-time');
          const logUnreadPill = document.getElementById('logistics-unread-pill');

          if (logMsgPreview && data.logistics.last_message) {
            logMsgPreview.innerText = data.logistics.last_message;
          }
          if (logTimePreview && data.logistics.last_message_time) {
            logTimePreview.innerText = data.logistics.last_message_time;
          }
          if (logUnreadPill) {
            if (activeConversationType === 'logistics') {
              logUnreadPill.style.display = 'none';
            } else if (data.logistics.unread_count > 0) {
              logUnreadPill.innerText = data.logistics.unread_count;
              logUnreadPill.style.display = 'inline-block';
            } else {
              logUnreadPill.style.display = 'none';
            }
          }
        }

        // Update Seller Conversation previews
        if (Array.isArray(data.conversations)) {
          data.conversations.forEach(c => {
            const item = document.querySelector(`.conv-item[data-seller-id="${c.seller_id}"]`);
            if (item) {
              const preview = item.querySelector('.last-msg-preview');
              if (preview && c.last_message) {
                preview.innerText = c.last_message;
              }
              const timePreview = item.querySelector('.conv-time-preview');
              if (timePreview && c.last_message_time) {
                timePreview.innerText = c.last_message_time;
              }
              const dot = item.querySelector('.status-dot-indicator');
              if (dot) {
                dot.style.background = c.is_online ? '#2ecc71' : '#cbd5e1';
                dot.title = c.is_online ? 'Active Now' : 'Offline';
              }

              // Unread count pill
              let pill = item.querySelector('.unread-pill');
              if (activeConversationType === 'seller' && c.seller_id === activeSellerId) {
                if (pill) pill.remove();
              } else if (c.unread_count > 0) {
                if (!pill) {
                  pill = document.createElement('span');
                  pill.className = 'unread-pill';
                  pill.style = 'font-size:0.65rem; background:var(--haat-clay); color:#fff; font-weight:700; padding:1px 6px; border-radius:10px; flex-shrink:0;';
                  item.appendChild(pill);
                }
                pill.innerText = c.unread_count;
              } else if (pill) {
                pill.remove();
              }
            }
          });
        }
      })
      .catch(console.error);
  }

  function startPolling() {
    stopPolling();
    pollCustomerConversations();
    chatPollTimer = setInterval(() => {
      pollCustomerConversations();
      const messagesPanel = document.getElementById('tab-messages');
      if (messagesPanel && messagesPanel.style.display !== 'none') {
        if (activeConversationType === 'logistics') {
          loadLogisticsMessages(true);
        } else if (activeSellerId) {
          loadMessages(activeSellerId, true);
        }
      }
    }, 3500);
  }

  function stopPolling() {
    if (chatPollTimer) {
      clearInterval(chatPollTimer);
      chatPollTimer = null;
    }
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
  }

  // Hash & URL Target Initializer
  window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#', '');
    const urlParams = new URLSearchParams(window.location.search);
    const hasTargetSeller = <?= $targetSellerId > 0 ? 'true' : 'false' ?>;
    const isLogisticsTarget = (urlParams.get('target') === 'logistics');

    if (hash && ['overview', 'orders', 'messages', 'wishlist', 'profile'].includes(hash)) {
      switchTab(hash);
      if (hash === 'messages') {
        if (isLogisticsTarget || !hasTargetSeller) {
          selectLogisticsConversation();
        } else if (activeSellerId) {
          loadMessages(activeSellerId);
        }
      }
    } else if (hasTargetSeller) {
      switchTab('messages');
      if (activeSellerId) {
        loadMessages(activeSellerId);
      }
    } else {
      switchTab('overview');
    }

    // Attach click listeners to all tab links
    document.querySelectorAll('.dash-tab-link').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const tab = link.getAttribute('data-tab');
        switchTab(tab);
      });
    });

    startPolling();
  });

  // Defensive DOM binding fallback
  function ensureCustomerBindings() {
    try {
      const links = document.querySelectorAll('.dash-tab-link');
      links.forEach(link => {
        if (!link.hasAttribute('data-has-binding')) {
          link.addEventListener('click', (e) => {
            e.preventDefault();
            const tab = link.getAttribute('data-tab');
            switchTab(tab);
          });
          link.setAttribute('data-has-binding', '1');
        }
      });
    } catch (err) {
      console.error(err);
    }
  }
  setTimeout(ensureCustomerBindings, 350);

  window.addEventListener('hashchange', () => {
    const hash = window.location.hash.replace('#', '');
    if (hash && ['overview', 'orders', 'messages', 'wishlist', 'profile'].includes(hash)) {
      switchTab(hash);
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
