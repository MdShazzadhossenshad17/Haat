<?php
/**
 * Professional Printable Invoice & Packaging Slip System
 * Supports:
 * 1. Customer Official Commercial Invoice (Full Order)
 * 2. Seller Artisan Dispatch Invoice & Packing Slip (Workshop Specific)
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/includes/functions.php';

// Authentication Check
if (!isLoggedIn()) {
    $redirectUrl = 'invoice.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header('Location: ' . BASE_URL . 'login.php?redirect=' . urlencode($redirectUrl));
    exit;
}

$currentUser = currentUser();
$currentSeller = currentSeller();
$userRole = $currentUser['role'] ?? 'customer';

$orderNumber = trim($_GET['order'] ?? '');
if (empty($orderNumber)) {
    header('Location: ' . BASE_URL . 'customer/#orders');
    exit;
}

// Fetch Order
$stmt = $db->prepare("SELECT o.*, u.name as buyer_account_name, u.email as buyer_email 
    FROM `orders` o 
    LEFT JOIN `users` u ON o.user_id = u.id 
    WHERE o.`order_number` = ? LIMIT 1");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    echo "<h3>Order not found.</h3>";
    exit;
}

if (!canAccessOrder($order)) {
    http_response_code(403);
    echo "<h3>You are not authorized to view this invoice.</h3>";
    exit;
}

// Check Permission
$isCustomerOwner = ((int)$order['user_id'] === (int)$currentUser['id']);
$isAdminUser = ($userRole === 'admin');

// Fetch all items for this order
$itemStmt = $db->prepare("SELECT oi.*, s.id as vendor_id, s.shop_name, s.shop_logo, s.district as vendor_district, 
    s.phone as vendor_phone, s.address as vendor_address, u.name as artisan_owner_name 
    FROM `order_items` oi 
    JOIN `sellers` s ON oi.seller_id = s.id 
    JOIN `users` u ON s.user_id = u.id 
    WHERE oi.order_id = ?");
$itemStmt->execute([$order['id']]);
$allItems = $itemStmt->fetchAll();

// Check if current user is an artisan seller in this order
$sellerWorkshopIds = [];
foreach ($allItems as $it) {
    $sellerWorkshopIds[(int)$it['vendor_id']] = true;
}

$isSellerInOrder = ($currentSeller && isset($sellerWorkshopIds[(int)$currentSeller['id']]));

if (!$isCustomerOwner && !$isAdminUser && !$isSellerInOrder) {
    echo "<div style='font-family:sans-serif; text-align:center; padding:50px;'>
        <h2>Access Denied</h2>
        <p>You do not have permission to view this invoice.</p>
        <a href='" . BASE_URL . "'>Return to HAAT</a>
    </div>";
    exit;
}

// Determine View Mode: 'seller' or 'customer'
$requestedView = $_GET['view'] ?? '';
$targetSellerId = (int)($_GET['seller_id'] ?? 0);

// If seller is logged in, default to seller invoice unless requested customer view and is allowed
$isSellerMode = false;
$activeSeller = null;

if ($currentSeller && ($requestedView === 'seller' || $targetSellerId > 0 || !$isCustomerOwner)) {
    $isSellerMode = true;
    $filterSellerId = ($targetSellerId > 0) ? $targetSellerId : (int)$currentSeller['id'];
    
    // Find active seller info
    foreach ($allItems as $it) {
        if ((int)$it['vendor_id'] === $filterSellerId) {
            $activeSeller = [
                'id' => (int)$it['vendor_id'],
                'shop_name' => $it['shop_name'],
                'shop_logo' => $it['shop_logo'],
                'district' => $it['vendor_district'],
                'phone' => $it['vendor_phone'],
                'address' => $it['vendor_address'],
                'owner' => $it['artisan_owner_name']
            ];
            break;
        }
    }
} elseif ($isAdminUser && $targetSellerId > 0) {
    $isSellerMode = true;
    foreach ($allItems as $it) {
        if ((int)$it['vendor_id'] === $targetSellerId) {
            $activeSeller = [
                'id' => (int)$it['vendor_id'],
                'shop_name' => $it['shop_name'],
                'shop_logo' => $it['shop_logo'],
                'district' => $it['vendor_district'],
                'phone' => $it['vendor_phone'],
                'address' => $it['vendor_address'],
                'owner' => $it['artisan_owner_name']
            ];
            break;
        }
    }
}

// Filter items if in seller mode
$displayItems = [];
$sellerItemSubtotal = 0;
if ($isSellerMode && $activeSeller) {
    foreach ($allItems as $it) {
        if ((int)$it['vendor_id'] === (int)$activeSeller['id']) {
            $displayItems[] = $it;
            $sellerItemSubtotal += (float)$it['subtotal'];
        }
    }
} else {
    $displayItems = $allItems;
}

$pageTitle = ($isSellerMode ? 'Artisan Packing Slip & Invoice' : 'Tax Invoice') . ' — ' . $order['order_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= sanitize($pageTitle) ?> — HAAT</title>
  <link rel="icon" type="image/jpeg" href="<?= BASE_URL ?>assets/images/logo.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    :root {
      --haat-green: #1b3d22;
      --haat-green-dark: #122b17;
      --haat-clay: #c2612d;
      --haat-sand: #f0ebe1;
      --haat-cream: #faf7f2;
      --haat-border: #e6dfd5;
      --text-main: #2b2b2b;
      --text-muted: #6b7280;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: var(--text-main);
      background-color: #f3f4f6;
      line-height: 1.5;
      padding: 24px 16px;
    }

    /* Screen-Only Control Toolbar */
    .invoice-toolbar {
      max-width: 840px;
      margin: 0 auto 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #ffffff;
      padding: 12px 20px;
      border-radius: 10px;
      border: 1px solid var(--haat-border);
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: 6px;
      font-size: 0.88rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
      border: none;
    }

    .btn-action-primary {
      background: var(--haat-green);
      color: #ffffff;
    }
    .btn-action-primary:hover {
      background: var(--haat-green-dark);
    }

    .btn-action-outline {
      background: #ffffff;
      border: 1px solid var(--haat-border);
      color: var(--haat-green-dark);
    }
    .btn-action-outline:hover {
      background: var(--haat-sand);
      border-color: var(--haat-green);
    }

    /* Printable Invoice Sheet (A4 Dimensions) */
    .invoice-sheet {
      max-width: 840px;
      margin: 0 auto;
      background: #ffffff;
      padding: 42px 48px;
      border-radius: 8px;
      border: 1px solid var(--haat-border);
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
      position: relative;
    }

    /* Top decorative brand stripe */
    .invoice-sheet::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--haat-green), var(--haat-clay));
      border-top-left-radius: 8px;
      border-top-right-radius: 8px;
    }

    /* Watermark / Stamp */
    .stamp-badge {
      display: inline-block;
      padding: 4px 14px;
      border-radius: 6px;
      font-weight: 800;
      font-size: 0.78rem;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      border: 2px solid;
    }
    .stamp-paid {
      color: #15803d;
      border-color: #15803d;
      background: #f0fdf4;
    }
    .stamp-cod {
      color: var(--haat-clay);
      border-color: var(--haat-clay);
      background: #fff7ed;
    }

    /* Items Table */
    .invoice-table {
      width: 100%;
      border-collapse: collapse;
      margin: 24px 0 20px;
      font-size: 0.9rem;
    }

    .invoice-table th {
      background: var(--haat-sand);
      color: var(--haat-green-dark);
      font-weight: 700;
      text-transform: uppercase;
      font-size: 0.78rem;
      letter-spacing: 0.03em;
      padding: 10px 14px;
      border-top: 1px solid var(--haat-border);
      border-bottom: 2px solid var(--haat-border);
      text-align: left;
    }

    .invoice-table td {
      padding: 12px 14px;
      border-bottom: 1px solid var(--haat-border);
      vertical-align: middle;
    }

    .invoice-table tr:last-child td {
      border-bottom: 2px solid var(--haat-border);
    }

    /* Summary Card */
    .summary-table {
      width: 290px;
      margin-left: auto;
      font-size: 0.92rem;
    }
    .summary-table td {
      padding: 6px 0;
    }

    /* Print Optimization Rules */
    @media print {
      @page {
        size: A4 portrait;
        margin: 10mm 14mm 10mm 14mm;
      }

      body {
        background: #ffffff !important;
        padding: 0 !important;
        color: #000000 !important;
      }

      .invoice-toolbar {
        display: none !important;
      }

      .invoice-sheet {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        max-width: 100% !important;
      }

      .invoice-sheet::before {
        display: none !important;
      }

      .no-print {
        display: none !important;
      }

      .invoice-table th {
        background-color: #f3f4f6 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .stamp-badge {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .page-break-avoid {
        page-break-inside: avoid;
      }
    }
  </style>
</head>
<body>

  <!-- Screen-Only Controls Bar -->
  <div class="invoice-toolbar no-print">
    <div style="display:flex; align-items:center; gap:12px;">
      <?php if ($isSellerMode): ?>
        <a href="<?= BASE_URL ?>seller/#orders" class="btn-action btn-action-outline">
          <i class="bi bi-arrow-left"></i> Back to Seller Orders
        </a>
        <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">
          Artisan Packing Slip & Workshop Copy
        </span>
      <?php else: ?>
        <a href="<?= BASE_URL ?>customer/#orders" class="btn-action btn-action-outline">
          <i class="bi bi-arrow-left"></i> Back to My Orders
        </a>
        <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">
          Buyer Official Tax Invoice
        </span>
      <?php endif; ?>
    </div>

    <div style="display:flex; align-items:center; gap:10px;">
      <!-- Toggle between Customer & Seller view if Admin -->
      <?php if ($isAdminUser && !empty($allItems)): ?>
        <select onchange="window.location.href=this.value" style="padding:7px 10px; border:1px solid var(--haat-border); border-radius:6px; font-size:0.82rem; font-weight:600;">
          <option value="<?= BASE_URL ?>invoice.php?order=<?= urlencode($orderNumber) ?>" <?= !$isSellerMode ? 'selected' : '' ?>>Customer View (Full Order)</option>
          <?php 
          $seenVendors = [];
          foreach ($allItems as $vItem):
              if (isset($seenVendors[$vItem['vendor_id']])) continue;
              $seenVendors[$vItem['vendor_id']] = true;
          ?>
            <option value="<?= BASE_URL ?>invoice.php?order=<?= urlencode($orderNumber) ?>&view=seller&seller_id=<?= $vItem['vendor_id'] ?>" <?= ($isSellerMode && $activeSeller && (int)$activeSeller['id'] === (int)$vItem['vendor_id']) ? 'selected' : '' ?>>
              Workshop View: <?= sanitize($vItem['shop_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>

      <button onclick="window.print()" class="btn-action btn-action-primary">
        <i class="bi bi-printer-fill"></i> Print / Save as PDF
      </button>
    </div>
  </div>

  <!-- Printable Invoice Document Sheet -->
  <div class="invoice-sheet" id="invoice-sheet">
    
    <!-- Top Header: Branding & Invoice Title -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:28px; padding-bottom:18px; border-bottom:2px solid var(--haat-sand);">
      <div>
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
          <img src="<?= BASE_URL ?>assets/images/logo.png" alt="HAAT Logo" style="height:44px; object-fit:contain;">
          <?php if ($isSellerMode && $activeSeller): ?>
            <div style="border-left:2px solid var(--haat-border); padding-left:12px;">
              <span style="font-size:0.75rem; text-transform:uppercase; color:var(--haat-clay); font-weight:700; letter-spacing:0.04em; display:block;">Artisan Guild Partner</span>
              <strong style="font-size:1.05rem; color:var(--haat-green-dark);"><?= sanitize($activeSeller['shop_name']) ?></strong>
            </div>
          <?php endif; ?>
        </div>
        <div style="font-size:0.8rem; color:var(--text-muted); line-height:1.4;">
          <strong>HAAT Multi-Vendor Marketplace</strong> — Global • Regional • Artisanal<br>
          Govt. E-Commerce Trade Lic: BD-DH-849102 | VAT REG: 00923841-0102<br>
          Dhaka Central Logistics Hub, Banani 11, Dhaka 1213, Bangladesh<br>
          Email: support@haat.com.bd | Helpline: +880 1711-000001
        </div>
      </div>

      <div style="text-align:right;">
        <div style="font-size:1.55rem; font-weight:800; color:var(--haat-green-dark); letter-spacing:-0.02em; line-height:1.1; margin-bottom:6px;">
          <?= $isSellerMode ? 'PACKING SLIP' : 'TAX INVOICE' ?>
        </div>
        <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:8px;">
          Invoice No: <strong style="color:var(--haat-green-dark); font-family:monospace; font-size:0.95rem;"><?= sanitize($order['order_number']) ?><?= $isSellerMode ? '-V' . $activeSeller['id'] : '' ?></strong><br>
          Order Date: <strong><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></strong><br>
          Invoice Date: <strong><?= date('d M Y') ?></strong>
        </div>
        <div>
          <?php if (strtoupper($order['payment_method']) === 'COD'): ?>
            <span class="stamp-badge stamp-cod">Cash On Delivery</span>
          <?php else: ?>
            <span class="stamp-badge stamp-paid"><?= strtoupper($order['payment_status']) === 'PAID' ? 'PAID / VERIFIED' : 'PAYMENT: ' . strtoupper($order['payment_status']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Metadata Grid: Two Columns (Buyer / Delivery & Vendor / Logistics) -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-bottom:26px; font-size:0.88rem;">
      
      <!-- Box 1: Billed & Shipped To -->
      <div style="background:var(--haat-cream); border:1px solid var(--haat-border); border-radius:8px; padding:16px;">
        <div style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:var(--haat-clay); margin-bottom:6px; letter-spacing:0.04em;">
          <i class="bi bi-geo-alt-fill"></i> Delivery Recipient & Destination
        </div>
        <div style="font-size:1rem; font-weight:700; color:var(--haat-green-dark); margin-bottom:4px;">
          <?= sanitize($order['shipping_name']) ?>
        </div>
        <div style="color:var(--text-main); line-height:1.45;">
          <strong>Phone:</strong> <?= sanitize($order['shipping_phone']) ?><br>
          <strong>Address:</strong> <?= sanitize($order['shipping_address']) ?><br>
          <strong>District:</strong> <?= sanitize($order['district']) ?>, <?= sanitize($order['division']) ?><br>
          <?php if (!empty($order['buyer_email'])): ?>
            <strong>Email:</strong> <?= sanitize($order['buyer_email']) ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Box 2: Fulfillment & Workshop Logistics -->
      <div style="background:var(--haat-cream); border:1px solid var(--haat-border); border-radius:8px; padding:16px;">
        <?php if ($isSellerMode && $activeSeller): ?>
          <div style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:var(--haat-clay); margin-bottom:6px; letter-spacing:0.04em;">
            <i class="bi bi-shop"></i> Fulfilling Artisan Guild
          </div>
          <div style="font-size:1rem; font-weight:700; color:var(--haat-green-dark); margin-bottom:4px;">
            <?= sanitize($activeSeller['shop_name']) ?>
          </div>
          <div style="color:var(--text-main); line-height:1.45;">
            <strong>District of Origin:</strong> <?= sanitize($activeSeller['district']) ?><br>
            <strong>Master Artisan:</strong> <?= sanitize($activeSeller['owner']) ?><br>
            <strong>Consignment Tracking:</strong> <?= sanitize($order['tracking_code'] ?? 'PTH-8849201') ?><br>
            <strong>Logistics Courier:</strong> <?= sanitize($order['courier_partner'] ?? 'Pathao Courier Logistics') ?>
          </div>
        <?php else: ?>
          <div style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:var(--haat-clay); margin-bottom:6px; letter-spacing:0.04em;">
            <i class="bi bi-credit-card-2-front"></i> Payment & Courier Tracking
          </div>
          <div style="color:var(--text-main); line-height:1.45;">
            <strong>Payment Method:</strong> <?= strtoupper($order['payment_method']) ?><br>
            <strong>Payment Status:</strong> <span style="font-weight:700; color:<?= $order['payment_status'] === 'paid' ? '#15803d' : 'var(--haat-clay)' ?>"><?= ucfirst($order['payment_status']) ?></span><br>
            <?php if (!empty($order['transaction_id'])): ?>
              <strong>Transaction TrxID:</strong> <span style="font-family:monospace;"><?= sanitize($order['transaction_id']) ?></span><br>
            <?php endif; ?>
            <strong>Assigned Courier:</strong> <?= sanitize($order['courier_partner'] ?? 'Pathao Courier Logistics') ?><br>
            <strong>Tracking Consignment:</strong> <span style="font-family:monospace; font-weight:700;"><?= sanitize($order['tracking_code'] ?? 'PTH-8849201') ?></span>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Items Table -->
    <table class="invoice-table">
      <thead>
        <tr>
          <th style="width:40px; text-align:center;">#</th>
          <th>Artisanal Item & Description</th>
          <?php if (!$isSellerMode): ?>
            <th>Artisan Workshop</th>
          <?php endif; ?>
          <th style="width:70px; text-align:center;">Qty</th>
          <th style="width:110px; text-align:right;">Unit Price</th>
          <th style="width:120px; text-align:right;">Total (BDT)</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $rowIdx = 1;
        foreach ($displayItems as $item): 
        ?>
          <tr>
            <td style="text-align:center; color:var(--text-muted); font-weight:600;"><?= $rowIdx++ ?></td>
            <td>
              <strong style="color:var(--haat-green-dark); display:block;"><?= sanitize($item['product_name']) ?></strong>
              <span style="font-size:0.78rem; color:var(--text-muted);">
                SKU: HAAT-PRD-<?= (int)$item['product_id'] ?> • Status: <?= strtoupper($item['vendor_status'] ?? 'PROCESSING') ?>
              </span>
            </td>
            <?php if (!$isSellerMode): ?>
              <td style="font-size:0.85rem; color:var(--haat-clay); font-weight:600;">
                <?= sanitize($item['shop_name']) ?>
                <div style="font-size:0.72rem; color:var(--text-muted); font-weight:400;"><?= sanitize($item['vendor_district']) ?> Origin</div>
              </td>
            <?php endif; ?>
            <td style="text-align:center; font-weight:700;"><?= (int)$item['quantity'] ?></td>
            <td style="text-align:right; font-family:monospace; font-size:0.92rem;"><?= formatPrice($item['price']) ?></td>
            <td style="text-align:right; font-weight:700; color:var(--haat-green-dark); font-family:monospace; font-size:0.95rem;">
              <?= formatPrice($item['subtotal']) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Totals Calculation Grid -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:30px;" class="page-break-avoid">
      
      <!-- Notes & Terms -->
      <div style="max-width:440px; font-size:0.8rem; color:var(--text-muted); line-height:1.45;">
        <?php if ($isSellerMode): ?>
          <div style="border:1px dashed var(--haat-border); border-radius:6px; padding:12px; background:#faf8f5;">
            <strong style="color:var(--haat-green-dark); display:block; margin-bottom:4px;">
              <i class="bi bi-box-seam"></i> Workshop Packaging Verification:
            </strong>
            <div style="display:flex; flex-direction:column; gap:4px; font-size:0.78rem;">
              <span>[ &check; ] Authentic handcraft inspection completed by master artisan</span>
              <span>[ &check; ] Bubble-wrap / moisture-barrier protective packaging applied</span>
              <span>[ &check; ] HAAT security seal affixed to outer carton</span>
            </div>
          </div>
        <?php else: ?>
          <div style="border-left:3px solid var(--haat-green); padding-left:10px;">
            <strong style="color:var(--haat-green-dark); display:block; margin-bottom:2px;">Authenticity Guaranteed:</strong>
            Every craft in this order is genuine, sourced directly from verified Bangladeshi artisan guilds, cottage potters, and organic rural harvesters.
          </div>
          <?php if (!empty($order['notes'])): ?>
            <div style="margin-top:10px; font-style:italic;">
              <strong>Delivery Notes:</strong> <?= sanitize($order['notes']) ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- Financial Totals -->
      <div class="summary-table">
        <table style="width:100%; border-collapse:collapse;">
          <?php if ($isSellerMode): ?>
            <tr>
              <td style="color:var(--text-muted);">Workshop Subtotal:</td>
              <td style="text-align:right; font-weight:700; font-family:monospace;"><?= formatPrice($sellerItemSubtotal) ?></td>
            </tr>
            <tr style="border-top:1px solid var(--haat-border); border-bottom:2px solid var(--haat-green); font-size:1.1rem;">
              <td style="font-weight:800; color:var(--haat-green-dark); padding:10px 0;">Artisan Parcel Total:</td>
              <td style="text-align:right; font-weight:800; color:var(--haat-green); font-family:monospace; padding:10px 0;"><?= formatPrice($sellerItemSubtotal) ?></td>
            </tr>
          <?php else: ?>
            <tr>
              <td style="color:var(--text-muted);">Items Subtotal:</td>
              <td style="text-align:right; font-weight:600; font-family:monospace;"><?= formatPrice($order['total_amount']) ?></td>
            </tr>
            <?php if ((float)$order['discount_amount'] > 0): ?>
              <tr>
                <td style="color:var(--haat-clay);">Coupon / Guild Discount:</td>
                <td style="text-align:right; font-weight:600; color:var(--haat-clay); font-family:monospace;">- <?= formatPrice($order['discount_amount']) ?></td>
              </tr>
            <?php endif; ?>
            <tr>
              <td style="color:var(--text-muted);">Standard Delivery:</td>
              <td style="text-align:right; font-weight:600; font-family:monospace;"><?= formatPrice($order['shipping_cost']) ?></td>
            </tr>
            <tr style="border-top:2px solid var(--haat-border); border-bottom:2px solid var(--haat-green-dark); font-size:1.15rem;">
              <td style="font-weight:800; color:var(--haat-green-dark); padding:10px 0;">Grand Total:</td>
              <td style="text-align:right; font-weight:800; color:var(--haat-green); font-family:monospace; padding:10px 0;"><?= formatPrice($order['grand_total']) ?></td>
            </tr>
          <?php endif; ?>
        </table>
      </div>

    </div>

    <!-- Official Signature / Authorization Footer -->
    <div style="margin-top:38px; padding-top:20px; border-top:1px solid var(--haat-border); display:flex; justify-content:space-between; align-items:flex-end;" class="page-break-avoid">
      <div style="font-size:0.75rem; color:var(--text-muted); line-height:1.4;">
        This document is an electronically generated <?= $isSellerMode ? 'packaging slip' : 'tax invoice' ?> under HAAT Platform Terms.<br>
        Questions? Contact help@haat.com.bd or hotline +880 1711-000001.<br>
        Thank you for preserving Bangladeshi heritage crafts.
      </div>
      
      <div style="text-align:center; width:200px;">
        <div style="font-family:'Courier New', monospace; font-size:0.78rem; font-weight:700; color:var(--haat-green-dark); margin-bottom:4px;">
          <?= $isSellerMode ? sanitize($activeSeller['owner']) : 'HAAT Marketplace Auth' ?>
        </div>
        <div style="border-top:1px solid #111; padding-top:4px; font-size:0.75rem; color:var(--text-muted); font-weight:600;">
          <?= $isSellerMode ? 'Artisan Workshop Signature' : 'Authorized Representative' ?>
        </div>
      </div>
    </div>

  </div>

  <?php if (!empty($_GET['print'])): ?>
    <script>
      window.onload = function() {
        window.print();
      };
    </script>
  <?php endif; ?>

</body>
</html>
