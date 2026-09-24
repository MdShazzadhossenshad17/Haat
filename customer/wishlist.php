<?php
/**
 * Customer Wishlist Page
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/../includes/functions.php';
requireCustomer();
header('Location: ' . BASE_URL . 'customer/');
exit;
?>

<div class="container" style="padding: 30px 20px;">
  <div style="margin-bottom:28px;">
    <h1 style="font-size:1.8rem; color:var(--haat-green-dark); margin-bottom:4px;">
      <i class="bi bi-heart-fill text-clay"></i> My Saved Crafts & Wishlist
    </h1>
    <p style="color:var(--text-muted); font-size:0.92rem;">Artisanal pieces you've saved for future occasions</p>
  </div>

  <div class="products-grid">
    <?php foreach ($products as $prod): ?>
      <div class="product-card">
        <div class="product-thumb">
          <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>">
            <img src="<?= sanitize($prod['featured_image']) ?>" alt="<?= sanitize($prod['name']) ?>">
          </a>
        </div>
        <div class="product-body">
          <div class="product-vendor-meta">
            <span class="vendor-link"><i class="bi bi-shop"></i> <?= sanitize($prod['shop_name']) ?></span>
            <span class="district-tag"><?= sanitize($prod['district_origin']) ?></span>
          </div>
          <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>" class="product-title">
            <?= sanitize($prod['name']) ?>
          </a>
          <div class="product-footer">
            <div class="price-wrap">
              <span class="current-price"><?= formatPrice($prod['sale_price'] ?: $prod['price']) ?></span>
            </div>
            <button type="button" class="btn btn-sm btn-clay btn-add-cart" data-product-id="<?= $prod['id'] ?>">
              <i class="bi bi-bag-plus"></i> Move to Cart
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
