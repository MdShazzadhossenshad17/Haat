<?php
/**
 * Product Details Page
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . 'shop.php');
    exit;
}

// Increment view counter
$db->exec("UPDATE `products` SET `views` = `views` + 1 WHERE `id` = {$id}");

// Fetch Product Details
$stmt = $db->prepare("SELECT p.*, s.shop_name, s.district as seller_district, s.division as seller_division, 
    s.rating as seller_rating, s.is_verified as seller_verified, s.id as seller_table_id,
    c.name as category_name, c.slug as category_slug, b.name as brand_name 
    FROM `products` p 
    JOIN `sellers` s ON p.seller_id = s.id 
    JOIN `categories` c ON p.category_id = c.id 
    LEFT JOIN `brands` b ON p.brand_id = b.id 
    WHERE p.id = ? AND p.is_active = 1 LIMIT 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . BASE_URL . 'shop.php');
    exit;
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please login to leave a review.');
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = trim($_POST['comment'] ?? '');
    if (!empty($comment)) {
        $ins = $db->prepare("INSERT INTO `product_reviews` (`product_id`, `user_id`, `rating`, `comment`) VALUES (?, ?, ?, ?)");
        $ins->execute([$id, $_SESSION['user_id'], $rating, $comment]);
        setFlash('success', 'Thank you! Your artisan review has been published.');
        header("Location: " . BASE_URL . "product.php?id=" . $id . "#reviews");
        exit;
    }
}

// Fetch Reviews
$revStmt = $db->prepare("SELECT r.*, u.name as reviewer_name, u.avatar 
    FROM `product_reviews` r 
    JOIN `users` u ON r.user_id = u.id 
    WHERE r.product_id = ? 
    ORDER BY r.id DESC");
$revStmt->execute([$id]);
$reviews = $revStmt->fetchAll();

// Related Products
$relStmt = $db->prepare("SELECT p.*, s.shop_name, s.district as seller_district 
    FROM `products` p 
    JOIN `sellers` s ON p.seller_id = s.id 
    WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1 
    LIMIT 4");
$relStmt->execute([$product['category_id'], $id]);
$relatedProducts = $relStmt->fetchAll();

$pageTitle = $product['name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 30px 20px;">
  
  <!-- Breadcrumb -->
  <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:24px;">
    <a href="<?= BASE_URL ?>" style="color:var(--haat-green); font-weight:600;">Home</a> &rarr; 
    <a href="<?= BASE_URL ?>shop.php?category=<?= sanitize($product['category_slug']) ?>" style="color:var(--haat-green); font-weight:600;"><?= sanitize($product['category_name']) ?></a> &rarr; 
    <span><?= sanitize($product['name']) ?></span>
  </div>

  <!-- Product Hero Grid -->
  <div style="display:grid; grid-template-columns: 1fr 1fr; gap:40px; background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:36px; box-shadow:var(--shadow-sm); margin-bottom:40px;">
    
    <!-- Image Gallery -->
    <div>
      <div style="aspect-ratio:1/1; border-radius:var(--radius-md); overflow:hidden; border:1px solid var(--haat-border); background:#fdfbf8; margin-bottom:16px;">
        <img id="main-prod-img" src="<?= sanitize($product['featured_image']) ?>" alt="<?= sanitize($product['name']) ?>" style="width:100%; height:100%; object-fit:cover;">
      </div>
      <div style="display:flex; gap:12px;">
        <div style="width:80px; height:80px; border-radius:var(--radius-sm); border:2px solid var(--haat-green); overflow:hidden; cursor:pointer;">
          <img src="<?= sanitize($product['featured_image']) ?>" style="width:100%; height:100%; object-fit:cover;">
        </div>
      </div>
    </div>

    <!-- Product Details Info -->
    <div>
      <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
        <span class="badge badge-green"><i class="bi bi-geo-alt-fill"></i> Origin: <?= sanitize($product['district_origin']) ?></span>
        <?php if ($product['is_flash_deal']): ?>
          <span class="badge badge-clay"><i class="bi bi-lightning-fill"></i> Flash Bazaar</span>
        <?php endif; ?>
        <?php if ($product['brand_name']): ?>
          <span class="badge badge-gold"><?= sanitize($product['brand_name']) ?></span>
        <?php endif; ?>
      </div>

      <h1 style="font-size:2rem; margin-bottom:6px; color:var(--haat-green-dark);"><?= sanitize($product['name']) ?></h1>
      <?php if (!empty($product['name_bn'])): ?>
        <h3 style="font-size:1.1rem; color:var(--text-muted); font-weight:500; margin-bottom:14px;"><?= sanitize($product['name_bn']) ?></h3>
      <?php endif; ?>

      <!-- Seller Link Box -->
      <div style="background:var(--haat-cream); border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:12px 18px; display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <i class="bi bi-shop text-clay" style="font-size:1.4rem;"></i>
          <div>
            <div style="font-size:0.75rem; color:var(--text-muted);">Artisan Workshop / Seller:</div>
            <a href="<?= BASE_URL ?>vendor.php?id=<?= $product['seller_table_id'] ?>" style="font-weight:700; color:var(--haat-green); font-size:0.95rem;">
              <?= sanitize($product['shop_name']) ?>
              <?php if ($product['seller_verified']): ?>
                <i class="bi bi-patch-check-fill text-green" title="Verified Artisan"></i>
              <?php endif; ?>
            </a>
          </div>
        </div>
        <a href="<?= BASE_URL ?>vendor.php?id=<?= $product['seller_table_id'] ?>" class="btn btn-sm btn-outline-clay">Visit Store</a>
      </div>

      <!-- Price Section -->
      <div style="display:flex; align-items:baseline; gap:16px; margin-bottom:20px; padding-bottom:20px; border-bottom:1px solid var(--haat-border);">
        <span style="font-size:2.4rem; font-weight:800; color:var(--haat-green);">
          <?= formatPrice($product['sale_price'] ?: $product['price']) ?>
        </span>
        <?php if ($product['sale_price'] && $product['price'] > $product['sale_price']): 
          $saving = $product['price'] - $product['sale_price'];
        ?>
          <span style="font-size:1.2rem; color:var(--text-light); text-decoration:line-through;">
            <?= formatPrice($product['price']) ?>
          </span>
          <span class="badge badge-clay" style="font-size:0.85rem;">Save <?= formatPrice($saving) ?></span>
        <?php endif; ?>
      </div>

      <!-- Short Description -->
      <p style="font-size:0.98rem; color:var(--text-main); line-height:1.6; margin-bottom:24px;">
        <?= nl2br(sanitize($product['short_description'])) ?>
      </p>

      <!-- Stock & SKU info -->
      <div style="margin-bottom:24px; display:flex; flex-direction:column; gap:6px; font-size:0.88rem;">
        <div>
          <strong>Availability: </strong>
          <?php if ($product['stock_quantity'] > 0): ?>
            <span style="color:#2b8a3e; font-weight:700;"><i class="bi bi-check-circle-fill"></i> In Stock (<?= $product['stock_quantity'] ?> <?= sanitize($product['unit']) ?>s left)</span>
          <?php else: ?>
            <span style="color:#e03131; font-weight:700;"><i class="bi bi-x-circle-fill"></i> Out of Stock</span>
          <?php endif; ?>
        </div>
        <div><strong>SKU: </strong><span style="color:var(--text-muted);"><?= sanitize($product['sku'] ?: 'HAAT-' . $product['id']) ?></span></div>
        <div><strong>Authenticity: </strong><span style="color:var(--text-muted);">GI / Master Artisan Craft Certified</span></div>
      </div>

      <!-- Action Buttons -->
      <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        
        <!-- Quantity Selector -->
        <div class="qty-wrapper" style="display:inline-flex; align-items:center; border:2px solid var(--haat-border); border-radius:var(--radius-md); overflow:hidden; background:#fff;">
          <button type="button" class="qty-btn-minus" style="border:none; background:transparent; width:38px; height:44px; font-size:1.1rem; cursor:pointer; font-weight:700;">-</button>
          <input type="number" id="product-qty" value="1" min="1" max="<?= $product['stock_quantity'] ?>" style="width:50px; text-align:center; border:none; outline:none; font-weight:700; font-size:1rem;">
          <button type="button" class="qty-btn-plus" style="border:none; background:transparent; width:38px; height:44px; font-size:1.1rem; cursor:pointer; font-weight:700;">+</button>
        </div>

        <button type="button" class="btn btn-clay btn-lg btn-add-to-cart-page" data-product-id="<?= $product['id'] ?>">
          <i class="bi bi-bag-plus"></i> Add to Haat Cart
        </button>

        <a href="<?= BASE_URL ?>cart.php?action=buy_now&product_id=<?= $product['id'] ?>" class="btn btn-primary btn-lg" onclick="addToCartAndCheckout(event, <?= $product['id'] ?>)">
          <i class="bi bi-lightning-charge-fill"></i> Buy Now
        </a>
      </div>

      <script>
        function addToCartAndCheckout(e, prodId) {
          e.preventDefault();
          const qty = document.getElementById('product-qty')?.value || 1;
          const fd = new FormData();
          fd.append('action', 'add');
          fd.append('product_id', prodId);
          fd.append('quantity', qty);
          fetch('<?= BASE_URL ?>api/cart.php', { method: 'POST', body: fd }).then(() => {
            window.location.href = '<?= BASE_URL ?>checkout.php';
          });
        }
      </script>

    </div>
  </div>

  <!-- Detailed Story / Specifications / Reviews Tabs -->
  <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:36px; box-shadow:var(--shadow-sm); margin-bottom:50px;">
    <h3 style="font-size:1.4rem; color:var(--haat-green-dark); margin-bottom:16px; padding-bottom:10px; border-bottom:2px solid var(--haat-sand);">
      Artisan Story & Craftsmanship
    </h3>
    <div style="line-height:1.8; color:var(--text-main); font-size:1.02rem; margin-bottom:36px;">
      <?= nl2br(sanitize($product['description'])) ?>
    </div>

    <!-- Specifications Table -->
    <h4 style="font-size:1.1rem; color:var(--haat-green); margin-bottom:14px;">Technical Craft Details</h4>
    <table style="width:100%; border-collapse:collapse; margin-bottom:40px; font-size:0.92rem;">
      <tr style="border-bottom:1px solid var(--haat-border);">
        <td style="padding:10px; font-weight:700; width:220px; background:var(--haat-sand);">District of Origin</td>
        <td style="padding:10px;"><?= sanitize($product['district_origin']) ?>, Bangladesh</td>
      </tr>
      <tr style="border-bottom:1px solid var(--haat-border);">
        <td style="padding:10px; font-weight:700; background:var(--haat-sand);">Category</td>
        <td style="padding:10px;"><?= sanitize($product['category_name']) ?></td>
      </tr>
      <tr style="border-bottom:1px solid var(--haat-border);">
        <td style="padding:10px; font-weight:700; background:var(--haat-sand);">Artisan Guild / Seller</td>
        <td style="padding:10px;"><?= sanitize($product['shop_name']) ?> (<?= sanitize($product['seller_district']) ?>)</td>
      </tr>
      <tr style="border-bottom:1px solid var(--haat-border);">
        <td style="padding:10px; font-weight:700; background:var(--haat-sand);">Delivery Method</td>
        <td style="padding:10px;">Nationwide Curated Courier (bKash / Nagad / Rocket / Cash on Delivery)</td>
      </tr>
    </table>

    <!-- Reviews Section -->
    <div id="reviews">
      <h3 style="font-size:1.4rem; color:var(--haat-green-dark); margin-bottom:20px;">
        Customer Reviews (<?= count($reviews) ?>)
      </h3>

      <!-- Reviews List -->
      <div style="display:flex; flex-direction:column; gap:16px; margin-bottom:32px;">
        <?php if (empty($reviews)): ?>
          <p style="color:var(--text-muted);">No reviews yet for this product. Be the first to share your experience!</p>
        <?php else: ?>
          <?php foreach ($reviews as $rev): ?>
            <div style="border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; background:#fdfbf8;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <strong style="color:var(--haat-green-dark);"><?= sanitize($rev['reviewer_name']) ?></strong>
                <span style="font-size:0.75rem; color:var(--text-muted);"><?= date('F j, Y', strtotime($rev['created_at'])) ?></span>
              </div>
              <div style="color:#e5a93c; font-size:0.85rem; margin-bottom:8px;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class="bi bi-star<?= $i <= $rev['rating'] ? '-fill' : '' ?>"></i>
                <?php endfor; ?>
              </div>
              <p style="color:var(--text-main); font-size:0.92rem; line-height:1.5;">
                <?= nl2br(sanitize($rev['comment'])) ?>
              </p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Add Review Form -->
      <div style="background:var(--haat-cream); border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px;">
        <h4 style="font-size:1.1rem; margin-bottom:14px; color:var(--haat-green-dark);">Write an Artisan Craft Review</h4>
        <?php if (isLoggedIn()): ?>
          <form action="<?= BASE_URL ?>product.php?id=<?= $product['id'] ?>" method="POST">
            <div style="margin-bottom:14px;">
              <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Your Rating</label>
              <select name="rating" style="padding:8px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-family:inherit; outline:none;">
                <option value="5">★★★★★ (5 Stars - Exceptional)</option>
                <option value="4">★★★★☆ (4 Stars - Very Good)</option>
                <option value="3">★★★☆☆ (3 Stars - Average)</option>
                <option value="2">★★☆☆☆ (2 Stars - Below Expectation)</option>
                <option value="1">★☆☆☆☆ (1 Star - Poor)</option>
              </select>
            </div>
            <div style="margin-bottom:16px;">
              <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Your Feedback & Experience</label>
              <textarea name="comment" rows="4" required placeholder="Share your experience with the craftsmanship, packaging, and delivery..." style="width:100%; padding:10px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-family:inherit; font-size:0.9rem; outline:none;"></textarea>
            </div>
            <button type="submit" name="submit_review" class="btn btn-clay">Submit Review</button>
          </form>
        <?php else: ?>
          <p style="font-size:0.9rem; color:var(--text-muted);">
            Please <a href="<?= BASE_URL ?>login.php" style="color:var(--haat-clay); font-weight:700;">login to your HAAT account</a> to submit an artisan review.
          </p>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- Related Products -->
  <?php if (!empty($relatedProducts)): ?>
    <div style="margin-bottom:40px;">
      <h3 style="font-size:1.4rem; color:var(--haat-green-dark); margin-bottom:20px;">More from this Category</h3>
      <div class="products-grid">
        <?php foreach ($relatedProducts as $rel): ?>
          <div class="product-card">
            <div class="product-thumb">
              <a href="<?= BASE_URL ?>product.php?id=<?= $rel['id'] ?>">
                <img src="<?= sanitize($rel['featured_image']) ?>" alt="<?= sanitize($rel['name']) ?>">
              </a>
            </div>
            <div class="product-body">
              <div class="product-vendor-meta">
                <span class="vendor-link"><i class="bi bi-shop"></i> <?= sanitize($rel['shop_name']) ?></span>
                <span class="district-tag"><?= sanitize($rel['district_origin']) ?></span>
              </div>
              <a href="<?= BASE_URL ?>product.php?id=<?= $rel['id'] ?>" class="product-title">
                <?= sanitize($rel['name']) ?>
              </a>
              <div class="product-footer">
                <div class="price-wrap">
                  <span class="current-price"><?= formatPrice($rel['sale_price'] ?: $rel['price']) ?></span>
                </div>
                <button type="button" class="btn-add-cart" data-product-id="<?= $rel['id'] ?>">
                  <i class="bi bi-bag-plus"></i>
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
