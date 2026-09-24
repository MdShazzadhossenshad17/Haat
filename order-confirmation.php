<?php
/**
 * Order Confirmation & Printable Invoice
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/includes/functions.php';

$orderNumber = trim($_GET['order'] ?? '');
if (empty($orderNumber)) {
    header('Location: ' . BASE_URL);
    exit;
}

// Fetch Order
$stmt = $db->prepare("SELECT * FROM `orders` WHERE `order_number` = ? LIMIT 1");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ' . BASE_URL);
    exit;
}

// Fetch Items
$itemStmt = $db->prepare("SELECT oi.*, s.shop_name, s.district as seller_district 
    FROM `order_items` oi 
    JOIN `sellers` s ON oi.seller_id = s.id 
    WHERE oi.order_id = ?");
$itemStmt->execute([$order['id']]);
$items = $itemStmt->fetchAll();

$pageTitle = 'Order Confirmed — ' . $order['order_number'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 40px 20px;">
  
  <!-- Success Hero Card -->
  <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:40px; box-shadow:var(--shadow-sm); text-align:center; max-width:850px; margin:0 auto 30px;">
    <div style="width:72px; height:72px; border-radius:50%; background:#e3efe6; color:var(--haat-green); display:flex; align-items:center; justify-content:center; font-size:2.4rem; margin:0 auto 16px;">
      <i class="bi bi-check-lg"></i>
    </div>
    <span class="badge badge-green" style="margin-bottom:8px;">Order Successfully Placed</span>
    <h1 style="font-size:2rem; margin-bottom:10px; color:var(--haat-green-dark);">
      Thank You For Empowering Bangladeshi Artisans!
    </h1>
    <p style="color:var(--text-muted); font-size:1.02rem; margin-bottom:20px;">
      Your order <strong style="color:var(--haat-clay);"><?= sanitize($order['order_number']) ?></strong> has been received and routed to the respective master workshops.
    </p>

    <div style="display:flex; justify-content:center; gap:16px; flex-wrap:wrap;">
      <a href="<?= BASE_URL ?>track-order.php?order=<?= urlencode($order['order_number']) ?>" class="btn btn-clay">
        <i class="bi bi-truck"></i> Track Delivery Status
      </a>
      <button onclick="window.print()" class="btn btn-outline-green">
        <i class="bi bi-printer"></i> Print Invoice
      </button>
      <a href="<?= BASE_URL ?>shop.php" class="btn btn-outline-clay">
        Continue Shopping
      </a>
    </div>
  </div>

  <!-- Detailed Invoice Card -->
  <div id="invoice-printable" style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:40px; box-shadow:var(--shadow-sm); max-width:850px; margin:0 auto;">
    
    <!-- Invoice Header -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:30px; padding-bottom:20px; border-bottom:2px solid var(--haat-sand);">
      <div>
        <img src="<?= BASE_URL ?>assets/images/logo.png" alt="HAAT Logo" style="height:50px; margin-bottom:8px;">
        <div style="font-size:0.85rem; color:var(--text-muted);">
          Bangladesh Multi-Vendor Artisanal Marketplace<br>
          Web: www.haat.com.bd | Support: +880 1711-000001
        </div>
      </div>
      <div style="text-align:right;">
        <h2 style="font-size:1.4rem; color:var(--haat-green); margin-bottom:4px;">INVOICE</h2>
        <div style="font-size:0.88rem; color:var(--text-muted);">
          Order #: <strong><?= sanitize($order['order_number']) ?></strong><br>
          Date: <strong><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></strong><br>
          Payment: <strong><?= strtoupper($order['payment_method']) ?> (<?= ucfirst($order['payment_status']) ?>)</strong>
          <?php if ($order['transaction_id']): ?>
            <br>TrxID: <strong><?= sanitize($order['transaction_id']) ?></strong>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Recipient & Delivery Info -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:30px; font-size:0.92rem;">
      <div style="background:var(--haat-cream); padding:16px; border-radius:var(--radius-md);">
        <strong style="color:var(--haat-green-dark); display:block; margin-bottom:6px;">Delivery Recipient:</strong>
        <div><strong><?= sanitize($order['shipping_name']) ?></strong></div>
        <div>Phone: <?= sanitize($order['shipping_phone']) ?></div>
        <div>Address: <?= sanitize($order['shipping_address']) ?></div>
        <div>District: <?= sanitize($order['district']) ?>, <?= sanitize($order['division']) ?></div>
      </div>

      <div style="background:var(--haat-cream); padding:16px; border-radius:var(--radius-md);">
        <strong style="color:var(--haat-green-dark); display:block; margin-bottom:6px;">Delivery Details:</strong>
        <div>Status: <span class="badge badge-<?= $order['order_status'] === 'delivered' ? 'green' : 'gold' ?>"><?= strtoupper($order['order_status']) ?></span></div>
        <div>Estimated Delivery: 2-3 Business Days</div>
        <?php if ($order['notes']): ?>
          <div>Notes: <?= sanitize($order['notes']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Items Table -->
    <table style="width:100%; border-collapse:collapse; margin-bottom:24px; font-size:0.92rem;">
      <thead>
        <tr style="background:var(--haat-sand); text-align:left; border-bottom:2px solid var(--haat-border);">
          <th style="padding:10px 12px;">Craft Item</th>
          <th style="padding:10px 12px;">Artisan Workshop</th>
          <th style="padding:10px 12px; text-align:center;">Qty</th>
          <th style="padding:10px 12px; text-align:right;">Unit Price</th>
          <th style="padding:10px 12px; text-align:right;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr style="border-bottom:1px solid var(--haat-border);">
            <td style="padding:12px; font-weight:600; color:var(--haat-green-dark);"><?= sanitize($it['product_name']) ?></td>
            <td style="padding:12px; color:var(--haat-clay); font-size:0.85rem;"><?= sanitize($it['shop_name']) ?></td>
            <td style="padding:12px; text-align:center;"><?= $it['quantity'] ?></td>
            <td style="padding:12px; text-align:right;"><?= formatPrice($it['price']) ?></td>
            <td style="padding:12px; text-align:right; font-weight:700; color:var(--haat-green);"><?= formatPrice($it['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Totals -->
    <div style="display:flex; justify-content:flex-end;">
      <div style="width:280px; display:flex; flex-direction:column; gap:8px; font-size:0.92rem;">
        <div style="display:flex; justify-content:space-between;">
          <span style="color:var(--text-muted);">Items Subtotal:</span>
          <strong><?= formatPrice($order['total_amount']) ?></strong>
        </div>
        <?php if ($order['discount_amount'] > 0): ?>
          <div style="display:flex; justify-content:space-between; color:#c52828;">
            <span>Discount:</span>
            <strong>- <?= formatPrice($order['discount_amount']) ?></strong>
          </div>
        <?php endif; ?>
        <div style="display:flex; justify-content:space-between;">
          <span style="color:var(--text-muted);">Delivery Fee:</span>
          <strong><?= formatPrice($order['shipping_cost']) ?></strong>
        </div>
        <div style="border-top:2px solid var(--haat-border); padding-top:8px; display:flex; justify-content:space-between; font-size:1.2rem;">
          <strong style="color:var(--haat-green-dark);">Grand Total:</strong>
          <strong style="color:var(--haat-green);"><?= formatPrice($order['grand_total']) ?></strong>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
