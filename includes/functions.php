<?php
/**
 * Core Helper Functions
 * HAAT - Bangladeshi Multi-Vendor E-Commerce Platform
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Format Currency in Bangladeshi Taka
function formatPrice($amount) {
    return '৳ ' . number_format((float)$amount, 0, '.', ',');
}

// Sanitize string
function sanitize($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

// Flash Messages
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Authentication & Role Check Helpers
function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'admin');
}

function isSeller() {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'seller');
}

function isCustomer() {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'customer');
}

function isLogistics() {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'logistics' || ($_SESSION['user_role'] ?? '') === 'admin');
}

function currentUser($refresh = false) {
    if (!isLoggedIn()) return null;
    global $db;
    static $user = null;
    if ($user === null || $refresh || (int)($user['id'] ?? 0) !== (int)$_SESSION['user_id']) {
        $stmt = $db->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user;
}

function currentSeller($refresh = false) {
    if (!isSeller()) return null;
    global $db;
    static $seller = null;
    if ($seller === null || $refresh || (int)($seller['user_id'] ?? 0) !== (int)$_SESSION['user_id']) {
        $stmt = $db->prepare("SELECT * FROM `sellers` WHERE `user_id` = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $seller = $stmt->fetch();
    }
    return $seller;
}

// Access Guards
function requireAdmin() {
    if (!isAdmin()) {
        setFlash('danger', 'Admin privilege required to access this portal.');
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function requireLogistics() {
    if (!isLogistics()) {
        setFlash('danger', 'HAATEX Logistics access required.');
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function requireSeller() {
    if (!isSeller()) {
        if (isAdmin()) {
            header('Location: ' . BASE_URL . 'admin/');
            exit;
        }
        setFlash('danger', 'Seller account required to access vendor portal.');
        header('Location: ' . BASE_URL . 'login.php?type=seller');
        exit;
    }
}

function requireCustomer() {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please log in to proceed.');
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
    if (isSeller()) {
        header('Location: ' . BASE_URL . 'seller/');
        exit;
    }
    if (($_SESSION['user_role'] ?? '') === 'logistics') {
        header('Location: ' . BASE_URL . 'logistics/');
        exit;
    }
    if (isAdmin()) {
        header('Location: ' . BASE_URL . 'admin/');
        exit;
    }
}

// Cart Management (Session Based)
function getCart() {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    return $_SESSION['cart'];
}

function addToCart($productId, $quantity = 1) {
    global $db;
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $productId = (int)$productId;
    $quantity = max(1, (int)$quantity);

    // Fetch product info to verify
    $stmt = $db->prepare("SELECT p.*, s.shop_name FROM `products` p JOIN `sellers` s ON p.seller_id = s.id WHERE p.id = ? AND p.is_active = 1 LIMIT 1");
    $stmt->execute([$productId]);
    $prod = $stmt->fetch();

    if (!$prod) return false;

    // Enforce global rule: sellers cannot make purchases
    if (isLoggedIn() && isSeller()) {
        return false;
    }

    if ((int)$prod['stock_quantity'] < 1) return false;

    $price = $prod['sale_price'] ?: $prod['price'];

    $existingQuantity = isset($_SESSION['cart'][$productId]) ? (int)$_SESSION['cart'][$productId]['quantity'] : 0;
    if ($existingQuantity + $quantity > (int)$prod['stock_quantity']) {
        return false;
    }

    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] = $existingQuantity + $quantity;
    } else {
        $_SESSION['cart'][$productId] = [
            'id' => $prod['id'],
            'seller_id' => $prod['seller_id'],
            'shop_name' => $prod['shop_name'],
            'name' => $prod['name'],
            'price' => (float)$price,
            'image' => $prod['featured_image'],
            'unit' => $prod['unit'],
            'quantity' => $quantity,
            'max_stock' => $prod['stock_quantity']
        ];
    }
    return true;
}

function updateCartQuantity($productId, $quantity) {
    global $db;
    $productId = (int)$productId;
    $quantity = (int)$quantity;
    if (!isset($_SESSION['cart'][$productId])) {
        return false;
    }
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$productId]);
        return true;
    }

    $stmt = $db->prepare("SELECT `stock_quantity` FROM `products` WHERE `id` = ? AND `is_active` = 1 LIMIT 1");
    $stmt->execute([$productId]);
    $availableStock = $stmt->fetchColumn();
    if ($availableStock === false || $quantity > (int)$availableStock) {
        return false;
    }

    $_SESSION['cart'][$productId]['quantity'] = $quantity;
    $_SESSION['cart'][$productId]['max_stock'] = (int)$availableStock;
    return true;
}

function removeFromCart($productId) {
    $productId = (int)$productId;
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }
}

function getCartCount() {
    $cart = getCart();
    $count = 0;
    foreach ($cart as $item) {
        $count += $item['quantity'];
    }
    return $count;
}

function getCartSubtotal() {
    $cart = getCart();
    $subtotal = 0;
    foreach ($cart as $item) {
        $subtotal += ($item['price'] * $item['quantity']);
    }
    return $subtotal;
}

// Generate Order Number
function generateOrderNumber() {
    return 'HAAT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function clearCart() {
    unset($_SESSION['cart']);
}

function calculateCouponDiscount($couponCode, $subtotal) {
    global $db;

    $result = ['coupon' => null, 'discount' => 0.0, 'error' => null];
    $couponCode = strtoupper(trim((string)$couponCode));
    if ($couponCode === '') return $result;

    $stmt = $db->prepare("SELECT * FROM `coupons` WHERE `code` = ? AND `is_active` = 1 LIMIT 1");
    $stmt->execute([$couponCode]);
    $coupon = $stmt->fetch();
    if (!$coupon) {
        $result['error'] = 'This coupon is invalid or no longer active.';
        return $result;
    }

    $minimumOrder = (float)($coupon['min_order'] ?? 0);
    if ((float)$subtotal < $minimumOrder) {
        $result['error'] = 'This coupon requires a minimum order of ' . formatPrice($minimumOrder) . '.';
        return $result;
    }

    $discount = ((float)$subtotal * (float)$coupon['discount_percent']) / 100;
    $maximumDiscount = (float)($coupon['max_discount'] ?? 0);
    if ($maximumDiscount > 0) $discount = min($discount, $maximumDiscount);

    $result['coupon'] = $coupon;
    $result['discount'] = round($discount, 2);
    return $result;
}

/**
 * Recalculate product average rating and seller rating.
 * Updates `products.rating` and `sellers.rating` in database.
 */
function recalculateRatings($productId)
{
    global $db;
    $productId = (int)$productId;
    if ($productId <= 0) return false;

    // Calculate product average and count
    $pr = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM `product_reviews` WHERE `product_id` = ?");
    $pr->execute([$productId]);
    $r = $pr->fetch(PDO::FETCH_ASSOC);
    $avg = ($r && $r['avg_rating'] !== null) ? round((float)$r['avg_rating'], 2) : 0.00;

    // Update products table
    try {
        $db->prepare("UPDATE `products` SET `rating` = ? WHERE `id` = ?")->execute([$avg, $productId]);
    } catch (Exception $e) {
        // ignore if column update fails
    }

    // Recalculate seller rating: average across all product reviews for this seller
    $sStmt = $db->prepare("SELECT `seller_id` FROM `products` WHERE `id` = ? LIMIT 1");
    $sStmt->execute([$productId]);
    $sellerId = (int)$sStmt->fetchColumn();
    if ($sellerId > 0) {
        recalculateSellerRating($sellerId);
    }

    return true;
}

function recalculateSellerRating($sellerId)
{
    global $db;
    $sellerId = (int)$sellerId;
    if ($sellerId <= 0) return false;

    $sr = $db->prepare("SELECT AVG(r.rating) as seller_avg, COUNT(r.id) as total_reviews 
        FROM `product_reviews` r 
        JOIN `products` p ON r.product_id = p.id 
        WHERE p.seller_id = ?");
    $sr->execute([$sellerId]);
    $sdata = $sr->fetch(PDO::FETCH_ASSOC);
    $sellerAvg = ($sdata && $sdata['seller_avg'] !== null) ? round((float)$sdata['seller_avg'], 2) : 0.00;
    try {
        $db->prepare("UPDATE `sellers` SET `rating` = ? WHERE `id` = ?")->execute([$sellerAvg, $sellerId]);
    } catch (Exception $e) {
        // ignore if column update fails
    }

    return ['avg' => $sellerAvg, 'count' => (int)($sdata['total_reviews'] ?? 0)];
}

function canAccessOrder($order) {
    if (isAdmin()) return true;

    $user = currentUser();
    if ($user && (int)$user['id'] === (int)$order['user_id']) return true;

    $seller = currentSeller();
    if ($seller) {
        global $db;
        $stmt = $db->prepare("SELECT 1 FROM `order_items` WHERE `order_id` = ? AND `seller_id` = ? LIMIT 1");
        $stmt->execute([(int)$order['id'], (int)$seller['id']]);
        if ($stmt->fetchColumn()) return true;
    }

    return !empty($_SESSION['guest_orders'][$order['order_number']]);
}

function canUpdateOrderTracking($order) {
    if (isAdmin()) return true;

    $seller = currentSeller();
    if (!$seller) return false;

    global $db;
    $stmt = $db->prepare("SELECT 1 FROM `order_items` WHERE `order_id` = ? AND `seller_id` = ? LIMIT 1");
    $stmt->execute([(int)$order['id'], (int)$seller['id']]);
    return (bool)$stmt->fetchColumn();
}

/**
 * ================================================================
 * Wishlist Functions & Helpers
 * ================================================================
 */
function getWishlistCount($userId = null) {
    global $db;
    if ($userId === null && isLoggedIn()) {
        $userId = $_SESSION['user_id'];
    }
    if ($userId) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM `wishlists` WHERE `user_id` = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
    return isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;
}

function isInWishlist($productId, $userId = null) {
    global $db;
    if ($userId === null && isLoggedIn()) {
        $userId = $_SESSION['user_id'];
    }
    if ($userId) {
        $stmt = $db->prepare("SELECT id FROM `wishlists` WHERE `user_id` = ? AND `product_id` = ? LIMIT 1");
        $stmt->execute([$userId, $productId]);
        return (bool)$stmt->fetch();
    }
    return isset($_SESSION['wishlist']) && in_array((int)$productId, $_SESSION['wishlist']);
}

function toggleWishlist($productId, $userId = null) {
    global $db;
    $productId = (int)$productId;
    if ($productId <= 0) {
        return ['success' => false, 'message' => 'Invalid craft product selected.'];
    }

    if ($userId === null && isLoggedIn()) {
        $userId = $_SESSION['user_id'];
    }

    if ($userId) {
        $stmt = $db->prepare("SELECT id FROM `wishlists` WHERE `user_id` = ? AND `product_id` = ? LIMIT 1");
        $stmt->execute([$userId, $productId]);
        $row = $stmt->fetch();

        if ($row) {
            $del = $db->prepare("DELETE FROM `wishlists` WHERE `id` = ?");
            $del->execute([$row['id']]);
            $inWishlist = false;
            $msg = 'Removed from your saved wishlist.';
        } else {
            $ins = $db->prepare("INSERT INTO `wishlists` (`user_id`, `product_id`, `created_at`) VALUES (?, ?, NOW())");
            $ins->execute([$userId, $productId]);
            $inWishlist = true;
            $msg = 'Added craft to your wishlist!';
        }
        $count = getWishlistCount($userId);
        return [
            'success' => true,
            'in_wishlist' => $inWishlist,
            'wishlist_count' => $count,
            'message' => $msg
        ];
    } else {
        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
        $key = array_search($productId, $_SESSION['wishlist']);
        if ($key !== false) {
            unset($_SESSION['wishlist'][$key]);
            $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
            $inWishlist = false;
            $msg = 'Removed from your saved wishlist.';
        } else {
            $_SESSION['wishlist'][] = $productId;
            $inWishlist = true;
            $msg = 'Added craft to your wishlist!';
        }
        return [
            'success' => true,
            'in_wishlist' => $inWishlist,
            'wishlist_count' => count($_SESSION['wishlist']),
            'message' => $msg
        ];
    }
}

function getUserWishlistProducts($userId = null) {
    global $db;
    if ($userId === null && isLoggedIn()) {
        $userId = $_SESSION['user_id'];
    }

    if ($userId) {
        $stmt = $db->prepare("
            SELECT p.*, s.shop_name, s.shop_slug, c.name as category_name, w.id as wishlist_id, w.created_at as saved_date
            FROM `wishlists` w
            JOIN `products` p ON w.product_id = p.id
            JOIN `sellers` s ON p.seller_id = s.id
            JOIN `categories` c ON p.category_id = c.id
            WHERE w.user_id = ?
            ORDER BY w.id DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } else {
        $prodIds = $_SESSION['wishlist'] ?? [];
        if (empty($prodIds)) return [];
        $placeholders = implode(',', array_fill(0, count($prodIds), '?'));
        $stmt = $db->prepare("
            SELECT p.*, s.shop_name, s.shop_slug, c.name as category_name, 0 as wishlist_id, NOW() as saved_date
            FROM `products` p
            JOIN `sellers` s ON p.seller_id = s.id
            JOIN `categories` c ON p.category_id = c.id
            WHERE p.id IN ($placeholders)
            ORDER BY p.id DESC
        ");
        $stmt->execute($prodIds);
        return $stmt->fetchAll();
    }
}

/**
 * ================================================================
 * Notification Functions & Helpers
 * ================================================================
 */
function createNotification($userId, $title, $message, $type = 'general', $link = null, $sellerId = null, $orderNumber = null) {
    global $db;
    try {
        if (!$orderNumber) {
            if (preg_match('/#([A-Z0-9\-]+)/', $title, $matches)) {
                $orderNumber = $matches[1];
            } elseif ($link && preg_match('/order=([A-Z0-9\-]+)/', $link, $matches)) {
                $orderNumber = $matches[1];
            }
        }
        $stmt = $db->prepare("INSERT INTO `notifications` (`user_id`, `seller_id`, `order_number`, `title`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())");
        return $stmt->execute([$userId, $sellerId, $orderNumber, $title, $message, $type, $link]);
    } catch (Exception $e) {
        return false;
    }
}

function dismissOrderNotifications($orderNumber) {
    global $db;
    try {
        if (empty($orderNumber)) return false;
        $stmt = $db->prepare("DELETE FROM `notifications` WHERE `order_number` = ? OR `link` LIKE ? OR `title` LIKE ?");
        return $stmt->execute([$orderNumber, "%order=" . $orderNumber . "%", "%" . $orderNumber . "%"]);
    } catch (Exception $e) {
        return false;
    }
}

function getUserUnreadNotificationCount($userId) {
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT COUNT(n.id) 
            FROM `notifications` n
            LEFT JOIN `orders` o ON (
                (n.order_number IS NOT NULL AND o.order_number = n.order_number)
                OR (n.order_number IS NULL AND n.link LIKE CONCAT('%order=', o.order_number, '%'))
            )
            WHERE n.`user_id` = ? 
              AND n.`is_read` = 0
              AND (o.order_status IS NULL OR o.order_status NOT IN ('shipped', 'delivered', 'cancelled'))
        ");
        $stmt->execute([(int)$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function getUserNotifications($userId, $limit = 10) {
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT n.* 
            FROM `notifications` n
            LEFT JOIN `orders` o ON (
                (n.order_number IS NOT NULL AND o.order_number = n.order_number)
                OR (n.order_number IS NULL AND n.link LIKE CONCAT('%order=', o.order_number, '%'))
            )
            WHERE n.`user_id` = ?
              AND (o.order_status IS NULL OR o.order_status NOT IN ('shipped', 'delivered', 'cancelled'))
            ORDER BY n.`id` DESC 
            LIMIT " . (int)$limit . "
        ");
        $stmt->execute([(int)$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Log standard order tracking event
 */
function logOrderTrackingEvent($orderId, $orderNumber, $title, $actor, $location, $statusKey, $note = '') {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO `order_tracking_events` 
            (`order_id`, `order_number`, `title`, `actor`, `location`, `status_key`, `note`, `created_at`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$orderId, $orderNumber, $title, $actor, $location, $statusKey, $note]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Send notification to all Logistics / Admin personnel
 */
function notifyLogisticsAdmins($title, $message, $link = null, $orderNumber = null) {
    global $db;
    try {
        $logisticsUsers = $db->query("SELECT id FROM `users` WHERE `role` IN ('logistics', 'admin')")->fetchAll();
        foreach ($logisticsUsers as $lu) {
            createNotification($lu['id'], $title, $message, 'logistics_request', $link ?: BASE_URL . 'logistics/#orders', null, $orderNumber);
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}


