<?php
/**
 * Global Header Component
 * HAAT - Bangladeshi Multi-Vendor E-Commerce Platform
 */
require_once __DIR__ . '/functions.php';

$user = currentUser();
$cartCount = getCartCount();
$cartSubtotal = getCartSubtotal();
$wishlistCount = getWishlistCount();
$userUnreadNotifs = $user ? getUserUnreadNotificationCount($user['id']) : 0;
$userRecentNotifs = $user ? getUserNotifications($user['id'], 6) : [];

// Categories for search dropdown
$stmt = $db->query("SELECT id, name, slug FROM `categories` ORDER BY name ASC");
$headerCategories = $stmt->fetchAll();
// Check if in Portal Mode
$isLogisticsPath = (strpos($_SERVER['REQUEST_URI'] ?? '', '/logistics') !== false);
$isAdminPath = (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') !== false);

if (!empty($hidePublicNav) || !empty($isPortal) || $isLogisticsPath || $isAdminPath) {
    $hideSearch = true;
    $hideNavbar = true;
    $hideWishlist = true;
    $hideCart = true;
    if ($isLogisticsPath && empty($portalBadge)) {
        $portalBadge = '<i class="bi bi-truck"></i> HAATEX LOGISTICS';
        $portalHomeUrl = BASE_URL . 'logistics/';
    } elseif ($isAdminPath && empty($portalBadge)) {
        $portalBadge = '<i class="bi bi-shield-check"></i> ADMIN OPERATIONS';
        $portalHomeUrl = BASE_URL . 'admin/';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — HAAT' : 'HAAT — Global • Regional • Artisanal | Bangladesh Multi-Vendor Marketplace' ?></title>
  <meta name="description" content="HAAT is Bangladesh's premier artisanal multi-vendor marketplace connecting rural weavers, potters, organic harvesters, and craftspeople with global buyers.">
  
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="icon" type="image/jpeg" href="<?= BASE_URL ?>assets/images/logo.png">
  <script>window.HAAT_BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>


  <!-- Main Header -->
  <header class="header-main" style="<?= (!empty($hideNavbar)) ? 'border-bottom:1px solid var(--haat-border);' : '' ?>">
    <div class="container" style="<?= (!empty($hideNavbar)) ? 'display:flex; justify-content:space-between; align-items:center;' : '' ?>">
      
      <!-- Brand Logo (User's Exact HAAT Logo) -->
      <div style="display:flex; align-items:center; gap:12px;">
        <a href="<?= !empty($portalHomeUrl) ? $portalHomeUrl : BASE_URL ?>" class="brand-logo" style="text-decoration:none;">
          <img src="<?= BASE_URL ?>assets/images/logo.png" alt="HAAT — Global • Regional • Artisanal">
        </a>
        <?php if (!empty($portalBadge)): ?>
          <span style="background:var(--haat-green-dark); color:#fff; font-size:0.75rem; font-weight:700; padding:4px 10px; border-radius:12px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 1px 4px rgba(0,0,0,0.1);">
            <?= $portalBadge ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- Search with Category Select -->
      <?php if (empty($hideSearch)): ?>
      <form action="<?= BASE_URL ?>shop.php" method="GET" class="search-box">
        <select name="category" class="search-category-select">
          <option value="">All Categories</option>
          <?php foreach ($headerCategories as $cat): ?>
            <option value="<?= sanitize($cat['slug']) ?>" <?= (isset($_GET['category']) && $_GET['category'] === $cat['slug']) ? 'selected' : '' ?>>
              <?= sanitize($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="q" class="search-input" placeholder="Search handloom sarees, clay pottery, wild honey..." value="<?= sanitize($_GET['q'] ?? '') ?>">
        <button type="submit" class="search-btn" title="Search"><i class="bi bi-search"></i></button>
      </form>
      <?php endif; ?>

      <!-- User Actions -->
      <div class="header-actions">
        
        <!-- Notifications Bell Dropdown -->
        <?php if ($user): ?>
          <div style="position:relative;" id="notif-wrapper">
            <button type="button" id="notif-bell-btn" style="width:42px; height:42px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); background:#fff; font-size:1.15rem; color:var(--haat-green-dark); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:var(--transition); box-shadow:var(--shadow-sm); position:relative;" onmouseover="this.style.borderColor='var(--haat-clay)';" onmouseout="this.style.borderColor='var(--haat-border)';" title="Order Updates & Tracking Alerts">
              <i class="bi bi-bell"></i>
              <span id="header-notif-badge" style="position:absolute; top:-4px; right:-4px; background:var(--haat-clay); color:#fff; font-size:0.68rem; font-weight:700; min-width:18px; height:18px; padding:0 4px; border-radius:10px; display:<?= $userUnreadNotifs > 0 ? 'inline-flex' : 'none' ?>; align-items:center; justify-content:center; border:2px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,0.15);">
                <?= $userUnreadNotifs ?>
              </span>
            </button>

            <!-- Notifications Dropdown Menu -->
            <div id="notif-menu-dropdown" style="display:none; position:absolute; top:calc(100% + 8px); right:0; background:#fff; border:1px solid var(--haat-border); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.12); width:350px; max-width:92vw; z-index:1002; overflow:hidden;">
              <div style="padding:12px 16px; background:linear-gradient(180deg, #ffffff 0%, var(--haat-cream) 100%); border-bottom:1px solid var(--haat-border); display:flex; justify-content:space-between; align-items:center;">
                <div style="font-weight:700; color:var(--haat-green-dark); font-size:0.92rem; display:flex; align-items:center; gap:6px;">
                  <i class="bi bi-bell-fill text-clay"></i> Order & Activity Alerts
                </div>
                <button type="button" id="mark-all-read-btn" onclick="markAllNotificationsRead(event)" style="background:var(--haat-clay-light); border:1px solid rgba(194,97,45,0.22); color:var(--haat-clay); font-size:0.75rem; font-weight:700; cursor:pointer; padding:3px 9px; border-radius:6px; display:<?= $userUnreadNotifs > 0 ? 'inline-flex' : 'none' ?>; align-items:center; gap:4px; transition:all 0.15s ease;" onmouseover="this.style.background='var(--haat-clay)'; this.style.color='#ffffff';" onmouseout="this.style.background='var(--haat-clay-light)'; this.style.color='var(--haat-clay)';">
                  <i class="bi bi-check2-all"></i> Mark all read
                </button>
              </div>

              <div id="notif-list-container" style="max-height:360px; overflow-y:auto; padding:4px 0;">
                <?php if (empty($userRecentNotifs)): ?>
                  <div style="text-align:center; padding:28px 16px; color:var(--text-muted); font-size:0.85rem;">
                    <i class="bi bi-bell-slash" style="font-size:1.8rem; display:block; margin-bottom:6px; opacity:0.5;"></i>
                    No notifications yet
                  </div>
                <?php else: ?>
                  <?php foreach ($userRecentNotifs as $n): 
                    $isUnread = !$n['is_read'];
                    $iconClass = 'bi-bell-fill';
                    $iconBg = 'var(--haat-sand)';
                    $iconColor = 'var(--haat-green-dark)';
                    if (strpos($n['type'], 'confirmed') !== false || strpos($n['type'], 'order_placed') !== false) {
                        $iconClass = 'bi-box-seam-fill';
                        $iconBg = '#e8efe9';
                        $iconColor = 'var(--haat-green)';
                    } elseif (strpos($n['type'], 'processing') !== false) {
                        $iconClass = 'bi-gear-wide-connected';
                        $iconBg = 'var(--haat-clay-light)';
                        $iconColor = 'var(--haat-clay)';
                    } elseif (strpos($n['type'], 'shipped') !== false || strpos($n['type'], 'dispatched') !== false) {
                        $iconClass = 'bi-truck';
                        $iconBg = '#fbf0e4';
                        $iconColor = '#b45309';
                    } elseif (strpos($n['type'], 'delivered') !== false) {
                        $iconClass = 'bi-check2-circle';
                        $iconBg = '#e2ede5';
                        $iconColor = 'var(--haat-green)';
                    } elseif (strpos($n['type'], 'message') !== false) {
                        $iconClass = 'bi-chat-dots-fill';
                        $iconBg = 'var(--haat-clay-light)';
                        $iconColor = 'var(--haat-clay)';
                    }
                    $targetLink = $n['link'] ?: BASE_URL . 'customer/#orders';
                  ?>
                    <a href="<?= sanitize($targetLink) ?>" class="notif-row <?= $isUnread ? 'is-unread' : '' ?>" onclick="markNotificationSingleRead(event, <?= (int)$n['id'] ?>, '<?= sanitize($targetLink) ?>')" style="display:flex; gap:12px; padding:11px 16px; text-decoration:none; color:inherit; border-bottom:1px solid rgba(0,0,0,0.04); background:<?= $isUnread ? '#fcfaf7' : '#fff' ?>; border-left:3px solid <?= $isUnread ? 'var(--haat-clay)' : 'transparent' ?>; transition:all 0.15s ease;" onmouseover="this.style.background='#faf7f2';" onmouseout="this.style.background=this.classList.contains('is-unread') ? '#fcfaf7' : '#fff';">
                      <div style="width:34px; height:34px; border-radius:8px; background:<?= $iconBg ?>; color:<?= $iconColor ?>; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0;">
                        <i class="bi <?= $iconClass ?>"></i>
                      </div>
                      <div style="flex:1; min-width:0;">
                        <div class="notif-title-row" style="font-size:0.85rem; font-weight:<?= $isUnread ? '700' : '600' ?>; color:var(--haat-green-dark); margin-bottom:2px; display:flex; justify-content:space-between; align-items:center;">
                          <span class="notif-title-text" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= sanitize($n['title']) ?></span>
                          <?php if ($isUnread): ?>
                            <span class="notif-mark-dot" title="Unread Alert" style="width:7px; height:7px; border-radius:50%; background:var(--haat-clay); display:inline-block; margin-left:6px; flex-shrink:0; box-shadow:0 0 0 2px rgba(194,97,45,0.25);"></span>
                          <?php endif; ?>
                        </div>
                        <div style="font-size:0.77rem; color:var(--text-muted); line-height:1.35; margin-bottom:3px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                          <?= sanitize($n['message']) ?>
                        </div>
                        <div style="font-size:0.7rem; color:var(--text-muted); opacity:0.85;">
                          <?= date('d M, h:i A', strtotime($n['created_at'])) ?>
                        </div>
                      </div>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <div style="padding:10px 16px; background:#faf8f5; border-top:1px solid var(--haat-border); text-align:center;">
                <a href="<?= BASE_URL ?>customer/#orders" style="font-size:0.82rem; font-weight:700; color:var(--haat-green-dark); text-decoration:none;">
                  View Live Tracking in Orders &rarr;
                </a>
              </div>
            </div>
          </div>

          <script>
            // Notification toggle & handlers
            const notifBtn = document.getElementById('notif-bell-btn');
            const notifDropdown = document.getElementById('notif-menu-dropdown');
            if (notifBtn && notifDropdown) {
              notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (typeof userDropdown !== 'undefined' && userDropdown) userDropdown.style.display = 'none';
                notifDropdown.style.display = (notifDropdown.style.display === 'none' || !notifDropdown.style.display) ? 'block' : 'none';
              });
              document.addEventListener('click', (e) => {
                if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
                  notifDropdown.style.display = 'none';
                }
              });
            }

            function markNotificationSingleRead(e, notifId, link) {
              if (e) e.preventDefault();
              fetch('<?= BASE_URL ?>api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_read&id=${encodeURIComponent(notifId)}`
              }).finally(() => {
                window.location.href = link;
              });
            }

            function markAllNotificationsRead(e) {
              if (e) e.stopPropagation();
              const btn = document.getElementById('mark-all-read-btn');
              if (btn) btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Marking...';

              fetch('<?= BASE_URL ?>api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_read'
              }).then(res => res.json()).then(data => {
                if (data.success) {
                  const b = document.getElementById('header-notif-badge');
                  if (b) b.style.display = 'none';
                  if (btn) btn.style.display = 'none';
                  // Remove all unread indicator dots
                  document.querySelectorAll('.notif-mark-dot').forEach(dot => dot.remove());
                  // Reset unread styles on all notification rows
                  document.querySelectorAll('.notif-row').forEach(row => {
                    row.classList.remove('is-unread');
                    row.style.background = '#ffffff';
                    row.style.borderLeft = '3px solid transparent';
                    const titleRow = row.querySelector('.notif-title-row');
                    if (titleRow) titleRow.style.fontWeight = '600';
                  });
                } else if (btn) {
                  btn.innerHTML = '<i class="bi bi-check2-all"></i> Mark all read';
                }
              }).catch(err => {
                console.error(err);
                if (btn) btn.innerHTML = '<i class="bi bi-check2-all"></i> Mark all read';
              });
            }
          </script>
        <?php endif; ?>

        <!-- Account Dropdown / Link (Clean Rectangular Button) -->
        <?php if ($user): ?>
          <div style="position:relative;">
            <button type="button" id="user-menu-btn" style="height:42px; padding:0 16px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); background:#fff; font-size:0.9rem; font-weight:600; color:var(--haat-green-dark); cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:var(--transition); box-shadow:var(--shadow-sm);" onmouseover="this.style.borderColor='var(--haat-clay)';" onmouseout="this.style.borderColor='var(--haat-border)';">
              <i class="bi bi-person-fill" style="color:var(--haat-clay); font-size:1.1rem;"></i>
              <span><?= sanitize($user['name']) ?></span>
              <i class="bi bi-chevron-down" style="font-size:0.75rem; color:var(--text-muted); margin-left:2px;"></i>
            </button>
            
            <!-- Dropdown Menu -->
            <div id="user-menu-dropdown" style="display:none; position:absolute; top:calc(100% + 8px); right:0; background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-sm); box-shadow:var(--shadow-lg); min-width:210px; z-index:1001; padding:6px 0;">
              <?php if (isAdmin()): ?>
                <a href="<?= BASE_URL ?>admin/" style="display:block; padding:8px 18px; font-weight:600; color:var(--haat-green-dark);"><i class="bi bi-speedometer2"></i> Admin Dashboard</a>
                <a href="<?= BASE_URL ?>logistics/" style="display:block; padding:8px 18px; color:var(--haat-clay); font-weight:700;"><i class="bi bi-truck"></i> HAATEX Logistics Hub</a>
                <a href="<?= BASE_URL ?>admin/sellers.php" style="display:block; padding:8px 18px; color:var(--text-main);"><i class="bi bi-shop"></i> Manage Sellers</a>
                <a href="<?= BASE_URL ?>admin/orders.php" style="display:block; padding:8px 18px; color:var(--text-main);"><i class="bi bi-box-seam"></i> Platform Orders</a>
              <?php elseif (($_SESSION['user_role'] ?? '') === 'logistics'): ?>
                <a href="<?= BASE_URL ?>logistics/" style="display:block; padding:8px 18px; font-weight:600; color:var(--haat-green-dark);"><i class="bi bi-truck"></i> HAATEX Logistics Hub</a>
                <a href="<?= BASE_URL ?>logistics/#orders" style="display:block; padding:8px 18px; color:var(--text-main); font-size:0.88rem;"><i class="bi bi-boxes"></i> Delivery Pipeline</a>
                <a href="<?= BASE_URL ?>logistics/#riders" style="display:block; padding:8px 18px; color:var(--text-main); font-size:0.88rem;"><i class="bi bi-bicycle"></i> Delivery Riders Fleet</a>
                <a href="<?= BASE_URL ?>logistics/#messages" style="display:block; padding:8px 18px; color:var(--text-main); font-size:0.88rem;"><i class="bi bi-chat-dots-fill"></i> Customer Comms Desk</a>
              <?php elseif (isSeller()): ?>
                <a href="<?= BASE_URL ?>seller/" style="display:block; padding:8px 18px; font-weight:600; color:var(--haat-green-dark);"><i class="bi bi-speedometer2"></i> Artisan Dashboard</a>
                <a href="<?= BASE_URL ?>seller/#products" style="display:block; padding:8px 18px; color:var(--text-main); font-size:0.88rem;"><i class="bi bi-box-seam"></i> My Crafts & Products</a>
                <a href="<?= BASE_URL ?>seller/#orders" style="display:block; padding:8px 18px; color:var(--text-main); font-size:0.88rem;"><i class="bi bi-receipt"></i> Workshop Orders</a>
              <?php else: ?>
                <a href="<?= BASE_URL ?>customer/" style="display:block; padding:8px 18px; font-weight:600; color:var(--haat-green-dark);"><i class="bi bi-speedometer2"></i> Buyer Dashboard</a>
                <a href="<?= BASE_URL ?>customer/#orders" style="display:block; padding:8px 18px; color:var(--text-main); font-size:0.88rem;"><i class="bi bi-bag-check"></i> My Orders</a>
                <a href="<?= BASE_URL ?>customer/#wishlist" style="display:block; padding:8px 18px; color:var(--text-main); font-size:0.88rem;"><i class="bi bi-heart"></i> Saved Wishlist</a>
                <a href="<?= BASE_URL ?>customer/#messages" style="display:block; padding:8px 18px; color:var(--text-main); font-size:0.88rem;"><i class="bi bi-chat-dots"></i> Seller Messages</a>
              <?php endif; ?>
              <div style="border-top:1px solid var(--haat-border); margin-top:6px; padding-top:6px;">
                <a href="<?= BASE_URL ?>logout.php" style="display:block; padding:8px 18px; color:#e63946; font-weight:600;"><i class="bi bi-box-arrow-right"></i> Log Out</a>
              </div>
            </div>
          </div>

          <script>
            const userBtn = document.getElementById('user-menu-btn');
            const userDropdown = document.getElementById('user-menu-dropdown');
            if (userBtn && userDropdown) {
              userBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (typeof notifDropdown !== 'undefined' && notifDropdown) notifDropdown.style.display = 'none';
                userDropdown.style.display = (userDropdown.style.display === 'none' || !userDropdown.style.display) ? 'block' : 'none';
              });
              document.addEventListener('click', (e) => {
                if (!userBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                  userDropdown.style.display = 'none';
                }
              });
            }
          </script>
        <?php else: ?>
          <a href="<?= BASE_URL ?>login.php" style="height:42px; padding:0 18px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); background:#fff; font-size:0.9rem; font-weight:600; color:var(--haat-green-dark); text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:var(--transition); box-shadow:var(--shadow-sm);" onmouseover="this.style.borderColor='var(--haat-clay)';" onmouseout="this.style.borderColor='var(--haat-border)';">
            <i class="bi bi-person" style="color:var(--haat-clay); font-size:1.1rem;"></i>
            <span>Sign In</span>
          </a>
        <?php endif; ?>

        <!-- Wishlist Action (For Shoppers & Buyers Only) -->
        <?php if (empty($hideWishlist) && !isSeller() && !isAdmin()): ?>
        <a href="<?= BASE_URL ?>customer/wishlist.php" class="action-item" title="Saved Crafts & Wishlist">
          <div class="action-icon-wrap">
            <i class="bi bi-heart"></i>
            <span class="badge-count wishlist-count-badge" style="<?= $wishlistCount > 0 ? '' : 'display:none;' ?>"><?= $wishlistCount ?></span>
          </div>
          <div class="action-text">
            <span class="action-subtitle">Saved Crafts</span>
            <span class="action-title">Wishlist</span>
          </div>
        </a>
        <?php elseif (isSeller() && empty($hideWishlist)): ?>
        <!-- For sellers, show direct link to seller dashboard (no wishlist) -->
        <a href="<?= BASE_URL ?>seller/" class="action-item" title="Artisan Workshop Dashboard">
          <div class="action-icon-wrap">
            <i class="bi bi-shop"></i>
          </div>
          <div class="action-text">
            <span class="action-subtitle">Artisan Store</span>
            <span class="action-title">Workshop</span>
          </div>
        </a>
        <?php endif; ?>

        <!-- Cart Action & Drawer Preview (For Shoppers & Buyers Only) -->
        <?php if (empty($hideCart) && !isSeller() && !isAdmin()): ?>
        <a href="<?= BASE_URL ?>cart.php" class="action-item" title="Shopping Cart">
          <div class="action-icon-wrap">
            <i class="bi bi-bag"></i>
            <span class="badge-count cart-count-badge"><?= $cartCount ?></span>
          </div>
          <div class="action-text">
            <span class="action-subtitle">My Haat Cart</span>
            <span class="action-title"><?= formatPrice($cartSubtotal) ?></span>
          </div>
        </a>
        <?php endif; ?>

      </div>
    </div>
  </header>

  <!-- Navbar -->
  <?php if (empty($hideNavbar)): require_once __DIR__ . '/navbar.php'; endif; ?>

  <!-- Flash Notification Message -->
  <?php $flash = getFlash(); if ($flash): ?>
    <div class="container" style="margin-top: 16px;">
      <div style="padding:14px 20px; border-radius:var(--radius-md); font-weight:600; display:flex; align-items:center; gap:10px; background:<?= $flash['type'] === 'success' ? '#e3efe6' : ($flash['type'] === 'danger' ? '#fdeeed' : '#fdf6e7') ?>; color:<?= $flash['type'] === 'success' ? 'var(--haat-green)' : ($flash['type'] === 'danger' ? '#c52828' : '#9c6f17') ?>; border: 1px solid <?= $flash['type'] === 'success' ? 'rgba(27,61,34,0.2)' : 'rgba(197,40,40,0.2)' ?>;">
        <i class="bi bi-info-circle-fill"></i>
        <span><?= sanitize($flash['message']) ?></span>
      </div>
    </div>
  <?php endif; ?>
  <script>
    // Poll notifications periodically and update header badge + dropdown
    (function(){
      const badge = document.getElementById('header-notif-badge');
      const notifList = document.getElementById('notif-list-container');
      const notifDropdown = document.getElementById('notif-menu-dropdown');

      function escapeHtml(s){
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
      }

      function renderNotifications(items){
        if (!notifList) return;
        if (!items || items.length === 0) {
          notifList.innerHTML = '<div style="text-align:center; padding:28px 16px; color:var(--text-muted); font-size:0.85rem;'><i class="bi bi-bell-slash" style="font-size:1.8rem; display:block; margin-bottom:6px; opacity:0.5;"></i>No notifications yet</div>';
          return;
        }
        notifList.innerHTML = items.map(n => {
          const bg = n.is_read ? '#fff' : '#fcfaf7';
          const left = n.is_read ? 'transparent' : 'var(--haat-clay)';
          return `
            <a href="${escapeHtml(n.link)}" class="notif-row ${n.is_read ? '' : 'is-unread'}" onclick="markNotificationSingleRead(event, ${n.id}, '${escapeHtml(n.link)}')" style="display:flex; gap:12px; padding:11px 16px; text-decoration:none; color:inherit; border-bottom:1px solid rgba(0,0,0,0.04); background:${bg}; border-left:3px solid ${left};">
              <div style="width:34px; height:34px; border-radius:8px; background:var(--haat-sand); color:var(--haat-green-dark); display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0;">
                <i class="bi bi-bell-fill"></i>
              </div>
              <div style="flex:1; min-width:0;">
                <div style="font-size:0.85rem; font-weight:700; color:var(--haat-green-dark); margin-bottom:2px; display:flex; justify-content:space-between; align-items:center;">
                  <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(n.title)}</span>
                  <span style="font-size:0.7rem; color:var(--text-muted);">${escapeHtml(n.time)}</span>
                </div>
                <div style="font-size:0.77rem; color:var(--text-muted); line-height:1.35; margin-bottom:3px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">${escapeHtml(n.message)}</div>
              </div>
            </a>
          `;
        }).join('');
      }

      function poll(){
        fetch(BASE_URL + 'api/notifications.php?action=get').then(r=>r.json()).then(data=>{
          if (!data || !data.success) return;
          const count = parseInt(data.unread_count || 0, 10);
          if (badge) {
            if (count > 0) { badge.style.display = 'inline-flex'; badge.textContent = count; }
            else badge.style.display = 'none';
          }
          // if dropdown is visible, refresh its list
          if (notifDropdown && notifDropdown.style.display === 'block') {
            renderNotifications(data.notifications || []);
          }
        }).catch(()=>{});
      }

      // initial poll + interval
      try { poll(); setInterval(poll, 20000); } catch(e){}
    })();
  </script>
