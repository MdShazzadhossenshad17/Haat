<?php
// =========================================================
// HAAT! API — Categories & Subcategories
// GET /api/categories.php              list all (with subcategories)
// GET /api/categories.php?id=:id       single category
// POST /api/categories.php             create (admin)
// POST /api/categories.php?action=sub  create subcategory (admin)
// PUT  /api/categories.php?id=:id      update category (admin)
// DELETE /api/categories.php?id=:id    delete category (admin)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = (int) query('id', 0);
$action = query('action', '');

// ─────────────────────────────────────────────────────────
// GET — categories (public)
// ─────────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $cat = $stmt->fetch();
        if (!$cat) json_error('Category not found', 404);

        $sub = $db->prepare('SELECT id, name, slug, description FROM subcategories WHERE category_id = ? ORDER BY name');
        $sub->execute([$id]);
        $cat['subcategories'] = $sub->fetchAll();
        json_ok($cat);
    }

    // List all with subcategories nested
    $cats = $db->query('SELECT id, name, slug, description FROM categories ORDER BY name')->fetchAll();
    $subs = $db->query('SELECT id, category_id, name, slug FROM subcategories ORDER BY name')->fetchAll();

    // Group subcategories by category_id
    $subMap = [];
    foreach ($subs as $s) {
        $subMap[$s['category_id']][] = $s;
    }
    foreach ($cats as &$c) {
        $c['subcategories'] = $subMap[$c['id']] ?? [];
    }

    json_ok($cats);
}

// ─────────────────────────────────────────────────────────
// POST — create category or subcategory (admin only)
// ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    role_required('admin');

    if ($action === 'sub') {
        // Create subcategory
        $data = require_body('category_id', 'name');
        $slug = make_slug($data['name']);

        $stmt = $db->prepare(
            'INSERT INTO subcategories (category_id, name, slug, description) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([(int) $data['category_id'], $data['name'], $slug, $data['description'] ?? null]);
        json_ok(['message' => 'Subcategory created', 'id' => (int) $db->lastInsertId()], 201);
    }

    // Create category
    $data = require_body('name');
    $slug = make_slug($data['name']);

    $stmt = $db->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)');
    $stmt->execute([$data['name'], $slug, $data['description'] ?? null]);
    json_ok(['message' => 'Category created', 'id' => (int) $db->lastInsertId()], 201);
}

// ─────────────────────────────────────────────────────────
// PUT — update category (admin only)
// ─────────────────────────────────────────────────────────
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
    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $params[] = $data['description'];
    }
    if (!$fields) json_error('Nothing to update', 422);
    $params[] = $id;
    $db->prepare('UPDATE categories SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    json_ok(['message' => 'Category updated']);
}

// ─────────────────────────────────────────────────────────
// DELETE (admin only)
// ─────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    role_required('admin');
    if (!$id) json_error('id required', 422);
    $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    json_ok(['message' => 'Category deleted']);
}

json_error('Method not allowed', 405);
