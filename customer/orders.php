<?php
/**
 * Customer Orders List
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/../includes/functions.php';
requireCustomer();
header('Location: ' . BASE_URL . 'customer/#orders');
exit;
?>

<div class="container" style="padding: 30px 20px;">
  
  <div style="margin-bottom:28px;">
    <h1 style="font-size:1.8rem; color:var(--haat-green-dark); margin-bottom:4px;">
      <i class="bi bi-bag-check text-clay"></i> My Order History
    </h1>
    <p style="color:var(--text-muted); font-size:0.92rem;">Review your previous artisan orders and track active dispatches</p>
  </div>

  <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:28px; box-shadow:var(--shadow-sm);">
    <?php if (empty($orders)): ?>
      <div style="text-align:center; padding:40px 0;">
        <i class="bi bi-box-seam text-muted" style="font-size:3rem; display:block; margin-bottom:12px;"></i>
        <h3>No Orders Found</h3>
        <p style="color:var(--text-muted); margin-bottom:20px;">You haven't ordered any artisanal crafts yet.</p>
        <a href="<?= BASE_URL ?>shop.php" class="btn btn-clay">Explore Haat Bazaar</a>
      </div>
    <?php else: ?>
      <table style="width:100%; border-collapse:collapse; font-size:0.92rem;">
        <thead>
          <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; color:var(--text-muted); font-size:0.82rem; text-transform:uppercase;">
            <th style="padding:12px 10px;">Order #</th>
            <th style="padding:12px 10px;">Placed On</th>
            <th style="padding:12px 10px;">Recipient</th>
            <th style="padding:12px 10px;">Amount</th>
            <th style="padding:12px 10px;">Payment</th>
            <th style="padding:12px 10px;">Status</th>
            <th style="padding:12px 10px; text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $ord): ?>
            <tr style="border-bottom:1px solid var(--haat-border);">
              <td style="padding:16px 10px; font-weight:700; color:var(--haat-green-dark);">
                <?= sanitize($ord['order_number']) ?>
              </td>
              <td style="padding:16px 10px; color:var(--text-muted); font-size:0.85rem;">
                <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
              </td>
              <td style="padding:16px 10px;">
                <strong><?= sanitize($ord['shipping_name']) ?></strong>
                <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($ord['district']) ?></div>
              </td>
              <td style="padding:16px 10px; font-weight:700; color:var(--haat-green); font-size:1.05rem;">
                <?= formatPrice($ord['grand_total']) ?>
              </td>
              <td style="padding:16px 10px;">
                <span class="pay-badge pay-<?= $ord['payment_method'] ?>" style="font-size:0.75rem;">
                  <?= strtoupper($ord['payment_method']) ?>
                </span>
                <span style="font-size:0.75rem; display:block; color:var(--text-muted); text-transform:capitalize;">
                  (<?= $ord['payment_status'] ?>)
                </span>
              </td>
              <td style="padding:16px 10px;">
                <span class="badge badge-<?= $ord['order_status'] === 'delivered' ? 'green' : 'gold' ?>">
                  <?= ucfirst($ord['order_status']) ?>
                </span>
              </td>
              <td style="padding:16px 10px; text-align:right;">
                <a href="<?= BASE_URL ?>order-confirmation.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-outline-green" style="margin-right:6px;">
                  <i class="bi bi-receipt"></i> Invoice
                </a>
                <a href="<?= BASE_URL ?>track-order.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-clay">
                  <i class="bi bi-truck"></i> Track
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
