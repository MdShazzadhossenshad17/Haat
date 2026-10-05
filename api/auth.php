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
    // Body: { name, email, password, phone?, role?, store_name?, address?, district?, division? }
    // -----------------------------------------------------
    case 'register':
        method('POST');
        $data = require_body('name', 'email', 'password');

        $name     = trim($data['name']);
        $email    = strtolower(trim($data['email']));
        $password = $data['password'];
        $phone    = trim($data['phone'] ?? '');
        $rawRole  = strtolower(trim($data['role'] ?? 'customer'));

        // Admin & HATEX corporate accounts are fixed — no one can register these roles
        if (in_array($rawRole, ['admin', 'hatex', 'logistics'])) {
            json_error('Admin and HATEX corporate accounts are fixed and cannot be registered publicly.', 403);
        }

        // Map roles to valid MySQL users.role ENUM ('customer','seller','admin','logistics')
        $dbRole = 'customer';
        if ($rawRole === 'seller') $dbRole = 'seller';
        elseif ($rawRole === 'rider') $dbRole = 'logistics';
        else $dbRole = 'customer';

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Invalid email address', 422);
        }
        if (strlen($password) < 6) {
            json_error('Password must be at least 6 characters', 422);
        }

        // Check duplicate email
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
        $ins->execute([$name, $email, $hash, $phone, $dbRole]);
        $userId = (int) $db->lastInsertId();

        $storeData = null;
        $riderData = null;

        // If seller, create store immediately
        if ($dbRole === 'seller') {
            $storeName = trim($data['store_name'] ?? '');
            if (!$storeName) $storeName = $name . "'s Store";
            $storeSlug = make_slug($storeName);
            $slugChk = $db->prepare('SELECT id FROM stores WHERE store_slug = ?');
            $slugChk->execute([$storeSlug]);
            if ($slugChk->fetch()) $storeSlug .= '-' . $userId;

            $address     = trim($data['address'] ?? 'Shop 8, New Market');
            $district    = trim($data['district'] ?? 'Dhaka');
            $division    = trim($data['division'] ?? 'Dhaka');
            $description = trim($data['description'] ?? ("Official HAAT merchant store: " . $storeName));

            $sIns = $db->prepare(
                'INSERT INTO stores (user_id, store_name, store_slug, description, address, district, division, status, is_published)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $sIns->execute([$userId, $storeName, $storeSlug, $description, $address, $district, $division, 'approved', 1]);
            $storeId = (int) $db->lastInsertId();

            $storeData = [
                'id'          => $storeId,
                'user_id'     => $userId,
                'store_name'  => $storeName,
                'store_slug'  => $storeSlug,
                'description' => $description,
                'address'     => $address,
                'district'    => $district,
                'division'    => $division,
                'status'      => 'approved',
                'is_published'=> 1
            ];
            $_SESSION['store_id'] = $storeId;
        } else {
            $_SESSION['store_id'] = null;
        }

        // If rider, create rider entry
        if ($rawRole === 'rider' || $dbRole === 'logistics') {
            $rIns = $db->prepare('INSERT INTO riders (user_id, status) VALUES (?, ?)');
            $rIns->execute([$userId, 'active']);
            $riderId = (int) $db->lastInsertId();
            $riderData = [
                'id'      => $riderId,
                'user_id' => $userId,
                'name'    => $name,
                'phone'   => $phone,
                'status'  => 'active'
            ];
        }

        // If customer has address, create address
        if ($dbRole === 'customer' && !empty($data['address'])) {
            $aIns = $db->prepare(
                'INSERT INTO customer_addresses (user_id, label, name, phone, address, district, division, is_default)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $aIns->execute([
                $userId,
                'Home',
                $name,
                $phone,
                trim($data['address']),
                trim($data['district'] ?? 'Dhaka'),
                trim($data['division'] ?? 'Dhaka'),
                1
            ]);
        }

        // Set session
        $_SESSION['user_id']   = $userId;
        $_SESSION['user_role'] = ($rawRole === 'rider' ? 'rider' : $dbRole);
        $_SESSION['user_name'] = $name;

        json_ok([
            'message' => 'Registration successful',
            'user'    => [
                'id'       => $userId,
                'name'     => $name,
                'email'    => $email,
                'phone'    => $phone,
                'role'     => ($rawRole === 'rider' ? 'rider' : $dbRole),
                'store_id' => $storeData ? $storeData['id'] : null,
            ],
            'store'   => $storeData,
            'rider'   => $riderData
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
            'SELECT id, name, email, password, phone, role, status FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            json_error('Invalid email or password', 401);
        }
        if ($user['status'] !== 'active') {
            json_error('Account is ' . $user['status'], 403);
        }

        // Fetch store if seller
        $storeId   = null;
        $storeData = null;
        if ($user['role'] === 'seller') {
            $sStmt = $db->prepare('SELECT id, user_id, store_name, store_slug, description, address, district, division, status, is_published FROM stores WHERE user_id = ?');
            $sStmt->execute([$user['id']]);
            $store = $sStmt->fetch();
            if ($store) {
                $storeId   = (int) $store['id'];
                $storeData = $store;
            }
        }

        // Fetch rider if logistics
        $riderData = null;
        if ($user['role'] === 'logistics') {
            $rStmt = $db->prepare('SELECT id, user_id, status FROM riders WHERE user_id = ?');
            $rStmt->execute([$user['id']]);
            $rRow = $rStmt->fetch();
            if ($rRow) {
                $riderData = [
                    'id'      => (int) $rRow['id'],
                    'user_id' => (int) $user['id'],
                    'name'    => $user['name'],
                    'phone'   => $user['phone'] ?? '',
                    'status'  => $rRow['status']
                ];
            }
        }

        // Set session
        $effectiveRole = ($user['role'] === 'logistics' && $riderData) ? 'rider' : $user['role'];
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['user_role'] = $effectiveRole;
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['store_id']  = $storeId;

        json_ok([
            'message' => 'Login successful',
            'user'    => [
                'id'       => (int) $user['id'],
                'name'     => $user['name'],
                'email'    => $user['email'],
                'phone'    => $user['phone'] ?? '',
                'role'     => $effectiveRole,
                'store_id' => $storeId,
            ],
            'store'   => $storeData,
            'rider'   => $riderData
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
    // Returns { logged_in: bool, user: {...}|null, store: {...}|null, rider: {...}|null }
    // -----------------------------------------------------
    case 'check':
        method('GET');
        $user = current_user();
        if ($user) {
            $stmt = $db->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            if ($row) {
                $storeData = null;
                if ($row['role'] === 'seller') {
                    $s = $db->prepare('SELECT id, user_id, store_name, store_slug, description, address, district, division, status, is_published FROM stores WHERE user_id = ?');
                    $s->execute([$row['id']]);
                    $storeData = $s->fetch() ?: null;
                }
                $riderData = null;
                if ($row['role'] === 'logistics') {
                    $r = $db->prepare('SELECT id, user_id, status FROM riders WHERE user_id = ?');
                    $r->execute([$row['id']]);
                    $rRow = $r->fetch();
                    if ($rRow) {
                        $riderData = [
                            'id'      => (int) $rRow['id'],
                            'user_id' => (int) $row['id'],
                            'name'    => $row['name'],
                            'phone'   => $row['phone'] ?? '',
                            'status'  => $rRow['status']
                        ];
                    }
                }
                $effectiveRole = ($row['role'] === 'logistics' && $riderData) ? 'rider' : $row['role'];
                json_ok([
                    'logged_in' => true,
                    'user'      => [
                        'id'       => (int) $row['id'],
                        'name'     => $row['name'],
                        'email'    => $row['email'],
                        'phone'    => $row['phone'] ?? '',
                        'role'     => $effectiveRole,
                        'store_id' => $storeData ? (int) $storeData['id'] : null,
                    ],
                    'store'     => $storeData,
                    'rider'     => $riderData
                ]);
            }
        }
        json_ok(['logged_in' => false, 'user' => null]);

    default:
        json_error('Unknown action. Use: login, register, logout, me, check', 400);
}
