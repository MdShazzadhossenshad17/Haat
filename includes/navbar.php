<?php
/**
 * Navigation Bar Component
 * HAAT - Multi-Vendor Marketplace
 */
$allNavCats = $db->query("SELECT * FROM `categories` ORDER BY id ASC")->fetchAll();
$deptCats = [
    'Clothes' => [],
    'Food' => [],
    'Art & Accessories' => []
];
foreach ($allNavCats as $c) {
    if (isset($deptCats[$c['department']])) {
        $deptCats[$c['department']][] = $c;
    }
}
$currentDept = $_GET['department'] ?? '';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$basePath = parse_url(BASE_URL, PHP_URL_PATH);
$relPath = trim(str_replace($basePath, '', $requestPath), '/');
$isHome = ($relPath === '' || $relPath === 'index.php') && empty($_SERVER['QUERY_STRING']);
?>
<nav class="navbar-sub">
  <div class="container">
    <div style="display:flex; align-items:center; gap:24px; height:100%;">
      
      <!-- Browse Categories Mega Dropdown -->
      <div style="position:relative;">
        <button class="category-dropdown-btn" id="cat-toggle-btn" type="button" aria-expanded="false">
          <i class="bi bi-grid-fill"></i>
          <span>All Categories</span>
          <i class="bi bi-chevron-down" style="font-size:0.75rem; margin-left:4px;"></i>
        </button>

        <div id="category-menu-flyout" style="display:none; position:absolute; top:calc(100% + 8px); left:0; width:640px; background:#fff; border:1px solid var(--haat-border); box-shadow:var(--shadow-lg); border-radius:var(--radius-md); z-index:999; padding:20px;">
          <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:18px;">
            
            <!-- Clothes Column -->
            <div>
              <a href="<?= BASE_URL ?>shop.php?department=Clothes" style="display:flex; align-items:center; gap:8px; font-size:0.95rem; font-weight:800; color:var(--haat-green); padding-bottom:8px; border-bottom:2px solid var(--haat-sand); margin-bottom:10px; text-decoration:none;">
                <i class="bi bi-person-standing"></i> Clothes
              </a>
              <ul style="display:flex; flex-direction:column; gap:6px; list-style:none; padding:0; margin:0;">
                <?php foreach ($deptCats['Clothes'] as $nc): ?>
                  <li>
                    <a href="<?= BASE_URL ?>shop.php?category=<?= sanitize($nc['slug']) ?>" style="display:flex; align-items:center; gap:8px; font-size:0.83rem; font-weight:500; color:var(--text-main); text-decoration:none; padding:4px 0; transition:var(--transition);" onmouseover="this.style.color='var(--haat-clay)'; this.style.transform='translateX(3px)';" onmouseout="this.style.color='var(--text-main)'; this.style.transform='none';">
                      <i class="bi <?= sanitize($nc['icon']) ?>" style="font-size:0.85rem; color:var(--text-muted);"></i>
                      <span><?= sanitize($nc['name']) ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <!-- Food Column -->
            <div>
              <a href="<?= BASE_URL ?>shop.php?department=Food" style="display:flex; align-items:center; gap:8px; font-size:0.95rem; font-weight:800; color:var(--haat-green); padding-bottom:8px; border-bottom:2px solid var(--haat-sand); margin-bottom:10px; text-decoration:none;">
                <i class="bi bi-basket2"></i> Food & Grocery
              </a>
              <ul style="display:flex; flex-direction:column; gap:6px; list-style:none; padding:0; margin:0;">
                <?php foreach ($deptCats['Food'] as $nc): ?>
                  <li>
                    <a href="<?= BASE_URL ?>shop.php?category=<?= sanitize($nc['slug']) ?>" style="display:flex; align-items:center; gap:8px; font-size:0.83rem; font-weight:500; color:var(--text-main); text-decoration:none; padding:4px 0; transition:var(--transition);" onmouseover="this.style.color='var(--haat-clay)'; this.style.transform='translateX(3px)';" onmouseout="this.style.color='var(--text-main)'; this.style.transform='none';">
                      <i class="bi <?= sanitize($nc['icon']) ?>" style="font-size:0.85rem; color:var(--text-muted);"></i>
                      <span><?= sanitize($nc['name']) ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <!-- Art & Accessories Column -->
            <div>
              <a href="<?= BASE_URL ?>shop.php?department=<?= urlencode('Art & Accessories') ?>" style="display:flex; align-items:center; gap:8px; font-size:0.95rem; font-weight:800; color:var(--haat-green); padding-bottom:8px; border-bottom:2px solid var(--haat-sand); margin-bottom:10px; text-decoration:none;">
                <i class="bi bi-gem"></i> Art & Accessories
              </a>
              <ul style="display:flex; flex-direction:column; gap:6px; list-style:none; padding:0; margin:0;">
                <?php foreach ($deptCats['Art & Accessories'] as $nc): ?>
                  <li>
                    <a href="<?= BASE_URL ?>shop.php?category=<?= sanitize($nc['slug']) ?>" style="display:flex; align-items:center; gap:8px; font-size:0.83rem; font-weight:500; color:var(--text-main); text-decoration:none; padding:4px 0; transition:var(--transition);" onmouseover="this.style.color='var(--haat-clay)'; this.style.transform='translateX(3px)';" onmouseout="this.style.color='var(--text-main)'; this.style.transform='none';">
                      <i class="bi <?= sanitize($nc['icon']) ?>" style="font-size:0.85rem; color:var(--text-muted);"></i>
                      <span><?= sanitize($nc['name']) ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>

          </div>

          <div style="border-top:1px solid var(--haat-border); margin-top:16px; padding-top:12px; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:0.8rem; color:var(--text-muted);">Handmade, Modern & Heritage Crafts</span>
            <a href="<?= BASE_URL ?>shop.php" style="font-size:0.85rem; font-weight:700; color:var(--haat-clay); text-decoration:none;">
              Explore All Categories &rarr;
            </a>
          </div>
        </div>
      </div>

      <script>
        const catBtn = document.getElementById('cat-toggle-btn');
        const catFlyout = document.getElementById('category-menu-flyout');
        if (catBtn && catFlyout) {
          catBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            catFlyout.style.display = (catFlyout.style.display === 'none' || !catFlyout.style.display) ? 'block' : 'none';
          });
          document.addEventListener('click', (e) => {
            if (!catBtn.contains(e.target) && !catFlyout.contains(e.target)) {
              catFlyout.style.display = 'none';
            }
          });
        }
      </script>

      <!-- Main Navigation Links -->
      <ul class="nav-menu">
        <li>
          <a href="<?= BASE_URL ?>" class="nav-link <?= $isHome ? 'active' : '' ?>">
            Home
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>shop.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'shop.php' && empty($currentDept) && !isset($_GET['flash']) ? 'active' : '' ?>">
            Shop
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>shop.php?department=Clothes" class="nav-link <?= $currentDept === 'Clothes' ? 'active' : '' ?>">
            Clothes
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>shop.php?department=Food" class="nav-link <?= $currentDept === 'Food' ? 'active' : '' ?>">
            Food
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>shop.php?department=<?= urlencode('Art & Accessories') ?>" class="nav-link <?= $currentDept === 'Art & Accessories' ? 'active' : '' ?>">
            Art & Accessories
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>shop.php?flash=1" class="nav-link <?= (isset($_GET['flash'])) ? 'active' : '' ?>">
            <i class="bi bi-lightning-charge-fill" style="color:var(--haat-clay); margin-right:4px;"></i>Flash Deals
          </a>
        </li>
        <li>
          <a href="<?= BASE_URL ?>track-order.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'track-order.php' ? 'active' : '' ?>">
            Track Order
          </a>
        </li>
      </ul>

    </div>

    <!-- Account Context CTA -->
    <div style="display:flex; align-items:center;">
      <?php if (isSeller()): ?>
        <a href="<?= BASE_URL ?>seller/" class="btn btn-clay" style="height:40px; padding:0 18px; font-size:0.88rem; border-radius:var(--radius-sm); display:inline-flex; align-items:center; gap:8px;">
          <i class="bi bi-speedometer2"></i> Seller Dashboard
        </a>
      <?php elseif (isAdmin()): ?>
        <a href="<?= BASE_URL ?>admin/" class="btn btn-clay" style="height:40px; padding:0 18px; font-size:0.88rem; border-radius:var(--radius-sm); display:inline-flex; align-items:center; gap:8px;">
          <i class="bi bi-speedometer2"></i> Admin Panel
        </a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>register.php?type=seller" class="btn btn-clay" style="height:40px; padding:0 18px; font-size:0.88rem; border-radius:var(--radius-sm); display:inline-flex; align-items:center; gap:8px;">
          <i class="bi bi-shop"></i> Become an Artisan Seller
        </a>
      <?php endif; ?>
    </div>

  </div>
</nav>

