<?php
// =========================================================
// HAAT! API — Users
// GET  /api/users.php           (admin: list all users)
// GET  /api/users.php?id=:id    (get single user)
// PUT  /api/users.php           (update own profile)
// PUT  /api/users.php?id=:id    (admin: update any user)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = auth_required();
$id     = (int) query('id', 0);

// ─────────────────────────────────────────────────────────
// GET — list users (admin) or get single user
// ─────────────────────────────────────────────────────────
if ($method === 'GET') {

    if ($id) {
        // Get single user — admin can see any, others only self
        if ($user['role'] !== 'admin' && $user['id'] !== $id) {
            json_error('Forbidden', 403);
        }
        $stmt = $db->prepare(
            'SELECT id, name, email, phone, role, status, created_at, updated_at
             FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_error('User not found', 404);
        $row['id'] = (int) $row['id'];
        json_ok($row);
    }

    // List all users — admin only
    role_required('admin');
    $p    = paginate(30);
    $role = query('role', '');
    $status = query('status', '');
    $search = query('search', '');

    $where  = [];
    $params = [];
    if ($role) {
        $where[]  = 'role = ?';
        $params[] = $role;
    }
    if ($status) {
        $where[]  = 'status = ?';
        $params[] = $status;
    }
    if ($search) {
        $where[]  = '(name LIKE ? OR email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt     = $db->prepare(
        "SELECT id, name, email, phone, role, status, created_at
         FROM users $whereStr ORDER BY created_at DESC
         LIMIT {$p['limit']} OFFSET {$p['offset']}"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Count
    $cStmt = $db->prepare("SELECT COUNT(*) FROM users $whereStr");
    $cStmt->execute($params);
    $total = (int) $cStmt->fetchColumn();

    json_ok(['users' => $rows, 'total' => $total, 'page' => $p['page']]);
}

// ─────────────────────────────────────────────────────────
// PUT — update profile
// ─────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $targetId = $id ?: $user['id'];

    // Non-admin can only update themselves
    if ($user['role'] !== 'admin' && $user['id'] !== $targetId) {
        json_error('Forbidden', 403);
    }

    $data   = get_body();
    $fields = [];
    $params = [];

    if (isset($data['name']) && trim($data['name'])) {
        $fields[] = 'name = ?';
        $params[] = trim($data['name']);
    }
    if (isset($data['phone'])) {
        $fields[] = 'phone = ?';
        $params[] = trim($data['phone']);
    }
    if (isset($data['password']) && strlen($data['password']) >= 6) {
        $fields[] = 'password = ?';
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    // Admin can change role/status
    if ($user['role'] === 'admin') {
        if (isset($data['role'])) {
            $fields[] = 'role = ?';
            $params[] = $data['role'];
        }
        if (isset($data['status'])) {
            $fields[] = 'status = ?';
            $params[] = $data['status'];
        }
    }

    if (!$fields) json_error('No valid fields to update', 422);

    $params[] = $targetId;
    $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')
       ->execute($params);

    // Refresh session name if self-update
    if ($targetId === $user['id'] && isset($data['name'])) {
        $_SESSION['user_name'] = trim($data['name']);
    }

    json_ok(['message' => 'Profile updated']);
}

json_error('Method not allowed', 405);
