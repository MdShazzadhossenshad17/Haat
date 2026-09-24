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

// Fetch all customer orders with order items
$ordStmt = $db->prepare("SELECT * FROM `orders` WHERE `user_id` = ? ORDER BY id DESC");
$ordStmt->execute([$user['id']]);
$orders = $ordStmt->fetchAll();

// Total spent & order count
$spentStmt = $db->prepare("SELECT COUNT(*) as total_orders, COALESCE(SUM(grand_total), 0) as total_spent FROM `orders` WHERE `user_id` = ?");
$spentStmt->execute([$user['id']]);
$stats = $spentStmt->fetch();

// Fetch Ordered Products & Sellers for Meta Messenger Live Chat
$uid = (int)$user['id'];
$sellerStmt = $db->query("
    SELECT oi.id as item_id, oi.order_id, oi.product_id, oi.product_name, oi.price,
           o.order_number, o.created_at as order_date,
           s.id as seller_id, s.shop_name, s.shop_logo, s.district,
           COALESCE(s.is_online, u.is_online, 0) as is_online,
           u.id as seller_user_id, u.name as artisan_name,
           p.featured_image,
           (SELECT message FROM messages WHERE (sender_id = {$uid} AND receiver_id = s.user_id) OR (sender_id = s.user_id AND receiver_id = {$uid}) ORDER BY id DESC LIMIT 1) as last_message,
           (SELECT created_at FROM messages WHERE (sender_id = {$uid} AND receiver_id = s.user_id) OR (sender_id = s.user_id AND receiver_id = {$uid}) ORDER BY id DESC LIMIT 1) as last_message_time,
           (SELECT COUNT(*) FROM messages WHERE sender_id = s.user_id AND receiver_id = {$uid} AND is_read = 0) as unread_count
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN sellers s ON oi.seller_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = {$uid}
    GROUP BY s.id, oi.product_id
    ORDER BY (last_message_time IS NOT NULL) DESC, last_message_time DESC, oi.id DESC
");
$orderedProducts = ($sellerStmt) ? $sellerStmt->fetchAll() : [];
$orderedSellers = is_array($orderedProducts) ? $orderedProducts : [];

// Total unread messages across all sellers
$unreadTotalStmt = $db->prepare("SELECT COUNT(*) FROM `messages` WHERE `receiver_id` = ? AND `is_read` = 0");
$unreadTotalStmt->execute([$user['id']]);
$totalUnreadMessages = (int)$unreadTotalStmt->fetchColumn();

// Customer Saved Wishlist
$wishlistProducts = getUserWishlistProducts($user['id']);
$wishlistCount = count($wishlistProducts);

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
      
      <!-- Customer Name Rectangular Box -->
      <div style="background:#f9fafb; border:1px solid var(--haat-border); border-radius:var(--radius-sm); padding:14px 16px; margin-bottom:18px; text-align:center;">
        <div style="color:var(--haat-green-dark); font-size:1.05rem; font-weight:700;">
          <?= sanitize($user['name']) ?>
        </div>
      </div>

      <!-- Unified Sidebar Navigation Tabs -->
      <ul class="dashboard-nav" style="display:flex; flex-direction:column; gap:6px; list-style:none; padding:0; margin:0; font-size:0.92rem; font-weight:600;">
        <li>
          <a href="#overview" class="dash-tab-link active" data-tab="overview">
            <i class="bi bi-speedometer2"></i>
            <span>Overview</span>
          </a>
        </li>
        <li>
          <a href="#orders" class="dash-tab-link" data-tab="orders">
            <i class="bi bi-bag-check"></i>
            <span>My Orders</span>
            <span class="badge" style="background:#eef4ee; color:var(--haat-green); font-size:0.75rem; padding:2px 8px; border-radius:12px; margin-left:auto;"><?= $stats['total_orders'] ?></span>
          </a>
        </li>
        <li>
          <a href="#messages" class="dash-tab-link" data-tab="messages">
            <i class="bi bi-chat-dots-fill"></i>
            <span>Seller Messages</span>
            <?php if ($totalUnreadMessages > 0): ?>
              <span class="badge" style="background:var(--haat-clay); color:#fff; font-size:0.72rem; padding:2px 8px; border-radius:12px; margin-left:auto;"><?= $totalUnreadMessages ?> new</span>
            <?php endif; ?>
          </a>
        </li>
        <li>
          <a href="#wishlist" class="dash-tab-link" data-tab="wishlist">
            <i class="bi bi-heart"></i>
            <span>Saved Wishlist</span>
            <span class="badge wishlist-count-badge" style="background:#fde8e8; color:#e63946; font-size:0.75rem; padding:2px 8px; border-radius:12px; margin-left:auto;"><?= $wishlistCount ?></span>
          </a>
        </li>
        <li>
          <a href="#profile" class="dash-tab-link" data-tab="profile">
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
            <div style="font-size:1.7rem; font-weight:800; color:#e63946; display:flex; align-items:center; gap:8px;">
              <span class="wishlist-count-badge"><?= $wishlistCount ?></span>
              <span style="font-size:0.72rem; font-weight:600; color:var(--text-muted);">Crafts</span>
            </div>
          </div>
        </div>

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
                        <?= ucfirst($ord['order_status']) ?>
                      </span>
                    </td>
                    <td style="padding:14px 0; text-align:right;">
                      <a href="<?= BASE_URL ?>order-confirmation.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-outline-green" style="padding:4px 10px; font-size:0.8rem;">
                        Invoice
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
           TAB 2: MY ORDERS PANEL
           ========================================== -->
      <div id="tab-orders" class="dash-panel" style="display:none;">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0;">Complete Order History</h2>
              <p style="font-size:0.85rem; color:var(--text-muted); margin:3px 0 0;">Review itemized orders, track deliveries, and print invoices</p>
            </div>
            <span class="badge badge-green"><?= count($orders) ?> Total Orders</span>
          </div>

          <?php if (empty($orders)): ?>
            <div style="text-align:center; padding:40px 0;">
              <i class="bi bi-bag-x text-muted" style="font-size:3rem; display:block; margin-bottom:12px;"></i>
              <h3>No Orders Placed Yet</h3>
              <a href="<?= BASE_URL ?>shop.php" class="btn btn-clay btn-sm">Explore Haat Crafts</a>
            </div>
          <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:20px;">
              <?php foreach ($orders as $ord): 
                // Fetch items for this order
                $itemStmt = $db->prepare("SELECT oi.*, s.id as seller_id, s.shop_name, p.featured_image 
                    FROM `order_items` oi 
                    JOIN `sellers` s ON oi.seller_id = s.id 
                    LEFT JOIN `products` p ON oi.product_id = p.id 
                    WHERE oi.order_id = ?");
                $itemStmt->execute([$ord['id']]);
                $orderItems = $itemStmt->fetchAll();
              ?>
                <div style="border:1px solid var(--haat-border); border-radius:var(--radius-md); overflow:hidden;">
                  
                  <!-- Order Card Header -->
                  <div style="background:#faf7f2; padding:14px 20px; border-bottom:1px solid var(--haat-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                      <strong style="color:var(--haat-green-dark); font-size:1rem;"><?= sanitize($ord['order_number']) ?></strong>
                      <span style="font-size:0.8rem; color:var(--text-muted); margin-left:10px;">Placed on <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?></span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                      <span class="badge badge-<?= $ord['order_status'] === 'delivered' ? 'green' : 'gold' ?>">
                        <?= strtoupper($ord['order_status']) ?>
                      </span>
                      <a href="<?= BASE_URL ?>order-confirmation.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-outline-green" style="padding:4px 10px; font-size:0.8rem;">
                        <i class="bi bi-receipt"></i> Invoice
                      </a>
                      <a href="<?= BASE_URL ?>track-order.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-clay" style="padding:4px 10px; font-size:0.8rem;">
                        <i class="bi bi-truck"></i> Track
                      </a>
                    </div>
                  </div>

                  <!-- Order Card Body & Items -->
                  <div style="padding:16px 20px;">
                    <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:14px;">
                      <?php foreach ($orderItems as $it): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px dashed var(--haat-border); padding-bottom:10px;">
                          <div style="display:flex; align-items:center; gap:12px;">
                            <?php if (!empty($it['featured_image'])): ?>
                              <img src="<?= sanitize($it['featured_image']) ?>" alt="thumb" style="width:48px; height:48px; border-radius:var(--radius-sm); object-fit:cover; border:1px solid var(--haat-border);">
                            <?php else: ?>
                              <div style="width:48px; height:48px; border-radius:var(--radius-sm); background:var(--haat-sand); display:flex; align-items:center; justify-content:center; color:var(--haat-clay);">
                                <i class="bi bi-box"></i>
                              </div>
                            <?php endif; ?>
                            <div>
                              <strong style="font-size:0.92rem; color:var(--text-main); display:block;"><?= sanitize($it['product_name']) ?></strong>
                              <span style="font-size:0.8rem; color:var(--text-muted);">
                                Workshop: <strong style="color:var(--haat-clay);"><?= sanitize($it['shop_name']) ?></strong> • Qty: <?= $it['quantity'] ?>
                              </span>
                            </div>
                          </div>

                          <div style="text-align:right;">
                            <span style="font-size:0.95rem; font-weight:700; color:var(--haat-green);"><?= formatPrice($it['price'] * $it['quantity']) ?></span>
                            <div style="margin-top:4px;">
                              <button type="button" onclick="openChatWithSeller(<?= $it['seller_id'] ?>, '<?= sanitize($ord['order_number']) ?>', '<?= addslashes(sanitize($it['product_name'])) ?>')" class="btn btn-sm btn-outline-clay" style="padding:2px 8px; font-size:0.75rem;">
                                <i class="bi bi-chat-dots"></i> Message Seller
                              </button>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>

                    <!-- Order Card Summary Footer -->
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem; color:var(--text-muted); padding-top:6px;">
                      <div>
                        Delivery: <strong><?= sanitize($ord['shipping_address']) ?>, <?= sanitize($ord['district']) ?></strong> (Phone: <?= sanitize($ord['shipping_phone']) ?>)
                      </div>
                      <div style="font-size:1rem; font-weight:800; color:var(--haat-green-dark);">
                        Grand Total: <?= formatPrice($ord['grand_total']) ?>
                      </div>
                    </div>
                  </div>

                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ==========================================
           TAB 3: META MESSENGER LIVE CHAT PANEL
           ========================================== -->
      <div id="tab-messages" class="dash-panel" style="display:none;">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); box-shadow:var(--shadow-sm); overflow:hidden;">
          
          <!-- Messenger Component Container -->
          <div style="display:grid; grid-template-columns: 330px 1fr; height: 580px;">
            
            <!-- Left Pane: Conversations / Ordered Products & Sellers -->
            <div style="border-right:1px solid var(--haat-border); display:flex; flex-direction:column; background:#faf7f2;">
              
              <div style="padding:16px 18px; border-bottom:1px solid var(--haat-border); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="font-size:1.02rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:8px;">
                  <i class="bi bi-chat-left-dots text-clay"></i> Ordered Products & Sellers
                </h3>
              </div>

              <!-- Conversation List -->
              <div id="conversation-list" style="flex:1; overflow-y:auto; padding:6px 0;">
                <?php if (empty($orderedProducts)): ?>
                  <div style="padding:30px 18px; text-align:center; color:var(--text-muted); font-size:0.85rem;">
                    <i class="bi bi-shop" style="font-size:2rem; display:block; margin-bottom:8px;"></i>
                    When you order handcrafted items, your product seller conversations will appear here.
                  </div>
                <?php else: ?>
                  <?php foreach ($orderedProducts as $idx => $s): ?>
                    <div class="conv-item <?= $idx === 0 ? 'active' : '' ?>" 
                         data-seller-id="<?= $s['seller_id'] ?>" 
                         data-order-number="<?= sanitize($s['order_number']) ?>"
                         data-product-name="<?= sanitize($s['product_name']) ?>"
                         data-is-online="<?= $s['is_online'] ? '1' : '0' ?>"
                         onclick="selectConversation(<?= $s['seller_id'] ?>, '<?= sanitize($s['order_number']) ?>', '<?= addslashes(sanitize($s['product_name'])) ?>', <?= $s['is_online'] ? '1' : '0' ?>)" 
                         style="padding:12px 14px; border-bottom:1px solid rgba(0,0,0,0.05); cursor:pointer; display:flex; gap:12px; align-items:flex-start; transition:var(--transition); background:<?= $idx === 0 ? '#ffffff' : 'transparent' ?>;">
                      
                      <!-- Product Image Thumbnail with Live Status Dot -->
                      <div style="position:relative; flex-shrink:0;">
                        <img src="<?= sanitize($s['featured_image'] ?: BASE_URL . 'assets/images/default-product.png') ?>" 
                             alt="<?= sanitize($s['product_name']) ?>" 
                             style="width:46px; height:46px; border-radius:8px; object-fit:cover; border:1px solid var(--haat-border); background:#fff;">
                        <!-- Workable Active Dot (Green if online, Red if offline) -->
                        <span class="status-dot-indicator" 
                              style="position:absolute; bottom:-2px; right:-2px; width:12px; height:12px; background:<?= $s['is_online'] ? '#2ecc71' : '#e74c3c' ?>; border-radius:50%; border:2px solid #fff;" 
                              title="<?= $s['is_online'] ? 'Active Now' : 'Offline' ?>"></span>
                      </div>

                      <!-- Product and Seller Info -->
                      <div style="flex:1; min-width:0;">
                        <div style="display:flex; justify-content:space-between; align-items:baseline; gap:6px;">
                          <strong style="font-size:0.86rem; color:var(--haat-green-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;">
                            <?= sanitize($s['product_name']) ?>
                          </strong>
                          <?php if (!empty($s['last_message_time'])): ?>
                            <span style="font-size:0.68rem; color:var(--text-muted); flex-shrink:0;">
                              <?= date('h:i A', strtotime($s['last_message_time'])) ?>
                            </span>
                          <?php endif; ?>
                        </div>

                        <!-- Seller Shop Name -->
                        <div style="font-size:0.75rem; color:var(--haat-clay); font-weight:600; display:flex; align-items:center; gap:4px; margin:2px 0;">
                          <i class="bi bi-shop" style="font-size:0.72rem;"></i>
                          <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= sanitize($s['shop_name']) ?></span>
                        </div>

                        <!-- Order Number and Last Message Preview -->
                        <div style="display:flex; align-items:center; gap:6px; font-size:0.73rem; color:var(--text-muted);">
                          <span class="badge" style="background:#f1eee9; color:#6b5847; padding:1px 5px; font-size:0.68rem; border-radius:4px; flex-shrink:0;">
                            #<?= sanitize($s['order_number']) ?>
                          </span>
                          <span class="last-msg-preview" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;">
                            <?= sanitize($s['last_message'] ?? 'Click to chat...') ?>
                          </span>
                        </div>
                      </div>

                      <?php if ($s['unread_count'] > 0): ?>
                        <span class="unread-pill" style="width:8px; height:8px; background:var(--haat-clay); border-radius:50%; flex-shrink:0; margin-top:6px;"></span>
                      <?php endif; ?>

                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

            </div>

            <!-- Right Pane: Active Live Chat Window -->
            <div style="display:flex; flex-direction:column; background:#ffffff;">
              
              <!-- Chat Header -->
              <div id="chat-header" style="padding:14px 20px; border-bottom:1px solid var(--haat-border); display:flex; justify-content:space-between; align-items:center; background:#ffffff;">
                <div style="display:flex; align-items:center; gap:12px;">
                  <div style="position:relative;">
                    <div id="active-chat-avatar" style="width:42px; height:42px; border-radius:50%; background:var(--haat-sand); display:flex; align-items:center; justify-content:center; color:var(--haat-green); font-size:1.2rem; font-weight:700;">
                      <?= !empty($orderedProducts) ? strtoupper(substr($orderedProducts[0]['shop_name'], 0, 1)) : 'S' ?>
                    </div>
                    <!-- Workable Active/Offline Dot -->
                    <span id="active-chat-avatar-status" 
                          style="position:absolute; bottom:0; right:0; width:11px; height:11px; background:<?= (!empty($orderedProducts) && $orderedProducts[0]['is_online']) ? '#2ecc71' : '#e74c3c' ?>; border-radius:50%; border:2px solid #fff;"></span>
                  </div>

                  <div>
                    <strong id="active-chat-title" style="color:var(--haat-green-dark); font-size:0.98rem; display:block;">
                      <?= !empty($orderedProducts) ? sanitize($orderedProducts[0]['shop_name']) : 'Select a Seller' ?>
                    </strong>
                  </div>
                </div>

                <!-- Only Order ID in Chat Header -->
                <div id="active-chat-order-tag">
                  <span id="active-chat-product-label" class="badge" style="background:#f7efe6; color:var(--haat-clay); font-size:0.84rem; font-weight:700; padding:6px 12px; border-radius:6px; letter-spacing:0.3px;">
                    <?php if (!empty($orderedProducts)): ?>
                      #<?= sanitize($orderedProducts[0]['order_number']) ?>
                    <?php endif; ?>
                  </span>
                </div>
              </div>

              <!-- Message Stream Bubbles -->
              <div id="chat-stream" style="flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:12px; background:#f9f9f9;">
                <div style="text-align:center; padding:40px; color:var(--text-muted); font-size:0.9rem;">
                  <i class="bi bi-chat-heart text-clay" style="font-size:2.4rem; display:block; margin-bottom:10px;"></i>
                  Loading conversation...
                </div>
              </div>

              <!-- Chat Input Bar -->
              <div style="border-top:1px solid var(--haat-border); padding:12px 18px; background:#ffffff;">
                
                <!-- Quick Suggestion Tags -->
                <div style="display:flex; gap:6px; margin-bottom:10px; overflow-x:auto; padding-bottom:4px;">
                  <button type="button" onclick="setQuickMsg('Assalamu Alaikum, has my order been dispatched?')" class="badge" style="background:#f4f4f4; color:var(--text-main); border:1px solid var(--haat-border); cursor:pointer; font-weight:500; font-size:0.75rem;">
                    🚚 Has it been dispatched?
                  </button>
                  <button type="button" onclick="setQuickMsg('Please ensure water-proof protective packaging.')" class="badge" style="background:#f4f4f4; color:var(--text-main); border:1px solid var(--haat-border); cursor:pointer; font-weight:500; font-size:0.75rem;">
                    📦 Please pack securely
                  </button>
                  <button type="button" onclick="setQuickMsg('Could you confirm expected delivery date?')" class="badge" style="background:#f4f4f4; color:var(--text-main); border:1px solid var(--haat-border); cursor:pointer; font-weight:500; font-size:0.75rem;">
                    📅 Delivery date?
                  </button>
                </div>

                <!-- Input Form -->
                <form id="chat-form" onsubmit="sendMessage(event)" style="display:flex; gap:10px; align-items:center;">
                  <input type="hidden" id="chat-seller-id" value="">
                  <input type="text" id="chat-input" placeholder="Type a message to the artisan seller..." required autocomplete="off" style="flex:1; padding:10px 16px; border:1px solid var(--haat-border); border-radius:24px; font-size:0.9rem; outline:none; transition:var(--transition);" onfocus="this.style.borderColor='var(--haat-clay)';" onblur="this.style.borderColor='var(--haat-border)';">
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
                    <div style="margin-top:auto; padding-top:8px; display:flex; justify-content:space-between; align-items:center;">
                      <span style="font-weight:800; color:var(--haat-green); font-size:1rem;">
                        <?= formatPrice($wItem['sale_price'] ?: $wItem['price']) ?>
                      </span>
                      <button type="button" class="btn btn-sm btn-clay btn-add-cart" data-product-id="<?= $wItem['id'] ?>" style="padding:4px 9px; font-size:0.78rem;">
                        <i class="bi bi-bag-plus"></i> Cart
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
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    color: var(--text-main);
    text-decoration: none;
    transition: var(--transition);
  }
  .dash-tab-link:hover {
    background: var(--haat-sand);
    color: var(--haat-green);
  }
  .dash-tab-link.active {
    background: var(--haat-sand);
    color: var(--haat-green);
    font-weight: 700;
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
  let activeSellerId = <?= !empty($orderedSellers) ? (int)$orderedSellers[0]['seller_id'] : 0 ?>;
  let chatPollTimer = null;

  // Tab switching logic (seamless on right-side panel without page navigation)
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
      if (activeSellerId) {
        loadMessages(activeSellerId);
        startPolling();
      }
    } else {
      stopPolling();
    }
  }

  // Hash-based tab activation on initial load
  window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#', '');
    if (hash && ['overview', 'orders', 'messages', 'wishlist', 'profile'].includes(hash)) {
      switchTab(hash);
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
  });

  // Messenger functions
  function selectConversation(sellerId, orderNum, productName, isOnline) {
    activeSellerId = sellerId;
    document.querySelectorAll('.conv-item').forEach(item => {
      const match = parseInt(item.getAttribute('data-seller-id')) === sellerId;
      if (match) {
        item.classList.add('active');
        item.style.background = '#ffffff';
      } else {
        item.classList.remove('active');
        item.style.background = 'transparent';
      }
    });

    if (orderNum) {
      const tag = document.getElementById('active-chat-product-label');
      if (tag) {
        tag.innerHTML = `#${escapeHtml(orderNum)}`;
      }
    }
    if (typeof isOnline !== 'undefined') {
      applyOnlineStatus(Boolean(isOnline));
    }
    loadMessages(sellerId);
  }

  function applyOnlineStatus(isOnline) {
    const statusDot = document.getElementById('active-chat-avatar-status');
    if (statusDot) {
      statusDot.style.background = isOnline ? '#2ecc71' : '#e74c3c';
      statusDot.title = isOnline ? 'Active Now' : 'Offline';
    }
  }

  function openChatWithSeller(sellerId, orderNum, productName, isOnline) {
    switchTab('messages');
    selectConversation(sellerId, orderNum, productName, isOnline);
    if (orderNum) {
      document.getElementById('chat-input').placeholder = `Type message regarding Order #${orderNum}...`;
    }
  }

  function setQuickMsg(text) {
    const input = document.getElementById('chat-input');
    input.value = text;
    input.focus();
  }

  function loadMessages(sellerId) {
    if (!sellerId) return;
    const stream = document.getElementById('chat-stream');
    document.getElementById('chat-seller-id').value = sellerId;

    fetch(`<?= BASE_URL ?>api/messages.php?action=get&seller_id=${sellerId}`)
      .then(res => res.json())
      .then(data => {
        if (!data.success) return;

        // Update header
        document.getElementById('active-chat-title').innerText = data.seller.shop_name;
        document.getElementById('active-chat-avatar').innerText = data.seller.shop_name.charAt(0).toUpperCase();
        applyOnlineStatus(Boolean(data.seller.is_online));

        // Render message bubbles
        if (data.messages.length === 0) {
          stream.innerHTML = `
            <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
              <i class="bi bi-chat-heart" style="font-size:2.5rem; color:var(--haat-clay); display:block; margin-bottom:8px;"></i>
              <strong style="color:var(--haat-green-dark); font-size:1rem; display:block;">Start conversation with ${data.seller.shop_name}</strong>
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
                  ${data.seller.shop_name.charAt(0).toUpperCase()}
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

  function sendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if (!msg || !activeSellerId) return;

    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('seller_id', activeSellerId);
    fd.append('message', msg);

    input.value = '';
    const sendBtn = document.getElementById('chat-send-btn');
    sendBtn.disabled = true;

    fetch('<?= BASE_URL ?>api/messages.php', {
      method: 'POST',
      body: fd
    })
      .then(res => res.json())
      .then(data => {
        sendBtn.disabled = false;
        if (data.success) {
          loadMessages(activeSellerId);
        }
      })
      .catch(err => {
        sendBtn.disabled = false;
        console.error(err);
      });
  }

  function startPolling() {
    stopPolling();
    chatPollTimer = setInterval(() => {
      const messagesPanel = document.getElementById('tab-messages');
      if (messagesPanel && messagesPanel.style.display !== 'none' && activeSellerId) {
        loadMessages(activeSellerId);
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
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
