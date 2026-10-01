<?php
// =========================================================
// HAAT! API — Riders
// GET /api/riders.php                list active riders (admin/seller)
// GET /api/riders.php?action=mine    get own rider profile (logistics)
// PUT /api/riders.php?action=status  update rider status (logistics)
// POST /api/riders.php               create rider profile (admin)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = auth_required();
$action = query('action', '');

if ($method === 'GET') {

    if ($action === 'mine') {
        role_required('logistics');
        $stmt = $db->prepare(
            'SELECT r.id, r.status, u.name, u.email, u.phone
             FROM riders r JOIN users u ON u.id = r.user_id
             WHERE r.user_id = ?'
        );
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if (!$row) json_error('Rider profile not found', 404);
        json_ok($row);
    }

    role_required(['admin', 'seller']);
    $status = query('status', '');
    $where  = $status ? 'WHERE r.status = ?' : '';
    $params = $status ? [$status] : [];

    $stmt = $db->prepare(
        "SELECT r.id, r.status, u.name, u.phone
         FROM riders r
         JOIN users u ON u.id = r.user_id
         $where
         ORDER BY u.name"
    );
    $stmt->execute($params);
    json_ok($stmt->fetchAll());
}

if ($method === 'POST') {
    role_required('admin');
    $data = require_body('user_id');
    $uid  = (int) $data['user_id'];

    // Ensure user has logistics role
    $db->prepare("UPDATE users SET role = 'logistics' WHERE id = ?")->execute([$uid]);

    $chk = $db->prepare('SELECT id FROM riders WHERE user_id = ?');
    $chk->execute([$uid]);
    if ($chk->fetch()) json_error('Rider profile already exists', 409);

    $db->prepare('INSERT INTO riders (user_id) VALUES (?)')->execute([$uid]);
    json_ok(['message' => 'Rider created', 'id' => (int) $db->lastInsertId()], 201);
}

if ($method === 'PUT' && $action === 'status') {
    role_required('logistics');
    $data   = require_body('status');
    $allowed = ['active', 'offline', 'busy'];
    if (!in_array($data['status'], $allowed)) json_error('Invalid status', 422);

    $db->prepare('UPDATE riders SET status = ? WHERE user_id = ?')->execute([$data['status'], $user['id']]);
    json_ok(['message' => 'Status updated']);
}

json_error('Method not allowed', 405);
