<?php
// =========================================================
// HAAT! API — Delivery Tracking
// GET  /api/delivery.php?seller_order_id=:id   get tracking events
// POST /api/delivery.php                        add tracking event (seller/admin/logistics)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db            = getDB();
$method        = $_SERVER['REQUEST_METHOD'];
$sellerOrderId = (int) query('seller_order_id', 0);

// ─── GET — tracking history ──────────────────────────────
if ($method === 'GET') {
    if (!$sellerOrderId) json_error('seller_order_id required', 422);

    $stmt = $db->prepare(
        "SELECT dt.id, dt.status, dt.location, dt.note, dt.created_at,
                u.name AS updated_by_name
         FROM delivery_tracking dt
         LEFT JOIN users u ON u.id = dt.updated_by
         WHERE dt.seller_order_id = ?
         ORDER BY dt.created_at ASC"
    );
    $stmt->execute([$sellerOrderId]);
    $events = $stmt->fetchAll();

    // Also get current assigned rider
    $soStmt = $db->prepare(
        "SELECT so.status, so.assigned_rider_id,
                ru.name AS rider_name, ru.phone AS rider_phone
         FROM seller_orders so
         LEFT JOIN riders r  ON r.id  = so.assigned_rider_id
         LEFT JOIN users ru  ON ru.id = r.user_id
         WHERE so.id = ?"
    );
    $soStmt->execute([$sellerOrderId]);
    $so = $soStmt->fetch();

    json_ok([
        'current_status' => $so['status'] ?? null,
        'rider_name'     => $so['rider_name'] ?? null,
        'rider_phone'    => $so['rider_phone'] ?? null,
        'events'         => $events,
    ]);
}

// ─── POST — add tracking event ───────────────────────────
if ($method === 'POST') {
    $user = role_required(['seller', 'admin', 'logistics']);
    $data = require_body('seller_order_id', 'status');

    $soId  = (int) $data['seller_order_id'];
    $status = $data['status'];

    // Get rider id if logistics
    $riderId = null;
    if ($user['role'] === 'logistics') {
        $rStmt = $db->prepare('SELECT id FROM riders WHERE user_id = ?');
        $rStmt->execute([$user['id']]);
        $rider = $rStmt->fetch();
        $riderId = $rider ? (int) $rider['id'] : null;
    }

    $db->prepare(
        'INSERT INTO delivery_tracking
         (seller_order_id, rider_id, status, location, latitude, longitude, note, updated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $soId, $riderId, $status,
        $data['location']  ?? null,
        $data['latitude']  ?? null,
        $data['longitude'] ?? null,
        $data['note']      ?? null,
        $user['id'],
    ]);

    // Update seller order status
    $db->prepare('UPDATE seller_orders SET status = ? WHERE id = ?')->execute([$status, $soId]);

    json_ok(['message' => 'Tracking event added'], 201);
}

json_error('Method not allowed', 405);
