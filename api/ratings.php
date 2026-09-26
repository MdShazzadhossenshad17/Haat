<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// Accept GET or POST
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$sellerId = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;
$productIds = isset($_GET['product_ids']) ? trim($_GET['product_ids']) : '';

try {
    // 1. Batch products rating fetch
    if (!empty($productIds)) {
        $ids = array_filter(array_map('intval', explode(',', $productIds)));
        if (empty($ids)) {
            echo json_encode(['success' => false, 'error' => 'Invalid product IDs']);
            exit;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("
            SELECT p.id as product_id,
                   COALESCE(AVG(r.rating), p.rating, 0.0) as avg_rating,
                   COUNT(r.id) as total_reviews
            FROM products p
            LEFT JOIN product_reviews r ON p.id = r.product_id
            WHERE p.id IN ($in)
            GROUP BY p.id
        ");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        foreach ($rows as $row) {
            $results[$row['product_id']] = [
                'avg' => round((float)$row['avg_rating'], 1),
                'count' => (int)$row['total_reviews']
            ];
        }
        echo json_encode(['success' => true, 'ratings' => $results]);
        exit;
    }

    // 2. Single product rating fetch
    if ($productId > 0) {
        $stmt = $db->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM product_reviews WHERE product_id = ?');
        $stmt->execute([$productId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $avg = ($r && $r['avg_rating'] !== null) ? round((float)$r['avg_rating'], 1) : 0.0;
        echo json_encode(['success' => true, 'product_id' => $productId, 'avg' => $avg, 'count' => (int)($r['total'] ?? 0)]);
        exit;
    }

    // 3. Single seller rating fetch
    if ($sellerId > 0) {
        $stmt = $db->prepare('SELECT AVG(r.rating) as avg_rating, COUNT(r.id) as total FROM product_reviews r JOIN products p ON r.product_id = p.id WHERE p.seller_id = ?');
        $stmt->execute([$sellerId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $avg = ($r && $r['avg_rating'] !== null) ? round((float)$r['avg_rating'], 1) : 0.0;
        echo json_encode(['success' => true, 'seller_id' => $sellerId, 'avg' => $avg, 'count' => (int)($r['total'] ?? 0)]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Missing product_id, seller_id or product_ids']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
