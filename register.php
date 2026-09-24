<?php
/**
 * Registration Page (Customer & Seller)
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL);
    exit;
}

$type = $_GET['type'] ?? 'customer'; // customer or seller
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] === 'seller' ? 'seller' : 'customer';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Seller fields
    $shopName = trim($_POST['shop_name'] ?? '');
    $district = trim($_POST['district'] ?? 'Dhaka');
    $division = trim($_POST['division'] ?? 'Dhaka');
    $bkashNumber = trim($_POST['bkash_number'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($role === 'seller' && empty($shopName)) {
        $error = 'Please provide your Artisan Workshop / Shop Name.';
    } else {
        // Check if email already registered
        $chk = $db->prepare("SELECT id FROM `users` WHERE `email` = ? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'This email address is already registered. Please sign in instead.';
        } else {
            try {
                $db->beginTransaction();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insUser = $db->prepare("INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`) VALUES (?, ?, ?, ?, ?, 'active')");
                $insUser->execute([$name, $email, $hash, $phone, $role]);
                $userId = $db->lastInsertId();

                if ($role === 'seller') {
                    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $shopName)) . '-' . rand(100, 999);
                    $insSeller = $db->prepare("INSERT INTO `sellers` 
                        (`user_id`, `shop_name`, `shop_slug`, `description`, `phone`, `district`, `division`, `bkash_number`, `is_verified`, `status`) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'active')");
                    $insSeller->execute([$userId, $shopName, $slug, $description, $phone, $district, $division, $bkashNumber]);
                }

                $db->commit();

                // Auto login
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_role'] = $role;
                $_SESSION['user_name'] = $name;

                setFlash('success', 'Welcome to HAAT! Your account has been created successfully.');

                if ($role === 'seller') {
                    header('Location: ' . BASE_URL . 'seller/');
                } else {
                    header('Location: ' . BASE_URL . 'customer/');
                }
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Join HAAT — Create Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 40px 20px;">
  <div style="max-width:580px; margin:0 auto; background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:36px; box-shadow:var(--shadow-md);">
    
    <div style="text-align:center; margin-bottom:24px;">
      <img src="<?= BASE_URL ?>assets/images/logo.png" alt="HAAT Logo" style="height:55px; margin:0 auto 12px;">
      <h1 style="font-size:1.6rem; color:var(--haat-green-dark); margin-bottom:4px;">Create Your HAAT Account</h1>
      <p style="font-size:0.88rem; color:var(--text-muted);">Choose your account type to get started</p>
    </div>

    <!-- Account Role Selector Tabs -->
    <div style="display:flex; border:1px solid var(--haat-border); border-radius:var(--radius-md); overflow:hidden; margin-bottom:24px;">
      <a href="<?= BASE_URL ?>register.php?type=customer" style="flex:1; text-align:center; padding:12px; font-weight:700; font-size:0.92rem; background:<?= $type === 'customer' ? 'var(--haat-green)' : '#fff' ?>; color:<?= $type === 'customer' ? '#fff' : 'var(--text-main)' ?>;">
        <i class="bi bi-person"></i> Customer / Buyer
      </a>
      <a href="<?= BASE_URL ?>register.php?type=seller" style="flex:1; text-align:center; padding:12px; font-weight:700; font-size:0.92rem; background:<?= $type === 'seller' ? 'var(--haat-clay)' : '#fff' ?>; color:<?= $type === 'seller' ? '#fff' : 'var(--text-main)' ?>;">
        <i class="bi bi-shop"></i> Artisan Seller
      </a>
    </div>

    <?php if ($error): ?>
      <div style="background:#fdeeed; border:1px solid rgba(197,40,40,0.3); border-radius:var(--radius-sm); padding:10px 14px; color:#c52828; font-size:0.88rem; margin-bottom:18px;">
        <i class="bi bi-exclamation-circle-fill"></i> <?= sanitize($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>register.php?type=<?= $type ?>">
      <input type="hidden" name="role" value="<?= $type ?>">

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
        <div>
          <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Full Name *</label>
          <input type="text" name="name" required placeholder="e.g. Al-Amin Mia" value="<?= sanitize($_POST['name'] ?? '') ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
        </div>

        <div>
          <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Phone Number *</label>
          <input type="text" name="phone" required placeholder="01XXXXXXXXX" value="<?= sanitize($_POST['phone'] ?? '') ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
        </div>
      </div>

      <div style="margin-bottom:16px;">
        <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Email Address *</label>
        <input type="email" name="email" required placeholder="name@email.com" value="<?= sanitize($_POST['email'] ?? '') ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
      </div>

      <div style="margin-bottom:<?= $type === 'seller' ? '20px' : '24px' ?>;">
        <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Password (Min 6 chars) *</label>
        <input type="password" name="password" required placeholder="••••••••" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
      </div>

      <!-- Extra fields for Artisan Seller -->
      <?php if ($type === 'seller'): ?>
        <div style="background:var(--haat-sand); border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; margin-bottom:24px;">
          <h4 style="font-size:0.98rem; margin-bottom:12px; color:var(--haat-clay);"><i class="bi bi-shop"></i> Artisan Workshop Information</h4>
          
          <div style="margin-bottom:12px;">
            <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Shop / Guild Name *</label>
            <input type="text" name="shop_name" required placeholder="e.g. Tangail Handloom Weavers" value="<?= sanitize($_POST['shop_name'] ?? '') ?>" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; background:#fff; outline:none;">
          </div>

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
            <div>
              <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Division *</label>
              <select name="division" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; background:#fff; outline:none;">
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
              <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">District Origin *</label>
              <input type="text" name="district" required placeholder="e.g. Tangail, Cumilla" value="<?= sanitize($_POST['district'] ?? '') ?>" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; background:#fff; outline:none;">
            </div>
          </div>

          <div style="margin-bottom:12px;">
            <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">bKash Payout Number *</label>
            <input type="text" name="bkash_number" placeholder="01XXXXXXXXX for receiving sales revenue" value="<?= sanitize($_POST['bkash_number'] ?? '') ?>" style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; background:#fff; outline:none;">
          </div>

          <div>
            <label style="font-weight:600; font-size:0.85rem; display:block; margin-bottom:4px;">Artisan Bio & Craft Specialty</label>
            <textarea name="description" rows="2" placeholder="Briefly describe your craft, technique, and village workshop..." style="width:100%; padding:9px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.9rem; background:#fff; outline:none;"><?= sanitize($_POST['description'] ?? '') ?></textarea>
          </div>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn <?= $type === 'seller' ? 'btn-clay' : 'btn-primary' ?> btn-lg" style="width:100%; margin-bottom:18px;">
        <?= $type === 'seller' ? 'Register As Artisan Seller' : 'Register Customer Account' ?>
      </button>

      <div style="text-align:center; font-size:0.88rem; color:var(--text-muted);">
        Already have an account? <a href="<?= BASE_URL ?>login.php" style="color:var(--haat-green); font-weight:700;">Sign In &rarr;</a>
      </div>
    </form>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
