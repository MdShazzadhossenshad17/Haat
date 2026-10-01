<?php
// =========================================================
// HAAT! API — Collections (seller product groups)
// GET    /api/collections.php?store_id=:id   list for a store (public)
// GET    /api/collections.php?action=mine     seller's own (auth)
// POST   /api/collections.php                 create (seller)
// PUT    /api/collections.php?id=:id          update (seller)
// DELETE /api/collections.php?id=:id          delete (seller)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db       = getDB();
$method   = $_SERVER['REQUEST_METHOD'];
$id       = (int) query('id', 0);
$storeId  = (int) query('store_id', 0);
$action   = query('action', '');

// ─── GET ─────────────────────────────────────────────────
if ($method === 'GET') {
    if ($action === 'mine') {
        $user = role_required('seller');
        $sid  = $user['store_id'];
        if (!$sid) json_error('No store found', 404);
        $stmt = $db->prepare(
            'SELECT id, name, slug, description, created_at FROM collections WHERE store_id = ? ORDER BY name'
        );
        $stmt->execute([$sid]);
        json_ok($stmt->fetchAll());
    }

    if ($storeId) {
        $stmt = $db->prepare(
            'SELECT id, name, slug, description FROM collections WHERE store_id = ? ORDER BY name'
        );
        $stmt->execute([$storeId]);
        json_ok($stmt->fetchAll());
    }

    json_error('Provide store_id or action=mine', 400);
}

// ─── POST — create collection (seller) ───────────────────
if ($method === 'POST') {
    $user = role_required('seller');
    $sid  = $user['store_id'];
    if (!$sid) json_error('No store found', 404);

    $data = require_body('name');
    $slug = make_slug($data['name']);

    $stmt = $db->prepare(
        'INSERT INTO collections (store_id, name, slug, description) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$sid, $data['name'], $slug, $data['description'] ?? null]);
    json_ok(['message' => 'Collection created', 'id' => (int) $db->lastInsertId()], 201);
}

// ─── PUT — update collection (seller) ────────────────────
if ($method === 'PUT') {
    $user = role_required('seller');
    $sid  = $user['store_id'];
    if (!$id) json_error('id required', 422);

    $own = $db->prepare('SELECT id FROM collections WHERE id = ? AND store_id = ?');
    $own->execute([$id, $sid]);
    if (!$own->fetch()) json_error('Collection not found', 404);

    $data   = get_body();
    $fields = [];
    $params = [];
    if (isset($data['name'])) {
        $fields[] = 'name = ?';
        $params[] = $data['name'];
        $fields[] = 'slug = ?';
        $params[] = make_slug($data['name']);
    }
    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $params[] = $data['description'];
    }
    if (!$fields) json_error('Nothing to update', 422);
    $params[] = $id;
    $db->prepare('UPDATE collections SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    json_ok(['message' => 'Collection updated']);
}

// ─── DELETE (seller) ─────────────────────────────────────
if ($method === 'DELETE') {
    $user = role_required('seller');
    $sid  = $user['store_id'];
    if (!$id) json_error('id required', 422);

    $stmt = $db->prepare('DELETE FROM collections WHERE id = ? AND store_id = ?');
    $stmt->execute([$id, $sid]);
    if ($stmt->rowCount() === 0) json_error('Collection not found', 404);
    json_ok(['message' => 'Collection deleted']);
}

json_error('Method not allowed', 405);
