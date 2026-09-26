<?php
/**
 * HAATEX Proprietary Logistics & Courier Operations Hub
 * HAAT Multi-Vendor E-Commerce Platform
 */
require_once __DIR__ . '/../includes/functions.php';
requireLogistics();

$user = currentUser();

// 1. Handle Direct Form Actions from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Action A: Update Order Logistics Status
    if (isset($_POST['action_update_logistics'])) {
        $orderId = (int)$_POST['order_id'];
        $newLogisticsStatus = trim($_POST['logistics_status'] ?? '');
        $riderId = (int)($_POST['rider_id'] ?? 0);
        $hubLocation = trim($_POST['location'] ?? 'HAATEX Central Hub (Dhaka)');
        $note = trim($_POST['note'] ?? '');

        $ordStmt = $db->prepare("SELECT * FROM `orders` WHERE `id` = ?");
        $ordStmt->execute([$orderId]);
        $ord = $ordStmt->fetch();

        if ($ord && in_array($newLogisticsStatus, ['pickup_requested', 'hub_received', 'out_for_delivery', 'delivered', 'cancelled'])) {
            $riderName = $ord['assigned_rider_name'];
            $riderPhone = $ord['assigned_rider_phone'];

            if ($riderId > 0) {
                $rInfo = $db->query("SELECT * FROM `riders` WHERE `id` = {$riderId}")->fetch();
                if ($rInfo) {
                    $riderName = $rInfo['name'];
                    $riderPhone = $rInfo['phone'];
                    $db->prepare("UPDATE `riders` SET `active_deliveries` = `active_deliveries` + 1 WHERE `id` = ?")->execute([$riderId]);
                }
            }

            $orderStatus = $ord['order_status'];
            $timestampField = null;

            if ($newLogisticsStatus === 'hub_received') {
                $orderStatus = 'shipped';
                $timestampField = 'logistics_accepted_at';
                $defaultTitle = 'Package Received at HAATEX Sorting Hub';
                $defaultNote = $note ?: 'Package safely received from artisan workshop at HAATEX regional fulfillment center.';
            } elseif ($newLogisticsStatus === 'out_for_delivery') {
                $orderStatus = 'shipped';
                $timestampField = 'out_for_delivery_at';
                $defaultTitle = 'Out for Delivery (HAATEX Express)';
                $defaultNote = $note ?: "Assigned to delivery rider {$riderName} ({$riderPhone}). Out for doorstep delivery.";
            } elseif ($newLogisticsStatus === 'delivered') {
                $orderStatus = 'delivered';
                $timestampField = 'delivered_at';
                $defaultTitle = 'Delivered to Recipient';
                $defaultNote = $note ?: 'Package successfully handed over to recipient at delivery address.';
                $db->prepare("UPDATE `order_items` SET `vendor_status` = 'delivered' WHERE `order_id` = ?")->execute([$orderId]);
            } else {
                $defaultTitle = 'Logistics Status Updated';
                $defaultNote = $note ?: "Status updated to " . strtoupper($newLogisticsStatus);
            }

            $timeSql = $timestampField ? ", `{$timestampField}` = NOW()" : "";
            $upStmt = $db->prepare("UPDATE `orders` SET 
                `logistics_status` = ?,
                `order_status` = ?,
                `assigned_rider_id` = ?,
                `assigned_rider_name` = ?,
                `assigned_rider_phone` = ?
                {$timeSql}
                WHERE `id` = ?");
            $upStmt->execute([$newLogisticsStatus, $orderStatus, $riderId ?: $ord['assigned_rider_id'], $riderName, $riderPhone, $orderId]);

            logOrderTrackingEvent(
                $orderId,
                $ord['order_number'],
                $defaultTitle,
                'HAATEX Logistics Hub',
                $hubLocation,
                $newLogisticsStatus === 'hub_received' ? 'processing' : ($newLogisticsStatus === 'out_for_delivery' ? 'shipped' : $newLogisticsStatus),
                $defaultNote
            );

            if (!empty($ord['user_id'])) {
                if ($newLogisticsStatus === 'out_for_delivery') {
                    createNotification(
                        $ord['user_id'],
                        "Out for Delivery: #{$ord['order_number']}",
                        "Your package is out for delivery with HAATEX Rider {$riderName} ({$riderPhone}). Please keep your phone reachable.",
                        "out_for_delivery",
                        BASE_URL . "track-order.php?order=" . urlencode($ord['order_number']),
                        null,
                        $ord['order_number']
                    );
                } elseif ($newLogisticsStatus === 'delivered') {
                    dismissOrderNotifications($ord['order_number']);
                }
            }

            setFlash('success', "Order #{$ord['order_number']} updated to " . ucfirst(str_replace('_', ' ', $newLogisticsStatus)) . " and broadcast to tracking.");
            header('Location: ' . BASE_URL . 'logistics/#orders');
            exit;
        }
    }

    // Action B: Register New Delivery Rider
    if (isset($_POST['action_add_rider'])) {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $hubZone = trim($_POST['hub_zone'] ?? 'Dhaka Central Hub');
        $vehicleType = trim($_POST['vehicle_type'] ?? 'Motorbike');

        if (!empty($name) && !empty($phone)) {
            $rIns = $db->prepare("INSERT INTO `riders` (`name`, `phone`, `hub_zone`, `vehicle_type`, `status`) VALUES (?, ?, ?, ?, 'active')");
            $rIns->execute([$name, $phone, $hubZone, $vehicleType]);
            setFlash('success', "Delivery Rider '{$name}' registered in HAATEX fleet.");
            header('Location: ' . BASE_URL . 'logistics/#riders');
            exit;
        }
    }

    // Action C: Toggle Rider Duty Status
    if (isset($_POST['action_toggle_rider_duty'])) {
        $riderId = (int)($_POST['rider_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'active');
        if ($riderId > 0 && in_array($newStatus, ['active', 'off_duty', 'inactive'])) {
            $db->prepare("UPDATE `riders` SET `status` = ? WHERE `id` = ?")->execute([$newStatus, $riderId]);
            $statusLabel = ($newStatus === 'active') ? 'Active on Duty' : 'Off Duty (Inactive)';
            setFlash('success', "Rider duty status updated to {$statusLabel}.");
            header('Location: ' . BASE_URL . 'logistics/#riders');
            exit;
        }
    }

    // Action D: Remove Rider from Fleet
    if (isset($_POST['action_delete_rider'])) {
        $riderId = (int)($_POST['rider_id'] ?? 0);
        if ($riderId > 0) {
            $db->prepare("DELETE FROM `riders` WHERE `id` = ?")->execute([$riderId]);
            setFlash('success', "Rider removed from active fleet roster.");
            header('Location: ' . BASE_URL . 'logistics/#riders');
            exit;
        }
    }
}

// 2. Fetch Performance & Pipeline KPI Metrics
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM `orders`")->fetchColumn();
$pickupRequests = (int)$db->query("SELECT COUNT(*) FROM `orders` WHERE `logistics_status` = 'pickup_requested'")->fetchColumn();
$inHub = (int)$db->query("SELECT COUNT(*) FROM `orders` WHERE `logistics_status` = 'hub_received'")->fetchColumn();
$outForDelivery = (int)$db->query("SELECT COUNT(*) FROM `orders` WHERE `logistics_status` = 'out_for_delivery'")->fetchColumn();
$deliveredCount = (int)$db->query("SELECT COUNT(*) FROM `orders` WHERE `logistics_status` = 'delivered'")->fetchColumn();

// Active Pipeline Count (only undelivered, non-cancelled orders)
$activePipelineCount = (int)$db->query("SELECT COUNT(*) FROM `orders` WHERE `order_status` NOT IN ('delivered', 'cancelled') AND (`logistics_status` IS NULL OR `logistics_status` NOT IN ('delivered', 'cancelled'))")->fetchColumn();

// Fetch Riders with Live Active Deliveries and Total Delivered Counts
$riders = $db->query("SELECT r.*, 
    (SELECT COUNT(*) FROM `orders` WHERE `assigned_rider_id` = r.id AND `logistics_status` IN ('pickup_requested', 'hub_received', 'out_for_delivery') AND `order_status` NOT IN ('delivered', 'cancelled')) as live_active_deliveries,
    (SELECT COUNT(*) FROM `orders` WHERE `assigned_rider_id` = r.id AND (`logistics_status` = 'delivered' OR `order_status` = 'delivered')) as total_delivered_orders
    FROM `riders` r 
    ORDER BY (r.status = 'active') DESC, r.id ASC")->fetchAll();

// Fetch All Pipeline Orders
$ordersStmt = $db->query("SELECT o.*, 
    u.name as buyer_name, u.email as buyer_email,
    (SELECT COUNT(*) FROM `order_items` WHERE `order_id` = o.id) as item_count,
    (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' (Qty: ', oi.quantity, ')') SEPARATOR ', ') FROM `order_items` oi WHERE oi.order_id = o.id) as item_summary,
    (SELECT s.shop_name FROM `order_items` oi JOIN `sellers` s ON oi.seller_id = s.id WHERE oi.order_id = o.id LIMIT 1) as seller_shop,
    (SELECT s.district FROM `order_items` oi JOIN `sellers` s ON oi.seller_id = s.id WHERE oi.order_id = o.id LIMIT 1) as seller_district
    FROM `orders` o 
    JOIN `users` u ON o.user_id = u.id 
    ORDER BY o.id DESC");
$allShipments = $ordersStmt->fetchAll();

// Fetch Communications with Customers (logistics messages only)
$chatUsersStmt = $db->query("SELECT DISTINCT u.id, u.name, u.email, u.phone, u.avatar, COALESCE(u.is_online, 0) as is_online,
    (SELECT message FROM messages WHERE (seller_id IS NULL OR seller_id = 0) AND (sender_id = u.id OR receiver_id = u.id) ORDER BY id DESC LIMIT 1) as last_message,
    (SELECT created_at FROM messages WHERE (seller_id IS NULL OR seller_id = 0) AND (sender_id = u.id OR receiver_id = u.id) ORDER BY id DESC LIMIT 1) as last_message_time,
    (SELECT COUNT(*) FROM messages WHERE (seller_id IS NULL OR seller_id = 0) AND sender_id = u.id AND is_read = 0) as unread_count
    FROM users u 
    WHERE u.role = 'customer'
    ORDER BY (last_message_time IS NOT NULL) DESC, last_message_time DESC LIMIT 25");
$customerComms = $chatUsersStmt->fetchAll();

// Fetch Communications with Sellers (Artisan Workshops)
$sellerCommsStmt = $db->query("SELECT s.id as seller_id, s.shop_name, s.phone as seller_phone, s.district,
    u.id as user_id, u.name as artisan_name, u.avatar, COALESCE(u.is_online, 1) as is_online,
    (SELECT message FROM messages WHERE seller_id = s.id AND (sender_id IN (SELECT id FROM users WHERE role = 'logistics' OR role = 'admin') OR receiver_id IN (SELECT id FROM users WHERE role = 'logistics' OR role = 'admin')) ORDER BY id DESC LIMIT 1) as last_message,
    (SELECT created_at FROM messages WHERE seller_id = s.id AND (sender_id IN (SELECT id FROM users WHERE role = 'logistics' OR role = 'admin') OR receiver_id IN (SELECT id FROM users WHERE role = 'logistics' OR role = 'admin')) ORDER BY id DESC LIMIT 1) as last_message_time,
    (SELECT COUNT(*) FROM messages WHERE seller_id = s.id AND receiver_id IN (SELECT id FROM users WHERE role = 'logistics' OR role = 'admin') AND is_read = 0) as unread_count
    FROM sellers s
    JOIN users u ON s.user_id = u.id
    ORDER BY (last_message_time IS NOT NULL) DESC, last_message_time DESC, s.id ASC");
$sellerComms = $sellerCommsStmt->fetchAll();

$totalUnreadSellerMsgs = 0;
foreach ($sellerComms as $sc) {
    $totalUnreadSellerMsgs += (int)$sc['unread_count'];
}

$totalUnreadCustomerMsgs = 0;
foreach ($customerComms as $cc) {
    $totalUnreadCustomerMsgs += (int)$cc['unread_count'];
}
$totalUnreadComms = $totalUnreadCustomerMsgs + $totalUnreadSellerMsgs;

$pageTitle = 'HAATEX Logistics Hub — Operations Command';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 28px 20px 60px;">

  <!-- Logistics Header Bar -->
  <div style="background:linear-gradient(135deg, #1b3d22 0%, #2d5a36 100%); color:#fff; border-radius:var(--radius-lg); padding:24px 30px; margin-bottom:28px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; box-shadow:0 4px 16px rgba(27,61,34,0.18);">
    <div style="display:flex; align-items:center; gap:16px;">
      <div style="width:52px; height:52px; border-radius:12px; background:rgba(255,255,255,0.15); display:flex; align-items:center; justify-content:center; font-size:1.6rem; color:var(--haat-sand); backdrop-filter:blur(4px); border:1px solid rgba(255,255,255,0.2);">
        <i class="bi bi-truck"></i>
      </div>
      <h1 style="font-size:1.55rem; margin:0; color:#fff; font-weight:800; letter-spacing:-0.3px;">
        HAATEX Logistics Command Hub
      </h1>
    </div>

    <div style="display:flex; align-items:center; gap:10px;">
      <button type="button" onclick="openAddRiderModal()" class="btn btn-sm btn-clay" style="font-size:0.85rem; padding:8px 16px; display:inline-flex; align-items:center; gap:6px;">
        <i class="bi bi-person-plus-fill"></i> + Register Delivery Rider
      </button>
    </div>
  </div>

  <!-- KPI Metrics Banner (5-Stat Cards) -->
  <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:14px; margin-bottom:28px;">
    
    <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm); cursor:pointer;" onclick="filterPipeline('all')">
      <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Total Shipments</div>
      <div style="font-size:1.7rem; font-weight:800; color:var(--haat-green-dark); margin-top:2px;"><?= $totalOrders ?></div>
      <span style="font-size:0.72rem; color:var(--text-muted);">All recorded platform orders</span>
    </div>

    <div style="background:<?= $pickupRequests > 0 ? '#fff8eb' : '#fff' ?>; border:1px solid <?= $pickupRequests > 0 ? '#fde68a' : 'var(--haat-border)' ?>; border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm); cursor:pointer;" onclick="filterPipeline('pickup_requested')">
      <div style="font-size:0.75rem; color:<?= $pickupRequests > 0 ? '#b45309' : 'var(--text-muted)' ?>; text-transform:uppercase; font-weight:700;">Pickup Requests</div>
      <div style="font-size:1.7rem; font-weight:800; color:#b45309; margin-top:2px; display:flex; align-items:center; gap:8px;">
        <span><?= $pickupRequests ?></span>
        <?php if ($pickupRequests > 0): ?>
          <span style="font-size:0.65rem; background:#b45309; color:#fff; padding:2px 6px; border-radius:10px; font-weight:700;">Action Req</span>
        <?php endif; ?>
      </div>
      <span style="font-size:0.72rem; color:var(--text-muted);">Artisan packed & waiting pickup</span>
    </div>

    <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm); cursor:pointer;" onclick="filterPipeline('hub_received')">
      <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">In Sorting Hub</div>
      <div style="font-size:1.7rem; font-weight:800; color:var(--haat-green); margin-top:2px;"><?= $inHub ?></div>
      <span style="font-size:0.72rem; color:var(--text-muted);">Received at regional hub</span>
    </div>

    <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm); cursor:pointer;" onclick="filterPipeline('out_for_delivery')">
      <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Out for Delivery</div>
      <div style="font-size:1.7rem; font-weight:800; color:var(--haat-clay); margin-top:2px;"><?= $outForDelivery ?></div>
      <span style="font-size:0.72rem; color:var(--text-muted);">With HAATEX riders</span>
    </div>

    <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; box-shadow:var(--shadow-sm); cursor:pointer;" onclick="filterPipeline('delivered')">
      <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Delivered</div>
      <div style="font-size:1.7rem; font-weight:800; color:#166534; margin-top:2px;"><?= $deliveredCount ?></div>
      <span style="font-size:0.72rem; color:var(--text-muted);">Doorstep completed</span>
    </div>

  </div>

  <!-- Unified Navigation Tabs for Logistics Operations -->
  <div style="display:grid; grid-template-columns: 260px 1fr; gap:28px; align-items:flex-start;">
    
    <!-- LEFT SIDEBAR -->
    <aside style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:20px; box-shadow:var(--shadow-sm); position:sticky; top:20px;">
      <div style="font-size:0.78rem; text-transform:uppercase; font-weight:800; color:var(--text-muted); margin-bottom:12px; letter-spacing:0.5px;">
        Operations Menu
      </div>
      <ul style="display:flex; flex-direction:column; gap:6px; list-style:none; padding:0; margin:0; font-size:0.92rem; font-weight:600;">
        <li>
          <a href="#orders" class="logistics-tab-link active" data-tab="orders" onclick="switchLogisticsTab('orders')">
            <i class="bi bi-boxes"></i>
            <span>Delivery Pipeline</span>
            <span class="badge" style="background:<?= $activePipelineCount > 0 ? 'var(--haat-sand)' : '#f1f5f9' ?>; color:<?= $activePipelineCount > 0 ? 'var(--haat-green-dark)' : 'var(--text-muted)' ?>; margin-left:auto; font-size:0.75rem; font-weight:700;"><?= $activePipelineCount ?></span>
          </a>
        </li>
        <li>
          <a href="#riders" class="logistics-tab-link" data-tab="riders" onclick="switchLogisticsTab('riders')">
            <i class="bi bi-bicycle"></i>
            <span>Delivery Riders Fleet</span>
          </a>
        </li>
        <li>
          <a href="#messages" class="logistics-tab-link" data-tab="messages" onclick="switchLogisticsTab('messages')">
            <i class="bi bi-chat-dots-fill"></i>
            <span>Live Comms Desk</span>
            <span id="logistics-nav-msg-badge" class="badge" style="background:var(--haat-clay); color:#fff; margin-left:auto; font-size:0.75rem; font-weight:700; <?= $totalUnreadComms > 0 ? '' : 'display:none;' ?>"><?= $totalUnreadComms ?></span>
          </a>
        </li>
      </ul>
    </aside>

    <!-- RIGHT SIDE CONTENT PANELS -->
    <main style="min-width:0;">
      
      <!-- ==========================================
           TAB 1: DELIVERY PIPELINE & ORDERS
           ========================================== -->
      <div id="logistics-panel-orders" class="logistics-panel" style="display:block;">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
          
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0;">
                <i class="bi bi-boxes text-clay"></i> Active Consignment Pipeline
              </h2>
              <p style="font-size:0.85rem; color:var(--text-muted); margin:3px 0 0;">
                Track artisan package pickups, assign HAATEX delivery riders, and dispatch to recipients
              </p>
            </div>

            <!-- Pipeline Filter Pills -->
            <div style="display:flex; gap:6px; background:#f1f5f9; padding:4px; border-radius:var(--radius-sm); font-size:0.8rem; font-weight:700;">
              <button type="button" class="pipe-filter active" onclick="filterPipeline('all', this)" style="border:none; padding:6px 12px; border-radius:var(--radius-sm); cursor:pointer; background:#fff; color:var(--haat-green-dark); box-shadow:var(--shadow-sm);">
                All (<?= count($allShipments) ?>)
              </button>
              <button type="button" class="pipe-filter" onclick="filterPipeline('pickup_requested', this)" style="border:none; padding:6px 12px; border-radius:var(--radius-sm); cursor:pointer; background:transparent; color:var(--text-muted);">
                Pickups (<?= $pickupRequests ?>)
              </button>
              <button type="button" class="pipe-filter" onclick="filterPipeline('hub_received', this)" style="border:none; padding:6px 12px; border-radius:var(--radius-sm); cursor:pointer; background:transparent; color:var(--text-muted);">
                In Hub (<?= $inHub ?>)
              </button>
              <button type="button" class="pipe-filter" onclick="filterPipeline('out_for_delivery', this)" style="border:none; padding:6px 12px; border-radius:var(--radius-sm); cursor:pointer; background:transparent; color:var(--text-muted);">
                Out for Delivery (<?= $outForDelivery ?>)
              </button>
              <button type="button" class="pipe-filter" onclick="filterPipeline('delivered', this)" style="border:none; padding:6px 12px; border-radius:var(--radius-sm); cursor:pointer; background:transparent; color:var(--text-muted);">
                Delivered (<?= $deliveredCount ?>)
              </button>
            </div>
          </div>

          <?php if (empty($allShipments)): ?>
            <div style="text-align:center; padding:50px 0; color:var(--text-muted);">
              <i class="bi bi-box-seam" style="font-size:3rem; display:block; margin-bottom:10px;"></i>
              <h3>No Shipments in Queue</h3>
              <p>When buyers order handcrafted products, shipments appear here.</p>
            </div>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                <thead>
                  <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.78rem; text-transform:uppercase; background:#faf8f5;">
                    <th style="padding:12px 10px;">Tracking / Order</th>
                    <th style="padding:12px 10px;">Origin (Artisan Store)</th>
                    <th style="padding:12px 10px;">Destination (Recipient)</th>
                    <th style="padding:12px 10px;">Items</th>
                    <th style="padding:12px 10px;">Assigned Rider</th>
                    <th style="padding:12px 10px;">Logistics Status</th>
                    <th style="padding:12px 10px; text-align:right;">Quick Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allShipments as $s): 
                    $logStatus = $s['logistics_status'] ?: 'pending';
                    $badgeBg = '#f1f5f9';
                    $badgeColor = '#475569';
                    $badgeText = ucfirst(str_replace('_', ' ', $logStatus));

                    if ($logStatus === 'pickup_requested') {
                        $badgeBg = '#fef3c7'; $badgeColor = '#92400e'; $badgeText = 'Pickup Requested (Packed)';
                    } elseif ($logStatus === 'hub_received') {
                        $badgeBg = '#e0f2fe'; $badgeColor = '#0369a1'; $badgeText = 'In Sorting Hub';
                    } elseif ($logStatus === 'out_for_delivery') {
                        $badgeBg = '#ffedd5'; $badgeColor = '#c2410c'; $badgeText = 'Out for Delivery';
                    } elseif ($logStatus === 'delivered') {
                        $badgeBg = '#dcfce7'; $badgeColor = '#15803d'; $badgeText = 'Delivered & Done';
                    }
                  ?>
                    <tr class="shipment-row" data-log-status="<?= $logStatus ?>" style="border-bottom:1px solid var(--haat-border);">
                      
                      <!-- Tracking Code & Order # -->
                      <td style="padding:14px 10px; white-space:nowrap;">
                        <div style="font-weight:800; color:var(--haat-clay); font-size:0.92rem;">
                          <?= sanitize($s['tracking_code'] ?: 'HTX-' . $s['id']) ?>
                        </div>
                        <div style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">
                          Order: <strong><?= sanitize($s['order_number']) ?></strong>
                        </div>
                        <div style="font-size:0.72rem; color:var(--text-muted); opacity:0.85;">
                          <?= date('d M, h:i A', strtotime($s['created_at'])) ?>
                        </div>
                      </td>

                      <!-- Origin (Artisan Workshop) -->
                      <td style="padding:14px 10px; font-size:0.85rem;">
                        <strong style="color:var(--haat-green-dark); display:block;"><?= sanitize($s['seller_shop'] ?: 'HAAT Artisan Hub') ?></strong>
                        <span style="font-size:0.75rem; color:var(--text-muted);"><i class="bi bi-geo-alt"></i> <?= sanitize($s['seller_district'] ?: 'Dhaka') ?></span>
                      </td>

                      <!-- Destination (Customer) -->
                      <td style="padding:14px 10px; font-size:0.85rem;">
                        <strong style="color:var(--text-main); display:block;"><?= sanitize($s['shipping_name']) ?></strong>
                        <span style="font-size:0.78rem; color:var(--text-muted); display:block;"><?= sanitize($s['shipping_phone']) ?></span>
                        <span style="font-size:0.75rem; color:var(--text-muted); display:block; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= sanitize($s['shipping_address']) ?>, <?= sanitize($s['district']) ?>">
                          <?= sanitize($s['shipping_address']) ?>, <?= sanitize($s['district']) ?>
                        </span>
                      </td>

                      <!-- Items Summary -->
                      <td style="padding:14px 10px; font-size:0.83rem;">
                        <span class="badge" style="background:#faf8f5; border:1px solid #e5e7eb; color:var(--haat-green-dark); font-weight:700;">
                          <?= $s['item_count'] ?> <?= $s['item_count'] === 1 ? 'Craft' : 'Crafts' ?>
                        </span>
                        <div style="font-size:0.74rem; color:var(--text-muted); margin-top:3px; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= sanitize($s['item_summary']) ?>">
                          <?= sanitize($s['item_summary']) ?>
                        </div>
                      </td>

                      <!-- Assigned Rider -->
                      <td style="padding:14px 10px; font-size:0.85rem; white-space:nowrap;">
                        <?php if (!empty($s['assigned_rider_name'])): ?>
                          <div style="display:flex; align-items:center; gap:6px;">
                            <i class="bi bi-person-badge text-clay" style="font-size:1.05rem;"></i>
                            <div>
                              <strong style="color:var(--haat-green-dark); font-size:0.85rem; display:block;"><?= sanitize($s['assigned_rider_name']) ?></strong>
                              <span style="font-size:0.74rem; color:var(--text-muted);"><?= sanitize($s['assigned_rider_phone'] ?? '') ?></span>
                            </div>
                          </div>
                        <?php else: ?>
                          <span style="color:var(--text-muted); font-size:0.78rem; font-style:italic;">Unassigned</span>
                        <?php endif; ?>
                      </td>

                      <!-- Status Badge -->
                      <td style="padding:14px 10px; white-space:nowrap;">
                        <span class="badge" style="background:<?= $badgeBg ?>; color:<?= $badgeColor ?>; font-weight:700; font-size:0.78rem; padding:4px 10px; border-radius:12px;">
                          <?= $badgeText ?>
                        </span>
                      </td>

                      <!-- Quick Actions Form -->
                      <td style="padding:14px 10px; text-align:right; white-space:nowrap;">
                        <form method="POST" action="<?= BASE_URL ?>logistics/" style="display:inline-flex; align-items:center; gap:6px; margin:0;">
                          <input type="hidden" name="action_update_logistics" value="1">
                          <input type="hidden" name="order_id" value="<?= $s['id'] ?>">

                          <select name="logistics_status" style="padding:5px 8px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.8rem; font-weight:600; outline:none; background:#fff;">
                            <option value="pickup_requested" <?= $logStatus === 'pickup_requested' ? 'selected' : '' ?>>1. Pickup Requested</option>
                            <option value="hub_received" <?= $logStatus === 'hub_received' ? 'selected' : '' ?>>2. Received at Hub</option>
                            <option value="out_for_delivery" <?= $logStatus === 'out_for_delivery' ? 'selected' : '' ?>>3. Out for Delivery</option>
                            <option value="delivered" <?= $logStatus === 'delivered' ? 'selected' : '' ?>>4. Delivered</option>
                          </select>

                          <select name="rider_id" style="padding:5px 8px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.8rem; font-weight:600; outline:none; background:#fff; max-width:145px;">
                            <option value="">-- Assign Rider --</option>
                            <?php foreach ($riders as $r): ?>
                              <option value="<?= $r['id'] ?>" <?= ((int)$s['assigned_rider_id'] === (int)$r['id']) ? 'selected' : '' ?> style="<?= $r['status'] !== 'active' ? 'color:#94a3b8;' : '' ?>">
                                <?= sanitize($r['name']) ?> <?= $r['status'] === 'active' ? '(On Duty)' : '(Off Duty)' ?>
                              </option>
                            <?php endforeach; ?>
                          </select>

                          <button type="submit" class="btn btn-sm btn-clay" style="padding:4px 10px; font-size:0.78rem;" title="Save & Dispatch">
                            Update
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
      </div>

      <!-- ==========================================
           TAB 2: DELIVERY RIDERS FLEET
           ========================================== -->
      <div id="logistics-panel-riders" class="logistics-panel" style="display:none;">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
          
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
            <div>
              <h2 style="font-size:1.35rem; color:var(--haat-green-dark); margin:0;">
                <i class="bi bi-bicycle text-clay"></i> HAATEX Delivery Fleet Roster
              </h2>
              <p style="font-size:0.85rem; color:var(--text-muted); margin:3px 0 0;">
                Manage certified delivery personnel, hub coverage zones, active duties, and live package allocations
              </p>
            </div>
            <button type="button" onclick="openAddRiderModal()" class="btn btn-sm btn-clay" style="display:inline-flex; align-items:center; gap:6px;">
              <i class="bi bi-person-plus-fill"></i> Add New Delivery Rider
            </button>
          </div>

          <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
              <thead>
                <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.8rem; text-transform:uppercase; background:#faf8f5;">
                  <th style="padding:12px 14px;">Rider Name</th>
                  <th style="padding:12px 14px;">Contact Phone</th>
                  <th style="padding:12px 14px;">Hub Coverage Zone</th>
                  <th style="padding:12px 14px;">Vehicle</th>
                  <th style="padding:12px 14px;">Live Deliveries</th>
                  <th style="padding:12px 14px;">Duty Status</th>
                  <th style="padding:12px 14px; text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($riders as $r): 
                  $activePkgs = (int)($r['live_active_deliveries'] ?? 0);
                  $deliveredPkgs = (int)($r['total_delivered_orders'] ?? 0);
                  $isActiveDuty = ($r['status'] === 'active');
                ?>
                  <tr style="border-bottom:1px solid var(--haat-border);">
                    <td style="padding:14px; font-weight:700; color:var(--haat-green-dark);">
                      <i class="bi bi-person-circle text-clay" style="margin-right:6px;"></i> <?= sanitize($r['name']) ?>
                    </td>
                    <td style="padding:14px; font-size:0.88rem; color:var(--text-main);">
                      <i class="bi bi-telephone"></i> <?= sanitize($r['phone']) ?>
                    </td>
                    <td style="padding:14px; font-size:0.85rem; color:var(--text-muted);">
                      <?= sanitize($r['hub_zone']) ?>
                    </td>
                    <td style="padding:14px; font-size:0.85rem;">
                      <span class="badge" style="background:#f1f5f9; color:var(--haat-green-dark); font-weight:600;">
                        <?= sanitize($r['vehicle_type']) ?>
                      </span>
                    </td>
                    <td style="padding:14px;">
                      <?php if ($activePkgs > 0): ?>
                        <span class="badge" style="background:#fef3c7; color:#92400e; font-weight:800; font-size:0.78rem;">
                          <i class="bi bi-box-seam"></i> <?= $activePkgs ?> in transit
                        </span>
                      <?php else: ?>
                        <span style="font-size:0.78rem; color:var(--text-muted); font-weight:600;">
                          0 active (<?= $deliveredPkgs ?> delivered)
                        </span>
                      <?php endif; ?>
                    </td>
                    <td style="padding:14px;">
                      <?php if (!$isActiveDuty): ?>
                        <span class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:700; font-size:0.75rem; padding:4px 8px; border-radius:12px;">
                          ● Off Duty (Inactive)
                        </span>
                      <?php elseif ($activePkgs > 0): ?>
                        <span class="badge" style="background:#dbeafe; color:#1e40af; font-weight:700; font-size:0.75rem; padding:4px 8px; border-radius:12px;">
                          <i class="bi bi-bicycle"></i> On Delivery
                        </span>
                      <?php else: ?>
                        <span class="badge" style="background:#dcfce7; color:#15803d; font-weight:700; font-size:0.75rem; padding:4px 8px; border-radius:12px;">
                          ● Active on Duty
                        </span>
                      <?php endif; ?>
                    </td>
                    <td style="padding:14px; text-align:right; white-space:nowrap;">
                      <div style="display:inline-flex; align-items:center; gap:6px;">
                        <?php if ($isActiveDuty): ?>
                          <form method="POST" action="<?= BASE_URL ?>logistics/#riders" style="display:inline; margin:0;">
                            <input type="hidden" name="action_toggle_rider_duty" value="1">
                            <input type="hidden" name="rider_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="status" value="off_duty">
                            <button type="submit" class="btn btn-sm" style="padding:4px 10px; font-size:0.76rem; background:#fff1f2; color:#be123c; border:1px solid #fecdd3; border-radius:var(--radius-sm); font-weight:600; display:inline-flex; align-items:center; gap:4px;" title="Close Rider Duty (Mark Inactive)">
                              <i class="bi bi-power"></i> Close Duty
                            </button>
                          </form>
                        <?php else: ?>
                          <form method="POST" action="<?= BASE_URL ?>logistics/#riders" style="display:inline; margin:0;">
                            <input type="hidden" name="action_toggle_rider_duty" value="1">
                            <input type="hidden" name="rider_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="btn btn-sm" style="padding:4px 10px; font-size:0.76rem; background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; border-radius:var(--radius-sm); font-weight:600; display:inline-flex; align-items:center; gap:4px;" title="Start Duty (Mark Active)">
                              <i class="bi bi-play-circle-fill"></i> Start Duty
                            </button>
                          </form>
                        <?php endif; ?>

                        <form method="POST" action="<?= BASE_URL ?>logistics/#riders" onsubmit="return confirm('Remove rider <?= addslashes(sanitize($r['name'])) ?> from fleet roster?');" style="display:inline; margin:0;">
                          <input type="hidden" name="action_delete_rider" value="1">
                          <input type="hidden" name="rider_id" value="<?= $r['id'] ?>">
                          <button type="submit" class="btn btn-sm" style="padding:4px 8px; font-size:0.76rem; background:#f8fafc; color:var(--text-muted); border:1px solid var(--haat-border); border-radius:var(--radius-sm);" title="Remove Rider">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>

      <!-- ==========================================
           TAB 3: CUSTOMER & ARTISAN LOGISTICS COMMS DESK
           ========================================== -->
      <div id="logistics-panel-messages" class="logistics-panel" style="display:none;">
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); overflow:hidden; box-shadow:var(--shadow-sm);">
          
          <div style="padding:16px 24px; border-bottom:1px solid var(--haat-border); background:#ffffff; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <div>
              <h2 style="font-size:1.3rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:10px;">
                <i class="bi bi-chat-dots-fill text-clay"></i> HAATEX Delivery Live Support & Dispatch Desk
              </h2>
              <p style="font-size:0.82rem; color:var(--text-muted); margin:3px 0 0;">
                Live delivery coordination with customer buyers & artisan workshop parcel dispatchers
              </p>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
              <span class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:0.75rem; padding:4px 10px;">
                ● Comms Gateway Active
              </span>
            </div>
          </div>

          <!-- Split-Screen Messenger Workspace -->
          <div style="display:grid; grid-template-columns: 330px 1fr; height: 600px; max-height: 600px;">
            
            <!-- Left Column: Channel Switcher & Conversations List -->
            <div style="border-right:1px solid var(--haat-border); background:#fcfbfa; display:flex; flex-direction:column; height: 600px; overflow:hidden;">
              
              <!-- Search & Category Toggle -->
              <div style="padding:12px 14px; border-bottom:1px solid var(--haat-border); background:#ffffff; flex-shrink: 0;">
                <div style="position:relative; margin-bottom:10px;">
                  <i class="bi bi-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.85rem;"></i>
                  <input type="text" id="logistics-search-conv" oninput="filterLogisticsConversations()" placeholder="Search name, workshop or phone..." style="width:100%; padding:7px 12px 7px 34px; border:1px solid var(--haat-border); border-radius:20px; font-size:0.82rem; outline:none; background:#f9fafb;">
                </div>

                <div style="display:flex; gap:6px;">
                  <button type="button" id="log-tab-btn-sellers" onclick="switchLogisticsCommsCategory('sellers')" class="badge" style="background:var(--haat-green); color:#fff; border:none; padding:5px 12px; cursor:pointer; font-weight:700; font-size:0.74rem; border-radius:12px; flex:1; display:flex; align-items:center; justify-content:center; gap:4px;">
                    <i class="bi bi-shop"></i> Artisans <span id="log-sellers-badge" class="badge" style="background:rgba(255,255,255,0.25); color:#fff; font-size:0.65rem; padding:1px 5px; <?= $totalUnreadSellerMsgs > 0 ? '' : 'display:none;' ?>"><?= $totalUnreadSellerMsgs ?></span>
                  </button>
                  <button type="button" id="log-tab-btn-customers" onclick="switchLogisticsCommsCategory('customers')" class="badge" style="background:#f4f4f4; color:var(--text-main); border:1px solid var(--haat-border); padding:5px 12px; cursor:pointer; font-weight:700; font-size:0.74rem; border-radius:12px; flex:1; display:flex; align-items:center; justify-content:center; gap:4px;">
                    <i class="bi bi-people"></i> Buyers <span id="log-cust-badge" class="badge" style="background:var(--haat-clay); color:#fff; font-size:0.65rem; padding:1px 5px; <?= $totalUnreadCustomerMsgs > 0 ? '' : 'display:none;' ?>"><?= $totalUnreadCustomerMsgs ?></span>
                  </button>
                </div>
              </div>

              <!-- List Container: ARTISAN SELLERS -->
              <div id="logistics-seller-list" style="flex:1; overflow-y:auto; display:block;">
                <?php if (empty($sellerComms)): ?>
                  <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
                    <i class="bi bi-shop text-clay" style="font-size:2.2rem; display:block; margin-bottom:8px;"></i>
                    <strong>No artisan inquiries yet</strong>
                    <p style="font-size:0.8rem; margin-top:4px;">When sellers message HAATEX regarding package pickups, threads appear here.</p>
                  </div>
                <?php else: ?>
                  <?php foreach ($sellerComms as $idx => $s): ?>
                    <div class="logistics-seller-item <?= $idx === 0 ? 'active' : '' ?>"
                         data-seller-id="<?= $s['seller_id'] ?>"
                         data-shop-name="<?= sanitize($s['shop_name']) ?>"
                         data-artisan-name="<?= sanitize($s['artisan_name']) ?>"
                         data-seller-phone="<?= sanitize($s['seller_phone'] ?? '') ?>"
                         data-district="<?= sanitize($s['district'] ?? '') ?>"
                         onclick="selectLogisticsSellerConversation(<?= $s['seller_id'] ?>, '<?= addslashes(sanitize($s['shop_name'])) ?>', '<?= addslashes(sanitize($s['seller_phone'] ?? '')) ?>', '<?= addslashes(sanitize($s['artisan_name'])) ?>', '<?= addslashes(sanitize($s['district'] ?? '')) ?>')"
                         style="padding:12px 14px; border-bottom:1px solid #f0ebe4; cursor:pointer; display:flex; gap:10px; align-items:center; background:<?= $idx === 0 ? '#ffffff' : 'transparent' ?>; <?= $idx === 0 ? 'border-left:3px solid var(--haat-clay);' : '' ?>">
                      
                      <div style="width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg, #1b3d22, #2d5a36); color:var(--haat-sand); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.1rem; flex-shrink:0;">
                        <i class="bi bi-shop"></i>
                      </div>

                      <div style="flex:1; min-width:0;">
                        <div style="display:flex; justify-content:space-between; align-items:baseline;">
                          <strong style="font-size:0.88rem; color:var(--haat-green-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;">
                            <?= sanitize($s['shop_name']) ?>
                          </strong>
                          <?php if (!empty($s['last_message_time'])): ?>
                            <span class="log-seller-time" style="font-size:0.68rem; color:var(--text-muted); flex-shrink:0;">
                              <?= date('h:i A', strtotime($s['last_message_time'])) ?>
                            </span>
                          <?php endif; ?>
                        </div>

                        <div style="font-size:0.73rem; color:var(--haat-clay); font-weight:600; display:flex; align-items:center; gap:4px; margin:1px 0;">
                          <i class="bi bi-person"></i>
                          <span><?= sanitize($s['artisan_name']) ?> (<?= sanitize($s['district'] ?: 'Workshop') ?>)</span>
                        </div>

                        <div style="display:flex; align-items:center; justify-content:space-between; gap:6px;">
                          <div class="log-seller-msg" style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;">
                            <?= sanitize($s['last_message'] ?: 'Artisan Workshop Hub') ?>
                          </div>
                          <?php if ($s['unread_count'] > 0): ?>
                            <span class="badge log-seller-unread-pill" style="background:var(--haat-clay); color:#fff; font-size:0.65rem; padding:1px 6px; border-radius:10px;">
                              <?= $s['unread_count'] ?>
                            </span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <!-- List Container: CUSTOMER BUYERS -->
              <div id="logistics-cust-list" style="flex:1; overflow-y:auto; display:none;">
                <?php if (empty($customerComms)): ?>
                  <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
                    <i class="bi bi-chat-left-text text-clay" style="font-size:2.2rem; display:block; margin-bottom:8px;"></i>
                    <strong>No customer inquiries yet</strong>
                    <p style="font-size:0.8rem; margin-top:4px;">When customers reach out to HAATEX Logistics, threads will appear here.</p>
                  </div>
                <?php else: ?>
                  <?php foreach ($customerComms as $idx => $c): ?>
                    <div class="logistics-cust-item"
                         data-user-id="<?= $c['id'] ?>"
                         data-user-name="<?= sanitize($c['name']) ?>"
                         data-user-phone="<?= sanitize($c['phone'] ?? '') ?>"
                         onclick="selectLogisticsCustomerConversation(<?= $c['id'] ?>, '<?= addslashes(sanitize($c['name'])) ?>', '<?= addslashes(sanitize($c['phone'] ?? '')) ?>')"
                         style="padding:12px 14px; border-bottom:1px solid #f0ebe4; cursor:pointer; display:flex; gap:10px; align-items:center; background:transparent;">
                      
                      <div style="width:40px; height:40px; border-radius:50%; background:var(--haat-sand); color:var(--haat-green-dark); display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0;">
                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                      </div>

                      <div style="flex:1; min-width:0;">
                        <div style="display:flex; justify-content:space-between; align-items:baseline;">
                          <strong style="font-size:0.88rem; color:var(--haat-green-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;">
                            <?= sanitize($c['name']) ?>
                          </strong>
                          <?php if (!empty($c['last_message_time'])): ?>
                            <span class="log-cust-time" style="font-size:0.68rem; color:var(--text-muted); flex-shrink:0;">
                              <?= date('h:i A', strtotime($c['last_message_time'])) ?>
                            </span>
                          <?php endif; ?>
                        </div>

                        <div style="display:flex; align-items:center; justify-content:space-between; gap:6px;">
                          <div class="log-cust-msg" style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;">
                            <?= sanitize($c['last_message'] ?: ($c['phone'] ?? 'Buyer Account')) ?>
                          </div>
                          <?php if ($c['unread_count'] > 0): ?>
                            <span class="badge log-cust-unread-pill" style="background:var(--haat-clay); color:#fff; font-size:0.65rem; padding:1px 6px; border-radius:10px;">
                              <?= $c['unread_count'] ?>
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
            <div style="display:flex; flex-direction:column; height: 600px; background:#ffffff; overflow:hidden;">
              
              <!-- Chat Header -->
              <div id="logistics-chat-header" style="padding:14px 20px; border-bottom:1px solid var(--haat-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                  <div style="width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg, #1b3d22, #2d5a36); color:var(--haat-sand); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.15rem;" id="log-chat-avatar">
                    <i class="bi bi-shop"></i>
                  </div>
                  <div>
                    <strong id="log-chat-title" style="color:var(--haat-green-dark); font-size:0.95rem; display:block;">
                      <?= !empty($sellerComms) ? sanitize($sellerComms[0]['shop_name']) : (!empty($customerComms) ? sanitize($customerComms[0]['name']) : 'Select Thread') ?>
                    </strong>
                    <span id="log-chat-subtitle" style="font-size:0.75rem; color:var(--text-muted);">
                      <?= !empty($sellerComms) ? sanitize($sellerComms[0]['artisan_name'] . ' (' . ($sellerComms[0]['district'] ?: 'Workshop') . ')') : (!empty($customerComms) ? sanitize($customerComms[0]['phone'] ?? 'Buyer Account') : 'Comms Desk') ?>
                    </span>
                  </div>
                </div>

                <!-- Quick Response Shortcuts Container -->
                <div id="log-chat-shortcuts" style="display:flex; gap:6px; overflow-x:auto;">
                  <!-- Populated dynamically based on category -->
                </div>
              </div>

              <!-- Messages Stream -->
              <div id="logistics-chat-stream" style="flex:1; padding:20px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; background:#faf8f5;">
                <div style="text-align:center; padding:30px 0; color:var(--text-muted); font-size:0.85rem;">
                  Loading chat history...
                </div>
              </div>

              <!-- Input Form -->
              <form id="logistics-chat-form" onsubmit="sendLogisticsMsg(event)" style="padding:14px 20px; border-top:1px solid var(--haat-border); display:flex; gap:10px; background:#ffffff;">
                <input type="hidden" id="log-chat-target-type" value="seller">
                <input type="hidden" id="log-chat-target-id" value="<?= !empty($sellerComms) ? (int)$sellerComms[0]['seller_id'] : 0 ?>">
                <input type="text" id="log-chat-input" placeholder="Type message or delivery update..." required style="flex:1; padding:10px 16px; border:1px solid var(--haat-border); border-radius:24px; font-size:0.88rem; outline:none;">
                <button type="submit" id="log-chat-send-btn" class="btn btn-clay" style="border-radius:24px; padding:0 20px; display:inline-flex; align-items:center; gap:6px;">
                  <i class="bi bi-send-fill"></i> Send
                </button>
              </form>

            </div>

          </div>

        </div>
      </div>

    </main>

  </div>

</div>

<!-- Modal: Add New Rider -->
<div id="modal-add-rider" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:20px;">
  <div style="background:#fff; border-radius:var(--radius-lg); max-width:480px; width:100%; padding:28px; box-shadow:var(--shadow-lg); position:relative;">
    <button type="button" onclick="closeAddRiderModal()" style="position:absolute; top:16px; right:16px; border:none; background:none; font-size:1.4rem; color:var(--text-muted); cursor:pointer;">&times;</button>
    
    <h3 style="font-size:1.3rem; color:var(--haat-green-dark); margin:0 0 4px;">
      <i class="bi bi-person-plus-fill text-clay"></i> Register HAATEX Delivery Rider
    </h3>
    <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:20px;">Add certified delivery courier personnel to the active fleet</p>

    <form method="POST" action="<?= BASE_URL ?>logistics/">
      <input type="hidden" name="action_add_rider" value="1">
      
      <div style="margin-bottom:14px;">
        <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Full Name *</label>
        <input type="text" name="name" required placeholder="e.g. Mahfuzur Rahman (Express Courier)" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none;">
      </div>

      <div style="margin-bottom:14px;">
        <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Contact Phone Number *</label>
        <input type="tel" name="phone" required placeholder="e.g. 01712345678" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none;">
      </div>

      <div style="margin-bottom:14px;">
        <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Hub Coverage Zone *</label>
        <input type="text" name="hub_zone" required value="Banani & Gulshan Hub (Dhaka North)" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none;">
      </div>

      <div style="margin-bottom:20px;">
        <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Vehicle Type</label>
        <select name="vehicle_type" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; outline:none; background:#fff;">
          <option value="Motorbike">Motorbike (Express City Courier)</option>
          <option value="Covered Van">Covered Van (Artisan Bulky Crafts)</option>
          <option value="Bicycle">Bicycle (Eco Short-Distance)</option>
        </select>
      </div>

      <button type="submit" class="btn btn-clay btn-lg" style="width:100%;">
        Save & Enlist Rider to Fleet
      </button>
    </form>
  </div>
</div>

<style>
  .logistics-tab-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    color: var(--text-main);
    text-decoration: none;
    transition: var(--transition);
  }
  .logistics-tab-link:hover {
    background: #faf8f5;
    color: var(--haat-clay);
  }
  .logistics-tab-link.active {
    background: var(--haat-green);
    color: #ffffff !important;
  }
  .logistics-tab-link.active .badge {
    background: #ffffff !important;
    color: var(--haat-green-dark) !important;
  }
</style>

<script>
  let activeLogisticsType = '<?= !empty($sellerComms) ? 'seller' : 'customer' ?>'; // 'seller' | 'customer'
  let activeLogisticsSellerId = <?= !empty($sellerComms) ? (int)$sellerComms[0]['seller_id'] : 0 ?>;
  let activeLogisticsUserId = <?= !empty($customerComms) ? (int)$customerComms[0]['id'] : 0 ?>;
  let lastLogisticsMsgCount = -1;
  let logisticsPollTimer = null;

  function switchLogisticsTab(tabId) {
    document.querySelectorAll('.logistics-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.logistics-tab-link').forEach(l => l.classList.remove('active'));

    const panel = document.getElementById('logistics-panel-' + tabId);
    const link = document.querySelector(`.logistics-tab-link[data-tab="${tabId}"]`);

    if (panel) panel.style.display = 'block';
    if (link) link.classList.add('active');
    window.location.hash = tabId;

    if (tabId === 'messages') {
      renderLogisticsShortcuts();
      loadLogisticsMessages();
      pollLogisticsConversations();
    }
  }

  function filterPipeline(statusKey, btn) {
    if (btn) {
      document.querySelectorAll('.pipe-filter').forEach(b => {
        b.style.background = 'transparent';
        b.style.color = 'var(--text-muted)';
        b.style.boxShadow = 'none';
      });
      btn.style.background = '#fff';
      btn.style.color = 'var(--haat-green-dark)';
      btn.style.boxShadow = 'var(--shadow-sm)';
    }

    document.querySelectorAll('.shipment-row').forEach(row => {
      if (statusKey === 'all' || row.getAttribute('data-log-status') === statusKey) {
        row.style.display = 'table-row';
      } else {
        row.style.display = 'none';
      }
    });
  }

  function openAddRiderModal() {
    const modal = document.getElementById('modal-add-rider');
    if (modal) modal.style.display = 'flex';
  }

  function closeAddRiderModal() {
    const modal = document.getElementById('modal-add-rider');
    if (modal) modal.style.display = 'none';
  }

  // Comms Category Switcher (Artisans vs Customer Buyers)
  function switchLogisticsCommsCategory(cat) {
    activeLogisticsType = cat;
    const btnSellers = document.getElementById('log-tab-btn-sellers');
    const btnCust = document.getElementById('log-tab-btn-customers');
    const sellerList = document.getElementById('logistics-seller-list');
    const custList = document.getElementById('logistics-cust-list');

    if (cat === 'sellers') {
      if (btnSellers) {
        btnSellers.style.background = 'var(--haat-green)';
        btnSellers.style.color = '#fff';
        btnSellers.style.border = 'none';
      }
      if (btnCust) {
        btnCust.style.background = '#f4f4f4';
        btnCust.style.color = 'var(--text-main)';
        btnCust.style.border = '1px solid var(--haat-border)';
      }
      if (sellerList) sellerList.style.display = 'block';
      if (custList) custList.style.display = 'none';

      const firstSeller = document.querySelector('.logistics-seller-item');
      if (firstSeller && !activeLogisticsSellerId) {
        firstSeller.click();
      } else {
        renderLogisticsShortcuts();
        loadLogisticsMessages();
      }
    } else {
      if (btnCust) {
        btnCust.style.background = 'var(--haat-green)';
        btnCust.style.color = '#fff';
        btnCust.style.border = 'none';
      }
      if (btnSellers) {
        btnSellers.style.background = '#f4f4f4';
        btnSellers.style.color = 'var(--text-main)';
        btnSellers.style.border = '1px solid var(--haat-border)';
      }
      if (custList) custList.style.display = 'block';
      if (sellerList) sellerList.style.display = 'none';

      const firstCust = document.querySelector('.logistics-cust-item');
      if (firstCust && !activeLogisticsUserId) {
        firstCust.click();
      } else {
        renderLogisticsShortcuts();
        loadLogisticsMessages();
      }
    }
  }

  function renderLogisticsShortcuts() {
    const container = document.getElementById('log-chat-shortcuts');
    if (!container) return;

    if (activeLogisticsType === 'seller') {
      container.innerHTML = `
        <button type="button" class="btn btn-sm" onclick="setLogisticsQuickMsg('🏍️ HAATEX delivery rider is dispatched to your workshop for parcel pickup.')" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:0.72rem; padding:3px 8px; white-space:nowrap;">
          🏍️ Rider Dispatched for Pickup
        </button>
        <button type="button" class="btn btn-sm" onclick="setLogisticsQuickMsg('📦 Consignment safely received and scanned at our central sorting hub.')" style="background:#f8fafc; border:1px solid var(--haat-border); font-size:0.72rem; padding:3px 8px; white-space:nowrap;">
          📦 Hub Received
        </button>
        <button type="button" class="btn btn-sm" onclick="setLogisticsQuickMsg('📍 Please reconfirm your workshop pickup address and contact phone.')" style="background:#f8fafc; border:1px solid var(--haat-border); font-size:0.72rem; padding:3px 8px; white-space:nowrap;">
          📍 Confirm Workshop Address
        </button>
      `;
    } else {
      container.innerHTML = `
        <button type="button" class="btn btn-sm" onclick="setLogisticsQuickMsg('Hello! Your HAATEX rider is on the way to your delivery address.')" style="background:#f8fafc; border:1px solid var(--haat-border); font-size:0.72rem; padding:3px 8px; white-space:nowrap;">
          🚚 Rider on the way
        </button>
        <button type="button" class="btn btn-sm" onclick="setLogisticsQuickMsg('Your package has arrived safely at the local HAATEX sorting hub.')" style="background:#f8fafc; border:1px solid var(--haat-border); font-size:0.72rem; padding:3px 8px; white-space:nowrap;">
          📦 At local hub
        </button>
        <button type="button" class="btn btn-sm" onclick="setLogisticsQuickMsg('Your artisanal order is out for doorstep delivery today.')" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:0.72rem; padding:3px 8px; white-space:nowrap;">
          ⏱️ Out for Doorstep Delivery
        </button>
      `;
    }
  }

  function selectLogisticsSellerConversation(sellerId, shopName, phone, artisanName, district) {
    activeLogisticsType = 'seller';
    activeLogisticsSellerId = sellerId;
    lastLogisticsMsgCount = -1;

    document.getElementById('log-chat-target-type').value = 'seller';
    document.getElementById('log-chat-target-id').value = sellerId;
    document.getElementById('log-chat-title').innerText = shopName;
    document.getElementById('log-chat-subtitle').innerText = `${artisanName || 'Artisan Workshop'} • ${district || 'Workshop'} • ${phone || ''}`;
    document.getElementById('log-chat-avatar').innerHTML = '<i class="bi bi-shop"></i>';
    document.getElementById('log-chat-input').placeholder = `Type message or pickup coordination to ${shopName}...`;

    document.querySelectorAll('.logistics-seller-item').forEach(el => {
      const match = parseInt(el.getAttribute('data-seller-id')) === sellerId;
      el.style.background = match ? '#ffffff' : 'transparent';
      el.style.borderLeft = match ? '3px solid var(--haat-clay)' : 'none';
      if (match) {
        const unread = el.querySelector('.log-seller-unread-pill');
        if (unread) unread.style.display = 'none';
      }
    });

    renderLogisticsShortcuts();
    loadLogisticsMessages();
  }

  function selectLogisticsCustomerConversation(userId, name, phone) {
    activeLogisticsType = 'customer';
    activeLogisticsUserId = userId;
    lastLogisticsMsgCount = -1;

    document.getElementById('log-chat-target-type').value = 'customer';
    document.getElementById('log-chat-target-id').value = userId;
    document.getElementById('log-chat-title').innerText = name;
    document.getElementById('log-chat-subtitle').innerText = phone || 'Buyer Account';
    document.getElementById('log-chat-avatar').innerText = name.charAt(0).toUpperCase();
    document.getElementById('log-chat-input').placeholder = `Type message or delivery update to ${name}...`;

    document.querySelectorAll('.logistics-cust-item').forEach(el => {
      const match = parseInt(el.getAttribute('data-user-id')) === userId;
      el.style.background = match ? '#ffffff' : 'transparent';
      el.style.borderLeft = match ? '3px solid var(--haat-clay)' : 'none';
      if (match) {
        const unread = el.querySelector('.log-cust-unread-pill');
        if (unread) unread.style.display = 'none';
      }
    });

    renderLogisticsShortcuts();
    loadLogisticsMessages();
  }

  function setLogisticsQuickMsg(text) {
    const input = document.getElementById('log-chat-input');
    if (input) {
      input.value = text;
      input.focus();
    }
  }

  function loadLogisticsMessages(isPolling = false) {
    const stream = document.getElementById('logistics-chat-stream');
    let url = '';

    if (activeLogisticsType === 'seller') {
      if (!activeLogisticsSellerId) return;
      url = `<?= BASE_URL ?>api/messages.php?action=get_logistics_chat&seller_id=${activeLogisticsSellerId}`;
    } else {
      if (!activeLogisticsUserId) return;
      url = `<?= BASE_URL ?>api/messages.php?action=get_logistics_chat&customer_id=${activeLogisticsUserId}`;
    }

    fetch(url)
      .then(res => res.json())
      .then(data => {
        if (!data.success) {
          if (!isPolling) {
            stream.innerHTML = `<div style="text-align:center; padding:20px; color:var(--text-muted);">Start conversation regarding dispatch or delivery...</div>`;
          }
          return;
        }

        if (isPolling && data.messages.length === lastLogisticsMsgCount) {
          return;
        }
        lastLogisticsMsgCount = data.messages.length;

        if (data.messages.length === 0) {
          const recipientLabel = activeLogisticsType === 'seller' ? (data.seller ? escapeHtml(data.seller.shop_name) : 'Artisan Workshop') : 'Customer';
          stream.innerHTML = `<div style="text-align:center; padding:40px 20px; color:var(--text-muted);">
            <i class="bi ${activeLogisticsType === 'seller' ? 'bi-shop' : 'bi-chat-left-text'} text-clay" style="font-size:2.4rem; display:block; margin-bottom:8px;"></i>
            <strong>No messages exchanged yet with ${recipientLabel}</strong>
            <p style="font-size:0.8rem; margin-top:4px;">Send a dispatch or courier notification below.</p>
          </div>`;
          return;
        }

        let html = '';
        data.messages.forEach(m => {
          const isMe = m.is_me;
          html += `
            <div style="display:flex; justify-content:${isMe ? 'flex-end' : 'flex-start'};">
              <div style="max-width:72%; background:${isMe ? 'var(--haat-green-dark)' : '#ffffff'}; color:${isMe ? '#fff' : 'var(--text-main)'}; padding:10px 14px; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,0.06); font-size:0.88rem; border:${isMe ? 'none' : '1px solid var(--haat-border)'};">
                <div style="font-size:0.72rem; font-weight:700; margin-bottom:2px; opacity:0.85; color:${isMe ? '#bbf7d0' : 'var(--haat-clay)'};">
                  ${isMe ? 'HAATEX Logistics Desk' : escapeHtml(m.sender_name || 'Sender')}
                </div>
                <div>${escapeHtml(m.message)}</div>
                <div style="font-size:0.68rem; margin-top:4px; text-align:right; opacity:0.8; color:${isMe ? 'rgba(255,255,255,0.8)' : 'var(--text-muted)'};">${m.time} • ${m.date || ''}</div>
              </div>
            </div>
          `;
        });
        stream.innerHTML = html;
        stream.scrollTop = stream.scrollHeight;
      })
      .catch(console.error);
  }

  function sendLogisticsMsg(e) {
    e.preventDefault();
    const input = document.getElementById('log-chat-input');
    const msg = input.value.trim();
    if (!msg) return;

    const sendBtn = document.getElementById('log-chat-send-btn');
    if (sendBtn) sendBtn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'send_logistics_chat');
    fd.append('message', msg);

    if (activeLogisticsType === 'seller') {
      fd.append('seller_id', activeLogisticsSellerId);
    } else {
      fd.append('customer_id', activeLogisticsUserId);
    }

    input.value = '';

    fetch('<?= BASE_URL ?>api/messages.php', {
      method: 'POST',
      body: fd
    }).then(res => res.json()).then(data => {
      if (sendBtn) sendBtn.disabled = false;
      if (data.success) {
        lastLogisticsMsgCount = -1;
        loadLogisticsMessages();
        pollLogisticsConversations();
      } else {
        alert(data.error || 'Failed to send message');
      }
    }).catch(err => {
      if (sendBtn) sendBtn.disabled = false;
      console.error(err);
    });
  }

  function filterLogisticsConversations() {
    const q = (document.getElementById('logistics-search-conv').value || '').toLowerCase();
    
    if (activeLogisticsType === 'seller') {
      document.querySelectorAll('.logistics-seller-item').forEach(item => {
        const shop = (item.getAttribute('data-shop-name') || '').toLowerCase();
        const artisan = (item.getAttribute('data-artisan-name') || '').toLowerCase();
        const phone = (item.getAttribute('data-seller-phone') || '').toLowerCase();
        const district = (item.getAttribute('data-district') || '').toLowerCase();
        if (shop.includes(q) || artisan.includes(q) || phone.includes(q) || district.includes(q)) {
          item.style.display = 'flex';
        } else {
          item.style.display = 'none';
        }
      });
    } else {
      document.querySelectorAll('.logistics-cust-item').forEach(item => {
        const name = (item.getAttribute('data-user-name') || '').toLowerCase();
        const phone = (item.getAttribute('data-user-phone') || '').toLowerCase();
        if (name.includes(q) || phone.includes(q)) {
          item.style.display = 'flex';
        } else {
          item.style.display = 'none';
        }
      });
    }
  }

  function pollLogisticsConversations() {
    fetch('<?= BASE_URL ?>api/messages.php?action=logistics_conversations')
      .then(res => res.json())
      .then(data => {
        if (!data.success) return;

        let totalSellerUnread = 0;
        let totalCustUnread = 0;

        // 1. Update Sellers List
        if (Array.isArray(data.sellers)) {
          data.sellers.forEach(s => {
            totalSellerUnread += (s.unread_count || 0);
            const item = document.querySelector(`.logistics-seller-item[data-seller-id="${s.id}"]`);
            if (item) {
              const preview = item.querySelector('.log-seller-msg');
              if (preview && s.last_message) preview.innerText = s.last_message;
              const time = item.querySelector('.log-seller-time');
              if (time && s.last_message_time) time.innerText = s.last_message_time;

              let unreadPill = item.querySelector('.log-seller-unread-pill');
              if (activeLogisticsType === 'seller' && activeLogisticsSellerId === s.id) {
                if (unreadPill) unreadPill.style.display = 'none';
              } else if (s.unread_count > 0) {
                if (unreadPill) {
                  unreadPill.innerText = s.unread_count;
                  unreadPill.style.display = 'inline-block';
                }
              } else if (unreadPill) {
                unreadPill.style.display = 'none';
              }
            }
          });
        }

        // 2. Update Customers List
        if (Array.isArray(data.customers)) {
          data.customers.forEach(c => {
            totalCustUnread += (c.unread_count || 0);
            const item = document.querySelector(`.logistics-cust-item[data-user-id="${c.id}"]`);
            if (item) {
              const preview = item.querySelector('.log-cust-msg');
              if (preview && c.last_message) preview.innerText = c.last_message;
              const time = item.querySelector('.log-cust-time');
              if (time && c.last_message_time) time.innerText = c.last_message_time;

              let unreadPill = item.querySelector('.log-cust-unread-pill');
              if (activeLogisticsType === 'customer' && activeLogisticsUserId === c.id) {
                if (unreadPill) unreadPill.style.display = 'none';
              } else if (c.unread_count > 0) {
                if (unreadPill) {
                  unreadPill.innerText = c.unread_count;
                  unreadPill.style.display = 'inline-block';
                }
              } else if (unreadPill) {
                unreadPill.style.display = 'none';
              }
            }
          });
        }

        // 3. Update category tab badges & sidebar badge
        const sBadge = document.getElementById('log-sellers-badge');
        if (sBadge) {
          sBadge.innerText = totalSellerUnread;
          sBadge.style.display = totalSellerUnread > 0 ? 'inline-block' : 'none';
        }
        const cBadge = document.getElementById('log-cust-badge');
        if (cBadge) {
          cBadge.innerText = totalCustUnread;
          cBadge.style.display = totalCustUnread > 0 ? 'inline-block' : 'none';
        }
        const navBadge = document.getElementById('logistics-nav-msg-badge');
        if (navBadge) {
          const totalUnread = totalSellerUnread + totalCustUnread;
          navBadge.innerText = totalUnread;
          navBadge.style.display = totalUnread > 0 ? 'inline-block' : 'none';
        }

        // 4. If messages panel is active, refresh active chat stream
        const messagesPanel = document.getElementById('logistics-panel-messages');
        if (messagesPanel && messagesPanel.style.display !== 'none') {
          loadLogisticsMessages(true);
        }
      })
      .catch(console.error);
  }

  function startLogisticsPolling() {
    if (logisticsPollTimer) clearInterval(logisticsPollTimer);
    logisticsPollTimer = setInterval(() => {
      const messagesPanel = document.getElementById('logistics-panel-messages');
      if (messagesPanel && messagesPanel.style.display !== 'none') {
        loadLogisticsMessages(true);
        pollLogisticsConversations();
      }
    }, 3000);
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
  }

  window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#', '') || 'orders';
    switchLogisticsTab(hash);
    startLogisticsPolling();
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
