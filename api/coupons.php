<?php
// =========================================================
// HAAT! API — Coupons
// GET  /api/coupons.php               list seller's own coupons (seller/admin)
// POST /api/coupons.php               create coupon (seller/admin)
// POST /api/coupons.php?action=validate body:{code,cart_total,store_id?}
// PUT  /api/coupons.php?id=:id        update (seller/admin)
// DELETE /api/coupons.php?id=:id      delete (seller/admin)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = (int) query('id', 0);
$action = query('action', '');

// ─── GET — list coupons ───────────────────────────────────
if ($method === 'GET') {
    $user = auth_required();

    if ($user['role'] === 'admin') {
        $storeId = (int) query('store_id', 0);
        $where   = $storeId ? 'WHERE store_id = ?' : '';
        $params  = $storeId ? [$storeId] : [];
        $stmt    = $db->prepare("SELECT * FROM coupons $where ORDER BY created_at DESC");
        $stmt->execute($params);
    } elseif ($user['role'] === 'seller') {
        $stmt = $db->prepare('SELECT * FROM coupons WHERE store_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user['store_id']]);
    } else {
        json_error('Forbidden', 403);
    }

    json_ok($stmt->fetchAll());
}

// ─── POST — create or validate ───────────────────────────
if ($method === 'POST') {

    // Validate coupon code (any logged-in user during checkout)
    if ($action === 'validate') {
        auth_required();
        $data  = require_body('code', 'cart_total');
        $code  = strtoupper(trim($data['code']));
        $total = (float) $data['cart_total'];

        $stmt = $db->prepare(
            "SELECT * FROM coupons
             WHERE code = ?
               AND is_active = 1
               AND (expiry_date IS NULL OR expiry_date > NOW())
               AND (usage_limit IS NULL OR used_count < usage_limit)
               AND min_order <= ?"
        );
        $stmt->execute([$code, $total]);
        $coupon = $stmt->fetch();

        if (!$coupon) {
            json_error('Invalid, expired, or inapplicable coupon code', 400);
        }

        // Calculate discount
        $discount = 0;
        if ($coupon['discount_type'] === 'percent') {
            $discount = $total * ($coupon['discount_value'] / 100);
            if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
                $discount = (float) $coupon['max_discount'];
            }
        } else {
            $discount = (float) $coupon['discount_value'];
        }
        $discount = min($discount, $total);

        json_ok([
            'valid'         => true,
            'coupon_id'     => (int) $coupon['id'],
            'code'          => $coupon['code'],
            'discount_type' => $coupon['discount_type'],
            'discount_value'=> (float) $coupon['discount_value'],
            'discount_amount'=> round($discount, 2),
            'new_total'     => round($total - $discount, 2),
        ]);
    }

    // Create coupon
    $user = role_required(['seller', 'admin']);
    $data = require_body('code', 'discount_type', 'discount_value');

    $storeId = null;
    if ($user['role'] === 'seller') {
        $storeId = $user['store_id'];
        if (!$storeId) json_error('No store found', 403);
    }

    $code = strtoupper(trim($data['code']));
    $chk  = $db->prepare('SELECT id FROM coupons WHERE code = ?');
    $chk->execute([$code]);
    if ($chk->fetch()) json_error('Coupon code already exists', 409);

    $stmt = $db->prepare(
        'INSERT INTO coupons
         (store_id, code, discount_type, discount_value,
          min_order, max_discount, expiry_date, usage_limit, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
    );
    $stmt->execute([
        $storeId,
        $code,
        $data['discount_type'],
        (float) $data['discount_value'],
        (float) ($data['min_order']    ?? 0),
        isset($data['max_discount'])  ? (float) $data['max_discount'] : null,
        $data['expiry_date']  ?? null,
        isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
    ]);
    json_ok(['message' => 'Coupon created', 'id' => (int) $db->lastInsertId()], 201);
}

// ─── PUT — update coupon ──────────────────────────────────
if ($method === 'PUT') {
    $user = role_required(['seller', 'admin']);
    if (!$id) json_error('id required', 422);

    $data   = get_body();
    $fields = [];
    $params = [];
    foreach (['discount_type','discount_value','min_order','max_discount',
              'expiry_date','usage_limit','is_active'] as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "$f = ?";
            $params[] = $data[$f];
        }
    }
    if (!$fields) json_error('Nothing to update', 422);
    $params[] = $id;
    $db->prepare('UPDATE coupons SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    json_ok(['message' => 'Coupon updated']);
}

// ─── DELETE ───────────────────────────────────────────────
if ($method === 'DELETE') {
    $user = role_required(['seller', 'admin']);
    if (!$id) json_error('id required', 422);
    $db->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
    json_ok(['message' => 'Coupon deleted']);
}

json_error('Method not allowed', 405);
