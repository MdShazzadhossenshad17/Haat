<?php
/**
 * Daraz-Style Authentic Live Order Tracking Page
 * HAAT Multi-Vendor Marketplace & HAATEX Logistics
 */
require_once __DIR__ . '/includes/functions.php';

$orderNumber = trim($_GET['order'] ?? '');
$phone = trim($_GET['phone'] ?? '');

// If user submitted an order to track, they must log in to their customer account
if (!empty($orderNumber) && !isLoggedIn()) {
    setFlash('warning', 'Please sign in to your customer account to track order ' . htmlspecialchars($orderNumber) . '.');
    $redirectUrl = 'track-order.php?order=' . urlencode($orderNumber) . (!empty($phone) ? '&phone=' . urlencode($phone) : '');
    header('Location: ' . BASE_URL . 'login.php?redirect=' . urlencode($redirectUrl));
    exit;
}

$pageTitle = 'Track Order — HAATEX Live Tracking';
require_once __DIR__ . '/includes/header.php';

$order = null;
$items = [];
$trackingEvents = [];

if (!empty($orderNumber)) {
    $where = "`order_number` = ?";
    $params = [$orderNumber];
    if (!empty($phone)) {
        $where .= " AND `shipping_phone` LIKE ?";
        $params[] = '%' . $phone;
    }
    $stmt = $db->prepare("SELECT * FROM `orders` WHERE {$where} LIMIT 1");
    $stmt->execute($params);
    $order = $stmt->fetch();

    if ($order) {
        $itStmt = $db->prepare("SELECT oi.*, s.shop_name, s.district as seller_district, p.featured_image 
            FROM `order_items` oi 
            JOIN `sellers` s ON oi.seller_id = s.id 
            LEFT JOIN `products` p ON oi.product_id = p.id 
            WHERE oi.order_id = ?");
        $itStmt->execute([$order['id']]);
        $items = $itStmt->fetchAll();

        // Fetch tracking events
        $evStmt = $db->prepare("SELECT * FROM `order_tracking_events` WHERE `order_id` = ? ORDER BY id ASC");
        $evStmt->execute([$order['id']]);
        $trackingEvents = $evStmt->fetchAll();
    }
}

// Compute HAATEX 5-Stage Daraz-Style Tracking Milestones
$orderStatus = $order['order_status'] ?? 'pending';
$logStatus = $order['logistics_status'] ?? 'pending';
$courierPartner = $order['courier_partner'] ?? 'HAATEX (HAAT Express Logistics)';
$trackingCode = $order['tracking_code'] ?? 'HTX-884920';
$estDelivery = $order['estimated_delivery'] ?? '25-28 Sep 2026';
$assignedRiderName = $order['assigned_rider_name'] ?? '';
$assignedRiderPhone = $order['assigned_rider_phone'] ?? '';

// Milestone states: 0: upcoming, 1: active/in-progress, 2: completed
$stepPlaced = 2; // Always completed

// Step 2: Packed by Seller
$stepPacked = 0;
if ($orderStatus === 'processing') {
    $stepPacked = 1;
} elseif ($logStatus === 'pickup_requested' || $logStatus === 'hub_received' || $logStatus === 'out_for_delivery' || $logStatus === 'delivered' || $orderStatus === 'shipped' || $orderStatus === 'delivered') {
    $stepPacked = 2;
}

// Step 3: Shipped / In Hub
$stepHub = 0;
if ($logStatus === 'pickup_requested') {
    $stepHub = 1;
} elseif ($logStatus === 'hub_received' || ($orderStatus === 'shipped' && $logStatus !== 'out_for_delivery' && $logStatus !== 'delivered')) {
    $stepHub = 1;
} elseif ($logStatus === 'out_for_delivery' || $logStatus === 'delivered' || $orderStatus === 'delivered') {
    $stepHub = 2;
}

// Step 4: Out for Delivery
$stepOut = 0;
if ($logStatus === 'out_for_delivery') {
    $stepOut = 1;
} elseif ($logStatus === 'delivered' || $orderStatus === 'delivered') {
    $stepOut = 2;
}

// Step 5: Delivered
$stepDelivered = ($orderStatus === 'delivered' || $logStatus === 'delivered') ? 2 : 0;

// Find event timestamps
$timePlaced = !empty($order) ? date('d M Y, h:i A', strtotime($order['created_at'])) : '';
$timePacked = '';
$timeHub = '';
$timeOut = '';
$timeDelivered = '';
$transitNotes = [];

foreach ($trackingEvents as $ev) {
    if (($ev['status_key'] === 'packed' || $ev['status_key'] === 'processing') && empty($timePacked)) {
        $timePacked = date('d M Y, h:i A', strtotime($ev['created_at']));
    }
    if (($ev['status_key'] === 'shipped' || $ev['status_key'] === 'hub_received') && empty($timeHub)) {
        $timeHub = date('d M Y, h:i A', strtotime($ev['created_at']));
    }
    if ($ev['status_key'] === 'out_for_delivery' && empty($timeOut)) {
        $timeOut = date('d M Y, h:i A', strtotime($ev['created_at']));
    }
    if ($ev['status_key'] === 'delivered') {
        $timeDelivered = date('d M Y, h:i A', strtotime($ev['created_at']));
    }

    $transitNotes[] = [
        'id' => $ev['id'],
        'time' => date('h:i A', strtotime($ev['created_at'])),
        'date' => date('d M Y', strtotime($ev['created_at'])),
        'title' => $ev['title'],
        'actor' => $ev['actor'],
        'location' => $ev['location'],
        'note' => $ev['note'],
        'status_key' => $ev['status_key']
    ];
}

$progressPercent = '0%';
if ($stepDelivered === 2) $progressPercent = '100%';
elseif ($stepOut === 1 || $stepOut === 2) $progressPercent = '75%';
elseif ($stepHub === 1 || $stepHub === 2) $progressPercent = '50%';
elseif ($stepPacked === 1 || $stepPacked === 2) $progressPercent = '25%';
else $progressPercent = '0%';
?>

<style>
  /* ==========================================================
     AUTHENTIC DARAZ-STYLE TRACKING VISUALIZATION STYLES
     ========================================================== */
  .daraz-tracking-wrapper {
    max-width: 920px;
    margin: 0 auto;
  }

  /* Status Hero Card */
  .daraz-status-banner {
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
  }
  .status-banner-delivered {
    background: linear-gradient(135deg, #1b3d22 0%, #2d5a36 100%);
    color: #ffffff;
  }
  .status-banner-out {
    background: linear-gradient(135deg, #c2612d 0%, #df7a44 100%);
    color: #ffffff;
  }
  .status-banner-transit {
    background: linear-gradient(135deg, #0f4c81 0%, #2575fc 100%);
    color: #ffffff;
  }
  .status-banner-packing {
    background: linear-gradient(135deg, #854d0e 0%, #b45309 100%);
    color: #ffffff;
  }
  .status-banner-pending {
    background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
    color: #ffffff;
  }

  /* Main Tracking Card */
  .daraz-card {
    background: #ffffff;
    border: 1px solid var(--haat-border);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    padding: 26px 28px;
    margin-bottom: 24px;
  }

  /* Daraz Horizontal Stepper */
  .daraz-stepper-container {
    position: relative;
    padding: 24px 10px 10px;
    margin-bottom: 36px;
  }
  .daraz-stepper-track-bg {
    position: absolute;
    top: 44px;
    left: 40px;
    right: 40px;
    height: 4px;
    background: #e2e8f0;
    z-index: 1;
    border-radius: 2px;
  }
  .daraz-stepper-track-fill {
    position: absolute;
    top: 44px;
    left: 40px;
    height: 4px;
    background: linear-gradient(90deg, #1b3d22 0%, #c2612d 100%);
    z-index: 2;
    border-radius: 2px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .daraz-stepper-steps {
    position: relative;
    display: flex;
    justify-content: space-between;
    z-index: 3;
  }
  .daraz-step-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    width: 120px;
  }
  .daraz-step-bubble {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: #ffffff;
    border: 3px solid #cbd5e1;
    color: #94a3b8;
    margin-bottom: 10px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
  }
  .daraz-step-node.is-done .daraz-step-bubble {
    background: #1b3d22;
    border-color: #1b3d22;
    color: #ffffff;
    box-shadow: 0 3px 10px rgba(27, 61, 34, 0.3);
  }
  .daraz-step-node.is-active .daraz-step-bubble {
    background: #c2612d;
    border-color: #c2612d;
    color: #ffffff;
    box-shadow: 0 0 0 5px rgba(194, 97, 45, 0.25);
    animation: activeBubblePulse 2s infinite;
  }
  @keyframes activeBubblePulse {
    0% { box-shadow: 0 0 0 0 rgba(194, 97, 45, 0.45); }
    70% { box-shadow: 0 0 0 10px rgba(194, 97, 45, 0); }
    100% { box-shadow: 0 0 0 0 rgba(194, 97, 45, 0); }
  }
  .daraz-step-label {
    font-size: 0.85rem;
    font-weight: 700;
    color: #64748b;
    line-height: 1.3;
    margin-bottom: 2px;
  }
  .daraz-step-node.is-done .daraz-step-label {
    color: var(--haat-green-dark);
  }
  .daraz-step-node.is-active .daraz-step-label {
    color: var(--haat-clay);
  }
  .daraz-step-time {
    font-size: 0.72rem;
    color: #94a3b8;
  }

  /* Courier / Rider Info Card */
  .daraz-courier-strip {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 28px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    align-items: center;
  }

  /* Daraz Vertical Timeline Activity Log */
  .daraz-vertical-timeline {
    position: relative;
    padding-left: 170px;
    margin: 20px 0 10px;
  }
  .daraz-vt-line {
    position: absolute;
    top: 10px;
    bottom: 20px;
    left: 155px;
    width: 2px;
    background: #e2e8f0;
  }
  .daraz-vt-item {
    position: relative;
    padding-bottom: 26px;
  }
  .daraz-vt-item:last-child {
    padding-bottom: 0;
  }
  .daraz-vt-timestamp {
    position: absolute;
    left: -170px;
    top: -2px;
    width: 140px;
    text-align: right;
  }
  .daraz-vt-time {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--haat-green-dark);
  }
  .daraz-vt-date {
    font-size: 0.75rem;
    color: var(--text-muted);
  }
  .daraz-vt-dot {
    position: absolute;
    left: -20px;
    top: 2px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #cbd5e1;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 2px #cbd5e1;
  }
  .daraz-vt-item.is-latest .daraz-vt-dot {
    background: var(--haat-clay);
    box-shadow: 0 0 0 3px rgba(194,97,45,0.3);
    animation: vtDotPulse 2s infinite;
  }
  @keyframes vtDotPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.3); }
    100% { transform: scale(1); }
  }
  .daraz-vt-item.is-completed .daraz-vt-dot {
    background: var(--haat-green);
    box-shadow: 0 0 0 2px var(--haat-green);
  }
  .daraz-vt-content {
    background: #fafaf9;
    border: 1px solid #f0eeeb;
    border-radius: 8px;
    padding: 12px 16px;
    transition: all 0.2s ease;
  }
  .daraz-vt-item.is-latest .daraz-vt-content {
    background: #ffffff;
    border: 1px solid rgba(194,97,45,0.3);
    box-shadow: 0 2px 8px rgba(194,97,45,0.08);
  }
  .daraz-vt-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--haat-green-dark);
    margin-bottom: 2px;
  }
  .daraz-vt-item.is-latest .daraz-vt-title {
    color: var(--haat-clay);
  }
  .daraz-vt-desc {
    font-size: 0.82rem;
    color: var(--text-main);
    line-height: 1.4;
    margin-bottom: 4px;
  }
  .daraz-vt-location {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  @media (max-width: 768px) {
    .daraz-stepper-steps {
      flex-wrap: wrap;
      gap: 16px;
    }
    .daraz-stepper-track-bg, .daraz-stepper-track-fill {
      display: none;
    }
    .daraz-step-node {
      width: 48%;
      flex-direction: row;
      text-align: left;
      gap: 10px;
    }
    .daraz-vertical-timeline {
      padding-left: 36px;
    }
    .daraz-vt-line {
      left: 10px;
    }
    .daraz-vt-timestamp {
      position: static;
      text-align: left;
      margin-bottom: 6px;
      width: auto;
    }
    .daraz-vt-dot {
      left: -32px;
    }
  }
</style>

<div class="container" style="padding: 32px 20px 80px;">
  
  <div class="daraz-tracking-wrapper">
    
    <!-- Page Header Title -->
    <div style="text-align:center; margin-bottom:24px;">
      <h1 style="font-size:1.85rem; color:var(--haat-green-dark); margin-bottom:4px; display:flex; align-items:center; justify-content:center; gap:10px;">
        <i class="bi bi-truck text-clay"></i> Live Order & Logistics Tracking
      </h1>
      <p style="color:var(--text-muted); font-size:0.88rem; margin:0 auto; max-width:520px;">
        Real-time doorstep fulfillment powered by HAATEX Express Logistics.
      </p>
    </div>

    <!-- Look-Up Search Form -->
    <div style="background:#fff; border:1px solid var(--haat-border); border-radius:12px; padding:18px 24px; box-shadow:var(--shadow-sm); margin-bottom:24px;">
      <form method="GET" action="<?= BASE_URL ?>track-order.php" style="display:grid; grid-template-columns: 1fr 1fr auto; gap:14px; align-items:flex-end;">
        <div>
          <label style="font-weight:700; font-size:0.82rem; display:block; margin-bottom:4px; color:var(--text-main); text-transform:uppercase; letter-spacing:0.5px;">Order Number</label>
          <input type="text" name="order" required placeholder="e.g. HAAT-2026-90412" value="<?= sanitize($orderNumber) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:6px; font-size:0.92rem; outline:none; text-transform:uppercase; font-weight:700; color:var(--haat-green-dark);">
        </div>

        <div>
          <label style="font-weight:700; font-size:0.82rem; display:block; margin-bottom:4px; color:var(--text-main); text-transform:uppercase; letter-spacing:0.5px;">Recipient Phone (Optional)</label>
          <input type="text" name="phone" placeholder="e.g. 01711223344" value="<?= sanitize($phone) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:6px; font-size:0.92rem; outline:none;">
        </div>

        <button type="submit" class="btn btn-clay" style="height:44px; padding:0 22px; display:inline-flex; align-items:center; gap:8px; font-weight:700;">
          <i class="bi bi-search"></i> Track Order
        </button>
      </form>
    </div>

    <?php if ($orderNumber && !$order): ?>
      <div style="background:#fff; border:1px solid var(--haat-border); border-radius:12px; padding:48px 24px; text-align:center;">
        <i class="bi bi-exclamation-triangle text-clay" style="font-size:2.8rem; display:block; margin-bottom:12px;"></i>
        <h3 style="color:var(--haat-green-dark); margin-bottom:6px;">Order Not Found</h3>
        <p style="color:var(--text-muted); font-size:0.9rem;">We couldn't find order <strong><?= sanitize($orderNumber) ?></strong>. Please verify your order ID from your confirmation email or order history.</p>
        <a href="<?= BASE_URL ?>customer/#orders" class="btn btn-sm btn-outline-green" style="margin-top:10px;">Back to My Orders</a>
      </div>
    <?php elseif ($order): ?>

      <?php
      // Determine Banner Status Class & Content
      $bannerClass = 'status-banner-pending';
      $bannerIcon = 'bi-box-seam';
      $bannerTitle = 'Order Confirmed & Placed';
      $bannerSubtitle = 'Payment confirmed. Artisan workshop notified to begin handcrafting and packaging.';

      if ($stepDelivered === 2) {
          $bannerClass = 'status-banner-delivered';
          $bannerIcon = 'bi-check-circle-fill';
          $bannerTitle = 'Delivered & Handed Over';
          $bannerSubtitle = 'Your package has been successfully delivered. Thank you for supporting local artisans!';
      } elseif ($stepOut === 1) {
          $bannerClass = 'status-banner-out';
          $bannerIcon = 'bi-bicycle';
          $bannerTitle = 'Out for Doorstep Delivery';
          $bannerSubtitle = !empty($assignedRiderName) ? "Assigned HAATEX Rider {$assignedRiderName} is en route to your address." : "HAATEX delivery rider is en route to your delivery address.";
      } elseif ($stepHub === 1 || $stepHub === 2) {
          $bannerClass = 'status-banner-transit';
          $bannerIcon = 'bi-truck';
          $bannerTitle = 'In Transit — HAATEX Sorting Hub';
          $bannerSubtitle = 'Package received at HAATEX fulfillment center, undergoing route sorting and dispatch.';
      } elseif ($stepPacked === 1 || $stepPacked === 2) {
          $bannerClass = 'status-banner-packing';
          $bannerIcon = 'bi-box-seam-fill';
          $bannerTitle = 'Packed by Artisan & Ready for Courier';
          $bannerSubtitle = 'Artisan has finished crafting and packing your items. Pickup courier scheduled.';
      } elseif ($orderStatus === 'processing') {
          $bannerClass = 'status-banner-packing';
          $bannerIcon = 'bi-hammer';
          $bannerTitle = 'Artisan Crafting & Workshop Preparation';
          $bannerSubtitle = 'Artisan is currently weaving / crafting your items with genuine care.';
      }
      ?>

      <!-- Daraz Status Hero Banner -->
      <div class="daraz-status-banner <?= $bannerClass ?>" id="daraz-live-banner">
        <div style="display:flex; align-items:center; gap:16px;">
          <div style="width:48px; height:48px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0; backdrop-filter:blur(4px);">
            <i class="bi <?= $bannerIcon ?>" id="banner-icon"></i>
          </div>
          <div>
            <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.8px; opacity:0.85; font-weight:700;">Package 1 • Standard Delivery</div>
            <h2 style="margin:2px 0 4px; font-size:1.35rem; font-weight:800; color:#fff;" id="banner-title">
              <?= $bannerTitle ?>
            </h2>
            <p style="margin:0; font-size:0.85rem; opacity:0.92;" id="banner-subtitle">
              <?= $bannerSubtitle ?>
            </p>
          </div>
        </div>

        <div style="display:flex; align-items:center; gap:10px;">
          <a href="<?= BASE_URL ?>customer/#messages" class="btn btn-sm" style="background:#fff; color:var(--haat-green-dark); font-weight:700; font-size:0.82rem; padding:8px 14px; border:none; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
            <i class="bi bi-chat-dots-fill text-clay"></i> Contact HAATEX
          </a>
          <a href="<?= BASE_URL ?>invoice.php?order=<?= urlencode($order['order_number']) ?>" target="_blank" class="btn btn-sm" style="background:rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4); font-weight:600; font-size:0.82rem; padding:8px 14px; display:inline-flex; align-items:center; gap:6px;">
            <i class="bi bi-printer"></i> Invoice
          </a>
        </div>
      </div>

      <!-- Main Tracking Card -->
      <div class="daraz-card">
        
        <!-- Header Info Bar -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-bottom:1px solid #f0eeeb; padding-bottom:18px; margin-bottom:24px;">
          <div>
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; letter-spacing:0.5px;">Consignment Number</div>
            <div style="display:flex; align-items:center; gap:8px; margin-top:2px;">
              <span style="font-size:1.15rem; font-weight:800; color:var(--haat-green-dark); font-family:monospace;" id="txt-tracking-code">
                <?= sanitize($trackingCode) ?>
              </span>
              <button type="button" onclick="navigator.clipboard.writeText('<?= sanitize($trackingCode) ?>'); this.innerText='Copied!';" style="background:var(--haat-sand); border:1px solid var(--haat-border); border-radius:4px; color:var(--haat-green-dark); font-size:0.72rem; font-weight:700; padding:2px 8px; cursor:pointer;">
                Copy
              </button>
            </div>
          </div>

          <div style="text-align:right;">
            <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; letter-spacing:0.5px;">Estimated Delivery</div>
            <strong style="font-size:1.05rem; color:var(--haat-clay);" id="txt-est-delivery">
              <?= sanitize($estDelivery) ?>
            </strong>
          </div>
        </div>

        <!-- Daraz Horizontal 5-Step Stepper -->
        <div class="daraz-stepper-container">
          <div class="daraz-stepper-track-bg"></div>
          <div class="daraz-stepper-track-fill" id="daraz-stepper-fill" style="width: <?= $progressPercent ?>;"></div>

          <div class="daraz-stepper-steps">
            
            <!-- Step 1: Placed -->
            <div class="daraz-step-node is-done" id="step-node-placed">
              <div class="daraz-step-bubble">
                <i class="bi bi-receipt"></i>
              </div>
              <div class="daraz-step-label">Order Placed</div>
              <div class="daraz-step-time" id="time-node-placed"><?= !empty($timePlaced) ? date('d M, h:i A', strtotime($order['created_at'])) : '' ?></div>
            </div>

            <!-- Step 2: Packed -->
            <div class="daraz-step-node <?= ($stepPacked === 2) ? 'is-done' : (($stepPacked === 1) ? 'is-active' : '') ?>" id="step-node-packed">
              <div class="daraz-step-bubble">
                <?php if ($stepPacked === 2): ?><i class="bi bi-check-lg"></i>
                <?php elseif ($stepPacked === 1): ?><i class="bi bi-box-seam-fill"></i>
                <?php else: ?><i class="bi bi-box-seam"></i><?php endif; ?>
              </div>
              <div class="daraz-step-label">Packed by Seller</div>
              <div class="daraz-step-time" id="time-node-packed"><?= !empty($timePacked) ? $timePacked : ($stepPacked === 1 ? 'In progress' : '') ?></div>
            </div>

            <!-- Step 3: Hub In Transit -->
            <div class="daraz-step-node <?= ($stepHub === 2) ? 'is-done' : (($stepHub === 1) ? 'is-active' : '') ?>" id="step-node-hub">
              <div class="daraz-step-bubble">
                <?php if ($stepHub === 2): ?><i class="bi bi-check-lg"></i>
                <?php elseif ($stepHub === 1): ?><i class="bi bi-truck"></i>
                <?php else: ?><i class="bi bi-truck"></i><?php endif; ?>
              </div>
              <div class="daraz-step-label">In Transit / Hub</div>
              <div class="daraz-step-time" id="time-node-hub"><?= !empty($timeHub) ? $timeHub : ($stepHub === 1 ? 'In sorting hub' : '') ?></div>
            </div>

            <!-- Step 4: Out for Delivery -->
            <div class="daraz-step-node <?= ($stepOut === 2) ? 'is-done' : (($stepOut === 1) ? 'is-active' : '') ?>" id="step-node-out">
              <div class="daraz-step-bubble">
                <?php if ($stepOut === 2): ?><i class="bi bi-check-lg"></i>
                <?php elseif ($stepOut === 1): ?><i class="bi bi-bicycle"></i>
                <?php else: ?><i class="bi bi-bicycle"></i><?php endif; ?>
              </div>
              <div class="daraz-step-label">Out for Delivery</div>
              <div class="daraz-step-time" id="time-node-out"><?= !empty($timeOut) ? $timeOut : ($stepOut === 1 ? 'With rider' : '') ?></div>
            </div>

            <!-- Step 5: Delivered -->
            <div class="daraz-step-node <?= ($stepDelivered === 2) ? 'is-done' : '' ?>" id="step-node-delivered">
              <div class="daraz-step-bubble">
                <?php if ($stepDelivered === 2): ?><i class="bi bi-house-check-fill"></i>
                <?php else: ?><i class="bi bi-house-door"></i><?php endif; ?>
              </div>
              <div class="daraz-step-label">Delivered</div>
              <div class="daraz-step-time" id="time-node-delivered"><?= !empty($timeDelivered) ? $timeDelivered : '' ?></div>
            </div>

          </div>
        </div>

        <!-- HAATEX Courier & Assigned Delivery Rider Card -->
        <div class="daraz-courier-strip" id="daraz-rider-card">
          <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:42px; height:42px; border-radius:8px; background:var(--haat-green-dark); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
              <i class="bi bi-truck"></i>
            </div>
            <div>
              <span style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; display:block;">Courier Service</span>
              <strong style="color:var(--haat-green-dark); font-size:0.92rem;" id="txt-courier-name"><?= sanitize($courierPartner) ?></strong>
            </div>
          </div>

          <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:42px; height:42px; border-radius:50%; background:var(--haat-clay-light); color:var(--haat-clay); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
              <i class="bi bi-person-badge-fill"></i>
            </div>
            <div>
              <span style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; display:block;">Assigned Delivery Hero</span>
              <strong style="color:var(--haat-clay); font-size:0.92rem;" id="txt-assigned-rider">
                <?= !empty($assignedRiderName) ? sanitize($assignedRiderName) : 'Assigned at local hub' ?>
              </strong>
            </div>
          </div>

          <?php if (!empty($assignedRiderPhone)): ?>
            <div style="text-align:right;">
              <a href="tel:<?= sanitize($assignedRiderPhone) ?>" class="btn btn-sm btn-clay" style="font-size:0.82rem; padding:6px 14px; display:inline-flex; align-items:center; gap:6px;">
                <i class="bi bi-telephone-fill"></i> Call Rider (<?= sanitize($assignedRiderPhone) ?>)
              </a>
            </div>
          <?php endif; ?>
        </div>

        <!-- Authentic Daraz Activity Log Timeline (Vertical Checkpoints) -->
        <div style="margin-top:28px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h3 style="font-size:1.1rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:8px;">
              <i class="bi bi-clock-history text-clay"></i> Tracking History & Checkpoints
            </h3>
            <span style="font-size:0.78rem; color:var(--text-muted);">Live updates from HAATEX Network</span>
          </div>

          <div class="daraz-vertical-timeline" id="daraz-vt-container">
            <div class="daraz-vt-line"></div>

            <?php if (empty($transitNotes)): ?>
              <!-- Default Initial Milestone -->
              <div class="daraz-vt-item is-latest is-completed">
                <div class="daraz-vt-timestamp">
                  <div class="daraz-vt-time"><?= date('h:i A', strtotime($order['created_at'])) ?></div>
                  <div class="daraz-vt-date"><?= date('d M Y', strtotime($order['created_at'])) ?></div>
                </div>
                <div class="daraz-vt-dot"></div>
                <div class="daraz-vt-content">
                  <div class="daraz-vt-title">Order Confirmed & Payment Verified</div>
                  <div class="daraz-vt-desc">Order #<?= sanitize($order['order_number']) ?> has been placed. Artisan workshop notified for preparation.</div>
                  <div class="daraz-vt-location"><i class="bi bi-geo-alt"></i> HAAT Platform Command</div>
                </div>
              </div>
            <?php else: ?>
              <?php 
              $reversedEvents = array_reverse($transitNotes);
              foreach ($reversedEvents as $idx => $ev): 
                  $isLatest = ($idx === 0);
              ?>
                <div class="daraz-vt-item <?= $isLatest ? 'is-latest' : 'is-completed' ?>">
                  <div class="daraz-vt-timestamp">
                    <div class="daraz-vt-time"><?= $ev['time'] ?></div>
                    <div class="daraz-vt-date"><?= $ev['date'] ?></div>
                  </div>
                  <div class="daraz-vt-dot"></div>
                  <div class="daraz-vt-content">
                    <div class="daraz-vt-title"><?= sanitize($ev['title']) ?></div>
                    <div class="daraz-vt-desc"><?= sanitize($ev['note'] ?: $ev['title']) ?></div>
                    <div class="daraz-vt-location">
                      <i class="bi bi-geo-alt"></i> <?= sanitize($ev['location'] ?: $ev['actor']) ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

          </div>
        </div>

      </div>

      <!-- Package Items Summary Card -->
      <div class="daraz-card">
        <div style="font-size:1.05rem; font-weight:800; color:var(--haat-green-dark); margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
          <span>Items in this Package (<?= count($items) ?>)</span>
          <span style="font-size:0.82rem; font-weight:600; color:var(--text-muted);">Verified Artisanal Craftsmanship</span>
        </div>

        <div style="display:flex; flex-direction:column; gap:10px;">
          <?php foreach ($items as $it): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#fdfbf8; border:1px solid var(--haat-border); border-radius:8px; font-size:0.88rem;">
              <div style="display:flex; align-items:center; gap:14px;">
                <?php if (!empty($it['featured_image'])): ?>
                  <img src="<?= sanitize($it['featured_image']) ?>" alt="thumb" style="width:48px; height:48px; border-radius:6px; object-fit:cover; border:1px solid var(--haat-border);">
                <?php else: ?>
                  <div style="width:48px; height:48px; border-radius:6px; background:var(--haat-sand); display:flex; align-items:center; justify-content:center; color:var(--haat-clay); font-size:1.2rem;">
                    <i class="bi bi-box"></i>
                  </div>
                <?php endif; ?>
                <div>
                  <strong style="color:var(--text-main); font-size:0.92rem; display:block;"><?= sanitize($it['product_name']) ?></strong>
                  <span style="font-size:0.75rem; color:var(--haat-clay);"><i class="bi bi-shop"></i> <?= sanitize($it['shop_name']) ?></span>
                </div>
              </div>
              <div style="text-align:right;">
                <div style="font-weight:800; color:var(--haat-green-dark); font-size:0.95rem;">
                  <?= formatPrice($it['subtotal']) ?>
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted);">
                  <?= $it['quantity'] ?> × <?= formatPrice($it['price']) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Recipient & Delivery Details -->
        <div style="background:var(--haat-cream); border-radius:8px; padding:16px 20px; font-size:0.85rem; margin-top:20px; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
          <div>
            <span style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; display:block;">Shipping Destination</span>
            <strong style="color:var(--haat-green-dark);"><?= sanitize($order['shipping_name']) ?> (<?= sanitize($order['shipping_phone']) ?>)</strong>
            <div style="color:var(--text-main); margin-top:2px;"><?= sanitize($order['shipping_address']) ?>, <?= sanitize($order['district']) ?></div>
          </div>
          <div>
            <span style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; display:block;">Total Payment</span>
            <strong style="font-size:1.1rem; color:var(--haat-green-dark);"><?= formatPrice($order['grand_total']) ?></strong>
            <div style="color:var(--text-muted); margin-top:2px;">Method: <?= strtoupper($order['payment_method']) ?> (<?= ucfirst($order['payment_status']) ?>)</div>
          </div>
        </div>

      </div>

      <!-- Live Auto-Polling Engine -->
      <script>
        const ORDER_NUM = '<?= addslashes($order['order_number']) ?>';
        let currentStatus = '<?= addslashes($order['order_status']) ?>';
        let currentLogStatus = '<?= addslashes($order['logistics_status'] ?? '') ?>';

        function pollLiveDarazTracking() {
          fetch(`<?= BASE_URL ?>api/track.php?action=get&order=${encodeURIComponent(ORDER_NUM)}`)
            .then(r => r.json())
            .then(data => {
              if (!data.success || !data.order) return;

              const ord = data.order;
              if (ord.order_status !== currentStatus || ord.logistics_status !== currentLogStatus) {
                currentStatus = ord.order_status;
                currentLogStatus = ord.logistics_status;
                applyLiveUpdates(data);
              }
            })
            .catch(e => console.error(e));
        }

        function applyLiveUpdates(data) {
          const ord = data.order;
          const status = ord.order_status;
          const logStatus = ord.logistics_status || '';

          // 1. Update Stepper Nodes & Fill Bar
          const fillBar = document.getElementById('daraz-stepper-fill');
          const nodePacked = document.getElementById('step-node-packed');
          const nodeHub = document.getElementById('step-node-hub');
          const nodeOut = document.getElementById('step-node-out');
          const nodeDelivered = document.getElementById('step-node-delivered');

          if (status === 'delivered' || logStatus === 'delivered') {
            if (fillBar) fillBar.style.width = '100%';
            setNodeState(nodePacked, 'is-done', '<i class="bi bi-check-lg"></i>');
            setNodeState(nodeHub, 'is-done', '<i class="bi bi-check-lg"></i>');
            setNodeState(nodeOut, 'is-done', '<i class="bi bi-check-lg"></i>');
            setNodeState(nodeDelivered, 'is-done', '<i class="bi bi-house-check-fill"></i>');
          } else if (logStatus === 'out_for_delivery') {
            if (fillBar) fillBar.style.width = '75%';
            setNodeState(nodePacked, 'is-done', '<i class="bi bi-check-lg"></i>');
            setNodeState(nodeHub, 'is-done', '<i class="bi bi-check-lg"></i>');
            setNodeState(nodeOut, 'is-active', '<i class="bi bi-bicycle"></i>');
            setNodeState(nodeDelivered, '', '<i class="bi bi-house-door"></i>');
          } else if (logStatus === 'hub_received' || status === 'shipped') {
            if (fillBar) fillBar.style.width = '50%';
            setNodeState(nodePacked, 'is-done', '<i class="bi bi-check-lg"></i>');
            setNodeState(nodeHub, 'is-active', '<i class="bi bi-truck"></i>');
            setNodeState(nodeOut, '', '<i class="bi bi-bicycle"></i>');
            setNodeState(nodeDelivered, '', '<i class="bi bi-house-door"></i>');
          } else if (logStatus === 'pickup_requested' || status === 'processing') {
            if (fillBar) fillBar.style.width = '25%';
            setNodeState(nodePacked, 'is-active', '<i class="bi bi-box-seam-fill"></i>');
            setNodeState(nodeHub, '', '<i class="bi bi-truck"></i>');
            setNodeState(nodeOut, '', '<i class="bi bi-bicycle"></i>');
            setNodeState(nodeDelivered, '', '<i class="bi bi-house-door"></i>');
          }

          // 2. Update Rider Strip
          if (ord.assigned_rider_name) {
            const rElem = document.getElementById('txt-assigned-rider');
            if (rElem) rElem.innerText = ord.assigned_rider_name + (ord.assigned_rider_phone ? ' (' + ord.assigned_rider_phone + ')' : '');
          }

          // 3. Update Checkpoints List
          if (data.events && data.events.length > 0) {
            const container = document.getElementById('daraz-vt-container');
            if (container) {
              let html = '<div class="daraz-vt-line"></div>';
              data.events.forEach((ev, idx) => {
                const isLatest = (idx === 0);
                html += `
                  <div class="daraz-vt-item ${isLatest ? 'is-latest' : 'is-completed'}">
                    <div class="daraz-vt-timestamp">
                      <div class="daraz-vt-time">${escapeHtml(ev.time)}</div>
                      <div class="daraz-vt-date">${escapeHtml(ev.date)}</div>
                    </div>
                    <div class="daraz-vt-dot"></div>
                    <div class="daraz-vt-content">
                      <div class="daraz-vt-title">${escapeHtml(ev.title)}</div>
                      <div class="daraz-vt-desc">${escapeHtml(ev.note || ev.title)}</div>
                      <div class="daraz-vt-location"><i class="bi bi-geo-alt"></i> ${escapeHtml(ev.location || ev.actor)}</div>
                    </div>
                  </div>
                `;
              });
              container.innerHTML = html;
            }
          }
        }

        function setNodeState(elem, stateClass, innerHtml) {
          if (!elem) return;
          elem.classList.remove('is-done', 'is-active');
          if (stateClass) elem.classList.add(stateClass);
          const bubble = elem.querySelector('.daraz-step-bubble');
          if (bubble && innerHtml) bubble.innerHTML = innerHtml;
        }

        function escapeHtml(str) {
          const p = document.createElement('p');
          p.textContent = str || '';
          return p.innerHTML;
        }

        // Live polling every 3 seconds
        setInterval(pollLiveDarazTracking, 3000);
      </script>

    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
