<?php
// =========================================================
// HAAT! API — Inventory
// GET /api/inventory.php?product_id=:id   get stock
// PUT /api/inventory.php?product_id=:id   update stock (seller/admin)
// GET /api/inventory.php?action=low       low stock list (seller/admin)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db        = getDB();
$method    = $_SERVER['REQUEST_METHOD'];
$productId = (int) query('product_id', 0);
$action    = query('action', '');

if ($method === 'GET') {

    if ($action === 'low') {
        // Low stock report for seller
        $user      = auth_required();
        $threshold = (int) query('threshold', 5);

        if ($user['role'] === 'seller') {
            $stmt = $db->prepare(
                "SELECT i.product_id, p.name, p.sku, i.quantity, i.reserved_quantity,
                        (i.quantity - i.reserved_quantity) AS available
                 FROM inventory i
                 JOIN products p ON p.id = i.product_id
                 WHERE p.store_id = ? AND i.quantity <= ?
                 ORDER BY i.quantity ASC"
            );
            $stmt->execute([$user['store_id'], $threshold]);
        } else {
            role_required('admin');
            $stmt = $db->prepare(
                "SELECT i.product_id, p.name, p.sku, i.quantity, i.reserved_quantity,
                        s.store_name
                 FROM inventory i
                 JOIN products p ON p.id = i.product_id
                 JOIN stores s   ON s.id = p.store_id
                 WHERE i.quantity <= ?
                 ORDER BY i.quantity ASC"
            );
            $stmt->execute([$threshold]);
        }
        json_ok($stmt->fetchAll());
    }

    if (!$productId) json_error('product_id required', 422);

    $stmt = $db->prepare(
        'SELECT product_id, quantity, reserved_quantity,
                (quantity - reserved_quantity) AS available, updated_at
         FROM inventory WHERE product_id = ?'
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    if (!$row) json_error('Inventory record not found', 404);
    json_ok($row);
}

if ($method === 'PUT') {
    $user = auth_required();
    if (!$productId) json_error('product_id required', 422);

    // Ownership check
    if ($user['role'] === 'seller') {
        $own = $db->prepare('SELECT id FROM products WHERE id = ? AND store_id = ?');
        $own->execute([$productId, $user['store_id']]);
        if (!$own->fetch()) json_error('Product not found or not yours', 403);
    } elseif ($user['role'] !== 'admin') {
        json_error('Forbidden', 403);
    }

    $data = get_body();
    if (!isset($data['quantity'])) json_error('quantity required', 422);

    $qty = max(0, (int) $data['quantity']);

    // Upsert inventory
    $db->prepare(
        'INSERT INTO inventory (product_id, quantity)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE quantity = ?'
    )->execute([$productId, $qty, $qty]);

    json_ok(['message' => 'Inventory updated', 'quantity' => $qty]);
}

json_error('Method not allowed', 405);
