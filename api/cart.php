<?php
/**
 * Cart AJAX Endpoint
 * HAAT Multi-Vendor Marketplace
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// Accept both form-data and JSON payloads
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
$payload = is_array($jsonData) ? array_merge($_REQUEST, $jsonData) : $_REQUEST;

$action = $payload['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'get' : '');

if ($action === 'get' || (empty($action) && $_SERVER['REQUEST_METHOD'] === 'GET')) {
    echo json_encode([
        'success' => true,
        'cart' => getCart(),
        'cart_count' => getCartCount(),
        'cart_subtotal' => formatPrice(getCartSubtotal())
    ]);
    exit;
}

if ($action === 'add') {
    $productId = (int)($payload['product_id'] ?? 0);
    $quantity = (int)($payload['quantity'] ?? 1);

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
        exit;
    }

    // Enforce strict marketplace rule: Sellers cannot purchase products (neither own nor others)
    if (isLoggedIn() && isSeller()) {
        echo json_encode([
            'success' => false, 
            'message' => 'Seller accounts are for selling crafts and cannot make purchases. Please use a buyer account to purchase.'
        ]);
        exit;
    }

    $added = addToCart($productId, $quantity);
    if ($added) {
        $cart = getCart();
        $prodName = $cart[$productId]['name'] ?? 'Product';
        echo json_encode([
            'success' => true,
            'message' => 'Added to cart successfully.',
            'product_name' => $prodName,
            'cart_count' => getCartCount(),
            'cart_subtotal' => formatPrice(getCartSubtotal())
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found or out of stock.']);
    }
    exit;
}

if ($action === 'update') {
    $productId = (int)($payload['product_id'] ?? 0);
    $quantity = (int)($payload['quantity'] ?? 1);
    $updated = updateCartQuantity($productId, $quantity);

    echo json_encode([
        'success' => $updated,
        'message' => $updated ? 'Cart updated successfully.' : 'The requested quantity is no longer available.',
        'cart_count' => getCartCount(),
        'cart_subtotal' => formatPrice(getCartSubtotal())
    ]);
    exit;
}

if ($action === 'remove') {
    $productId = (int)($payload['product_id'] ?? 0);
    removeFromCart($productId);

    echo json_encode([
        'success' => true,
        'cart_count' => getCartCount(),
        'cart_subtotal' => formatPrice(getCartSubtotal())
    ]);
    exit;
}

if ($action === 'clear') {
    clearCart();
    echo json_encode([
        'success' => true,
        'cart_count' => 0,
        'cart_subtotal' => formatPrice(0)
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
