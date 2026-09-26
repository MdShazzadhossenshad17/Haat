<?php
/**
 * Shop / Catalog Page with Advanced Filters
 * HAAT Multi-Vendor Marketplace
 */
$pageTitle = 'Explore Crafts & Products';
require_once __DIR__ . '/includes/header.php';

// Filter Parameters
$department = $_GET['department'] ?? '';
$categorySlug = $_GET['category'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');
$isFlash = isset($_GET['flash']);
$district = $_GET['district'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;

// Base Query
$where = ["p.is_active = 1"];
$params = [];

if ($department !== '') {
    $where[] = "c.department = ?";
    $params[] = $department;
}

if ($categorySlug !== '') {
    $where[] = "c.slug = ?";
    $params[] = $categorySlug;
}

if ($searchQuery !== '') {
    $cleanQuery = strtolower($searchQuery);
    $rawWords = preg_split('/[\s,\-_]+/', $cleanQuery, -1, PREG_SPLIT_NO_EMPTY);
    
    // Stemming & plural normalization
    $searchTerms = [$cleanQuery];
    foreach ($rawWords as $w) {
        $searchTerms[] = $w;
        // Singular form (e.g. moneybags -> moneybag, sarees -> saree, wallets -> wallet)
        if (strlen($w) > 3 && substr($w, -1) === 's') {
            $searchTerms[] = substr($w, 0, -1);
        }
        if (strlen($w) > 4 && substr($w, -2) === 'es') {
            $searchTerms[] = substr($w, 0, -2);
        }
    }
    
    // Synonyms & contextual keyword mapping
    $synonyms = [
        'moneybag' => ['wallet', 'wallets', 'moneybag', 'moneybags', 'leather', 'purse'],
        'moneybags' => ['wallet', 'wallets', 'moneybag', 'moneybags', 'leather', 'purse'],
        'money' => ['wallet', 'moneybag', 'cash'],
        'wallet' => ['wallet', 'wallets', 'moneybag', 'leather', 'bifold'],
        'wallets' => ['wallet', 'wallets', 'moneybag', 'leather', 'bifold'],
        'bag' => ['bag', 'bags', 'wallet', 'leather', 'jute', 'purse'],
        'bags' => ['bag', 'bags', 'wallet', 'leather', 'jute', 'purse'],
        'saree' => ['saree', 'saris', 'sari', 'jamdani', 'dhakai', 'handloom', 'শাড়ি'],
        'sarees' => ['saree', 'saris', 'sari', 'jamdani', 'dhakai', 'handloom', 'শাড়ি'],
        'sari' => ['saree', 'saris', 'jamdani', 'handloom'],
        'saris' => ['saree', 'saris', 'jamdani', 'handloom'],
        'panjabi' => ['panjabi', 'punjabi', 'kurta', 'পাঞ্জাবি'],
        'panjabis' => ['panjabi', 'punjabi', 'kurta'],
        'punjabi' => ['panjabi', 'punjabi', 'kurta'],
        'dress' => ['dress', 'maxi', 'gowns', 'জামার'],
        'dresses' => ['dress', 'maxi'],
        'shirt' => ['shirt', 't-shirt', 'tshirt', 'crewneck', 'টি-শার্ট'],
        'shirts' => ['shirt', 't-shirt', 'tshirt', 'crewneck'],
        'tshirt' => ['t-shirt', 'shirt', 'crewneck'],
        't-shirt' => ['t-shirt', 'shirt', 'crewneck'],
        'honey' => ['honey', 'sundarbans', 'mangrove', 'মধু'],
        'মধু' => ['honey', 'sundarbans'],
        'oil' => ['mustard', 'oil', 'ghani', 'তেল'],
        'oils' => ['mustard', 'oil', 'ghani'],
        'mustard' => ['mustard', 'oil', 'ghani'],
        'tea' => ['tea', 'sreemangal', 'black tea', 'চা'],
        'চা' => ['tea', 'sreemangal'],
        'pottery' => ['terracotta', 'pottery', 'clay', 'মাটির'],
        'clay' => ['terracotta', 'pottery', 'clay'],
        'terracotta' => ['terracotta', 'pottery', 'clay'],
        'watch' => ['watch', 'watches', 'sapphire', 'timepiece', 'ঘড়ি'],
        'watches' => ['watch', 'watches', 'sapphire', 'timepiece', 'ঘড়ি'],
        'rug' => ['rug', 'rugs', 'jute', 'carpet', 'mat', 'মাদুর'],
        'rugs' => ['rug', 'rugs', 'jute', 'carpet', 'mat'],
        'chocolate' => ['chocolate', 'chocolates', 'cocoa', 'চকলেট'],
        'chocolates' => ['chocolate', 'chocolates', 'cocoa'],
        'sweets' => ['khaja', 'sweets', 'delicacies', 'মিষ্টি'],
        'sweet' => ['khaja', 'sweets', 'delicacies'],
        'khaja' => ['khaja', 'tiler khaja', 'sweets'],
        'nuts' => ['nuts', 'cashew', 'almond', 'বাদাম'],
        'nut' => ['nuts', 'cashew', 'almond'],
        'clothes' => ['clothes', 'dress', 'saree', 'panjabi', 'shirt', 'romper', 'পোশাক'],
        'clothing' => ['clothes', 'dress', 'saree', 'panjabi', 'shirt', 'romper'],
        'baby' => ['baby', 'romper', 'infant', 'kids', 'শিশুদের'],
        'bamboo' => ['bamboo', 'cane', 'craft', 'বাঁশ'],
        'cane' => ['cane', 'bamboo', 'craft', 'বেত'],
        'rickshaw' => ['rickshaw', 'canvas', 'painting', 'রিকশা']
    ];

    $allExpandedTerms = $searchTerms;
    foreach ($searchTerms as $term) {
        if (isset($synonyms[$term])) {
            $allExpandedTerms = array_merge($allExpandedTerms, $synonyms[$term]);
        }
    }
    $allExpandedTerms = array_unique(array_filter($allExpandedTerms));

    $searchConditions = [];
    foreach ($allExpandedTerms as $term) {
        $searchConditions[] = "(p.name LIKE ? OR p.name_bn LIKE ? OR p.short_description LIKE ? OR p.description LIKE ? OR p.keywords LIKE ? OR p.sku LIKE ? OR c.name LIKE ? OR c.name_bn LIKE ? OR c.department LIKE ? OR s.shop_name LIKE ? OR p.district_origin LIKE ?)";
        $termLike = "%" . $term . "%";
        for ($k = 0; $k < 11; $k++) {
            $params[] = $termLike;
        }
    }
    
    if (!empty($searchConditions)) {
        $where[] = "(" . implode(' OR ', $searchConditions) . ")";
    }
}

if ($isFlash) {
    $where[] = "p.is_flash_deal = 1";
}

if ($district !== '') {
    $where[] = "p.district_origin = ?";
    $params[] = $district;
}

if ($minPrice > 0) {
    $where[] = "COALESCE(p.sale_price, p.price) >= ?";
    $params[] = $minPrice;
}

if ($maxPrice > 0) {
    $where[] = "COALESCE(p.sale_price, p.price) <= ?";
    $params[] = $maxPrice;
}

// Sorting
$orderClause = "p.id DESC";
if ($sortBy === 'price_asc') $orderClause = "COALESCE(p.sale_price, p.price) ASC";
if ($sortBy === 'price_desc') $orderClause = "COALESCE(p.sale_price, p.price) DESC";
if ($sortBy === 'popular') $orderClause = "p.views DESC";

$sql = "SELECT p.*, s.shop_name, s.district as seller_district, s.is_verified as seller_verified, c.name as category_name, c.slug as category_slug, c.department,
        COALESCE((SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id), p.rating, 0.0) as rating,
        (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
        FROM `products` p 
        JOIN `sellers` s ON p.seller_id = s.id 
        JOIN `categories` c ON p.category_id = c.id 
        WHERE " . implode(' AND ', $where) . " 
        ORDER BY " . $orderClause;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Intelligent category fallback: If user had a category selected and searched for a keyword that yielded 0 results in that category, search across ALL categories!
$searchFallbackNotice = false;
if (empty($products) && !empty($searchQuery) && !empty($categorySlug)) {
    $fallbackWhere = array_values(array_filter($where, function($clause) {
        return strpos($clause, 'c.slug = ?') === false;
    }));
    $fallbackParams = [];
    $catSlugMatched = false;
    foreach ($params as $paramValue) {
        if ($paramValue === $categorySlug && !$catSlugMatched) {
            $catSlugMatched = true;
            continue;
        }
        $fallbackParams[] = $paramValue;
    }
    $fallbackSql = "SELECT p.*, s.shop_name, s.district as seller_district, s.is_verified as seller_verified, c.name as category_name, c.slug as category_slug, c.department,
            COALESCE((SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id), p.rating, 0.0) as rating,
            (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
            FROM `products` p 
            JOIN `sellers` s ON p.seller_id = s.id 
            JOIN `categories` c ON p.category_id = c.id 
            WHERE " . implode(' AND ', $fallbackWhere) . " 
            ORDER BY " . $orderClause;
    $fbStmt = $db->prepare($fallbackSql);
    $fbStmt->execute($fallbackParams);
    $fallbackProducts = $fbStmt->fetchAll();
    if (!empty($fallbackProducts)) {
        $products = $fallbackProducts;
        $searchFallbackNotice = true;
    }
}

// Categories with counts
$categoriesWithCount = $db->query("SELECT c.*, COUNT(p.id) as total_products 
    FROM `categories` c 
    LEFT JOIN `products` p ON c.id = p.category_id AND p.is_active = 1 
    GROUP BY c.id ORDER BY c.id ASC")->fetchAll();

$deptGrouped = [
    'Clothes' => [],
    'Food' => [],
    'Art & Accessories' => []
];
foreach ($categoriesWithCount as $c) {
    if (isset($deptGrouped[$c['department']])) {
        $deptGrouped[$c['department']][] = $c;
    }
}

// Districts list
$districts = $db->query("SELECT DISTINCT district_origin FROM `products` WHERE is_active = 1 ORDER BY district_origin ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container" style="padding: 24px 20px 60px;">
  
  <!-- Breadcrumb & Department Filter Pills -->
  <div style="margin-bottom: 20px;">
    <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:12px;">
      <a href="<?= BASE_URL ?>" style="color:var(--haat-green); font-weight:600; text-decoration:none;">Home</a> &rarr; 
      <a href="<?= BASE_URL ?>shop.php" style="color:inherit; text-decoration:none;">Shop</a>
      <?php if ($department): ?> &rarr; <span style="font-weight:700; color:var(--haat-clay);"><?= sanitize($department) ?></span><?php endif; ?>
      <?php if ($categorySlug): ?> &rarr; <span style="font-weight:700; color:var(--haat-clay);"><?= sanitize($categorySlug) ?></span><?php endif; ?>
    </div>

    <!-- Quick Department Pill Bar -->
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
      <a href="<?= BASE_URL ?>shop.php<?= $isFlash ? '?flash=1' : '' ?>" class="badge" style="padding:8px 16px; font-size:0.85rem; text-decoration:none; background:<?= empty($department) ? 'var(--haat-green)' : '#fff' ?>; color:<?= empty($department) ? '#fff' : 'var(--text-main)' ?>; border:1px solid var(--haat-border); border-radius:20px; font-weight:600;">
        All Departments
      </a>
      <a href="<?= BASE_URL ?>shop.php?department=Clothes<?= $isFlash ? '&flash=1' : '' ?>" class="badge" style="padding:8px 16px; font-size:0.85rem; text-decoration:none; background:<?= $department === 'Clothes' ? 'var(--haat-green)' : '#fff' ?>; color:<?= $department === 'Clothes' ? '#fff' : 'var(--text-main)' ?>; border:1px solid var(--haat-border); border-radius:20px; font-weight:600;">
        <i class="bi bi-person-standing"></i> Clothes
      </a>
      <a href="<?= BASE_URL ?>shop.php?department=Food<?= $isFlash ? '&flash=1' : '' ?>" class="badge" style="padding:8px 16px; font-size:0.85rem; text-decoration:none; background:<?= $department === 'Food' ? 'var(--haat-green)' : '#fff' ?>; color:<?= $department === 'Food' ? '#fff' : 'var(--text-main)' ?>; border:1px solid var(--haat-border); border-radius:20px; font-weight:600;">
        <i class="bi bi-basket2"></i> Food & Grocery
      </a>
      <a href="<?= BASE_URL ?>shop.php?department=<?= urlencode('Art & Accessories') ?><?= $isFlash ? '&flash=1' : '' ?>" class="badge" style="padding:8px 16px; font-size:0.85rem; text-decoration:none; background:<?= $department === 'Art & Accessories' ? 'var(--haat-green)' : '#fff' ?>; color:<?= $department === 'Art & Accessories' ? '#fff' : 'var(--text-main)' ?>; border:1px solid var(--haat-border); border-radius:20px; font-weight:600;">
        <i class="bi bi-gem"></i> Art & Accessories
      </a>
    </div>
  </div>

  <div style="display:grid; grid-template-columns: 280px 1fr; gap: 32px; align-items:flex-start;">
    
    <!-- Sidebar Filters -->
    <aside style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:24px; box-shadow:var(--shadow-sm);">
      <h3 style="font-size:1.15rem; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid var(--haat-sand); display:flex; justify-content:space-between; align-items:center;">
        <span><i class="bi bi-funnel text-clay"></i> Filter Products</span>
        <a href="<?= BASE_URL ?>shop.php" style="font-size:0.75rem; color:var(--haat-clay); font-weight:600;">Reset All</a>
      </h3>

      <!-- Grouped Categories by Department -->
      <div style="margin-bottom: 24px;">
        <h4 style="font-size:0.95rem; margin-bottom:12px; color:var(--haat-green);">Categories by Department</h4>
        
        <?php foreach ($deptGrouped as $deptName => $deptCategoryList): ?>
          <div style="margin-bottom: 14px;">
            <div style="font-size:0.82rem; font-weight:800; color:var(--haat-clay); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; display:flex; justify-content:space-between;">
              <a href="<?= BASE_URL ?>shop.php?department=<?= urlencode($deptName) ?>" style="color:inherit; text-decoration:none;"><?= $deptName ?></a>
            </div>
            <ul style="display:flex; flex-direction:column; gap:5px; list-style:none; padding-left:4px; margin:0;">
              <?php foreach ($deptCategoryList as $c): ?>
                <li>
                  <a href="<?= BASE_URL ?>shop.php?category=<?= sanitize($c['slug']) ?>" style="font-size:0.82rem; font-weight:<?= $categorySlug === $c['slug'] ? '700' : '400' ?>; color:<?= $categorySlug === $c['slug'] ? 'var(--haat-clay)' : 'var(--text-main)' ?>; text-decoration:none; display:flex; justify-content:space-between; align-items:center; padding:2px 0;">
                    <span><?= sanitize($c['name']) ?></span>
                    <span style="font-size:0.72rem; color:var(--text-muted);">(<?= $c['total_products'] ?>)</span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- District of Origin Filter -->
      <div style="margin-bottom: 24px;">
        <h4 style="font-size:0.95rem; margin-bottom:12px; color:var(--haat-green);">Artisan Origin District</h4>
        <div style="display:flex; flex-wrap:wrap; gap:6px;">
          <?php foreach ($districts as $dist): ?>
            <a href="<?= BASE_URL ?>shop.php?district=<?= urlencode($dist) ?><?= $categorySlug ? '&category=' . urlencode($categorySlug) : '' ?><?= $department ? '&department=' . urlencode($department) : '' ?>" class="badge <?= $district === $dist ? 'badge-dark' : 'badge-green' ?>" style="text-decoration:none;">
              <?= sanitize($dist) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Price Filter Form -->
      <div>
        <h4 style="font-size:0.95rem; margin-bottom:12px; color:var(--haat-green);">Price Range (BDT)</h4>
        <form action="<?= BASE_URL ?>shop.php" method="GET" style="display:flex; flex-direction:column; gap:10px;">
          <?php if ($department): ?><input type="hidden" name="department" value="<?= sanitize($department) ?>"><?php endif; ?>
          <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= sanitize($categorySlug) ?>"><?php endif; ?>
          <?php if ($searchQuery): ?><input type="hidden" name="q" value="<?= sanitize($searchQuery) ?>"><?php endif; ?>
          <div style="display:flex; gap:8px;">
            <input type="number" name="min_price" placeholder="Min ৳" value="<?= $minPrice ?: '' ?>" style="width:50%; padding:8px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.85rem;">
            <input type="number" name="max_price" placeholder="Max ৳" value="<?= $maxPrice ?: '' ?>" style="width:50%; padding:8px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-size:0.85rem;">
          </div>
          <button type="submit" class="btn btn-sm btn-outline-clay">Apply Filter</button>
        </form>
      </div>

    </aside>

    <!-- Main Product Grid & Header -->
    <main>
      <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; box-shadow:var(--shadow-sm);">
        <div>
          <span style="font-weight:700; color:var(--haat-green); font-size:1.1rem;">
            <?= count($products) ?> Products Found
          </span>
          <?php if ($searchQuery): ?>
            <span style="font-size:0.9rem; color:var(--text-muted);">for "<?= sanitize($searchQuery) ?>"</span>
          <?php endif; ?>
          <?php if (!empty($searchFallbackNotice)): ?>
            <div style="font-size:0.8rem; color:var(--haat-clay); font-weight:600; margin-top:3px;">
              <i class="bi bi-info-circle"></i> No results in selected category — showing matches found across All Categories
            </div>
          <?php endif; ?>
        </div>

        <!-- Sorting dropdown -->
        <div style="display:flex; align-items:center; gap:10px;">
          <label style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">Sort By:</label>
          <select onchange="location = this.value;" style="padding:8px 12px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); font-family:inherit; font-size:0.88rem; outline:none; cursor:pointer;">
            <option value="<?= BASE_URL ?>shop.php?sort=newest<?= $categorySlug ? '&category=' . $categorySlug : '' ?>" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest Arrivals</option>
            <option value="<?= BASE_URL ?>shop.php?sort=price_asc<?= $categorySlug ? '&category=' . $categorySlug : '' ?>" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="<?= BASE_URL ?>shop.php?sort=price_desc<?= $categorySlug ? '&category=' . $categorySlug : '' ?>" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
          </select>
        </div>
      </div>

      <!-- Products Grid -->
      <?php if (empty($products)): ?>
        <div style="background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-md); padding:60px 20px; text-align:center;">
          <i class="bi bi-search" style="font-size:3rem; color:var(--text-light); margin-bottom:14px; display:block;"></i>
          <h3 style="margin-bottom:8px;">No artisanal products found matching your filters</h3>
          <p style="color:var(--text-muted); margin-bottom:20px;">Try clearing filters or search for another term.</p>
          <a href="<?= BASE_URL ?>shop.php" class="btn btn-clay">Reset All Filters</a>
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
                <?php if (!isSeller() && !isAdmin()): ?>
                <button type="button" class="product-wishlist-btn <?= isInWishlist($prod['id']) ? 'active' : '' ?>" data-product-id="<?= $prod['id'] ?>" title="Save to Wishlist">
                  <i class="bi <?= isInWishlist($prod['id']) ? 'bi-heart-fill' : 'bi-heart' ?>" <?= isInWishlist($prod['id']) ? 'style="color:#e63946;"' : '' ?>></i>
                </button>
                <?php endif; ?>
              </div>

              <div class="product-body">
                <div class="product-vendor-meta">
                  <a href="<?= BASE_URL ?>vendor.php?id=<?= $prod['seller_id'] ?>" class="vendor-link">
                    <i class="bi bi-shop"></i> <?= sanitize($prod['shop_name']) ?>
                    <?php if (!empty($prod['seller_verified'])): ?>
                      <i class="bi bi-patch-check-fill text-green" style="font-size:0.75rem;" title="Verified Store"></i>
                    <?php endif; ?>
                  </a>
                  <span class="district-tag"><?= sanitize($prod['district_origin']) ?></span>
                </div>

                <a href="<?= BASE_URL ?>product.php?id=<?= $prod['id'] ?>" class="product-title">
                  <?= sanitize($prod['name']) ?>
                </a>

                <div class="product-rating" data-product-id="<?= $prod['id'] ?>" data-seller-id="<?= $prod['seller_id'] ?>" style="display:flex; align-items:center; gap:5px; font-size:0.82rem; margin-bottom:8px;">
                  <span style="color:#f59e0b; display:inline-flex; align-items:center; gap:2px;"><i class="bi bi-star-fill"></i></span>
                  <span class="product-avg" style="font-weight:700; color:var(--text-main);"><?= number_format($prod['rating'] ?? 0, 1) ?></span>
                  <small style="color:var(--text-muted);">(<span class="product-count"><?= (int)($prod['review_count'] ?? 0) ?></span>)</small>
                </div>

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
        <script>
          // Batch refresh ratings for visible product cards
          (function() {
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
                      const avgEl = c.querySelector('.product-avg');
                      const cntEl = c.querySelector('.product-count');
                      if (avgEl) avgEl.textContent = parseFloat(data.ratings[pid].avg).toFixed(1);
                      if (cntEl) cntEl.textContent = data.ratings[pid].count;
                    }
                  });
                }
              }).catch(()=>{});
          })();
        </script>
      <?php endif; ?>

    </main>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
