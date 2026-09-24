<?php
/**
 * Daraz-Style Live Order Tracking Page
 * HAAT Multi-Vendor Marketplace
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

$pageTitle = 'Track Order — HAAT';
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

// Compute Daraz milestones
$orderStatus = $order['order_status'] ?? 'pending';
$courierPartner = $order['courier_partner'] ?? 'Pathao Courier Logistics';
$trackingCode = $order['tracking_code'] ?? 'PTH-8849201';
$estDelivery = $order['estimated_delivery'] ?? '25-27 Sep 2026';

// Milestone states: 0: upcoming, 1: current/active, 2: completed
$stepOrderConfirmed = 2; // Always confirmed once order exists
$stepReadyStore = ($orderStatus === 'processing' || $orderStatus === 'shipped' || $orderStatus === 'delivered') ? ($orderStatus === 'processing' ? 1 : 2) : 0;
$stepHeadedDelivery = ($orderStatus === 'shipped' || $orderStatus === 'delivered') ? ($orderStatus === 'shipped' ? 1 : 2) : 0;
$stepDelivered = ($orderStatus === 'delivered') ? 2 : 0;

// Find event timestamps
$timeConfirmed = !empty($order) ? date('d M Y, h:i A', strtotime($order['created_at'])) : '';
$timeReady = '';
$timeShipped = '';
$timeDelivered = '';
$transitNotes = [];

foreach ($trackingEvents as $ev) {
    if ($ev['status_key'] === 'processing' && empty($timeReady)) {
        $timeReady = date('d M Y, h:i A', strtotime($ev['created_at']));
    }
    if ($ev['status_key'] === 'shipped') {
        $timeShipped = date('d M Y, h:i A', strtotime($ev['created_at']));
        $transitNotes[] = [
            'time' => date('h:i A, d M', strtotime($ev['created_at'])),
            'title' => $ev['title'],
            'actor' => $ev['actor'],
            'location' => $ev['location'],
            'note' => $ev['note']
        ];
    }
    if ($ev['status_key'] === 'delivered') {
        $timeDelivered = date('d M Y, h:i A', strtotime($ev['created_at']));
    }
}
?>

<style>
  /* Daraz-style vertical tracking timeline */
  .daraz-timeline {
    position: relative;
    padding-left: 38px;
    margin: 28px 0;
  }
  .daraz-timeline-line {
    position: absolute;
    top: 14px;
    bottom: 24px;
    left: 15px;
    width: 2px;
    background: #e2e8f0;
    z-index: 1;
  }
  .daraz-timeline-progress {
    position: absolute;
    top: 14px;
    left: 15px;
    width: 2px;
    background: var(--haat-green);
    z-index: 2;
    transition: height 0.5s ease;
  }
  .daraz-step {
    position: relative;
    margin-bottom: 28px;
    z-index: 3;
  }
  .daraz-step:last-child {
    margin-bottom: 0;
  }
  .daraz-dot {
    position: absolute;
    left: -38px;
    top: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    transition: all 0.3s ease;
  }
  .daraz-dot-done {
    background: var(--haat-green);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(30, 63, 32, 0.25);
  }
  .daraz-dot-active {
    background: var(--haat-clay);
    color: #ffffff;
    box-shadow: 0 0 0 4px rgba(194, 97, 45, 0.22);
    animation: activePulse 2s infinite;
  }
  .daraz-dot-pending {
    background: #f8fafc;
    color: #94a3b8;
    border: 2px solid #cbd5e1;
  }
  @keyframes activePulse {
    0% { box-shadow: 0 0 0 0 rgba(194, 97, 45, 0.4); }
    70% { box-shadow: 0 0 0 7px rgba(194, 97, 45, 0); }
    100% { box-shadow: 0 0 0 0 rgba(194, 97, 45, 0); }
  }

  .daraz-card-header {
    border-bottom: 1px solid var(--haat-border);
    padding-bottom: 16px;
    margin-bottom: 24px;
  }
</style>

<div class="container" style="padding: 36px 20px 80px;">
  
  <div style="max-width:800px; margin:0 auto;">
    
    <!-- Page Header -->
    <div style="text-align:center; margin-bottom:28px;">
      <h1 style="font-size:2rem; color:var(--haat-green-dark); margin-bottom:6px; display:flex; align-items:center; justify-content:center; gap:10px;">
        <i class="bi bi-truck text-clay"></i> Track Order
      </h1>
      <p style="color:var(--text-muted); font-size:0.9rem; margin:0 auto; max-width:500px;">
        Follow your package delivery progress from artisan store preparation to doorstep arrival.
      </p>
    </div>

    <!-- Search / Look-Up Bar -->
    <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:20px 24px; box-shadow:var(--shadow-sm); margin-bottom:28px;">
      <form method="GET" action="<?= BASE_URL ?>track-order.php" style="display:grid; grid-template-columns: 1fr 1fr auto; gap:14px; align-items:flex-end;">
        <div>
          <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px; color:var(--text-main);">Order Number</label>
          <input type="text" name="order" required placeholder="e.g. HAAT-2026-90412" value="<?= sanitize($orderNumber) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none; text-transform:uppercase; font-weight:700; color:var(--haat-green-dark);">
        </div>

        <div>
          <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px; color:var(--text-main);">Recipient Phone (Optional)</label>
          <input type="text" name="phone" placeholder="e.g. 01511556677" value="<?= sanitize($phone) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
        </div>

        <button type="submit" class="btn btn-clay" style="height:44px; display:inline-flex; align-items:center; gap:8px;">
          <i class="bi bi-search"></i> Track
        </button>
      </form>
    </div>

    <?php if ($orderNumber && !$order): ?>
      <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:40px; text-align:center;">
        <i class="bi bi-exclamation-triangle text-clay" style="font-size:2.6rem; display:block; margin-bottom:12px;"></i>
        <h3 style="color:var(--haat-green-dark); margin-bottom:6px;">Order Not Found</h3>
        <p style="color:var(--text-muted); font-size:0.9rem;">We couldn't locate order <strong><?= sanitize($orderNumber) ?></strong>. Please double-check the order ID from your confirmation email.</p>
        <a href="<?= BASE_URL ?>customer/#orders" class="btn btn-sm btn-outline-green" style="margin-top:10px;">Go to My Orders</a>
      </div>
    <?php elseif ($order): ?>

      <!-- Main Daraz-Style Tracking Container -->
      <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:30px; box-shadow:var(--shadow-sm); margin-bottom:28px;">
        
        <!-- Clean Header (Black marked clutter completely removed) -->
        <div class="daraz-card-header">
          <h2 style="color:var(--haat-green-dark); font-size:1.35rem; margin:0; font-weight:700;">
            Order #<?= sanitize($order['order_number']) ?>
          </h2>
        </div>

        <!-- Unified Daraz-Style Vertical Progression (Yellow + Red unified) -->
        <div class="daraz-timeline" id="daraz-tracking-timeline">
          
          <!-- Background Line -->
          <div class="daraz-timeline-line"></div>
          <!-- Progress Line -->
          <div class="daraz-timeline-progress" id="daraz-line-fill" style="height: <?= $stepDelivered === 2 ? '100%' : ($stepHeadedDelivery ? '70%' : ($stepReadyStore ? '40%' : '10%')) ?>;"></div>

          <!-- STEP 1: Order Confirmed -->
          <div class="daraz-step" id="step-confirmed">
            <div class="daraz-dot daraz-dot-done">
              <i class="bi bi-check-lg"></i>
            </div>
            <div>
              <div style="display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:6px;">
                <strong style="color:var(--haat-green-dark); font-size:1rem;">Order Confirmed</strong>
                <span id="txt-time-confirmed" style="font-size:0.78rem; color:var(--text-muted);"><?= $timeConfirmed ?></span>
              </div>
              <p style="margin:4px 0 0; font-size:0.85rem; color:var(--text-muted); line-height:1.45;">
                Payment verified via <?= strtoupper($order['payment_method']) ?>. Your order has been placed into the HAAT system.
              </p>
            </div>
          </div>

          <!-- STEP 2: Ready by Seller / Store -->
          <div class="daraz-step" id="step-ready">
            <div class="daraz-dot <?= $stepReadyStore === 2 ? 'daraz-dot-done' : ($stepReadyStore === 1 ? 'daraz-dot-active' : 'daraz-dot-pending') ?>">
              <?php if ($stepReadyStore === 2): ?>
                <i class="bi bi-check-lg"></i>
              <?php elseif ($stepReadyStore === 1): ?>
                <i class="bi bi-box-seam"></i>
              <?php else: ?>
                <i class="bi bi-circle"></i>
              <?php endif; ?>
            </div>
            <div>
              <div style="display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:6px;">
                <strong style="color:<?= $stepReadyStore ? 'var(--haat-green-dark)' : 'var(--text-muted)' ?>; font-size:1rem;">
                  Ready by Seller / Store
                </strong>
                <span id="txt-time-ready" style="font-size:0.78rem; color:var(--text-muted);">
                  <?= $timeReady ?: ($stepReadyStore ? 'In preparation' : '') ?>
                </span>
              </div>
              <p style="margin:4px 0 0; font-size:0.85rem; color:var(--text-muted); line-height:1.45;">
                Artisan workshop prepares, inspects craftsmanship, and packages the items for delivery handover.
              </p>
            </div>
          </div>

          <!-- STEP 3: Headed to Delivery / In Transit (Shows Delivery Partner and Checkpoints) -->
          <div class="daraz-step" id="step-shipped">
            <div class="daraz-dot <?= $stepHeadedDelivery === 2 ? 'daraz-dot-done' : ($stepHeadedDelivery === 1 ? 'daraz-dot-active' : 'daraz-dot-pending') ?>">
              <?php if ($stepHeadedDelivery === 2): ?>
                <i class="bi bi-check-lg"></i>
              <?php elseif ($stepHeadedDelivery === 1): ?>
                <i class="bi bi-truck"></i>
              <?php else: ?>
                <i class="bi bi-circle"></i>
              <?php endif; ?>
            </div>
            <div>
              <div style="display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:6px;">
                <strong style="color:<?= $stepHeadedDelivery ? 'var(--haat-green-dark)' : 'var(--text-muted)' ?>; font-size:1rem;">
                  Headed to Delivery (In Transit)
                </strong>
                <span id="txt-time-shipped" style="font-size:0.78rem; color:var(--text-muted);">
                  <?= $timeShipped ?: ($stepHeadedDelivery ? 'On the way' : '') ?>
                </span>
              </div>
              <p style="margin:4px 0 0; font-size:0.85rem; color:var(--text-muted); line-height:1.45;">
                Package handed over to logistics carrier and moving through sorting hubs to destination.
              </p>

              <!-- Integrated Delivery Partner Details Box (Like Daraz) -->
              <div id="daraz-delivery-partner-card" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 18px; margin-top:12px;">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; align-items:center;">
                  <div>
                    <span style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; display:block;">Delivery Partner</span>
                    <strong id="dp-partner-name" style="color:var(--haat-green-dark); font-size:0.92rem; display:flex; align-items:center; gap:6px;">
                      <i class="bi bi-truck text-clay"></i> <?= sanitize($courierPartner) ?>
                    </strong>
                  </div>

                  <div>
                    <span style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; display:block;">Tracking Consignment Code</span>
                    <div style="display:flex; align-items:center; gap:6px; margin-top:2px;">
                      <code id="dp-tracking-code" style="background:#fff; border:1px solid #cbd5e1; padding:2px 8px; border-radius:4px; font-weight:700; color:var(--haat-clay); font-size:0.85rem;">
                        <?= sanitize($trackingCode) ?>
                      </code>
                      <button type="button" onclick="navigator.clipboard.writeText('<?= sanitize($trackingCode) ?>'); this.innerText='Copied!';" style="background:none; border:none; color:var(--text-muted); font-size:0.75rem; cursor:pointer; text-decoration:underline;">
                        Copy
                      </button>
                    </div>
                  </div>

                  <div>
                    <span style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; display:block;">Estimated Delivery</span>
                    <strong id="dp-est-delivery" style="color:var(--haat-green); font-size:0.9rem;">
                      <?= sanitize($estDelivery) ?>
                    </strong>
                  </div>
                </div>

                <!-- Live Transit Checkpoints Bullet List -->
                <?php if (!empty($transitNotes)): ?>
                  <div id="daraz-transit-checkpoints" style="border-top:1px dashed #cbd5e1; margin-top:12px; padding-top:10px;">
                    <span style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; display:block; margin-bottom:6px;">
                      Transit Checkpoints:
                    </span>
                    <div id="checkpoints-list" style="display:flex; flex-direction:column; gap:6px;">
                      <?php foreach (array_reverse($transitNotes) as $c): ?>
                        <div style="font-size:0.8rem; color:var(--text-main); display:flex; gap:8px;">
                          <span style="color:var(--haat-clay); font-weight:700; flex-shrink:0;">• <?= $c['time'] ?>:</span>
                          <span><strong><?= sanitize($c['title']) ?></strong> (<?= sanitize($c['location'] ?: $c['actor']) ?>)</span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

            </div>
          </div>

          <!-- STEP 4: Delivered -->
          <div class="daraz-step" id="step-delivered">
            <div class="daraz-dot <?= $stepDelivered === 2 ? 'daraz-dot-done' : 'daraz-dot-pending' ?>">
              <?php if ($stepDelivered === 2): ?>
                <i class="bi bi-house-check-fill"></i>
              <?php else: ?>
                <i class="bi bi-circle"></i>
              <?php endif; ?>
            </div>
            <div>
              <div style="display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:6px;">
                <strong style="color:<?= $stepDelivered === 2 ? 'var(--haat-green-dark)' : 'var(--text-muted)' ?>; font-size:1rem;">
                  Delivered
                </strong>
                <span id="txt-time-delivered" style="font-size:0.78rem; color:var(--text-muted);">
                  <?= $timeDelivered ?: '' ?>
                </span>
              </div>
              <p style="margin:4px 0 0; font-size:0.85rem; color:var(--text-muted); line-height:1.45;">
                Package successfully handed over to recipient at delivery address.
              </p>
            </div>
          </div>

        </div>

        <!-- Package Items Summary -->
        <div style="margin-top:32px; padding-top:20px; border-top:1px solid var(--haat-border);">
          <div style="font-size:0.95rem; font-weight:700; color:var(--haat-green-dark); margin-bottom:12px;">
            Package Items (<?= count($items) ?>)
          </div>
          <div style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($items as $it): ?>
              <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; background:#fdfbf8; border:1px solid var(--haat-border); border-radius:6px; font-size:0.88rem;">
                <div style="display:flex; align-items:center; gap:10px;">
                  <?php if (!empty($it['featured_image'])): ?>
                    <img src="<?= sanitize($it['featured_image']) ?>" alt="thumb" style="width:40px; height:40px; border-radius:6px; object-fit:cover; border:1px solid var(--haat-border);">
                  <?php else: ?>
                    <div style="width:40px; height:40px; border-radius:6px; background:var(--haat-sand); display:flex; align-items:center; justify-content:center; color:var(--haat-clay);">
                      <i class="bi bi-box"></i>
                    </div>
                  <?php endif; ?>
                  <div>
                    <strong style="color:var(--text-main); font-size:0.88rem;"><?= sanitize($it['product_name']) ?></strong>
                    <div style="font-size:0.75rem; color:var(--haat-clay);">Seller: <?= sanitize($it['shop_name']) ?></div>
                  </div>
                </div>
                <div style="text-align:right;">
                  <span style="font-weight:700; color:var(--haat-green); font-size:0.88rem;">
                    <?= $it['quantity'] ?> × <?= formatPrice($it['price']) ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Delivery Address & Payment Summary -->
        <div style="background:var(--haat-cream); border-radius:8px; padding:14px 18px; font-size:0.85rem; margin-top:20px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:12px;">
          <div>
            <strong>Delivery Address:</strong> <?= sanitize($order['shipping_name']) ?> (<?= sanitize($order['shipping_phone']) ?>), <?= sanitize($order['shipping_address']) ?>, <?= sanitize($order['district']) ?>
          </div>
          <div style="text-align:right;">
            <strong>Total:</strong> <span style="font-weight:800; color:var(--haat-green-dark);"><?= formatPrice($order['grand_total']) ?></span> (<?= strtoupper($order['payment_method']) ?>)
          </div>
        </div>


      </div>

      <!-- Real-Time Auto-Update Polling Script -->
      <script>
        const ORDER_NUMBER = '<?= addslashes($order['order_number']) ?>';
        let currentKnownStatus = '<?= addslashes($order['order_status']) ?>';

        function pollLiveTracking() {
          fetch(`<?= BASE_URL ?>api/track.php?action=get&order=${encodeURIComponent(ORDER_NUMBER)}`)
            .then(res => res.json())
            .then(data => {
              if (!data.success) return;

              const status = data.order.order_status;
              if (status !== currentKnownStatus || data.events.length > 0) {
                updateDarazTimeline(data);
                currentKnownStatus = status;
              }
            })
            .catch(err => console.error("Tracking poll error:", err));
        }

        function updateDarazTimeline(data) {
          const status = data.order.order_status;
          const lineFill = document.getElementById('daraz-line-fill');

          const stepReady = document.getElementById('step-ready');
          const stepShipped = document.getElementById('step-shipped');
          const stepDelivered = document.getElementById('step-delivered');

          // Progress line height
          if (status === 'delivered') {
            if (lineFill) lineFill.style.height = '100%';
          } else if (status === 'shipped') {
            if (lineFill) lineFill.style.height = '70%';
          } else if (status === 'processing') {
            if (lineFill) lineFill.style.height = '40%';
          } else {
            if (lineFill) lineFill.style.height = '10%';
          }

          // Step 2: Ready by Store
          if (stepReady) {
            const dot = stepReady.querySelector('.daraz-dot');
            if (status === 'processing') {
              dot.className = 'daraz-dot daraz-dot-active';
              dot.innerHTML = '<i class="bi bi-box-seam"></i>';
            } else if (status === 'shipped' || status === 'delivered') {
              dot.className = 'daraz-dot daraz-dot-done';
              dot.innerHTML = '<i class="bi bi-check-lg"></i>';
            }
          }

          // Step 3: Headed to Delivery
          if (stepShipped) {
            const dot = stepShipped.querySelector('.daraz-dot');
            if (status === 'shipped') {
              dot.className = 'daraz-dot daraz-dot-active';
              dot.innerHTML = '<i class="bi bi-truck"></i>';
            } else if (status === 'delivered') {
              dot.className = 'daraz-dot daraz-dot-done';
              dot.innerHTML = '<i class="bi bi-check-lg"></i>';
            }
          }

          // Step 4: Delivered
          if (stepDelivered) {
            const dot = stepDelivered.querySelector('.daraz-dot');
            if (status === 'delivered') {
              dot.className = 'daraz-dot daraz-dot-done';
              dot.innerHTML = '<i class="bi bi-house-check-fill"></i>';
            }
          }

          // Update Delivery Partner Details
          if (data.order.courier_partner) {
            const pElem = document.getElementById('dp-partner-name');
            if (pElem) pElem.innerHTML = `<i class="bi bi-truck text-clay"></i> ${escapeHtml(data.order.courier_partner)}`;
          }
          if (data.order.tracking_code) {
            const tElem = document.getElementById('dp-tracking-code');
            if (tElem) tElem.innerText = data.order.tracking_code;
          }
          if (data.order.estimated_delivery) {
            const eElem = document.getElementById('dp-est-delivery');
            if (eElem) eElem.innerText = data.order.estimated_delivery;
          }

          // Update Transit Checkpoints
          const checkpointsList = document.getElementById('checkpoints-list');
          if (checkpointsList && data.events) {
            const shippedEvents = data.events.filter(e => e.status_key === 'shipped');
            if (shippedEvents.length > 0) {
              let cHtml = '';
              shippedEvents.forEach(c => {
                cHtml += `
                  <div style="font-size:0.8rem; color:var(--text-main); display:flex; gap:8px;">
                    <span style="color:var(--haat-clay); font-weight:700; flex-shrink:0;">• ${escapeHtml(c.time)}, ${escapeHtml(c.date)}:</span>
                    <span><strong>${escapeHtml(c.title)}</strong> (${escapeHtml(c.location || c.actor)})</span>
                  </div>
                `;
              });
              checkpointsList.innerHTML = cHtml;
            }
          }
        }

        function escapeHtml(text) {
          const div = document.createElement('div');
          div.innerText = text || '';
          return div.innerHTML;
        }

        // Live polling every 3 seconds
        setInterval(pollLiveTracking, 3000);
      </script>

    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
