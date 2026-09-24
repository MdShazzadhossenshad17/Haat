<?php
/**
 * Cart AJAX Endpoint
 * HAAT Multi-Vendor Marketplace
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
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
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    updateCartQuantity($productId, $quantity);

    echo json_encode([
        'success' => true,
        'cart_count' => getCartCount(),
        'cart_subtotal' => formatPrice(getCartSubtotal())
    ]);
    exit;
}

if ($action === 'remove') {
    $productId = (int)($_POST['product_id'] ?? 0);
    removeFromCart($productId);

    echo json_encode([
        'success' => true,
        'cart_count' => getCartCount(),
        'cart_subtotal' => formatPrice(getCartSubtotal())
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
