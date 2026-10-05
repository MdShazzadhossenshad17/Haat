<?php
// =========================================================
// HAAT! API — Seller Orders
// GET /api/seller_orders.php               list seller's sub-orders
// GET /api/seller_orders.php?id=:id        single seller order detail
// GET /api/seller_orders.php?action=all    admin: all seller orders
// PUT /api/seller_orders.php?id=:id        update status / assign rider
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = auth_required();
$id     = (int) query('id', 0);
$action = query('action', '');

// ─────────────────────────────────────────────────────────
// GET
// ─────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Admin: all seller orders
    if ($action === 'all') {
        role_required('admin');
        $p      = paginate(20);
        $status = query('status', '');
        $where  = $status ? 'WHERE so.status = ?' : '';
        $params = $status ? [$status] : [];

        $stmt = $db->prepare(
            "SELECT so.id, so.seller_order_number, so.status,
                    so.subtotal, so.seller_total, so.created_at,
                    s.store_name, o.order_number,
                    r.id AS rider_id, ru.name AS rider_name
             FROM seller_orders so
             JOIN stores s  ON s.id  = so.store_id
             JOIN orders o  ON o.id  = so.order_id
             LEFT JOIN riders r  ON r.id  = so.assigned_rider_id
             LEFT JOIN users ru  ON ru.id = r.user_id
             $where
             ORDER BY so.created_at DESC
             LIMIT {$p['limit']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        json_ok(['seller_orders' => $stmt->fetchAll(), 'page' => $p['page']]);
    }

    // Single seller order
    if ($id) {
        $stmt = $db->prepare(
            "SELECT so.*, s.store_name, o.order_number,
                    o.shipping_name, o.shipping_phone, o.shipping_address,
                    o.district, o.division, o.postal_code,
                    r.id AS rider_id, ru.name AS rider_name
             FROM seller_orders so
             JOIN stores s ON s.id = so.store_id
             JOIN orders o ON o.id = so.order_id
             LEFT JOIN riders r  ON r.id  = so.assigned_rider_id
             LEFT JOIN users ru  ON ru.id = r.user_id
             WHERE so.id = ?"
        );
        $stmt->execute([$id]);
        $so = $stmt->fetch();
        if (!$so) json_error('Seller order not found', 404);

        // Access control
        if ($user['role'] === 'seller' && (int) $so['store_id'] !== $user['store_id']) {
            json_error('Forbidden', 403);
        }

        // Items
        $iStmt = $db->prepare('SELECT * FROM order_items WHERE seller_order_id = ?');
        $iStmt->execute([$id]);
        $items = $iStmt->fetchAll();
        $base = api_base_url();
        foreach ($items as &$it) {
            $it['image_url'] = "$base/images.php?type=product&id={$it['product_id']}&n=1";
        }
        $so['items'] = $items;

        // Tracking
        $tStmt = $db->prepare(
            'SELECT dt.status, dt.location, dt.note, dt.created_at, u.name AS updated_by_name
             FROM delivery_tracking dt
             LEFT JOIN users u ON u.id = dt.updated_by
             WHERE dt.seller_order_id = ?
             ORDER BY dt.created_at ASC'
        );
        $tStmt->execute([$id]);
        $so['tracking'] = $tStmt->fetchAll();

        json_ok($so);
    }

    // Seller / HATEX / Rider / Admin: list seller orders
    $user   = role_required(['seller', 'logistics', 'hatex', 'rider', 'admin']);
    $p      = paginate(15);
    $status = query('status', '');

    if ($user['role'] === 'seller') {
        $sid   = $user['store_id'];
        if (!$sid) json_error('No store found', 404);
        $where  = $status ? 'AND so.status = ?' : '';
        $params = $status ? [$sid, $status] : [$sid];

        $stmt = $db->prepare(
            "SELECT so.id, so.seller_order_number, so.status,
                    so.subtotal, so.seller_total, so.created_at,
                    o.order_number, o.shipping_name, o.district
             FROM seller_orders so
             JOIN orders o ON o.id = so.order_id
             WHERE so.store_id = ? $where
             ORDER BY so.created_at DESC
             LIMIT {$p['limit']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
    } elseif ($user['role'] === 'hatex' || $user['role'] === 'admin') {
        // HATEX / Admin: all seller orders or by status
        $where  = $status ? 'WHERE so.status = ?' : '';
        $params = $status ? [$status] : [];
        $stmt = $db->prepare(
            "SELECT so.id, so.seller_order_number, so.status,
                    so.seller_total, so.created_at, so.assigned_rider_id,
                    o.order_number, o.shipping_name, o.district, o.division,
                    s.store_name, r.id AS rider_id, ru.name AS rider_name
             FROM seller_orders so
             JOIN orders o ON o.id = so.order_id
             JOIN stores s ON s.id = so.store_id
             LEFT JOIN riders r ON r.id = so.assigned_rider_id
             LEFT JOIN users ru ON ru.id = r.user_id
             $where
             ORDER BY so.created_at DESC
             LIMIT {$p['limit']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
    } else {
        // Rider: orders assigned to them
        $riderStmt = $db->prepare('SELECT id FROM riders WHERE user_id = ?');
        $riderStmt->execute([$user['id']]);
        $rider = $riderStmt->fetch();

        if ($rider) {
            $stmt = $db->prepare(
                "SELECT so.id, so.seller_order_number, so.status,
                        so.seller_total, so.created_at,
                        o.order_number, o.shipping_name, o.district, o.division,
                        s.store_name
                 FROM seller_orders so
                 JOIN orders o ON o.id = so.order_id
                 JOIN stores s ON s.id = so.store_id
                 WHERE so.assigned_rider_id = ?
                 ORDER BY so.created_at DESC
                 LIMIT {$p['limit']} OFFSET {$p['offset']}"
            );
            $stmt->execute([$rider['id']]);
        } else {
            // Unassigned rider pool
            $stmt = $db->prepare(
                "SELECT so.id, so.seller_order_number, so.status,
                        so.seller_total, so.created_at,
                        o.order_number, o.shipping_name, o.district, o.division,
                        s.store_name
                 FROM seller_orders so
                 JOIN orders o ON o.id = so.order_id
                 JOIN stores s ON s.id = so.store_id
                 ORDER BY so.created_at DESC
                 LIMIT {$p['limit']} OFFSET {$p['offset']}"
            );
            $stmt->execute();
        }
    }

    json_ok(['seller_orders' => $stmt->fetchAll(), 'page' => $p['page']]);
}

// ─────────────────────────────────────────────────────────
// PUT — update status / assign rider
// ─────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) json_error('id required', 422);
    $data = get_body();

    $chk = $db->prepare('SELECT so.*, s.user_id AS seller_user_id FROM seller_orders so JOIN stores s ON s.id = so.store_id WHERE so.id = ?');
    $chk->execute([$id]);
    $so = $chk->fetch();
    if (!$so) json_error('Seller order not found', 404);

    // Seller can only update their own
    if ($user['role'] === 'seller' && (int) $so['seller_user_id'] !== $user['id']) {
        json_error('Forbidden', 403);
    } elseif (!in_array($user['role'], ['seller','admin','logistics','hatex','rider'])) {
        json_error('Forbidden', 403);
    }

    $fields = [];
    $params = [];

    $trackStatus = null;
    if (isset($data['status'])) {
        $statusVal = strtolower(trim($data['status']));
        $statusMap = [
            'order_accepted'   => 'confirmed',
            'accept'           => 'confirmed',
            'confirmed'        => 'confirmed',
            'processing'       => 'processing',
            'packaged'         => 'ready_to_ship',
            'ready'            => 'ready_to_ship',
            'ready_to_ship'    => 'ready_to_ship',
            'shipped'          => 'shipped',
            'dispatch'         => 'shipped',
            'picked_up'        => 'shipped',
            'in_transit'       => 'shipped',
            'out_for_delivery' => 'shipped',
            'delivered'        => 'delivered',
            'cancelled'        => 'cancelled',
            'refunded'         => 'refunded'
        ];
        $sellerOrderStatus = $statusMap[$statusVal] ?? $statusVal;
        $validStatuses = ['pending','confirmed','processing','ready_to_ship','shipped','delivered','cancelled','refunded'];
        if (!in_array($sellerOrderStatus, $validStatuses)) {
            json_error('Invalid status: ' . $data['status'], 422);
        }
        $fields[] = 'status = ?';
        $params[] = $sellerOrderStatus;

        $trackMap = [
            'pending'       => 'order_placed',
            'confirmed'     => 'processing',
            'processing'    => 'processing',
            'ready_to_ship' => 'processing',
            'shipped'       => 'in_transit',
            'delivered'     => 'delivered',
            'cancelled'     => 'failed',
            'refunded'      => 'returned'
        ];
        $trackStatus = $trackMap[$sellerOrderStatus] ?? 'processing';
    }
    if (isset($data['assigned_rider_id']) && $user['role'] !== 'seller') {
        $fields[] = 'assigned_rider_id = ?';
        $params[] = (int) $data['assigned_rider_id'];
    }
    if (!$fields) json_error('Nothing to update', 422);

    $params[] = $id;
    $db->prepare('UPDATE seller_orders SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

    // Auto-insert delivery tracking on status change
    if ($trackStatus) {
        $db->prepare(
            'INSERT INTO delivery_tracking (seller_order_id, rider_id, status, note, updated_by)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $id,
            $so['assigned_rider_id'] ?? null,
            $trackStatus,
            $data['note'] ?? null,
            $user['id'],
        ]);

        // Synchronize parent order status in orders table
        if ($sellerOrderStatus === 'confirmed') {
            $db->prepare("UPDATE orders SET order_status = 'confirmed', updated_at = NOW() WHERE id = ? AND order_status IN ('pending', 'order_placed')")->execute([$so['order_id']]);
        } elseif ($sellerOrderStatus === 'processing') {
            $db->prepare("UPDATE orders SET order_status = 'processing', updated_at = NOW() WHERE id = ? AND order_status IN ('pending', 'order_placed', 'confirmed')")->execute([$so['order_id']]);
        } elseif ($sellerOrderStatus === 'ready_to_ship') {
            $chkPack = $db->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN status IN ("ready_to_ship","packaged","shipped","delivered") THEN 1 ELSE 0 END) as pack_cnt FROM seller_orders WHERE order_id = ?');
            $chkPack->execute([$so['order_id']]);
            $pRow = $chkPack->fetch();
            if ($pRow && (int)$pRow['total'] === (int)$pRow['pack_cnt']) {
                $db->prepare("UPDATE orders SET order_status = 'processing', updated_at = NOW() WHERE id = ?")->execute([$so['order_id']]);
            }
        } elseif ($sellerOrderStatus === 'shipped') {
            $db->prepare("UPDATE orders SET order_status = 'shipped', updated_at = NOW() WHERE id = ?")->execute([$so['order_id']]);
        } elseif ($sellerOrderStatus === 'delivered') {
            $chkParent = $db->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered_cnt FROM seller_orders WHERE order_id = ?');
            $chkParent->execute([$so['order_id']]);
            $pRow = $chkParent->fetch();
            if ($pRow && (int)$pRow['total'] === (int)$pRow['delivered_cnt']) {
                $db->prepare("UPDATE orders SET order_status = 'delivered', updated_at = NOW() WHERE id = ?")->execute([$so['order_id']]);
            }
        }

        // Notify customer
        $cusStmt = $db->prepare('SELECT o.user_id FROM orders o JOIN seller_orders so ON so.order_id = o.id WHERE so.id = ?');
        $cusStmt->execute([$id]);
        $cusRow = $cusStmt->fetch();
        if ($cusRow) {
            $db->prepare(
                'INSERT INTO notifications (user_id, order_id, title, message, type)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $cusRow['user_id'],
                $so['order_id'],
                'Order Status Updated',
                "Your order #{$so['seller_order_number']} is now: " . strtoupper($sellerOrderStatus),
                'order_status',
            ]);
        }
    }

    json_ok(['message' => 'Seller order updated']);
}

json_error('Method not allowed', 405);
