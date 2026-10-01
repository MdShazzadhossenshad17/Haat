<?php
// =========================================================
// HAAT! API — Brands
// GET  /api/brands.php        list all brands
// GET  /api/brands.php?id=:id single brand
// POST /api/brands.php        create brand (admin, with logo upload)
// PUT  /api/brands.php?id=:id update brand (admin)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = (int) query('id', 0);

// ─── GET (public) ─────────────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare('SELECT id, name, slug FROM brands WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_error('Brand not found', 404);
        // Logo served via /api/images.php?type=brand_logo&id=X
        $row['logo_url'] = "/HAAT!/api/images.php?type=brand_logo&id={$id}";
        json_ok($row);
    }

    $rows = $db->query('SELECT id, name, slug FROM brands ORDER BY name')->fetchAll();
    foreach ($rows as &$r) {
        $r['logo_url'] = "/HAAT!/api/images.php?type=brand_logo&id={$r['id']}";
    }
    json_ok($rows);
}

// ─── POST — create brand (admin) ──────────────────────────
if ($method === 'POST') {
    role_required('admin');
    $data = require_body('name');
    $slug = make_slug($data['name']);

    $logo     = null;
    $logoMime = null;
    if (!empty($_FILES['logo'])) {
        $logoMime = $_FILES['logo']['type'];
        $logo     = file_get_contents($_FILES['logo']['tmp_name']);
    }

    $stmt = $db->prepare('INSERT INTO brands (name, slug, logo, logo_mime_type) VALUES (?, ?, ?, ?)');
    $stmt->execute([$data['name'], $slug, $logo, $logoMime]);
    json_ok(['message' => 'Brand created', 'id' => (int) $db->lastInsertId()], 201);
}

// ─── PUT — update brand (admin) ───────────────────────────
if ($method === 'PUT') {
    role_required('admin');
    if (!$id) json_error('id required', 422);

    $data   = get_body();
    $fields = [];
    $params = [];
    if (isset($data['name'])) {
        $fields[] = 'name = ?';
        $params[] = $data['name'];
        $fields[] = 'slug = ?';
        $params[] = make_slug($data['name']);
    }
    if (!$fields) json_error('Nothing to update', 422);
    $params[] = $id;
    $db->prepare('UPDATE brands SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    json_ok(['message' => 'Brand updated']);
}

json_error('Method not allowed', 405);
