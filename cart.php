<?php
/**
 * Shopping Cart Page
 * HAAT Multi-Vendor Marketplace
 */
$pageTitle = 'My Haat Cart';
require_once __DIR__ . '/includes/header.php';

// Handle Coupon application
$couponCode = $_POST['coupon_code'] ?? $_SESSION['applied_coupon'] ?? '';
$discountPercent = 0;
$discountAmount = 0;

if (!empty($couponCode)) {
    $cStmt = $db->prepare("SELECT * FROM `coupons` WHERE `code` = ? AND `is_active` = 1 LIMIT 1");
    $cStmt->execute([strtoupper(trim($couponCode))]);
    $cpn = $cStmt->fetch();
    if ($cpn) {
        $discountPercent = (int)$cpn['discount_percent'];
        $_SESSION['applied_coupon'] = $cpn['code'];
    }
}

$cart = getCart();
$subtotal = getCartSubtotal();
if ($discountPercent > 0) {
    $discountAmount = ($subtotal * $discountPercent) / 100;
}
$shipping = $subtotal > 0 ? ($subtotal >= 3000 ? 0.00 : 80.00) : 0.00;
$grandTotal = max(0, $subtotal - $discountAmount + $shipping);
?>

<div class="container" style="padding: 30px 20px;">
  <h1 style="font-size:1.8rem; margin-bottom:24px; color:var(--haat-green-dark);">
    <i class="bi bi-bag-check text-clay"></i> Your Haat Shopping Cart (<?= getCartCount() ?> items)
  </h1>

  <?php if (empty($cart)): ?>
    <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:60px 20px; text-align:center; box-shadow:var(--shadow-sm);">
      <i class="bi bi-cart-x text-muted" style="font-size:3.5rem; display:block; margin-bottom:14px;"></i>
      <h3 style="font-size:1.4rem; margin-bottom:8px;">Your shopping cart is empty</h3>
      <p style="color:var(--text-muted); margin-bottom:24px;">Explore authentic Bangladeshi artisanal crafts and add something wonderful to your basket!</p>
      <a href="<?= BASE_URL ?>shop.php" class="btn btn-clay btn-lg"><i class="bi bi-shop"></i> Explore Haat Crafts</a>
    </div>
  <?php else: ?>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:32px; align-items:flex-start;">
      
      <!-- Cart Items Table / List -->
      <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:2px solid var(--haat-sand); text-align:left; font-size:0.85rem; color:var(--text-muted); text-transform:uppercase;">
              <th style="padding-bottom:12px;">Craft Item</th>
              <th style="padding-bottom:12px;">Price</th>
              <th style="padding-bottom:12px;">Quantity</th>
              <th style="padding-bottom:12px;">Subtotal</th>
              <th style="padding-bottom:12px;"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cart as $item): 
              $itemSubtotal = $item['price'] * $item['quantity'];
            ?>
              <tr style="border-bottom:1px solid var(--haat-border);" id="cart-row-<?= $item['id'] ?>">
                <td style="padding:16px 0; display:flex; align-items:center; gap:16px;">
                  <div style="width:70px; height:70px; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--haat-border); flex-shrink:0;">
                    <img src="<?= sanitize($item['image']) ?>" alt="<?= sanitize($item['name']) ?>" style="width:100%; height:100%; object-fit:cover;">
                  </div>
                  <div>
                    <a href="<?= BASE_URL ?>product.php?id=<?= $item['id'] ?>" style="font-weight:700; color:var(--haat-green-dark); font-size:0.95rem; display:block; margin-bottom:4px;">
                      <?= sanitize($item['name']) ?>
                    </a>
                    <span style="font-size:0.78rem; color:var(--haat-clay); font-weight:600;">
                      <i class="bi bi-shop"></i> <?= sanitize($item['shop_name']) ?>
                    </span>
                  </div>
                </td>

                <td style="padding:16px 0; font-weight:600; color:var(--text-main);">
                  <?= formatPrice($item['price']) ?>
                </td>

                <td style="padding:16px 0;">
                  <div class="qty-wrapper" style="display:inline-flex; align-items:center; border:1px solid var(--haat-border); border-radius:var(--radius-sm); overflow:hidden;">
                    <button type="button" class="qty-btn-minus" onclick="changeQty(<?= $item['id'] ?>, -1)" style="border:none; background:transparent; width:30px; height:32px; cursor:pointer;">-</button>
                    <input type="text" id="qty-input-<?= $item['id'] ?>" value="<?= $item['quantity'] ?>" readonly style="width:36px; text-align:center; border:none; outline:none; font-weight:700; font-size:0.9rem;">
                    <button type="button" class="qty-btn-plus" onclick="changeQty(<?= $item['id'] ?>, 1)" style="border:none; background:transparent; width:30px; height:32px; cursor:pointer;">+</button>
                  </div>
                </td>

                <td style="padding:16px 0; font-weight:800; color:var(--haat-green); font-size:1.05rem;">
                  <?= formatPrice($itemSubtotal) ?>
                </td>

                <td style="padding:16px 0; text-align:right;">
                  <button type="button" onclick="removeItem(<?= $item['id'] ?>)" style="background:none; border:none; color:#c52828; font-size:1.1rem; cursor:pointer;" title="Remove Item">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center;">
          <a href="<?= BASE_URL ?>shop.php" style="color:var(--haat-clay); font-weight:600; font-size:0.9rem;">
            &larr; Continue Exploring Haat Crafts
          </a>
        </div>
      </div>

      <!-- Order Summary Card -->
      <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
        <h3 style="font-size:1.2rem; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid var(--haat-sand); color:var(--haat-green-dark);">
          Order Summary
        </h3>

        <!-- Coupon Form -->
        <form method="POST" style="margin-bottom:20px; display:flex; gap:8px;">
          <input type="text" name="coupon_code" placeholder="Enter coupon (e.g. HAAT10)" value="<?= sanitize($couponCode) ?>" style="flex:1; padding:8px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.85rem; text-transform:uppercase;">
          <button type="submit" class="btn btn-sm btn-outline-green">Apply</button>
        </form>

        <?php if ($discountPercent > 0): ?>
          <div style="background:#e3efe6; color:var(--haat-green); font-size:0.82rem; padding:8px 12px; border-radius:var(--radius-sm); margin-bottom:16px; font-weight:600;">
            <i class="bi bi-tag-fill"></i> Coupon <strong><?= sanitize($couponCode) ?></strong> applied (<?= $discountPercent ?>% Discount)!
          </div>
        <?php endif; ?>

        <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:20px; font-size:0.92rem;">
          <div style="display:flex; justify-content:space-between;">
            <span style="color:var(--text-muted);">Items Subtotal:</span>
            <strong style="color:var(--text-main);"><?= formatPrice($subtotal) ?></strong>
          </div>

          <?php if ($discountAmount > 0): ?>
            <div style="display:flex; justify-content:space-between; color:#c52828;">
              <span>Coupon Discount:</span>
              <strong>- <?= formatPrice($discountAmount) ?></strong>
            </div>
          <?php endif; ?>

          <div style="display:flex; justify-content:space-between;">
            <span style="color:var(--text-muted);">Nationwide Delivery:</span>
            <strong style="color:var(--text-main);"><?= $shipping === 0.00 ? '<span class="badge badge-green">Free</span>' : formatPrice($shipping) ?></strong>
          </div>

          <div style="border-top:1px solid var(--haat-border); padding-top:12px; display:flex; justify-content:space-between; font-size:1.25rem;">
            <strong style="color:var(--haat-green-dark);">Grand Total:</strong>
            <strong style="color:var(--haat-green); font-size:1.35rem;"><?= formatPrice($grandTotal) ?></strong>
          </div>
        </div>

        <a href="<?= BASE_URL ?>checkout.php" class="btn btn-clay btn-lg" style="width:100%; font-size:1.05rem;">
          Proceed to Checkout <i class="bi bi-arrow-right"></i>
        </a>

        <div style="margin-top:16px; font-size:0.8rem; color:var(--text-muted); text-align:center;">
          <i class="bi bi-shield-check text-green"></i> 100% Buyer Protection & Artisan Guarantee
        </div>
      </div>

    </div>

    <script>
      async function changeQty(productId, delta) {
        const input = document.getElementById('qty-input-' + productId);
        let current = parseInt(input.value) || 1;
        let newQty = current + delta;
        if (newQty < 1) newQty = 1;

        const fd = new FormData();
        fd.append('action', 'update');
        fd.append('product_id', productId);
        fd.append('quantity', newQty);

        await fetch('api/cart.php', { method: 'POST', body: fd });
        location.reload();
      }

      async function removeItem(productId) {
        if (!confirm('Remove this craft item from your cart?')) return;
        const fd = new FormData();
        fd.append('action', 'remove');
        fd.append('product_id', productId);

        await fetch('api/cart.php', { method: 'POST', body: fd });
        location.reload();
      }
    </script>

  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
