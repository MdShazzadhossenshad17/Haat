<?php
/**
 * User / Seller / Admin Login Page
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    if (isAdmin()) header('Location: ' . BASE_URL . 'admin/');
    elseif (isSeller()) header('Location: ' . BASE_URL . 'seller/');
    else header('Location: ' . BASE_URL . 'customer/');
    exit;
}

$error = '';
$accountType = $_GET['type'] ?? 'customer'; // customer, seller, admin

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        $stmt = $db->prepare("SELECT * FROM `users` WHERE `email` = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact HAAT support.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];

                // Sync guest wishlist items to database if any exist
                if (!empty($_SESSION['wishlist']) && is_array($_SESSION['wishlist'])) {
                    $wStmt = $db->prepare("INSERT IGNORE INTO `wishlists` (`user_id`, `product_id`) VALUES (?, ?)");
                    foreach ($_SESSION['wishlist'] as $pId) {
                        if ((int)$pId > 0) {
                            $wStmt->execute([$user['id'], (int)$pId]);
                        }
                    }
                    unset($_SESSION['wishlist']);
                }

                setFlash('success', 'Welcome back, ' . $user['name'] . '!');

                if ($user['role'] === 'admin') {
                    header('Location: ' . BASE_URL . 'admin/');
                } elseif ($user['role'] === 'logistics') {
                    header('Location: ' . BASE_URL . 'logistics/');
                } elseif ($user['role'] === 'seller') {
                    header('Location: ' . BASE_URL . 'seller/');
                } else {
                    $redirectParam = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
                    if (!empty($redirectParam)) {
                        header('Location: ' . BASE_URL . ltrim($redirectParam, '/'));
                    } else {
                        $redirect = !empty(getCart()) ? BASE_URL . 'checkout.php' : BASE_URL . 'customer/';
                        header('Location: ' . $redirect);
                    }
                }
                exit;
            }
        } else {
            $error = 'Invalid email or password combination.';
        }
    }
}

$pageTitle = 'Sign In to Your HAAT Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 40px 20px;">
  <div style="max-width:480px; margin:0 auto; background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:36px; box-shadow:var(--shadow-md);">
    
    <div style="text-align:center; margin-bottom:24px;">
      <img src="<?= BASE_URL ?>assets/images/logo.png" alt="HAAT Logo" style="height:55px; margin:0 auto 12px;">
      <h1 style="font-size:1.6rem; color:var(--haat-green-dark); margin-bottom:4px;">Welcome Back</h1>
      <p style="font-size:0.88rem; color:var(--text-muted);">Sign in to access your HAAT account</p>
    </div>

    <!-- Quick Demo Logins for Pair Programming / Review -->
    <div style="background:var(--haat-sand); border-radius:var(--radius-md); padding:14px; margin-bottom:20px; font-size:0.82rem;">
      <strong style="color:var(--haat-green-dark); display:block; margin-bottom:6px;"><i class="bi bi-lightning-charge-fill text-clay"></i> Quick 1-Click Demo Logins:</strong>
      <div style="display:flex; flex-direction:column; gap:6px;">
        <button type="button" onclick="fillLogin('admin@haat.com.bd', 'Admin@123')" class="btn btn-sm" style="background:#fff; border:1px solid var(--haat-border); justify-content:flex-start;">
          ⚙️ <strong>Admin Portal:</strong> admin@haat.com.bd (Admin@123)
        </button>
        <button type="button" onclick="fillLogin('logistics@haat.com.bd', 'Logistics@123')" class="btn btn-sm" style="background:#fff; border:1px solid var(--haat-border); justify-content:flex-start; color:var(--haat-clay); font-weight:700;">
          🚚 <strong>Admin Logistics (HAATEX):</strong> logistics@haat.com.bd (Logistics@123)
        </button>
        <button type="button" onclick="fillLogin('jamdani@haat.com.bd', 'Seller@123')" class="btn btn-sm" style="background:#fff; border:1px solid var(--haat-border); justify-content:flex-start;">
          🧵 <strong>Artisan Seller (Sonargaon Jamdani):</strong> jamdani@haat.com.bd (Seller@123)
        </button>
        <button type="button" onclick="fillLogin('honey@haat.com.bd', 'Seller@123')" class="btn btn-sm" style="background:#fff; border:1px solid var(--haat-border); justify-content:flex-start;">
          🍯 <strong>Artisan Seller (Sundarbans Honey):</strong> honey@haat.com.bd (Seller@123)
        </button>
        <button type="button" onclick="fillLogin('pottery@haat.com.bd', 'Seller@123')" class="btn btn-sm" style="background:#fff; border:1px solid var(--haat-border); justify-content:flex-start;">
          🏺 <strong>Artisan Seller (Bijoypur Pottery):</strong> pottery@haat.com.bd (Seller@123)
        </button>
        <button type="button" onclick="fillLogin('customer@haat.com.bd', 'Customer@123')" class="btn btn-sm" style="background:#fff; border:1px solid var(--haat-border); justify-content:flex-start;">
          👤 <strong>Customer Buyer (Tanvir Hossain):</strong> customer@haat.com.bd (Customer@123)
        </button>
      </div>
    </div>

    <script>
      function fillLogin(email, pass) {
        document.getElementById('login-email').value = email;
        document.getElementById('login-pass').value = pass;
      }
    </script>

    <?php if ($error): ?>
      <div style="background:#fdeeed; border:1px solid rgba(197,40,40,0.3); border-radius:var(--radius-sm); padding:10px 14px; color:#c52828; font-size:0.88rem; margin-bottom:18px;">
        <i class="bi bi-exclamation-circle-fill"></i> <?= sanitize($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>login.php">
      <?php if (!empty($_GET['redirect']) || !empty($_POST['redirect'])): ?>
        <input type="hidden" name="redirect" value="<?= sanitize($_GET['redirect'] ?? $_POST['redirect']) ?>">
      <?php endif; ?>
      <div style="margin-bottom:16px;">
        <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Email Address *</label>
        <input type="email" id="login-email" name="email" required placeholder="name@email.com" value="<?= sanitize($_POST['email'] ?? '') ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
      </div>

      <div style="margin-bottom:20px;">
        <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Password *</label>
        <input type="password" id="login-pass" name="password" required placeholder="••••••••" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
      </div>

      <button type="submit" class="btn btn-clay btn-lg" style="width:100%; margin-bottom:18px;">
        Sign In to HAAT
      </button>

      <div style="text-align:center; font-size:0.88rem; color:var(--text-muted);">
        Don't have an account yet? <a href="<?= BASE_URL ?>register.php" style="color:var(--haat-green); font-weight:700;">Create Account &rarr;</a>
      </div>
    </form>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
