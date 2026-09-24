<?php
/**
 * Customer Profile Page
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/../includes/functions.php';
requireCustomer();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!empty($name)) {
        $up = $db->prepare("UPDATE `users` SET `name` = ?, `phone` = ? WHERE `id` = ?");
        $up->execute([$name, $phone, $user['id']]);
        $_SESSION['user_name'] = $name;
        setFlash('success', 'Profile information updated successfully.');
    }
    header('Location: ' . BASE_URL . 'customer/#profile');
    exit;
}

header('Location: ' . BASE_URL . 'customer/#profile');
exit;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 30px 20px;">
  <div style="max-width:600px; margin:0 auto; background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:36px; box-shadow:var(--shadow-sm);">
    <h1 style="font-size:1.6rem; color:var(--haat-green-dark); margin-bottom:6px;">
      <i class="bi bi-person-gear text-clay"></i> Account Profile
    </h1>
    <p style="color:var(--text-muted); font-size:0.88rem; margin-bottom:24px;">Update your name, contact phone, and delivery defaults</p>

    <form method="POST" action="<?= BASE_URL ?>customer/profile.php">
      <div style="margin-bottom:16px;">
        <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Full Name</label>
        <input type="text" name="name" required value="<?= sanitize($user['name']) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
      </div>

      <div style="margin-bottom:16px;">
        <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Email Address (Read Only)</label>
        <input type="email" disabled value="<?= sanitize($user['email']) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; background:#f4f4f4; color:#777;">
      </div>

      <div style="margin-bottom:24px;">
        <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Phone Number (Bangladeshi)</label>
        <input type="text" name="phone" value="<?= sanitize($user['phone'] ?? '') ?>" placeholder="01XXXXXXXXX" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
      </div>

      <button type="submit" class="btn btn-clay">Save Profile Changes</button>
      <a href="<?= BASE_URL ?>customer/" class="btn btn-outline-green" style="margin-left:10px;">Back to Dashboard</a>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
