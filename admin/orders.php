<?php
/**
 * Admin Platform Orders Management
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Handle Order Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $orderId = (int)$_POST['order_id'];
    $orderStatus = $_POST['order_status'];
    $paymentStatus = $_POST['payment_status'];

    $up = $db->prepare("UPDATE `orders` SET `order_status` = ?, `payment_status` = ? WHERE `id` = ?");
    $up->execute([$orderStatus, $paymentStatus, $orderId]);

    // Also log tracking event
    $ordInfo = $db->query("SELECT user_id, order_number, courier_partner FROM `orders` WHERE `id` = {$orderId}")->fetch();
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
            $ordInfo['courier_partner'] ?? 'Delivery Partner Logistics',
            'Central Logistics Hub (Dhaka)',
            $orderStatus,
            "Platform updated overall delivery status to " . strtoupper($orderStatus) . "."
        ]);
        if ($orderStatus === 'delivered' || $orderStatus === 'shipped') {
            $db->prepare("UPDATE `order_items` SET `vendor_status` = ? WHERE `order_id` = ?")->execute([$orderStatus, $orderId]);
        }

        // Order Notifications
        if ($orderStatus === 'shipped' || $orderStatus === 'delivered' || $orderStatus === 'cancelled') {
            dismissOrderNotifications($ordInfo['order_number']);
        } elseif ($orderStatus === 'processing') {
            createNotification(
                $ordInfo['user_id'],
                "Crafting in Progress: #{$ordInfo['order_number']}",
                "Artisans have begun preparing your handcrafted items.",
                "order_processing",
                BASE_URL . "track-order.php?order=" . urlencode($ordInfo['order_number']),
                null,
                $ordInfo['order_number']
            );
        }
    }

    setFlash('success', 'Order status and payment details updated and pushed to live tracking.');
    header('Location: ' . BASE_URL . 'admin/orders.php');
    exit;
}

// Fetch all orders
$orders = $db->query("SELECT * FROM `orders` ORDER BY id DESC")->fetchAll();

$pageTitle = 'Manage Platform Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 30px 20px;">
  
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px;">
    <div>
      <h1 style="font-size:1.8rem; color:var(--haat-green-dark); margin-bottom:4px;">
        <i class="bi bi-receipt text-clay"></i> Platform Orders & Transactions (<?= count($orders) ?>)
      </h1>
      <p style="color:var(--text-muted); font-size:0.92rem;">Audit orders, verify bKash/Nagad transactions, and oversee nationwide deliveries</p>
    </div>
    <a href="<?= BASE_URL ?>admin/" class="btn btn-sm btn-outline-green">&larr; Back to Dashboard</a>
  </div>

  <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:24px; box-shadow:var(--shadow-sm);">
    <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
      <thead>
        <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
          <th style="padding:12px 10px;">Order #</th>
          <th style="padding:12px 10px;">Placed On</th>
          <th style="padding:12px 10px;">Buyer / Recipient</th>
          <th style="padding:12px 10px;">Amount</th>
          <th style="padding:12px 10px;">Payment Gateway</th>
          <th style="padding:12px 10px;">Delivery Status</th>
          <th style="padding:12px 10px; text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $ord): ?>
          <tr style="border-bottom:1px solid var(--haat-border);">
            <td style="padding:16px 10px; font-weight:700; color:var(--haat-green-dark);">
              <?= sanitize($ord['order_number']) ?>
            </td>
            <td style="padding:16px 10px; color:var(--text-muted); font-size:0.82rem;">
              <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
            </td>
            <td style="padding:16px 10px; font-size:0.85rem;">
              <strong><?= sanitize($ord['shipping_name']) ?></strong> (<?= sanitize($ord['shipping_phone']) ?>)<br>
              <span style="color:var(--text-muted);"><?= sanitize($ord['shipping_address']) ?>, <?= sanitize($ord['district']) ?></span>
            </td>
            <td style="padding:16px 10px; font-weight:800; color:var(--haat-green); font-size:1.05rem;">
              <?= formatPrice($ord['grand_total']) ?>
            </td>
            <td style="padding:16px 10px;">
              <span class="pay-badge pay-<?= $ord['payment_method'] ?>" style="font-size:0.75rem;">
                <?= strtoupper($ord['payment_method']) ?>
              </span>
              <div style="font-size:0.75rem; margin-top:2px;">
                Status: <strong><?= ucfirst($ord['payment_status']) ?></strong>
              </div>
              <?php if ($ord['transaction_id']): ?>
                <div style="font-size:0.72rem; color:#e2136e; font-weight:700;">TrxID: <?= sanitize($ord['transaction_id']) ?></div>
              <?php endif; ?>
            </td>
            <td style="padding:16px 10px;">
              <span class="badge badge-<?= $ord['order_status'] === 'delivered' ? 'green' : ($ord['order_status'] === 'pending' ? 'gold' : 'clay') ?>">
                <?= ucfirst($ord['order_status']) ?>
              </span>
            </td>
            <td style="padding:16px 10px; text-align:right;">
              <form method="POST" action="<?= BASE_URL ?>admin/orders.php" style="display:inline-flex; align-items:center; gap:6px;">
                <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                <select name="order_status" style="padding:4px 8px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.8rem;">
                  <option value="pending" <?= $ord['order_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                  <option value="processing" <?= $ord['order_status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                  <option value="shipped" <?= $ord['order_status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                  <option value="delivered" <?= $ord['order_status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                  <option value="cancelled" <?= $ord['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <select name="payment_status" style="padding:4px 8px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.8rem;">
                  <option value="paid" <?= $ord['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                  <option value="unpaid" <?= $ord['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                  <option value="refunded" <?= $ord['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                </select>
                <button type="submit" name="update_order" class="btn btn-sm btn-outline-green" style="padding:4px 8px;" title="Update">
                  <i class="bi bi-check-lg"></i>
                </button>
                <a href="<?= BASE_URL ?>invoice.php?order=<?= urlencode($ord['order_number']) ?>" target="_blank" class="btn btn-sm btn-outline-clay" style="padding:4px 8px; margin-left:2px;" title="Print Official Invoice">
                  <i class="bi bi-printer"></i>
                </a>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
