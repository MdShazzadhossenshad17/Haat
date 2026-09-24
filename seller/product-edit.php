<?php
/**
 * Edit Product Form
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/../includes/functions.php';
requireSeller();

$seller = currentSeller();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM `products` WHERE `id` = ? AND `seller_id` = ? LIMIT 1");
$stmt->execute([$id, $seller['id']]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . BASE_URL . 'seller/products.php');
    exit;
}

$categories = $db->query("SELECT * FROM `categories` ORDER BY name ASC")->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $salePrice = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $stock = (int)($_POST['stock_quantity'] ?? 0);
    $shortDesc = trim($_POST['short_description'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $isFlash = isset($_POST['is_flash_deal']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name) || $categoryId <= 0 || $price <= 0) {
        $error = 'Please fill in all required fields.';
    } else {
        $up = $db->prepare("UPDATE `products` SET 
            `name` = ?, `category_id` = ?, `price` = ?, `sale_price` = ?, 
            `stock_quantity` = ?, `short_description` = ?, `description` = ?, 
            `is_featured` = ?, `is_flash_deal` = ?, `is_active` = ? 
            WHERE `id` = ? AND `seller_id` = ?");

        $up->execute([
            $name, $categoryId, $price, $salePrice, 
            $stock, $shortDesc, $desc, 
            $isFeatured, $isFlash, $isActive, 
            $id, $seller['id']
        ]);

        setFlash('success', 'Product details updated successfully.');
        header('Location: ' . BASE_URL . 'seller/#products');
        exit;
    }
}

$hideNavbar = true;
$hideCart = true;
$pageTitle = 'Edit Product — ' . $product['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 30px 20px;">
  <div style="max-width:800px; margin:0 auto; background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:36px; box-shadow:var(--shadow-sm);">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
      <div>
        <h1 style="font-size:1.6rem; color:var(--haat-green-dark); margin:0;">
          <i class="bi bi-pencil text-clay"></i> Edit Product
        </h1>
        <p style="color:var(--text-muted); font-size:0.88rem;">Update pricing, stock availability, or description</p>
      </div>
      <a href="<?= BASE_URL ?>seller/#products" class="btn btn-sm btn-outline-green">&larr; Back to Products</a>
    </div>

    <?php if ($error): ?>
      <div style="background:#fdeeed; border:1px solid rgba(197,40,40,0.3); border-radius:var(--radius-sm); padding:10px 14px; color:#c52828; font-size:0.88rem; margin-bottom:20px;">
        <i class="bi bi-exclamation-circle-fill"></i> <?= sanitize($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>seller/product-edit.php?id=<?= $product['id'] ?>">
      
      <div style="margin-bottom:16px;">
        <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Product Name *</label>
        <input type="text" name="name" required value="<?= sanitize($product['name']) ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
        <div>
          <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Craft Category *</label>
          <select name="category_id" required style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none; background:#fff;">
            <?php 
            $currDept = '';
            foreach ($categories as $cat): 
                if ($cat['department'] !== $currDept):
                    if ($currDept !== '') echo '</optgroup>';
                    $currDept = $cat['department'];
                    echo '<optgroup label="' . htmlspecialchars($currDept) . '">';
                endif;
            ?>
              <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
            <?php endforeach; if ($currDept !== '') echo '</optgroup>'; ?>
          </select>
        </div>

        <div>
          <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Stock Quantity</label>
          <input type="number" name="stock_quantity" value="<?= $product['stock_quantity'] ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
        </div>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
        <div>
          <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Regular Price (৳) *</label>
          <input type="number" step="0.01" name="price" required value="<?= $product['price'] ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
        </div>

        <div>
          <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Discount / Sale Price (৳)</label>
          <input type="number" step="0.01" name="sale_price" value="<?= $product['sale_price'] ?>" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;">
        </div>
      </div>

      <div style="margin-bottom:16px;">
        <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Short Summary</label>
        <textarea name="short_description" rows="2" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;"><?= sanitize($product['short_description']) ?></textarea>
      </div>

      <div style="margin-bottom:20px;">
        <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Artisan Story & Craft Specifications</label>
        <textarea name="description" rows="4" style="width:100%; padding:10px 14px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.92rem; outline:none;"><?= sanitize($product['description']) ?></textarea>
      </div>

      <div style="display:flex; gap:20px; margin-bottom:24px;">
        <label style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:0.88rem; cursor:pointer;">
          <input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?> style="accent-color:var(--haat-green);"> Active on Marketplace
        </label>
        <label style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:0.88rem; cursor:pointer;">
          <input type="checkbox" name="is_featured" value="1" <?= $product['is_featured'] ? 'checked' : '' ?> style="accent-color:var(--haat-green);"> Feature on Homepage
        </label>
        <label style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:0.88rem; cursor:pointer;">
          <input type="checkbox" name="is_flash_deal" value="1" <?= $product['is_flash_deal'] ? 'checked' : '' ?> style="accent-color:var(--haat-clay);"> Flash Deal
        </label>
      </div>

      <button type="submit" class="btn btn-clay btn-lg" style="width:100%;">
        Save Product Changes
      </button>

    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
