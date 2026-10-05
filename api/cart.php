<?php
// =========================================================
// HAAT! API — Cart
// GET    /api/cart.php            get cart items
// POST   /api/cart.php            add item to cart
// PUT    /api/cart.php?id=:id     update quantity
// DELETE /api/cart.php?id=:id     remove one item
// DELETE /api/cart.php?action=clear  clear entire cart
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = auth_required();
$id     = (int) query('id', 0);
$action = query('action', '');

// ─── GET — fetch cart ────────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->prepare(
        "SELECT c.id, c.product_id, c.quantity, c.variant_name, c.variant_value,
                p.name AS product_name, p.slug AS product_slug,
                p.price, p.sale_price,
                COALESCE(p.sale_price, p.price) AS unit_price,
                COALESCE(p.sale_price, p.price) * c.quantity AS line_total,
                s.store_name, s.id AS store_id,
                COALESCE(i.quantity, 0) AS stock,
                p.id AS pid
         FROM cart c
         JOIN products p ON p.id = c.product_id
         JOIN stores s   ON s.id = p.store_id
         LEFT JOIN inventory i ON i.product_id = p.id
         WHERE c.user_id = ?
         ORDER BY c.created_at DESC"
    );
    $stmt->execute([$user['id']]);
    $items = $stmt->fetchAll();

    $base = api_base_url();
    foreach ($items as &$it) {
        $it['image_url'] = "$base/images.php?type=product&id={$it['pid']}&n=1";
    }

    $total = array_sum(array_column($items, 'line_total'));

    json_ok(['items' => $items, 'total' => round($total, 2), 'count' => count($items)]);
}

// ─── POST — add to cart ──────────────────────────────────
if ($method === 'POST') {
    $data      = require_body('product_id');
    $productId = (int) $data['product_id'];
    $qty       = max(1, (int) ($data['quantity'] ?? 1));

    // Check product exists and is in stock
    $pStmt = $db->prepare(
        "SELECT p.id, COALESCE(i.quantity, 0) AS stock
         FROM products p
         LEFT JOIN inventory i ON i.product_id = p.id
         WHERE p.id = ?"
    );
    $pStmt->execute([$productId]);
    $product = $pStmt->fetch();
    if (!$product) json_error('Product not found', 404);
    if ($product['stock'] < $qty) json_error('Insufficient stock', 400);

    // Check if already in cart
    $exists = $db->prepare('SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?');
    $exists->execute([$user['id'], $productId]);
    $row = $exists->fetch();

    if ($row) {
        // Update quantity
        $newQty = $row['quantity'] + $qty;
        if ($newQty > $product['stock']) {
            json_error('Not enough stock for requested quantity', 400);
        }
        $db->prepare('UPDATE cart SET quantity = ? WHERE id = ?')->execute([$newQty, $row['id']]);
        json_ok(['message' => 'Cart updated', 'id' => (int) $row['id']]);
    }

    // Insert new row
    $stmt = $db->prepare(
        'INSERT INTO cart (user_id, product_id, quantity, variant_name, variant_value)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'], $productId, $qty,
        $data['variant_name']  ?? null,
        $data['variant_value'] ?? null,
    ]);
    json_ok(['message' => 'Added to cart', 'id' => (int) $db->lastInsertId()], 201);
}

// ─── PUT — update quantity ───────────────────────────────
if ($method === 'PUT') {
    if (!$id) json_error('Cart item id required', 422);
    $data = require_body('quantity');
    $qty  = max(1, (int) $data['quantity']);

    // Ownership + stock check
    $own = $db->prepare(
        'SELECT c.id, p.id AS product_id, COALESCE(i.quantity, 0) AS stock
         FROM cart c
         JOIN products p ON p.id = c.product_id
         LEFT JOIN inventory i ON i.product_id = p.id
         WHERE c.id = ? AND c.user_id = ?'
    );
    $own->execute([$id, $user['id']]);
    $item = $own->fetch();
    if (!$item) json_error('Cart item not found', 404);
    if ($qty > $item['stock']) json_error('Insufficient stock', 400);

    $db->prepare('UPDATE cart SET quantity = ? WHERE id = ?')->execute([$qty, $id]);
    json_ok(['message' => 'Quantity updated']);
}

// ─── DELETE — remove item or clear cart ──────────────────
if ($method === 'DELETE') {
    if ($action === 'clear') {
        $db->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$user['id']]);
        json_ok(['message' => 'Cart cleared']);
    }
    if (!$id) json_error('Cart item id required', 422);
    $stmt = $db->prepare('DELETE FROM cart WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    if ($stmt->rowCount() === 0) json_error('Cart item not found', 404);
    json_ok(['message' => 'Item removed from cart']);
}

json_error('Method not allowed', 405);
