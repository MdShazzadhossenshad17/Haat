<?php
/**
 * Wishlist AJAX API Endpoint
 * HAAT Multi-Vendor Marketplace
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
$payload = is_array($jsonData) ? array_merge($_REQUEST, $jsonData) : $_REQUEST;

$action = $payload['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'get' : 'toggle');

if ($action === 'get') {
    $count = getWishlistCount();
    $items = getUserWishlistProducts();
    $ids = array_map(function($p) { return (int)$p['id']; }, $items);
    echo json_encode([
        'success' => true,
        'wishlist_count' => $count,
        'product_ids' => $ids
    ]);
    exit;
}

if ($action === 'toggle') {
    $productId = (int)($payload['product_id'] ?? 0);
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
        exit;
    }

    $res = toggleWishlist($productId);
    echo json_encode($res);
    exit;
}

if ($action === 'remove') {
    $productId = (int)($payload['product_id'] ?? 0);
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
        exit;
    }

    if (isLoggedIn()) {
        $del = $db->prepare("DELETE FROM `wishlists` WHERE `user_id` = ? AND `product_id` = ?");
        $del->execute([$_SESSION['user_id'], $productId]);
    } else {
        if (isset($_SESSION['wishlist'])) {
            $key = array_search($productId, $_SESSION['wishlist']);
            if ($key !== false) {
                unset($_SESSION['wishlist'][$key]);
                $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'in_wishlist' => false,
        'wishlist_count' => getWishlistCount(),
        'message' => 'Craft removed from wishlist.'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
exit;
