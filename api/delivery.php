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
    $orderId = (int) query('order_id', 0);
    if (!$sellerOrderId && !$orderId) json_error('seller_order_id or order_id required', 422);

    if ($orderId) {
        $stmt = $db->prepare(
            "SELECT dt.id, dt.seller_order_id, dt.status, dt.location, dt.latitude, dt.longitude, dt.note, dt.created_at,
                    u.name AS updated_by_name, so.seller_order_number, s.store_name,
                    r.id AS rider_id, ru.name AS rider_name, ru.phone AS rider_phone
             FROM delivery_tracking dt
             JOIN seller_orders so ON so.id = dt.seller_order_id
             JOIN stores s ON s.id = so.store_id
             LEFT JOIN riders r ON r.id = dt.rider_id
             LEFT JOIN users ru ON ru.id = r.user_id
             LEFT JOIN users u ON u.id = dt.updated_by
             WHERE so.order_id = ?
             ORDER BY dt.created_at ASC"
        );
        $stmt->execute([$orderId]);
        $events = $stmt->fetchAll();

        // Also get order status
        $ordStmt = $db->prepare('SELECT order_number, order_status FROM orders WHERE id = ?');
        $ordStmt->execute([$orderId]);
        $ord = $ordStmt->fetch();

        json_ok([
            'order_number' => $ord['order_number'] ?? null,
            'order_status' => $ord['order_status'] ?? null,
            'events'       => $events,
        ]);
    }

    $stmt = $db->prepare(
        "SELECT dt.id, dt.status, dt.location, dt.latitude, dt.longitude, dt.note, dt.created_at,
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
    $user = role_required(['seller', 'admin', 'logistics', 'hatex', 'rider']);
    $data = require_body('seller_order_id', 'status');

    $soId  = (int) $data['seller_order_id'];
    $status = $data['status'];

    // Get rider id if logistics, hatex, or rider
    $riderId = null;
    if ($user['role'] === 'logistics' || $user['role'] === 'rider' || $user['role'] === 'hatex') {
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

    // Map tracking status to seller_orders status
    $soStatusMap = [
        'order_placed'     => 'pending',
        'processing'       => 'processing',
        'picked_up'        => 'shipped',
        'in_transit'       => 'shipped',
        'out_for_delivery' => 'shipped',
        'delivered'        => 'delivered',
        'failed'           => 'cancelled',
        'returned'         => 'refunded'
    ];
    $soStatus = $soStatusMap[$status] ?? 'shipped';
    $db->prepare('UPDATE seller_orders SET status = ? WHERE id = ?')->execute([$soStatus, $soId]);

    // If delivered, check if parent order is completed
    if ($soStatus === 'delivered') {
        $chkParent = $db->prepare('SELECT order_id, COUNT(*) as total, SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered_cnt FROM seller_orders WHERE order_id = (SELECT order_id FROM seller_orders WHERE id = ?)');
        $chkParent->execute([$soId]);
        $pRow = $chkParent->fetch();
        if ($pRow && (int)$pRow['total'] === (int)$pRow['delivered_cnt']) {
            $db->prepare("UPDATE orders SET order_status = 'delivered', updated_at = NOW() WHERE id = ?")->execute([$pRow['order_id']]);
        }
    }

    json_ok(['message' => 'Tracking event added'], 201);
}

json_error('Method not allowed', 405);
