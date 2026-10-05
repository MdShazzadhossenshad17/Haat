<?php
// =========================================================
// HAAT! API — Product Reviews
// GET  /api/reviews.php?product_id=:id   get reviews for product
// GET  /api/reviews.php?action=mine      customer's own reviews
// POST /api/reviews.php                  submit review (customer)
// DELETE /api/reviews.php?id=:id         delete review (customer/admin)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db        = getDB();
$method    = $_SERVER['REQUEST_METHOD'];
$productId = (int) query('product_id', 0);
$id        = (int) query('id', 0);
$action    = query('action', '');

// ─── GET ─────────────────────────────────────────────────
if ($method === 'GET') {

    if ($action === 'mine') {
        $user = auth_required();
        $stmt = $db->prepare(
            "SELECT r.id, r.product_id, r.rating, r.comment, r.created_at,
                    p.name AS product_name, p.slug AS product_slug
             FROM product_reviews r
             JOIN products p ON p.id = r.product_id
             WHERE r.user_id = ?
             ORDER BY r.created_at DESC"
        );
        $stmt->execute([$user['id']]);
        json_ok($stmt->fetchAll());
    }

    if (!$productId) {
        $p = paginate(100);
        $stmt = $db->prepare(
            "SELECT r.id, r.product_id, r.user_id, r.rating, r.comment, r.created_at,
                    u.name AS user_name, u.name AS reviewer_name,
                    p.name AS product_name
             FROM product_reviews r
             JOIN users u ON u.id = r.user_id
             JOIN products p ON p.id = r.product_id
             ORDER BY r.created_at DESC
             LIMIT {$p['limit']} OFFSET {$p['offset']}"
        );
        $stmt->execute();
        $allReviews = $stmt->fetchAll();
        json_ok(['reviews' => $allReviews, 'page' => $p['page']]);
    }

    $p    = paginate(10);
    $stmt = $db->prepare(
        "SELECT r.id, r.rating, r.comment, r.created_at,
                u.name AS reviewer_name, u.name AS user_name
         FROM product_reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ?
         ORDER BY r.created_at DESC
         LIMIT {$p['limit']} OFFSET {$p['offset']}"
    );
    $stmt->execute([$productId]);
    $reviews = $stmt->fetchAll();

    // Summary
    $sumStmt = $db->prepare(
        'SELECT ROUND(AVG(rating),1) AS avg, COUNT(*) AS total,
                SUM(rating=5) AS five, SUM(rating=4) AS four,
                SUM(rating=3) AS three, SUM(rating=2) AS two, SUM(rating=1) AS one
         FROM product_reviews WHERE product_id = ?'
    );
    $sumStmt->execute([$productId]);
    $summary = $sumStmt->fetch();

    json_ok(['reviews' => $reviews, 'summary' => $summary, 'page' => $p['page']]);
}

// ─── POST — submit review ────────────────────────────────
if ($method === 'POST') {
    $user = role_required('customer');
    $data = require_body('product_id', 'rating');

    $pid    = (int) $data['product_id'];
    $rating = (int) $data['rating'];

    if ($rating < 1 || $rating > 5) json_error('Rating must be 1-5', 422);

    // Check purchased & delivered
    $uStmt = $db->prepare('SELECT phone, email FROM users WHERE id = ?');
    $uStmt->execute([$user['id']]);
    $uInfo = $uStmt->fetch();
    $uPhone = !empty($uInfo['phone']) ? $uInfo['phone'] : null;
    $uEmail = !empty($uInfo['email']) ? $uInfo['email'] : null;

    $purch = $db->prepare(
        "SELECT oi.id FROM order_items oi
         JOIN seller_orders so ON so.id = oi.seller_order_id
         JOIN orders o ON o.id = so.order_id
         WHERE (o.user_id = ? OR (? IS NOT NULL AND o.shipping_phone = ?) OR (? IS NOT NULL AND o.shipping_email = ?))
           AND oi.product_id = ?
           AND (so.status = 'delivered' OR o.order_status = 'delivered')
         LIMIT 1"
    );
    $purch->execute([$user['id'], $uPhone, $uPhone, $uEmail, $uEmail, $pid]);
    if (!$purch->fetch()) json_error('You can only review products you have received', 403);

    // Check already reviewed -> update if already reviewed, else insert
    $dup = $db->prepare('SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?');
    $dup->execute([$user['id'], $pid]);
    $existing = $dup->fetch();
    if ($existing) {
        $db->prepare(
            'UPDATE product_reviews SET rating = ?, comment = ?, created_at = NOW() WHERE id = ?'
        )->execute([$rating, $data['comment'] ?? null, $existing['id']]);
        json_ok(['message' => 'Review updated successfully']);
    }

    $db->prepare(
        'INSERT INTO product_reviews (product_id, user_id, rating, comment) VALUES (?,?,?,?)'
    )->execute([$pid, $user['id'], $rating, $data['comment'] ?? null]);

    json_ok(['message' => 'Review submitted'], 201);
}

// ─── DELETE ───────────────────────────────────────────────
if ($method === 'DELETE') {
    $user = auth_required();
    if (!$id) json_error('id required', 422);

    if ($user['role'] === 'admin') {
        $db->prepare('DELETE FROM product_reviews WHERE id = ?')->execute([$id]);
    } else {
        $stmt = $db->prepare('DELETE FROM product_reviews WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        if ($stmt->rowCount() === 0) json_error('Review not found', 404);
    }
    json_ok(['message' => 'Review deleted']);
}

json_error('Method not allowed', 405);
