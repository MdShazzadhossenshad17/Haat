<?php
// =========================================================
// HAAT! API — Wishlists
// GET    /api/wishlists.php                  get wishlist
// POST   /api/wishlists.php                  add product
// DELETE /api/wishlists.php?product_id=:id   remove product
// GET    /api/wishlists.php?action=check&product_id=:id
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db        = getDB();
$method    = $_SERVER['REQUEST_METHOD'];
$user      = auth_required();
$productId = (int) query('product_id', 0);
$action    = query('action', '');

// ─── GET ─────────────────────────────────────────────────
if ($method === 'GET') {
    if ($action === 'check') {
        if (!$productId) json_error('product_id required', 422);
        $stmt = $db->prepare('SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$user['id'], $productId]);
        json_ok(['in_wishlist' => (bool) $stmt->fetch()]);
    }

    $stmt = $db->prepare(
        "SELECT w.id, w.product_id, w.created_at,
                p.name, p.slug, p.price, p.sale_price,
                s.store_name
         FROM wishlists w
         JOIN products p ON p.id = w.product_id
         JOIN stores s   ON s.id = p.store_id
         WHERE w.user_id = ?
         ORDER BY w.created_at DESC"
    );
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
    $base = api_base_url();
    foreach ($rows as &$r) {
        $r['image_url'] = "$base/images.php?type=product&id={$r['product_id']}&n=1";
    }
    json_ok($rows);
}

// ─── POST — add to wishlist ──────────────────────────────
if ($method === 'POST') {
    $data      = require_body('product_id');
    $productId = (int) $data['product_id'];

    // Already in wishlist?
    $chk = $db->prepare('SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?');
    $chk->execute([$user['id'], $productId]);
    if ($chk->fetch()) json_ok(['message' => 'Already in wishlist']);

    $db->prepare('INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([$user['id'], $productId]);
    json_ok(['message' => 'Added to wishlist'], 201);
}

// ─── DELETE — remove from wishlist ───────────────────────
if ($method === 'DELETE') {
    if (!$productId) json_error('product_id required', 422);
    $stmt = $db->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$user['id'], $productId]);
    if ($stmt->rowCount() === 0) json_error('Not in wishlist', 404);
    json_ok(['message' => 'Removed from wishlist']);
}

json_error('Method not allowed', 405);
