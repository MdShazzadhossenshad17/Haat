<?php
/**
 * Checkout Page with Bangladeshi Payment Gateways
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/includes/functions.php';

$cart = getCart();
if (empty($cart)) {
    header('Location: ' . BASE_URL . 'cart.php');
    exit;
}

$user = currentUser();
$subtotal = getCartSubtotal();
$discountAmount = 0;
$appliedCoupon = $_SESSION['applied_coupon'] ?? '';

if (!empty($appliedCoupon)) {
    $cStmt = $db->prepare("SELECT * FROM `coupons` WHERE `code` = ? AND `is_active` = 1 LIMIT 1");
    $cStmt->execute([$appliedCoupon]);
    $cpn = $cStmt->fetch();
    if ($cpn) {
        $discountAmount = ($subtotal * (int)$cpn['discount_percent']) / 100;
    }
}

$shipping = $subtotal >= 3000 ? 0.00 : 80.00;
$grandTotal = max(0, $subtotal - $discountAmount + $shipping);

$errors = [];

// Handle Order Placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shippingName = trim($_POST['shipping_name'] ?? '');
    $shippingPhone = trim($_POST['shipping_phone'] ?? '');
    $division = trim($_POST['division'] ?? 'Dhaka');
    $district = trim($_POST['district'] ?? 'Dhaka');
    $shippingAddress = trim($_POST['shipping_address'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'cod');
    $transactionId = trim($_POST['transaction_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($shippingName)) $errors[] = "Please provide your recipient name.";
    if (empty($shippingPhone)) $errors[] = "Please provide a valid Bangladeshi contact phone number.";
    if (empty($shippingAddress)) $errors[] = "Please specify full delivery address.";

    if (in_array($paymentMethod, ['bkash', 'nagad', 'rocket']) && empty($transactionId)) {
        $errors[] = "Please enter your " . strtoupper($paymentMethod) . " Transaction ID (TrxID).";
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $orderNumber = generateOrderNumber();
            $userId = $user ? $user['id'] : null;

            // If guest buyer, optionally create or link account
            if (!$userId) {
                // Check if user exists by phone/email or create guest user
                $chkUser = $db->prepare("SELECT id FROM `users` WHERE `phone` = ? LIMIT 1");
                $chkUser->execute([$shippingPhone]);
                $existingUser = $chkUser->fetch();
                if ($existingUser) {
                    $userId = $existingUser['id'];
                } else {
                    $dummyEmail = 'guest_' . time() . '_' . rand(100, 999) . '@haat.com.bd';
                    $dummyPass = password_hash('Guest@123', PASSWORD_DEFAULT);
                    $cUser = $db->prepare("INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`) VALUES (?, ?, ?, ?, 'customer')");
                    $cUser->execute([$shippingName, $dummyEmail, $dummyPass, $shippingPhone]);
                    $userId = $db->lastInsertId();
                }
            }

            $paymentStatus = in_array($paymentMethod, ['bkash', 'nagad', 'rocket']) ? 'paid' : 'unpaid';

            $orderStmt = $db->prepare("INSERT INTO `orders` 
                (`order_number`, `user_id`, `total_amount`, `shipping_cost`, `discount_amount`, `grand_total`, 
                 `payment_method`, `payment_status`, `transaction_id`, `order_status`, `shipping_name`, `shipping_phone`, 
                 `shipping_address`, `district`, `division`, `notes`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)");

            $orderStmt->execute([
                $orderNumber,
                $userId,
                $subtotal,
                $shipping,
                $discountAmount,
                $grandTotal,
                $paymentMethod,
                $paymentStatus,
                $transactionId,
                $shippingName,
                $shippingPhone,
                $shippingAddress,
                $district,
                $division,
                $notes
            ]);

            $orderId = $db->lastInsertId();

            // Insert Order Items
            $itemStmt = $db->prepare("INSERT INTO `order_items` 
                (`order_id`, `seller_id`, `product_id`, `product_name`, `price`, `quantity`, `subtotal`, `vendor_status`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");

            foreach ($cart as $item) {
                $itemSub = $item['price'] * $item['quantity'];
                $itemStmt->execute([
                    $orderId,
                    $item['seller_id'],
                    $item['id'],
                    $item['name'],
                    $item['price'],
                    $item['quantity'],
                    $itemSub
                ]);

                // Reduce product stock
                $db->exec("UPDATE `products` SET `stock_quantity` = GREATEST(0, stock_quantity - " . (int)$item['quantity'] . ") WHERE `id` = " . (int)$item['id']);
                
                // Update seller total sales
                $db->exec("UPDATE `sellers` SET `total_sales` = total_sales + {$itemSub} WHERE `id` = " . (int)$item['seller_id']);
            }

            // Create initial tracking event in database
            $trStmt = $db->prepare("INSERT INTO `order_tracking_events` 
                (`order_id`, `order_number`, `title`, `actor`, `location`, `status_key`, `note`, `created_at`) 
                VALUES (?, ?, 'Order Received & Placed', 'HAAT Marketplace System', ?, 'pending', 'Customer order received and assigned to respective artisan guilds.', NOW())");
            $trStmt->execute([$orderId, $orderNumber, $district . ', ' . $division]);

            $db->commit();

            // Clear Cart
            unset($_SESSION['cart']);
            unset($_SESSION['applied_coupon']);

            // Redirect to Confirmation
            header('Location: ' . BASE_URL . 'order-confirmation.php?order=' . urlencode($orderNumber));
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Error processing your order: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Secure Checkout';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 30px 20px;">
  <h1 style="font-size:1.8rem; margin-bottom:24px; color:var(--haat-green-dark);">
    <i class="bi bi-shield-check text-green"></i> HAAT Secure Checkout
  </h1>

  <?php if (!empty($errors)): ?>
    <div style="background:#fdeeed; border:1px solid rgba(197,40,40,0.3); border-radius:var(--radius-md); padding:16px 20px; color:#c52828; margin-bottom:24px;">
      <ul style="padding-left:18px;">
        <?php foreach ($errors as $err): ?>
          <li><?= sanitize($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>checkout.php">
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:32px; align-items:flex-start;">
      
      <!-- Delivery and Payment Information -->
      <div style="display:flex; flex-direction:column; gap:24px;">
        
        <!-- Shipping Address Card -->
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
          <h3 style="font-size:1.2rem; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid var(--haat-sand); color:var(--haat-green-dark); display:flex; align-items:center; gap:8px;">
            <i class="bi bi-geo-alt-fill text-clay"></i> 1. Delivery & Recipient Details
          </h3>

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
            <div>
              <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Full Name *</label>
              <input type="text" name="shipping_name" required value="<?= sanitize($_POST['shipping_name'] ?? ($user ? $user['name'] : '')) ?>" placeholder="e.g. Tanvir Hossain" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
            </div>

            <div>
              <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Contact Phone (01XXXXXXXXX) *</label>
              <input type="text" name="shipping_phone" required value="<?= sanitize($_POST['shipping_phone'] ?? ($user ? $user['phone'] : '')) ?>" placeholder="e.g. 01711223344" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
            </div>
          </div>

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
            <div>
              <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Division *</label>
              <select name="division" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
                <option value="Dhaka">Dhaka</option>
                <option value="Chittagong">Chittagong</option>
                <option value="Rajshahi">Rajshahi</option>
                <option value="Sylhet">Sylhet</option>
                <option value="Khulna">Khulna</option>
                <option value="Barisal">Barisal</option>
                <option value="Rangpur">Rangpur</option>
                <option value="Mymensingh">Mymensingh</option>
              </select>
            </div>

            <div>
              <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">District *</label>
              <input type="text" name="district" required value="<?= sanitize($_POST['district'] ?? 'Dhaka') ?>" placeholder="e.g. Dhaka, Cumilla, Narayanganj" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
            </div>
          </div>

          <div style="margin-bottom:16px;">
            <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Detailed Home / Office Address *</label>
            <textarea name="shipping_address" rows="3" required placeholder="House number, Road, Area, Landmark..." style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;"><?= sanitize($_POST['shipping_address'] ?? '') ?></textarea>
          </div>

          <div>
            <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Special Delivery Instructions (Optional)</label>
            <input type="text" name="notes" placeholder="e.g. Call before delivery, handle pottery with extra care" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
          </div>
        </div>

        <!-- Payment Method Card -->
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
          <h3 style="font-size:1.2rem; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid var(--haat-sand); color:var(--haat-green-dark); display:flex; align-items:center; gap:8px;">
            <i class="bi bi-wallet2 text-clay"></i> 2. Payment Method (Bangladeshi Gateways)
          </h3>

          <div style="display:flex; flex-direction:column; gap:14px; margin-bottom:20px;">
            
            <!-- Cash on Delivery -->
            <label style="border:2px solid var(--haat-border); border-radius:var(--radius-md); padding:16px; display:flex; align-items:center; gap:14px; cursor:pointer; transition:var(--transition);" onmouseover="this.style.borderColor='var(--haat-green)';" onmouseout="this.style.borderColor='var(--haat-border)';">
              <input type="radio" name="payment_method" value="cod" checked style="accent-color:var(--haat-green); transform:scale(1.2);">
              <div style="flex:1;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                  <strong style="color:var(--haat-green-dark);"><i class="bi bi-cash-stack text-green"></i> Cash on Delivery (COD)</strong>
                  <span class="badge badge-green">Recommended</span>
                </div>
                <div style="font-size:0.82rem; color:var(--text-muted); margin-top:2px;">
                  Inspect your authentic handcrafted items at your doorstep before paying in cash.
                </div>
              </div>
            </label>

            <!-- bKash -->
            <label style="border:2px solid var(--haat-border); border-radius:var(--radius-md); padding:16px; display:flex; align-items:center; gap:14px; cursor:pointer; transition:var(--transition);" onmouseover="this.style.borderColor='#e2136e';" onmouseout="this.style.borderColor='var(--haat-border)';">
              <input type="radio" name="payment_method" value="bkash" style="accent-color:#e2136e; transform:scale(1.2);">
              <div style="flex:1;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                  <strong style="color:#e2136e;"><i class="bi bi-phone"></i> bKash Online / Mobile Payment</strong>
                  <span class="pay-badge pay-bkash" style="font-size:0.75rem;">bKash</span>
                </div>
                <div style="font-size:0.82rem; color:var(--text-muted); margin-top:2px;">
                  Instant digital payment via bKash Merchant / Personal wallet.
                </div>
              </div>
            </label>

            <!-- Nagad -->
            <label style="border:2px solid var(--haat-border); border-radius:var(--radius-md); padding:16px; display:flex; align-items:center; gap:14px; cursor:pointer; transition:var(--transition);" onmouseover="this.style.borderColor='#f7941d';" onmouseout="this.style.borderColor='var(--haat-border)';">
              <input type="radio" name="payment_method" value="nagad" style="accent-color:#f7941d; transform:scale(1.2);">
              <div style="flex:1;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                  <strong style="color:#d97008;"><i class="bi bi-wallet-fill"></i> Nagad Digital Wallet</strong>
                  <span class="pay-badge pay-nagad" style="font-size:0.75rem;">Nagad</span>
                </div>
                <div style="font-size:0.82rem; color:var(--text-muted); margin-top:2px;">
                  Send money to official Haat Nagad account.
                </div>
              </div>
            </label>

            <!-- Rocket -->
            <label style="border:2px solid var(--haat-border); border-radius:var(--radius-md); padding:16px; display:flex; align-items:center; gap:14px; cursor:pointer;">
              <input type="radio" name="payment_method" value="rocket" style="accent-color:#8c3494; transform:scale(1.2);">
              <div style="flex:1;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                  <strong style="color:#8c3494;"><i class="bi bi-credit-card-2-front"></i> Dutch-Bangla Rocket</strong>
                  <span class="pay-badge pay-rocket" style="font-size:0.75rem;">Rocket</span>
                </div>
              </div>
            </label>

          </div>

          <!-- bKash / Mobile Wallet Transaction Box -->
          <div id="bkash-details-box" style="display:none; background:#fdf0f5; border:1px solid #f8c8dc; border-radius:var(--radius-md); padding:18px; margin-bottom:16px;">
            <h4 style="color:#e2136e; font-size:1rem; margin-bottom:8px;"><i class="bi bi-info-circle-fill"></i> bKash Payment Instructions</h4>
            <ol style="padding-left:18px; font-size:0.88rem; line-height:1.6; color:#491223; margin-bottom:14px;">
              <li>Open your bKash App or dial *247#</li>
              <li>Select <strong>Send Money</strong> to Merchant/Personal No: <strong>01711-000001</strong></li>
              <li>Amount: <strong><?= formatPrice($grandTotal) ?></strong></li>
              <li>Reference: <strong>HAAT</strong></li>
              <li>Copy the 10-digit <strong>Transaction ID (TrxID)</strong> and paste it below:</li>
            </ol>
            <div>
              <label style="font-weight:700; font-size:0.88rem; display:block; margin-bottom:4px; color:#e2136e;">bKash Transaction ID (TrxID) *</label>
              <input type="text" name="transaction_id" placeholder="e.g. 9B81AX2034" style="width:100%; padding:10px 14px; border:1px solid #e2136e; border-radius:var(--radius-sm); font-size:0.95rem; font-weight:700; text-transform:uppercase; outline:none;">
            </div>
          </div>

          <div id="nagad-details-box" style="display:none; background:#fff7ed; border:1px solid #fed7aa; border-radius:var(--radius-md); padding:18px; margin-bottom:16px;">
            <h4 style="color:#ea580c; font-size:1rem; margin-bottom:8px;">Nagad Payment Instructions</h4>
            <p style="font-size:0.88rem; color:#7c2d12; margin-bottom:10px;">
              Send <strong><?= formatPrice($grandTotal) ?></strong> to Nagad wallet: <strong>01711-000001</strong> and enter your TrxID below:
            </p>
            <input type="text" name="transaction_id" placeholder="Nagad TrxID (e.g. NG71B8291)" style="width:100%; padding:10px 14px; border:1px solid #ea580c; border-radius:var(--radius-sm); font-size:0.95rem; font-weight:700; text-transform:uppercase; outline:none;">
          </div>

        </div>

      </div>

      <!-- Order Review Sidebar -->
      <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm); position:sticky; top:100px;">
        <h3 style="font-size:1.2rem; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid var(--haat-sand); color:var(--haat-green-dark);">
          Order Summary (<?= count($cart) ?> items)
        </h3>

        <!-- Mini items list -->
        <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:20px; max-height:260px; overflow-y:auto; padding-right:6px;">
          <?php foreach ($cart as $item): ?>
            <div style="display:flex; align-items:center; gap:12px; font-size:0.88rem;">
              <img src="<?= sanitize($item['image']) ?>" alt="thumb" style="width:46px; height:46px; border-radius:var(--radius-sm); object-fit:cover; border:1px solid var(--haat-border);">
              <div style="flex:1; overflow:hidden;">
                <div style="font-weight:600; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;"><?= sanitize($item['name']) ?></div>
                <div style="font-size:0.75rem; color:var(--text-muted);"><?= $item['quantity'] ?> × <?= formatPrice($item['price']) ?></div>
              </div>
              <strong style="color:var(--haat-green); font-size:0.9rem;"><?= formatPrice($item['price'] * $item['quantity']) ?></strong>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px; font-size:0.92rem; border-top:1px solid var(--haat-border); padding-top:16px;">
          <div style="display:flex; justify-content:space-between;">
            <span style="color:var(--text-muted);">Subtotal:</span>
            <strong><?= formatPrice($subtotal) ?></strong>
          </div>
          <?php if ($discountAmount > 0): ?>
            <div style="display:flex; justify-content:space-between; color:#c52828;">
              <span>Coupon Discount:</span>
              <strong>- <?= formatPrice($discountAmount) ?></strong>
            </div>
          <?php endif; ?>
          <div style="display:flex; justify-content:space-between;">
            <span style="color:var(--text-muted);">Nationwide Delivery:</span>
            <strong><?= $shipping === 0.00 ? '<span class="badge badge-green">Free</span>' : formatPrice($shipping) ?></strong>
          </div>
          <div style="border-top:1px solid var(--haat-border); padding-top:12px; display:flex; justify-content:space-between; font-size:1.3rem;">
            <strong style="color:var(--haat-green-dark);">Total Amount:</strong>
            <strong style="color:var(--haat-green);"><?= formatPrice($grandTotal) ?></strong>
          </div>
        </div>

        <button type="submit" name="place_order" class="btn btn-clay btn-lg" style="width:100%; font-size:1.05rem;">
          <i class="bi bi-bag-check-fill"></i> Confirm & Place Order
        </button>

        <div style="margin-top:14px; text-align:center; font-size:0.75rem; color:var(--text-muted);">
          By placing your order, you support Bangladeshi rural artisans & weavers.
        </div>
      </div>

    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
