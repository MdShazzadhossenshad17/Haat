<?php
/**
 * Home Page (Clean, Simple, Easy to Use)
 * HAAT — Global • Regional • Artisanal Multi-Vendor Marketplace
 */
$pageTitle = 'Home — Bangladesh Multi-Vendor Marketplace';
require_once __DIR__ . '/includes/header.php';

// Fetch Flash Deals (up to 8 diverse items) with dynamic rating & review count
$flashProducts = $db->query("SELECT p.*, s.shop_name, c.name as category_name,
    COALESCE((SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id), p.rating, 0.0) as rating,
    (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
    FROM `products` p 
    JOIN `sellers` s ON p.seller_id = s.id 
    JOIN `categories` c ON p.category_id = c.id 
    WHERE p.is_flash_deal = 1 AND p.is_active = 1 
    ORDER BY p.id DESC LIMIT 8")->fetchAll();

// Fetch Latest Products with dynamic rating & review count
$allProducts = $db->query("SELECT p.*, s.shop_name, c.name as category_name,
    COALESCE((SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id), p.rating, 0.0) as rating,
    (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
    FROM `products` p 
    JOIN `sellers` s ON p.seller_id = s.id 
    JOIN `categories` c ON p.category_id = c.id 
    WHERE p.is_active = 1 
    ORDER BY p.id DESC LIMIT 8")->fetchAll();

// Fetch Top Sellers with live ratings calculation
$sellers = $db->query("SELECT s.*, 
    (SELECT COUNT(*) FROM `products` WHERE seller_id = s.id AND is_active = 1) as product_count,
    COALESCE((SELECT AVG(r.rating) FROM product_reviews r JOIN products p ON r.product_id = p.id WHERE p.seller_id = s.id), s.rating, 0.0) as live_rating,
    (SELECT COUNT(r.id) FROM product_reviews r JOIN products p ON r.product_id = p.id WHERE p.seller_id = s.id) as review_count
    FROM `sellers` s 
    WHERE s.status = 'active' 
    ORDER BY live_rating DESC LIMIT 3")->fetchAll();
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
            <?php if (!isSeller() && !isAdmin()): ?>
            <button type="button" class="product-wishlist-btn <?= isInWishlist($prod['id']) ? 'active' : '' ?>" data-product-id="<?= $prod['id'] ?>" title="Save to Wishlist">
              <i class="bi <?= isInWishlist($prod['id']) ? 'bi-heart-fill' : 'bi-heart' ?>" <?= isInWishlist($prod['id']) ? 'style="color:#e63946;"' : '' ?>></i>
            </button>
            <?php endif; ?>
          </div>

          <div class="product-body" style="padding: 14px;">
            <div style="font-size: 0.75rem; color: var(--haat-clay); font-weight: 600; margin-bottom: 4px;">
              <?= sanitize($prod['shop_name']) ?>
            </div>

            <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>" class="product-title" style="font-size: 0.92rem; margin-bottom: 8px;">
              <?= sanitize($prod['name']) ?>
            </a>

            <div class="product-rating" data-product-id="<?= $prod['id'] ?>" style="display:flex; align-items:center; gap:5px; font-size:0.82rem; margin-bottom:8px;">
              <span style="color:#f59e0b; display:inline-flex; align-items:center; gap:2px;"><i class="bi bi-star-fill"></i></span>
              <span class="product-avg" style="font-weight:700; color:var(--text-main);"><?= number_format($prod['rating'] ?? 0, 1) ?></span>
              <small style="color:var(--text-muted);">(<span class="product-count"><?= (int)($prod['review_count'] ?? 0) ?></span>)</small>
            </div>

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
            <?php if (!isSeller() && !isAdmin()): ?>
            <button type="button" class="product-wishlist-btn <?= isInWishlist($prod['id']) ? 'active' : '' ?>" data-product-id="<?= $prod['id'] ?>" title="Save to Wishlist">
              <i class="bi <?= isInWishlist($prod['id']) ? 'bi-heart-fill' : 'bi-heart' ?>" <?= isInWishlist($prod['id']) ? 'style="color:#e63946;"' : '' ?>></i>
            </button>
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

            <div class="product-rating" data-product-id="<?= $prod['id'] ?>" style="display:flex; align-items:center; gap:5px; font-size:0.82rem; margin-bottom:8px;">
              <span style="color:#f59e0b; display:inline-flex; align-items:center; gap:2px;"><i class="bi bi-star-fill"></i></span>
              <span class="product-avg" style="font-weight:700; color:var(--text-main);"><?= number_format($prod['rating'] ?? 0, 1) ?></span>
              <small style="color:var(--text-muted);">(<span class="product-count"><?= (int)($prod['review_count'] ?? 0) ?></span>)</small>
            </div>

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
            <strong style="color: var(--haat-green-dark); font-size: 0.95rem; display: flex; align-items: center; gap: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              <span><?= sanitize($seller['shop_name']) ?></span>
              <?php if (!empty($seller['is_verified'])): ?>
                <i class="bi bi-patch-check-fill text-green" style="font-size:0.88rem;" title="Verified Artisan Store"></i>
              <?php endif; ?>
            </strong>
            <span style="font-size: 0.78rem; color: var(--text-muted);">
              <?= sanitize($seller['district']) ?> • <?= $seller['product_count'] ?> Products • <span style="color:#d97706; font-weight:700;">★ <?= number_format($seller['live_rating'], 1) ?></span>
            </span>
          </div>
          <span style="color: var(--haat-clay); font-size: 1rem;"><i class="bi bi-arrow-right-short"></i></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<script>
  // Batch refresh dynamic ratings on homepage
  (function(){
    const cards = Array.from(document.querySelectorAll('.product-rating[data-product-id]'));
    if (!cards.length) return;
    const pids = cards.map(c => c.getAttribute('data-product-id')).filter(Boolean).join(',');
    if (!pids) return;

    fetch('<?= BASE_URL ?>api/ratings.php?product_ids=' + pids)
      .then(r => r.json())
      .then(data => {
        if (data && data.success && data.ratings) {
          cards.forEach(c => {
            const pid = c.getAttribute('data-product-id');
            if (data.ratings[pid]) {
              const avg = c.querySelector('.product-avg');
              const cnt = c.querySelector('.product-count');
              if (avg) avg.textContent = parseFloat(data.ratings[pid].avg).toFixed(1);
              if (cnt) cnt.textContent = data.ratings[pid].count;
            }
          });
        }
      }).catch(()=>{});
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
