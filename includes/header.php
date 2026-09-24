<?php
/**
 * Global Header Component
 * HAAT - Bangladeshi Multi-Vendor E-Commerce Platform
 */
require_once __DIR__ . '/functions.php';

$user = currentUser();
$cartCount = getCartCount();
$cartSubtotal = getCartSubtotal();

// Categories for search dropdown
$stmt = $db->query("SELECT id, name, slug FROM `categories` ORDER BY name ASC");
$headerCategories = $stmt->fetchAll();
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
</head>
<body>


  <!-- Main Header -->
  <header class="header-main">
    <div class="container">
      
      <!-- Brand Logo (User's Exact HAAT Logo) -->
      <a href="<?= BASE_URL ?>" class="brand-logo">
        <img src="<?= BASE_URL ?>assets/images/logo.png" alt="HAAT — Global • Regional • Artisanal">
      </a>

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
        
        <!-- Account Dropdown / Link (Clean Rectangular Button) -->
        <?php if ($user): ?>
          <div style="position:relative;">
            <button type="button" id="user-menu-btn" style="height:42px; padding:0 16px; border:1px solid var(--haat-border); border-radius:var(--radius-sm); background:#fff; font-size:0.9rem; font-weight:600; color:var(--haat-green-dark); cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:var(--transition); box-shadow:var(--shadow-sm);" onmouseover="this.style.borderColor='var(--haat-clay)';" onmouseout="this.style.borderColor='var(--haat-border)';">
              <i class="bi bi-person-fill" style="color:var(--haat-clay); font-size:1.1rem;"></i>
              <span><?= sanitize($user['name']) ?></span>
              <i class="bi bi-chevron-down" style="font-size:0.75rem; color:var(--text-muted); margin-left:2px;"></i>
            </button>
            
            <!-- Dropdown Menu -->
            <div id="user-menu-dropdown" style="display:none; position:absolute; top:calc(100% + 8px); right:0; background:#fff; border:1px solid var(--haat-border); border-radius:var(--radius-sm); box-shadow:var(--shadow-lg); min-width:180px; z-index:1001; padding:6px 0;">
              <?php if (isAdmin()): ?>
                <a href="<?= BASE_URL ?>admin/" style="display:block; padding:8px 18px; font-weight:600;"><i class="bi bi-speedometer2"></i> Admin Dashboard</a>
                <a href="<?= BASE_URL ?>admin/sellers.php" style="display:block; padding:8px 18px;"><i class="bi bi-shop"></i> Manage Sellers</a>
                <a href="<?= BASE_URL ?>admin/orders.php" style="display:block; padding:8px 18px;"><i class="bi bi-box-seam"></i> Platform Orders</a>
              <?php elseif (isSeller()): ?>
                <a href="<?= BASE_URL ?>seller/" style="display:block; padding:8px 18px; font-weight:600; color:var(--haat-green-dark);"><i class="bi bi-speedometer2"></i> Dashboard</a>
              <?php else: ?>
                <a href="<?= BASE_URL ?>customer/" style="display:block; padding:8px 18px; font-weight:600; color:var(--haat-green-dark);"><i class="bi bi-speedometer2"></i> Dashboard</a>
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

        <!-- Cart Action & Drawer Preview -->
        <?php if (empty($hideCart)): ?>
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
