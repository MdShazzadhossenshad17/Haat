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

// Authentication Helpers
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) return null;
    global $db;
    static $user = null;
    if ($user === null) {
        $stmt = $db->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user;
}

function isAdmin() {
    $u = currentUser();
    return $u && $u['role'] === 'admin';
}

function isSeller() {
    $u = currentUser();
    return $u && $u['role'] === 'seller';
}

function isCustomer() {
    $u = currentUser();
    return $u && $u['role'] === 'customer';
}

function currentSeller() {
    if (!isSeller()) return null;
    global $db;
    static $seller = null;
    if ($seller === null) {
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

function requireSeller() {
    if (!isSeller()) {
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

    $price = $prod['sale_price'] ?: $prod['price'];

    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] += $quantity;
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
    $productId = (int)$productId;
    $quantity = (int)$quantity;
    if (isset($_SESSION['cart'][$productId])) {
        if ($quantity <= 0) {
            unset($_SESSION['cart'][$productId]);
        } else {
            $_SESSION['cart'][$productId]['quantity'] = $quantity;
        }
    }
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
    return 'HAAT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}
