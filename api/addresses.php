<?php
// =========================================================
// HAAT! API — Customer Addresses
// GET    /api/addresses.php          list own addresses
// POST   /api/addresses.php          add address
// PUT    /api/addresses.php?id=:id   update address
// DELETE /api/addresses.php?id=:id   delete address
// PUT    /api/addresses.php?action=set_default&id=:id
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = auth_required();
$id     = (int) query('id', 0);
$action = query('action', '');

// ─────────────────────────────────────────────────────────
// GET — list addresses
// ─────────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT id, label, name, phone, address, district, division,
                postal_code, latitude, longitude, is_default
         FROM customer_addresses
         WHERE user_id = ?
         ORDER BY is_default DESC, id DESC'
    );
    $stmt->execute([$user['id']]);
    json_ok($stmt->fetchAll());
}

// ─────────────────────────────────────────────────────────
// POST — add address
// ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $data = require_body('name', 'phone', 'address', 'district', 'division');

    // If first address, make it default
    $cntStmt = $db->prepare('SELECT COUNT(*) FROM customer_addresses WHERE user_id = ?');
    $cntStmt->execute([$user['id']]);
    $isDefault = ($cntStmt->fetchColumn() == 0) ? 1 : (int) ($data['is_default'] ?? 0);

    // If setting as default, unset others
    if ($isDefault) {
        $db->prepare('UPDATE customer_addresses SET is_default = 0 WHERE user_id = ?')
           ->execute([$user['id']]);
    }

    $stmt = $db->prepare(
        'INSERT INTO customer_addresses
         (user_id, label, name, phone, address, district, division, postal_code,
          latitude, longitude, is_default)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'],
        $data['label']       ?? 'Home',
        $data['name'],
        $data['phone'],
        $data['address'],
        $data['district'],
        $data['division'],
        $data['postal_code'] ?? null,
        $data['latitude']    ?? null,
        $data['longitude']   ?? null,
        $isDefault,
    ]);

    json_ok(['message' => 'Address added', 'id' => (int) $db->lastInsertId()], 201);
}

// ─────────────────────────────────────────────────────────
// PUT — update address or set as default
// ─────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) json_error('Address id required', 422);

    // Ownership check
    $own = $db->prepare('SELECT id FROM customer_addresses WHERE id = ? AND user_id = ?');
    $own->execute([$id, $user['id']]);
    if (!$own->fetch()) json_error('Address not found', 404);

    if ($action === 'set_default') {
        $db->prepare('UPDATE customer_addresses SET is_default = 0 WHERE user_id = ?')
           ->execute([$user['id']]);
        $db->prepare('UPDATE customer_addresses SET is_default = 1 WHERE id = ?')
           ->execute([$id]);
        json_ok(['message' => 'Default address updated']);
    }

    $data   = get_body();
    $fields = [];
    $params = [];

    foreach (['label','name','phone','address','district','division','postal_code','latitude','longitude'] as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $params[] = $data[$f];
        }
    }
    if (!$fields) json_error('No fields to update', 422);
    $params[] = $id;
    $db->prepare('UPDATE customer_addresses SET ' . implode(', ', $fields) . ' WHERE id = ?')
       ->execute($params);

    json_ok(['message' => 'Address updated']);
}

// ─────────────────────────────────────────────────────────
// DELETE — remove address
// ─────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) json_error('Address id required', 422);
    $stmt = $db->prepare('DELETE FROM customer_addresses WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    if ($stmt->rowCount() === 0) json_error('Address not found', 404);
    json_ok(['message' => 'Address deleted']);
}

json_error('Method not allowed', 405);
