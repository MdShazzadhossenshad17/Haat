<?php
/**
 * Home Page (Clean, Simple, Easy to Use)
 * HAAT — Global • Regional • Artisanal Multi-Vendor Marketplace
 */
$pageTitle = 'Home — Bangladesh Multi-Vendor Marketplace';
require_once __DIR__ . '/includes/header.php';


// Fetch Flash Deals (up to 8 diverse items)
$flashProducts = $db->query("SELECT p.*, s.shop_name, c.name as category_name 
    FROM `products` p 
    JOIN `sellers` s ON p.seller_id = s.id 
    JOIN `categories` c ON p.category_id = c.id 
    WHERE p.is_flash_deal = 1 AND p.is_active = 1 
    ORDER BY p.id DESC LIMIT 8")->fetchAll();

// Fetch Latest Products
$allProducts = $db->query("SELECT p.*, s.shop_name, c.name as category_name 
    FROM `products` p 
    JOIN `sellers` s ON p.seller_id = s.id 
    JOIN `categories` c ON p.category_id = c.id 
    WHERE p.is_active = 1 
    ORDER BY p.id DESC LIMIT 8")->fetchAll();

// Fetch Top Sellers
$sellers = $db->query("SELECT s.*, 
    (SELECT COUNT(*) FROM `products` WHERE seller_id = s.id AND is_active = 1) as product_count 
    FROM `sellers` s 
    WHERE s.status = 'active' 
    ORDER BY s.rating DESC LIMIT 3")->fetchAll();
?>

<!-- ==========================================
     CLEAN, MINIMAL HERO BANNER
     ========================================== -->
<section style="padding: 20px 0 10px;">
  <div class="container">
    <div style="background: linear-gradient(rgba(17, 36, 21, 0.60), rgba(17, 36, 21, 0.75)), url('<?= BASE_URL ?>assets/images/village_hero_bg.jpg') center/cover no-repeat; border-radius: var(--radius-md); padding: 56px 44px; color: #ffffff; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; min-height: 250px; box-shadow: var(--shadow-md);">
      <h1 style="font-size: 2.6rem; color: #ffffff; line-height: 1.2; margin-bottom: 20px; font-weight: 800; text-shadow: 0 2px 10px rgba(0,0,0,0.5);">
        Welcome to HAAT
      </h1>
      <div style="display: flex; gap: 14px; flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>shop.php" class="btn btn-clay btn-lg" style="box-shadow: 0 4px 14px rgba(0,0,0,0.3);">
          <i class="bi bi-bag"></i> Start Shopping
        </a>
        <a href="<?= BASE_URL ?>register.php?type=seller" class="btn btn-lg" style="background: rgba(255,255,255,0.22); color: #fff; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.4); box-shadow: 0 4px 14px rgba(0,0,0,0.25);">
          <i class="bi bi-shop"></i> Sell on Haat
        </a>
      </div>
    </div>
  </div>
</section>






<!-- ==========================================
     FLASH DEALS (Clean & Simple)
     ========================================== -->
<?php if (!empty($flashProducts)): ?>
<section style="padding: 16px 0 24px;">
  <div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <h2 style="font-size: 1.35rem; color: var(--haat-green-dark); margin: 0; display:flex; align-items:center; gap:8px;">
          <i class="bi bi-lightning-charge-fill text-clay"></i> Flash Deals
        </h2>
      </div>
      <a href="<?= BASE_URL ?>shop.php?flash=1" style="font-size: 0.88rem; color: var(--haat-clay); font-weight: 600;">See All Deals &rarr;</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 18px;">
      <?php foreach ($flashProducts as $prod): ?>
        <div class="product-card">
          <div class="product-thumb">
            <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>">
              <img src="<?= sanitize($prod['featured_image']) ?>" alt="<?= sanitize($prod['name']) ?>">
            </a>
            <?php if ($prod['sale_price'] && $prod['price'] > $prod['sale_price']): 
              $discount = round((($prod['price'] - $prod['sale_price']) / $prod['price']) * 100);
            ?>
              <div class="product-badges">
                <span class="badge badge-clay">-<?= $discount ?>%</span>
              </div>
            <?php endif; ?>
          </div>

          <div class="product-body" style="padding: 14px;">
            <div style="font-size: 0.75rem; color: var(--haat-clay); font-weight: 600; margin-bottom: 4px;">
              <?= sanitize($prod['shop_name']) ?>
            </div>

            <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>" class="product-title" style="font-size: 0.92rem; margin-bottom: 8px;">
              <?= sanitize($prod['name']) ?>
            </a>

            <div class="product-footer" style="padding-top: 8px;">
              <div class="price-wrap">
                <span class="current-price" style="font-size: 1.15rem;"><?= formatPrice($prod['sale_price'] ?: $prod['price']) ?></span>
                <?php if ($prod['sale_price']): ?>
                  <span class="old-price"><?= formatPrice($prod['price']) ?></span>
                <?php endif; ?>
              </div>
              <button type="button" class="btn-add-cart" data-product-id="<?= $prod['id'] ?>" title="Add to Cart">
                <i class="bi bi-bag-plus"></i>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ==========================================
     ALL PRODUCTS SHOWCASE (Clean & Simple)
     ========================================== -->
<section style="padding: 16px 0 32px;">
  <div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h2 style="font-size: 1.35rem; color: var(--haat-green-dark); margin: 0;">Featured Products</h2>
      <a href="<?= BASE_URL ?>shop.php" style="font-size: 0.88rem; color: var(--haat-clay); font-weight: 600;">View Full Shop &rarr;</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 18px;">
      <?php foreach ($allProducts as $prod): ?>
        <div class="product-card">
          <div class="product-thumb">
            <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>">
              <img src="<?= sanitize($prod['featured_image']) ?>" alt="<?= sanitize($prod['name']) ?>">
            </a>
            <?php if ($prod['sale_price'] && $prod['price'] > $prod['sale_price']): 
              $discount = round((($prod['price'] - $prod['sale_price']) / $prod['price']) * 100);
            ?>
              <div class="product-badges">
                <span class="badge badge-clay">-<?= $discount ?>%</span>
              </div>
            <?php endif; ?>
          </div>

          <div class="product-body" style="padding: 14px;">
            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 4px;">
              <span style="color: var(--haat-clay); font-weight: 600;"><?= sanitize($prod['shop_name']) ?></span>
              <span class="district-tag"><?= sanitize($prod['district_origin']) ?></span>
            </div>

            <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>" class="product-title" style="font-size: 0.92rem; margin-bottom: 8px;">
              <?= sanitize($prod['name']) ?>
            </a>

            <div class="product-footer" style="padding-top: 8px;">
              <div class="price-wrap">
                <span class="current-price" style="font-size: 1.15rem;"><?= formatPrice($prod['sale_price'] ?: $prod['price']) ?></span>
                <?php if ($prod['sale_price']): ?>
                  <span class="old-price"><?= formatPrice($prod['price']) ?></span>
                <?php endif; ?>
              </div>
              <button type="button" class="btn-add-cart" data-product-id="<?= $prod['id'] ?>" title="Add to Cart">
                <i class="bi bi-bag-plus"></i>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ==========================================
     ARTISAN SELLERS (Clean Minimal List)
     ========================================== -->
<section style="padding: 16px 0 36px;">
  <div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h2 style="font-size: 1.35rem; color: var(--haat-green-dark); margin: 0;">Featured Artisan Stores</h2>
      <a href="<?= BASE_URL ?>register.php?type=seller" style="font-size: 0.88rem; color: var(--haat-clay); font-weight: 600;">Become a Seller &rarr;</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
      <?php foreach ($sellers as $seller): ?>
        <a href="<?= BASE_URL ?>vendor.php?id=<?= $seller['id'] ?>" style="background: #fff; border: 1px solid var(--haat-border); border-radius: var(--radius-md); padding: 18px; display: flex; align-items: center; gap: 14px; transition: var(--transition);" onmouseover="this.style.borderColor='var(--haat-clay)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='var(--haat-border)'; this.style.transform='none';">
          <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--haat-sand); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: var(--haat-clay); flex-shrink: 0;">
            <i class="bi bi-shop"></i>
          </div>
          <div style="flex: 1; overflow: hidden;">
            <strong style="color: var(--haat-green-dark); font-size: 0.95rem; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              <?= sanitize($seller['shop_name']) ?>
            </strong>
            <span style="font-size: 0.78rem; color: var(--text-muted);">
              <?= sanitize($seller['district']) ?> • <?= $seller['product_count'] ?> Products
            </span>
          </div>
          <span style="color: var(--haat-clay); font-size: 1rem;"><i class="bi bi-arrow-right-short"></i></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
