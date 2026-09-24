<?php
/**
 * Global Footer Component
 * HAAT - Bangladeshi Multi-Vendor E-Commerce Platform
 */
?>
  <!-- Footer -->
  <footer class="footer-main">
    <div class="container">
      <div class="footer-grid">
        
        <!-- Brand Info -->
        <div class="footer-brand">
          <img src="<?= BASE_URL ?>assets/images/logo.png" alt="HAAT Logo">
          <p>
            HAAT is Bangladesh's dedicated multi-vendor marketplace connecting authentic rural artisans, handloom weavers, clay sculptors, and organic harvesters directly with conscious homes across Bangladesh and worldwide.
          </p>
          <div style="display:flex; gap:12px;">
            <span class="badge badge-green"><i class="bi bi-shield-check"></i> 100% Genuine Artisanal</span>
            <span class="badge badge-clay"><i class="bi bi-geo-alt"></i> 64 Districts</span>
          </div>
        </div>

        <!-- Customer Links -->
        <div class="footer-col">
          <h4>Explore HAAT</h4>
          <ul class="footer-links">
            <li><a href="<?= BASE_URL ?>shop.php">All Crafts & Products</a></li>
            <li><a href="<?= BASE_URL ?>shop.php?flash=1">Flash Bazaar Deals</a></li>
            <li><a href="<?= BASE_URL ?>#sellers-section">Featured Artisan Guilds</a></li>
            <li><a href="<?= BASE_URL ?>track-order.php">Track Your Delivery</a></li>
            <li><a href="<?= BASE_URL ?>cart.php">Shopping Cart</a></li>
          </ul>
        </div>

        <!-- Vendor & Account Links -->
        <div class="footer-col">
          <h4>Artisans & Sellers</h4>
          <ul class="footer-links">
            <li><a href="<?= BASE_URL ?>register.php?type=seller">Open a Seller Store</a></li>
            <li><a href="<?= BASE_URL ?>login.php?type=seller">Seller Dashboard Login</a></li>
            <li><a href="<?= BASE_URL ?>login.php?type=admin">Admin Control Portal</a></li>
            <li><a href="<?= BASE_URL ?>customer/">Buyer Account Portal</a></li>
            <li><a href="<?= BASE_URL ?>login.php">Sign In / Register</a></li>
          </ul>
        </div>

        <!-- Payment & Delivery -->
        <div class="footer-col">
          <h4>Accepted Payment Methods</h4>
          <p style="font-size:0.86rem; color:#b0c2b4; margin-bottom:12px;">
            Safe, verified payments via Bangladesh's leading mobile financial services and cash on delivery:
          </p>
          <div class="payment-badges-row">
            <span class="pay-badge pay-bkash"><i class="bi bi-phone"></i> bKash</span>
            <span class="pay-badge pay-nagad"><i class="bi bi-wallet2"></i> Nagad</span>
            <span class="pay-badge pay-rocket"><i class="bi bi-credit-card-2-front"></i> Rocket</span>
            <span class="pay-badge pay-cod"><i class="bi bi-cash-stack"></i> Cash on Delivery</span>
          </div>
          <div style="margin-top:20px; font-size:0.85rem; color:#b0c2b4;">
            <i class="bi bi-telephone-fill text-clay"></i> Hotline: <strong>+880 1711-000001</strong><br>
            <i class="bi bi-envelope-fill text-clay"></i> Support: <strong>support@haat.com.bd</strong>
          </div>
        </div>

      </div>

      <!-- Bottom Bar -->
      <div class="footer-bottom">
        <div>
          &copy; <?= date('Y') ?> <strong>HAAT</strong> (Global • Regional • Artisanal). All Rights Reserved.
        </div>
        <div style="display:flex; gap:16px;">
          <span>Handcrafted with pride in Bangladesh 🇧🇩</span>
        </div>
      </div>
    </div>
  </footer>

  <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
