<?php
/**
 * Vendor / Seller Storefront Page
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/includes/functions.php';

$vendorId = (int)($_GET['id'] ?? 0);
if ($vendorId <= 0) {
    header('Location: ' . BASE_URL . 'shop.php');
    exit;
}

// Fetch Vendor Profile
$stmt = $db->prepare("SELECT s.*, u.name as owner_name, u.email as owner_email 
    FROM `sellers` s 
    JOIN `users` u ON s.user_id = u.id 
    WHERE s.id = ? AND s.status = 'active' LIMIT 1");
$stmt->execute([$vendorId]);
$seller = $stmt->fetch();

if (!$seller) {
    header('Location: ' . BASE_URL . 'shop.php');
    exit;
}

// Fetch Seller Products
$prodStmt = $db->prepare("SELECT p.*, c.name as category_name 
    FROM `products` p 
    JOIN `categories` c ON p.category_id = c.id 
    WHERE p.seller_id = ? AND p.is_active = 1 
    ORDER BY p.id DESC");
$prodStmt->execute([$vendorId]);
$products = $prodStmt->fetchAll();

$pageTitle = $seller['shop_name'] . ' — Artisan Workshop';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 30px 20px;">
  
  <!-- Vendor Banner & Profile Header -->
  <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); overflow:hidden; box-shadow:var(--shadow-sm); margin-bottom:36px;">
    <div style="height:180px; background:linear-gradient(135deg, var(--haat-green-dark), var(--haat-clay)); position:relative;"></div>
    
    <!-- Profile Body -->
    <div style="padding:0 36px 28px; position:relative;">
      <!-- Avatar & Titles Row -->
      <div style="display:flex; align-items:flex-end; gap:22px; margin-top:-54px; margin-bottom:16px; flex-wrap:wrap;">
        <!-- Logo -->
        <div style="width:108px; height:108px; border-radius:50%; border:4px solid #ffffff; background:#ffffff; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.14); flex-shrink:0; display:flex; align-items:center; justify-content:center;">
          <img src="<?= BASE_URL ?>assets/images/logo.png" alt="<?= sanitize($seller['shop_name']) ?>" style="width:100%; height:100%; object-fit:contain; padding:6px;">
        </div>

        <!-- Name & Meta -->
        <div style="flex:1; min-width:280px; padding-bottom:6px;">
          <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <h1 style="font-size:1.85rem; color:var(--haat-green-dark); margin:0; font-weight:800; line-height:1.2;">
              <?= sanitize($seller['shop_name']) ?>
            </h1>
            <?php if ($seller['is_verified']): ?>
              <span class="badge badge-green" style="font-size:0.75rem; padding:3px 8px; font-weight:600; display:inline-flex; align-items:center; gap:4px;">
                <i class="bi bi-patch-check-fill"></i> Verified
              </span>
            <?php endif; ?>
          </div>

          <div style="font-size:0.88rem; color:var(--text-muted); display:flex; align-items:center; flex-wrap:wrap; gap:12px; margin-top:8px;">
            <span><i class="bi bi-geo-alt-fill text-clay"></i> <?= sanitize($seller['district']) ?>, <?= sanitize($seller['division']) ?></span>
            <span style="color:#d1d5db;">•</span>
            <span class="text-gold" style="font-weight:700;"><i class="bi bi-star-fill"></i> <?= number_format($seller['rating'], 1) ?> Rating</span>
            <span style="color:#d1d5db;">•</span>
            <span><i class="bi bi-boxes" style="color:var(--haat-green);"></i> <?= count($products) ?> Products Listed</span>
          </div>
        </div>
      </div>

      <!-- Description -->
      <?php if (!empty($seller['description'])): ?>
        <p style="font-size:0.95rem; color:#475569; line-height:1.65; max-width:850px; margin:0; padding-top:12px; border-top:1px solid #f1ece5;">
          <?= nl2br(sanitize($seller['description'])) ?>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Vendor Products Catalog -->
  <div class="section-header">
    <div class="section-title-wrap">
      <h2>Products from <?= sanitize($seller['shop_name']) ?> (<?= count($products) ?>)</h2>
      <p class="section-subtitle">Direct workshop pricing with authentic quality certification</p>
    </div>
  </div>

  <?php if (empty($products)): ?>
    <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:40px; text-align:center;">
      <p style="color:var(--text-muted);">This artisan workshop does not have any active products listed right now.</p>
    </div>
  <?php else: ?>
    <div class="products-grid">
      <?php foreach ($products as $prod): ?>
        <div class="product-card">
          <div class="product-thumb">
            <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>">
              <img src="<?= sanitize($prod['featured_image']) ?>" alt="<?= sanitize($prod['name']) ?>">
            </a>
            <div class="product-badges">
              <?php if ($prod['is_flash_deal']): ?>
                <span class="badge badge-clay"><i class="bi bi-lightning-fill"></i> Flash Deal</span>
              <?php endif; ?>
              <?php if ($prod['sale_price'] && $prod['price'] > $prod['sale_price']): 
                $discount = round((($prod['price'] - $prod['sale_price']) / $prod['price']) * 100);
              ?>
                <span class="badge badge-gold">-<?= $discount ?>%</span>
              <?php endif; ?>
            </div>
            <button type="button" class="product-wishlist-btn <?= isInWishlist($prod['id']) ? 'active' : '' ?>" data-product-id="<?= $prod['id'] ?>" title="Save to Wishlist">
              <i class="bi <?= isInWishlist($prod['id']) ? 'bi-heart-fill' : 'bi-heart' ?>" <?= isInWishlist($prod['id']) ? 'style="color:#e63946;"' : '' ?>></i>
            </button>
          </div>

          <div class="product-body">
            <div class="product-vendor-meta">
              <span class="district-tag"><?= sanitize($prod['district_origin']) ?></span>
              <span style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($prod['category_name']) ?></span>
            </div>

            <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>" class="product-title">
              <?= sanitize($prod['name']) ?>
            </a>

            <div class="product-footer">
              <div class="price-wrap">
                <span class="current-price"><?= formatPrice($prod['sale_price'] ?: $prod['price']) ?></span>
                <?php if ($prod['sale_price']): ?>
                  <span class="old-price"><?= formatPrice($prod['price']) ?></span>
                <?php endif; ?>
              </div>
              <button type="button" class="btn-add-cart" data-product-id="<?= $prod['id'] ?>" title="Add to Haat Cart">
                <i class="bi bi-bag-plus"></i>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
