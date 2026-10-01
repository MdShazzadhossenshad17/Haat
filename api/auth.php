<?php
// =========================================================
// HAAT! API — Authentication
// POST /api/auth.php?action=login
// POST /api/auth.php?action=register
// POST /api/auth.php?action=logout
// GET  /api/auth.php?action=me
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$action = query('action', '');
$db     = getDB();

switch ($action) {

    // -----------------------------------------------------
    // POST /api/auth.php?action=register
    // Body: { name, email, password, phone?, role? }
    // -----------------------------------------------------
    case 'register':
        method('POST');
        $data = require_body('name', 'email', 'password');

        $name     = trim($data['name']);
        $email    = strtolower(trim($data['email']));
        $password = $data['password'];
        $phone    = trim($data['phone'] ?? '');
        $role     = in_array($data['role'] ?? '', ['customer', 'seller', 'logistics'])
                    ? $data['role']
                    : 'customer';

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Invalid email address', 422);
        }
        if (strlen($password) < 6) {
            json_error('Password must be at least 6 characters', 422);
        }

        // Check duplicate
        $chk = $db->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            json_error('Email already registered', 409);
        }

        // Insert user
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins  = $db->prepare(
            'INSERT INTO users (name, email, password, phone, role)
             VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute([$name, $email, $hash, $phone, $role]);
        $userId = (int) $db->lastInsertId();

        // Set session
        $_SESSION['user_id']   = $userId;
        $_SESSION['user_role'] = $role;
        $_SESSION['user_name'] = $name;
        $_SESSION['store_id']  = null;

        json_ok([
            'message' => 'Registration successful',
            'user'    => [
                'id'    => $userId,
                'name'  => $name,
                'email' => $email,
                'role'  => $role,
            ],
        ], 201);

    // -----------------------------------------------------
    // POST /api/auth.php?action=login
    // Body: { email, password }
    // -----------------------------------------------------
    case 'login':
        method('POST');
        $data  = require_body('email', 'password');
        $email = strtolower(trim($data['email']));

        $stmt = $db->prepare(
            'SELECT id, name, email, password, role, status FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            json_error('Invalid email or password', 401);
        }
        if ($user['status'] !== 'active') {
            json_error('Account is ' . $user['status'], 403);
        }

        // Get store_id if seller
        $storeId = null;
        if ($user['role'] === 'seller') {
            $sStmt = $db->prepare('SELECT id FROM stores WHERE user_id = ?');
            $sStmt->execute([$user['id']]);
            $store   = $sStmt->fetch();
            $storeId = $store ? (int) $store['id'] : null;
        }

        // Set session
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['store_id']  = $storeId;

        json_ok([
            'message' => 'Login successful',
            'user'    => [
                'id'       => (int) $user['id'],
                'name'     => $user['name'],
                'email'    => $user['email'],
                'role'     => $user['role'],
                'store_id' => $storeId,
            ],
        ]);

    // -----------------------------------------------------
    // POST /api/auth.php?action=logout
    // -----------------------------------------------------
    case 'logout':
        method('POST');
        session_unset();
        session_destroy();
        json_ok(['message' => 'Logged out successfully']);

    // -----------------------------------------------------
    // GET /api/auth.php?action=me
    // Returns current session user details
    // -----------------------------------------------------
    case 'me':
        method('GET');
        $user = auth_required();

        $stmt = $db->prepare(
            'SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ?'
        );
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if (!$row) json_error('User not found', 404);

        $row['id'] = (int) $row['id'];
        $row['store_id'] = $user['store_id'];

        json_ok($row);

    // -----------------------------------------------------
    // GET /api/auth.php?action=check
    // Returns { logged_in: bool, user: {...}|null }
    // -----------------------------------------------------
    case 'check':
        method('GET');
        $user = current_user();
        if ($user) {
            $stmt = $db->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            json_ok(['logged_in' => true, 'user' => $row]);
        }
        json_ok(['logged_in' => false, 'user' => null]);

    default:
        json_error('Unknown action. Use: login, register, logout, me, check', 400);
}
