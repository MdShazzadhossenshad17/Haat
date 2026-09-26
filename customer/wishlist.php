<?php
/**
 * Customer Wishlist Page — Saved Artisanal Crafts
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/../includes/functions.php';

// Handle removal via GET
if (isset($_GET['remove'])) {
    $rmId = (int)$_GET['remove'];
    if ($rmId > 0) {
        if (isLoggedIn()) {
            $del = $db->prepare("DELETE FROM `wishlists` WHERE `user_id` = ? AND `product_id` = ?");
            $del->execute([$_SESSION['user_id'], $rmId]);
        } else {
            if (isset($_SESSION['wishlist'])) {
                $key = array_search($rmId, $_SESSION['wishlist']);
                if ($key !== false) {
                    unset($_SESSION['wishlist'][$key]);
                    $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
                }
            }
        }
        setFlash('success', 'Craft removed from your wishlist.');
    }
    header('Location: ' . BASE_URL . 'customer/wishlist.php');
    exit;
}

$wishlistProducts = getUserWishlistProducts();
$wishlistCount = count($wishlistProducts);

// Prevent sellers/admins accidentally accessing customer wishlist page
if (isLoggedIn() && (isSeller() || isAdmin())) {
  setFlash('danger', 'Buyer wishlist is not available in vendor or admin accounts.');
  header('Location: ' . BASE_URL . (isSeller() ? 'seller/' : 'admin/'));
  exit;
}

$pageTitle = 'My Saved Crafts & Wishlist (' . $wishlistCount . ') — HAAT';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 32px 20px 60px;">
  
  <!-- Header Title Bar -->
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:28px; border-bottom:1px solid var(--haat-border); padding-bottom:18px;">
    <div>
      <h1 style="font-size:1.85rem; color:var(--haat-green-dark); margin:0; display:flex; align-items:center; gap:10px;">
        <i class="bi bi-heart-fill text-clay"></i> My Saved Crafts & Wishlist
        <span class="badge badge-clay" style="font-size:0.85rem; padding:3px 10px; border-radius:14px;"><?= $wishlistCount ?> items</span>
      </h1>
      <p style="color:var(--text-muted); font-size:0.92rem; margin:4px 0 0;">
        Personal collection of authentic heritage weaves, pottery, and organic harvests you've bookmarked
      </p>
    </div>

    <div style="display:flex; gap:10px;">
      <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>admin/" class="btn btn-sm btn-outline-green">
          <i class="bi bi-speedometer2"></i> Admin Dashboard
        </a>
      <?php elseif (isSeller()): ?>
        <a href="<?= BASE_URL ?>seller/" class="btn btn-sm btn-outline-green">
          <i class="bi bi-speedometer2"></i> Seller Dashboard
        </a>
      <?php elseif (isLoggedIn()): ?>
        <a href="<?= BASE_URL ?>customer/" class="btn btn-sm btn-outline-green">
          <i class="bi bi-speedometer2"></i> Buyer Dashboard
        </a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>login.php" class="btn btn-sm btn-outline-green">
          <i class="bi bi-box-arrow-in-right"></i> Sign In to Save Across Devices
        </a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>shop.php" class="btn btn-sm btn-clay">
        <i class="bi bi-shop"></i> Explore Haat Bazaar
      </a>
    </div>
  </div>

  <?php if (empty($wishlistProducts)): ?>
    <!-- Empty Wishlist State -->
    <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-lg); padding:60px 20px; text-align:center; max-width:650px; margin:0 auto; box-shadow:var(--shadow-sm);">
      <div style="width:80px; height:80px; border-radius:50%; background:#fcf2eb; color:var(--haat-clay); display:flex; align-items:center; justify-content:center; font-size:2.5rem; margin:0 auto 18px;">
        <i class="bi bi-heart"></i>
      </div>
      <h2 style="font-size:1.4rem; color:var(--haat-green-dark); margin:0 0 8px;">Your Wishlist is Empty</h2>
      <p style="color:var(--text-muted); font-size:0.92rem; max-width:440px; margin:0 auto 24px; line-height:1.5;">
        Browse our artisan guilds to discover authentic Dhakai Jamdani, Rajshahi silks, terracotta, and pure village harvests. Click the heart icon on any craft to save it here!
      </p>
      <a href="<?= BASE_URL ?>shop.php" class="btn btn-clay btn-lg" style="display:inline-flex; align-items:center; gap:8px;">
        <i class="bi bi-bag"></i> Discover Authentic Crafts
      </a>
    </div>
  <?php else: ?>
    <!-- Wishlist Products Grid -->
    <div class="products-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:22px;">
      <?php foreach ($wishlistProducts as $prod): ?>
        <div class="product-card" id="wishlist-card-<?= $prod['id'] ?>" style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); overflow:hidden; display:flex; flex-direction:column; box-shadow:var(--shadow-sm); transition:var(--transition); position:relative;">
          
          <!-- Remove Bookmark Button -->
          <a href="<?= BASE_URL ?>customer/wishlist.php?remove=<?= $prod['id'] ?>" onclick="return handleWishlistRemove(event, <?= $prod['id'] ?>)" class="product-wishlist-btn active" title="Remove from wishlist" style="position:absolute; top:12px; right:12px; z-index:3; width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,0.92); display:flex; align-items:center; justify-content:center; color:#e63946; text-decoration:none; box-shadow:0 2px 6px rgba(0,0,0,0.12);">
            <i class="bi bi-heart-fill"></i>
          </a>

          <!-- Product Image Thumbnail -->
          <div class="product-thumb" style="position:relative; aspect-ratio:1/1; overflow:hidden; background:#f5f5f5;">
            <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>" style="display:block; width:100%; height:100%;">
              <img src="<?= sanitize($prod['featured_image']) ?>" alt="<?= sanitize($prod['name']) ?>" style="width:100%; height:100%; object-fit:cover; transition:transform 0.4s ease;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';">
            </a>
          </div>

          <!-- Product Body -->
          <div class="product-body" style="padding:16px; display:flex; flex-direction:column; flex:1;">
            <div class="product-vendor-meta" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; font-size:0.75rem;">
              <span class="vendor-link" style="color:var(--text-muted); font-weight:600;">
                <i class="bi bi-shop" style="color:var(--haat-clay);"></i> <?= sanitize($prod['shop_name']) ?>
              </span>
              <span class="district-tag" style="background:var(--haat-sand); padding:2px 6px; border-radius:4px; font-weight:600; color:var(--haat-green);">
                <?= sanitize($prod['district_origin']) ?>
              </span>
            </div>

            <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>" class="product-title" style="font-weight:700; color:var(--haat-green-dark); text-decoration:none; font-size:0.95rem; margin-bottom:8px; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
              <?= sanitize($prod['name']) ?>
            </a>

            <div style="margin-top:auto; padding-top:10px; border-top:1px solid #f1f1f1; display:flex; justify-content:space-between; align-items:center;">
              <div class="price-wrap">
                <span class="current-price" style="font-weight:800; font-size:1.15rem; color:var(--haat-green);">
                  <?= formatPrice($prod['sale_price'] ?: $prod['price']) ?>
                </span>
                <?php if ($prod['sale_price']): ?>
                  <span style="font-size:0.78rem; color:var(--text-muted); text-decoration:line-through; margin-left:4px;">
                    <?= formatPrice($prod['price']) ?>
                  </span>
                <?php endif; ?>
              </div>

              <div>
                <?php if ($prod['stock_quantity'] > 0): ?>
                  <span style="font-size:0.75rem; color:#2b8a3e; font-weight:700;"><i class="bi bi-check2"></i> In Stock</span>
                <?php else: ?>
                  <span style="font-size:0.75rem; color:#c52828; font-weight:700;">Out of Stock</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Action Buttons -->
            <div style="display:grid; grid-template-columns: 1fr auto; gap:8px; margin-top:14px;">
              <button type="button" class="btn btn-sm btn-clay btn-add-cart btn-add-cart-action" data-product-id="<?= $prod['id'] ?>" style="width:100%; background:var(--haat-clay) !important; color:#ffffff !important; border:none; padding:8px 16px; font-weight:700; border-radius:var(--radius-sm); display:inline-flex; align-items:center; justify-content:center; gap:6px; cursor:pointer; box-shadow:0 2px 6px rgba(194,97,45,0.25); transition:all 0.2s ease;">
                <i class="bi bi-bag-plus-fill"></i> Move to Cart
              </button>
              <a href="<?= BASE_URL ?>customer/wishlist.php?remove=<?= $prod['id'] ?>" class="btn btn-sm btn-outline-danger" title="Remove" style="padding:6px 10px; color:#c52828; border:1px solid #f8c8dc; border-radius:var(--radius-sm); text-decoration:none;">
                <i class="bi bi-trash"></i>
              </a>
            </div>

          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<script>
  function handleWishlistRemove(event, productId) {
    event.preventDefault();
    if (!confirm('Remove this craft from your saved wishlist?')) return false;

    const fd = new FormData();
    fd.append('action', 'remove');
    fd.append('product_id', productId);

    fetch('<?= BASE_URL ?>api/wishlist.php', {
      method: 'POST',
      body: fd
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const card = document.getElementById(`wishlist-card-${productId}`);
        if (card) {
          card.style.opacity = '0';
          card.style.transform = 'scale(0.9)';
          setTimeout(() => {
            card.remove();
            // Update header badge
            document.querySelectorAll('.wishlist-count-badge').forEach(b => b.innerText = data.wishlist_count);
            // If empty, reload to show empty state
            if (data.wishlist_count === 0) {
              location.reload();
            }
          }, 300);
        }
        showToast('Craft removed from your wishlist.', 'success');
      }
    })
    .catch(() => {
      window.location.href = `<?= BASE_URL ?>customer/wishlist.php?remove=${productId}`;
    });

    return false;
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
