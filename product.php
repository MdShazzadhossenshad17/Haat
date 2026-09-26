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

// Fetch Product Details with live ratings calculation
$stmt = $db->prepare("SELECT p.*, s.shop_name, s.district as seller_district, s.division as seller_division, 
    COALESCE((SELECT AVG(r.rating) FROM product_reviews r JOIN products p2 ON r.product_id = p2.id WHERE p2.seller_id = s.id), s.rating, 0.0) as seller_rating,
    (SELECT COUNT(r.id) FROM product_reviews r JOIN products p2 ON r.product_id = p2.id WHERE p2.seller_id = s.id) as seller_review_count,
    s.is_verified as seller_verified, s.id as seller_table_id, s.user_id as seller_user_id,
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
        setFlash('warning', 'Please login to your buyer account to leave a review.');
        header("Location: " . BASE_URL . "login.php");
        exit;
    }

    // Strict rule: Sellers cannot review ANY product (neither own nor other sellers')
    if (isSeller()) {
        setFlash('danger', 'Sellers are not permitted to submit product reviews on the marketplace to prevent bias.');
        header("Location: " . BASE_URL . "product.php?id=" . $id . "#reviews");
        exit;
    }

    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = trim($_POST['comment'] ?? '');
    if (!empty($comment)) {
        $ins = $db->prepare("INSERT INTO `product_reviews` (`product_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES (?, ?, ?, ?, NOW())");
        $ins->execute([$id, $_SESSION['user_id'], $rating, $comment]);

        // Recalculate product average rating and seller rating in database
        recalculateRatings($id);

        setFlash('success', 'Thank you! Your artisan craft review has been published.');
        header("Location: " . BASE_URL . "product.php?id=" . $id . "#reviews");
        exit;
    } else {
        setFlash('warning', 'Please provide a feedback description for your review.');
        header("Location: " . BASE_URL . "product.php?id=" . $id . "#reviews");
        exit;
    }
}

// Fetch Product Reviews
$revStmt = $db->prepare("SELECT r.*, u.name as reviewer_name, u.avatar 
    FROM `product_reviews` r 
    JOIN `users` u ON r.user_id = u.id 
    WHERE r.product_id = ? 
    ORDER BY r.id DESC");
$revStmt->execute([$id]);
$reviews = $revStmt->fetchAll();

// Product live average
$totalReviews = count($reviews);
$prodAvgRating = 0.0;
if ($totalReviews > 0) {
    $sum = 0;
    foreach ($reviews as $rev) {
        $sum += (int)$rev['rating'];
    }
    $prodAvgRating = round($sum / $totalReviews, 1);
} else {
    $prodAvgRating = round((float)($product['rating'] ?? 0), 1);
}

// Check seller ownership
$curSeller = currentSeller();
$isOwnProduct = ($curSeller && (int)$curSeller['id'] === (int)$product['seller_table_id']);
$isAnySeller = isSeller();

// Related Products with ratings
$relStmt = $db->prepare("SELECT p.*, s.shop_name, s.district as seller_district, s.is_verified as seller_verified,
    COALESCE((SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id), p.rating, 0.0) as rating,
    (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
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
      
      <!-- Live Star Ratings Display (like Daraz) -->
      <div id="rating-block" style="margin-top:6px; margin-bottom:12px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <div style="color:#f59e0b; font-weight:700; display:flex; align-items:center; gap:6px; font-size:1rem;">
          <i class="bi bi-star-fill"></i>
          <span id="product-avg-rating"><?= number_format($prodAvgRating, 1) ?></span>
          <small style="color:var(--text-muted); font-size:0.85rem;">(<span id="product-rating-count"><?= $totalReviews ?></span> <?= $totalReviews === 1 ? 'review' : 'reviews' ?>)</small>
        </div>
        <span style="color:#d1d5db;">•</span>
        <div style="font-size:0.88rem; color:var(--text-muted);">
          Artisan Workshop: <strong id="seller-name" style="color:var(--haat-green-dark);"><?= sanitize($product['shop_name']) ?></strong> — <span class="text-gold" style="font-weight:700;">★ <span id="seller-avg-rating"><?= number_format($product['seller_rating'], 1) ?></span></span>
        </div>
      </div>

      <?php if (!empty($product['name_bn'])): ?>
        <h3 style="font-size:1.1rem; color:var(--text-muted); font-weight:500; margin-bottom:14px;"><?= sanitize($product['name_bn']) ?></h3>
      <?php endif; ?>

      <!-- Seller Link Box -->
      <div style="background:var(--haat-cream); border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:12px 18px; display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <i class="bi bi-shop text-clay" style="font-size:1.4rem;"></i>
          <div>
            <div style="font-size:0.75rem; color:var(--text-muted);">Artisan Guild / Seller:</div>
            <a href="<?= BASE_URL ?>vendor.php?id=<?= $product['seller_table_id'] ?>" style="font-weight:700; color:var(--haat-green); font-size:0.95rem; display:inline-flex; align-items:center; gap:4px;">
              <span><?= sanitize($product['shop_name']) ?></span>
              <?php if (!empty($product['seller_verified'])): ?>
                <i class="bi bi-patch-check-fill text-green" title="Verified Artisan Guild"></i>
              <?php endif; ?>
            </a>
          </div>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <?php if (!$isAnySeller && !$isOwnProduct): ?>
            <a href="<?= BASE_URL ?>customer/?seller_id=<?= $product['seller_table_id'] ?>#messages" class="btn btn-sm btn-clay" style="display:inline-flex; align-items:center; gap:6px;">
              <i class="bi bi-chat-dots-fill"></i> Chat with Artisan
            </a>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>vendor.php?id=<?= $product['seller_table_id'] ?>" class="btn btn-sm btn-outline-clay">Visit Store</a>
        </div>
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
        
        <?php if ($isAnySeller): ?>
          <!-- Seller Browsing View (Purchasing Restricted) -->
          <div style="background:var(--haat-sand); border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:14px 20px; display:flex; align-items:center; gap:12px; max-width:480px;">
            <i class="bi bi-shop text-clay" style="font-size:1.3rem;"></i>
            <div>
              <strong style="color:var(--haat-green-dark); font-size:0.92rem; display:block;">Browsing in Seller Mode</strong>
              <span style="font-size:0.82rem; color:var(--text-muted);">Purchasing is restricted for seller accounts. You can manage your crafts in your dashboard.</span>
            </div>
          </div>
        <?php else: ?>
          <!-- Buyer Action Buttons -->
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

          <?php if (!isAdmin()): ?>
          <button type="button" class="btn btn-outline-green btn-lg btn-wishlist-toggle <?= isInWishlist($product['id']) ? 'active' : '' ?>" data-product-id="<?= $product['id'] ?>" title="Save to Wishlist" style="display:inline-flex; align-items:center; gap:8px;">
            <i class="bi <?= isInWishlist($product['id']) ? 'bi-heart-fill' : 'bi-heart' ?>" <?= isInWishlist($product['id']) ? 'style="color:#e63946;"' : '' ?>></i>
            <span>Wishlist</span>
          </button>
          <?php endif; ?>
        <?php endif; ?>

      </div>

      <script>
        function addToCartAndCheckout(e, prodId) {
          e.preventDefault();
          const qty = document.getElementById('product-qty')?.value || 1;
          const fd = new FormData();
          fd.append('action', 'add');
          fd.append('product_id', prodId);
          fd.append('quantity', qty);
          fetch('<?= BASE_URL ?>api/cart.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
              if (data && data.success) {
                window.location.href = '<?= BASE_URL ?>checkout.php';
              } else {
                if (typeof showToast === 'function') {
                  showToast(data.message || 'Could not add to cart', 'danger');
                } else {
                  alert(data.message || 'Could not add to cart');
                }
              }
            })
            .catch(() => {
              window.location.href = '<?= BASE_URL ?>checkout.php';
            });
        }
      </script>

      <script>
        // Dynamic ratings sync via API
        function refreshRatings() {
          const pid = <?= (int)$product['id'] ?>;
          const sid = <?= (int)$product['seller_table_id'] ?>;
          fetch('<?= BASE_URL ?>api/ratings.php?product_id=' + pid)
            .then(r => r.json())
            .then(data => {
              if (data && data.success) {
                const pAvg = document.getElementById('product-avg-rating');
                const pCnt = document.getElementById('product-rating-count');
                if (pAvg) pAvg.textContent = parseFloat(data.avg).toFixed(1);
                if (pCnt) pCnt.textContent = data.count;
              }
            })
            .catch(()=>{});

          fetch('<?= BASE_URL ?>api/ratings.php?seller_id=' + sid)
            .then(r => r.json())
            .then(data => {
              if (data && data.success) {
                const sAvg = document.getElementById('seller-avg-rating');
                if (sAvg) sAvg.textContent = parseFloat(data.avg).toFixed(1);
              }
            })
            .catch(()=>{});
        }

        document.addEventListener('DOMContentLoaded', function() {
          refreshRatings();
        });
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
      <h3 style="font-size:1.4rem; color:var(--haat-green-dark); margin-bottom:20px; display:flex; align-items:center; gap:10px;">
        <span>Customer Reviews (<?= count($reviews) ?>)</span>
        <span style="font-size:1rem; color:#f59e0b; font-weight:700; display:inline-flex; align-items:center; gap:4px;">
          <i class="bi bi-star-fill"></i> <?= number_format($prodAvgRating, 1) ?>
        </span>
      </h3>

      <!-- Reviews List -->
      <div style="display:flex; flex-direction:column; gap:16px; margin-bottom:32px;">
        <?php if (empty($reviews)): ?>
          <p style="color:var(--text-muted);">No reviews yet for this craft item. Be the first customer to share your experience!</p>
        <?php else: ?>
          <?php foreach ($reviews as $rev): ?>
            <div style="border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:18px; background:#fdfbf8;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <strong style="color:var(--haat-green-dark); font-size:0.95rem;"><?= sanitize($rev['reviewer_name']) ?></strong>
                <span style="font-size:0.75rem; color:var(--text-muted);"><?= date('F j, Y', strtotime($rev['created_at'])) ?></span>
              </div>
              <div style="color:#f59e0b; font-size:0.85rem; margin-bottom:8px;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class="bi bi-star<?= $i <= $rev['rating'] ? '-fill' : '' ?>"></i>
                <?php endfor; ?>
                <span style="color:var(--text-main); font-weight:700; font-size:0.8rem; margin-left:4px;"><?= $rev['rating'] ?>.0</span>
              </div>
              <p style="color:var(--text-main); font-size:0.92rem; line-height:1.5; margin:0;">
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
          <?php if (isSeller()): ?>
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md); padding:16px; color:#475569; display:flex; align-items:center; gap:12px;">
              <i class="bi bi-shield-lock text-clay" style="font-size:1.3rem;"></i>
              <div>
                <strong style="color:var(--haat-green-dark); display:block; font-size:0.92rem;">Reviews Disabled for Sellers</strong>
                <span style="font-size:0.83rem;">To keep customer ratings transparent and unbiased, seller accounts cannot write reviews.</span>
              </div>
            </div>
          <?php else: ?>
            <form action="<?= BASE_URL ?>product.php?id=<?= $product['id'] ?>" method="POST">
              <div style="margin-bottom:14px;">
                <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Your Rating</label>
                <select name="rating" style="padding:8px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-family:inherit; outline:none; font-weight:600; color:var(--haat-green-dark);">
                  <option value="5">★★★★★ (5 Stars - Exceptional Craftsmanship)</option>
                  <option value="4">★★★★☆ (4 Stars - Very Good Quality)</option>
                  <option value="3">★★★☆☆ (3 Stars - Average / Standard)</option>
                  <option value="2">★★☆☆☆ (2 Stars - Below Expectation)</option>
                  <option value="1">★☆☆☆☆ (1 Star - Poor Experience)</option>
                </select>
              </div>
              <div style="margin-bottom:16px;">
                <label style="font-weight:600; font-size:0.88rem; display:block; margin-bottom:4px;">Your Feedback & Experience</label>
                <textarea name="comment" rows="4" required placeholder="Share your experience with the craftsmanship, packaging, and delivery..." style="width:100%; padding:10px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-family:inherit; font-size:0.9rem; outline:none;"></textarea>
              </div>
              <button type="submit" name="submit_review" class="btn btn-clay">Submit Review</button>
            </form>
          <?php endif; ?>
        <?php else: ?>
          <p style="font-size:0.9rem; color:var(--text-muted); margin:0;">
            Please <a href="<?= BASE_URL ?>login.php" style="color:var(--haat-clay); font-weight:700;">login to your customer account</a> to submit an artisan review.
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
              <?php if (!isSeller() && !isAdmin()): ?>
              <button type="button" class="product-wishlist-btn <?= isInWishlist($rel['id']) ? 'active' : '' ?>" data-product-id="<?= $rel['id'] ?>" title="Save to Wishlist">
                <i class="bi <?= isInWishlist($rel['id']) ? 'bi-heart-fill' : 'bi-heart' ?>" <?= isInWishlist($rel['id']) ? 'style="color:#e63946;"' : '' ?>></i>
              </button>
              <?php endif; ?>
            </div>
            <div class="product-body">
              <div class="product-vendor-meta">
                <span class="vendor-link"><i class="bi bi-shop"></i> <?= sanitize($rel['shop_name']) ?></span>
                <span class="district-tag"><?= sanitize($rel['district_origin']) ?></span>
              </div>
              <a href="<?= BASE_URL ?>product.php?id=<?= $rel['id'] ?>" class="product-title">
                <?= sanitize($rel['name']) ?>
              </a>
              <div class="product-rating" data-product-id="<?= $rel['id'] ?>" style="display:flex; align-items:center; gap:5px; font-size:0.82rem; margin-bottom:8px;">
                <span style="color:#f59e0b; display:inline-flex; align-items:center; gap:2px;"><i class="bi bi-star-fill"></i></span>
                <span class="product-avg" style="font-weight:700; color:var(--text-main);"><?= number_format($rel['rating'] ?? 0, 1) ?></span>
                <small style="color:var(--text-muted);">(<span class="product-count"><?= (int)($rel['review_count'] ?? 0) ?></span>)</small>
              </div>
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
