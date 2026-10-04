<?php
// =========================================================
// HAAT! API — Shared Helper Functions
// =========================================================

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------
// Bootstrap: CORS + headers + session
// ---------------------------------------------------------

function boot(): void {
    // CORS — allow the SPA running on same origin (XAMPP)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Content-Type: application/json; charset=utf-8');

    // Pre-flight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // Session
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

// ---------------------------------------------------------
// Response helpers
// ---------------------------------------------------------

function json_ok(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------
// Auth helpers
// ---------------------------------------------------------

/**
 * Returns current session user array or sends 401.
 */
function auth_required(): array {
    $db = getDB();
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId && !empty($_SERVER['HTTP_X_USER_ID'])) {
        $userId = (int) $_SERVER['HTTP_X_USER_ID'];
    }

    if (!$userId) {
        json_error('Unauthorized — please log in', 401);
    }

    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role']) || empty($_SESSION['store_id'])) {
        $uStmt = $db->prepare('SELECT id, name, role FROM users WHERE id = ?');
        $uStmt->execute([$userId]);
        $u = $uStmt->fetch();
        if (!$u) json_error('User not found', 401);

        $_SESSION['user_id']   = (int) $u['id'];
        $_SESSION['user_role'] = $u['role'];
        $_SESSION['user_name'] = $u['name'];

        if ($u['role'] === 'seller') {
            $sStmt = $db->prepare('SELECT id FROM stores WHERE user_id = ?');
            $sStmt->execute([$userId]);
            $sid = $sStmt->fetchColumn();
            if ($sid) $_SESSION['store_id'] = (int) $sid;
        }
    }

    return [
        'id'       => (int) $_SESSION['user_id'],
        'role'     => $_SESSION['user_role'],
        'name'     => $_SESSION['user_name'],
        'store_id' => $_SESSION['store_id'] ?? null,
    ];
}

/**
 * Checks session AND required role(s).
 * @param string|array $roles
 */
function role_required(string|array $roles): array {
    $user  = auth_required();
    $roles = (array) $roles;
    if (!in_array($user['role'], $roles, true)) {
        json_error('Forbidden — insufficient role', 403);
    }
    if ($user['role'] === 'seller' && empty($user['store_id'])) {
        $sStmt = getDB()->prepare('SELECT id FROM stores WHERE user_id = ?');
        $sStmt->execute([$user['id']]);
        $sid = $sStmt->fetchColumn();
        if ($sid) {
            $_SESSION['store_id'] = (int) $sid;
            $user['store_id']     = (int) $sid;
        }
    }
    return $user;
}

/**
 * Returns current user or null (no forced 401).
 */
function current_user(): ?array {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId && !empty($_SERVER['HTTP_X_USER_ID'])) {
        $userId = (int) $_SERVER['HTTP_X_USER_ID'];
    }
    if (!$userId) return null;
    return [
        'id'       => (int) $userId,
        'role'     => $_SESSION['user_role'] ?? 'customer',
        'name'     => $_SESSION['user_name'] ?? 'User',
        'store_id' => $_SESSION['store_id'] ?? null,
    ];
}

// ---------------------------------------------------------
// Request helpers
// ---------------------------------------------------------

/**
 * Parse JSON request body into array.
 */
function get_body(): array {
    static $body = null;
    if ($body === null) {
        $raw  = file_get_contents('php://input');
        $body = $raw ? (json_decode($raw, true) ?? []) : [];
    }
    return $body;
}

/**
 * Get a value from JSON body or $_POST with optional default.
 */
function body(string $key, mixed $default = null): mixed {
    $data = get_body();
    // Also check $_POST for form submissions
    return $data[$key] ?? $_POST[$key] ?? $default;
}

/**
 * Get a query string param with optional default.
 */
function query(string $key, mixed $default = null): mixed {
    return $_GET[$key] ?? $default;
}

/**
 * Enforce HTTP method; send 405 otherwise.
 */
function method(string ...$allowed): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowed, true)) {
        json_error('Method not allowed', 405);
    }
}

// ---------------------------------------------------------
// Validation helpers
// ---------------------------------------------------------

/**
 * Require keys in body or send 422.
 */
function require_body(string ...$keys): array {
    $data    = get_body();
    $missing = [];
    foreach ($keys as $k) {
        if (!isset($data[$k]) || $data[$k] === '') {
            $missing[] = $k;
        }
    }
    if ($missing) {
        json_error('Missing required fields: ' . implode(', ', $missing), 422);
    }
    return $data;
}

/**
 * Generate a slug from a string.
 */
function make_slug(string $str): string {
    $str = mb_strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/[\s-]+/', '-', $str);
    return trim($str, '-');
}

/**
 * Generate a random order number.
 */
function generate_order_number(): string {
    return 'ORD-' . strtoupper(bin2hex(random_bytes(5)));
}

/**
 * Generate a seller order number.
 */
function generate_seller_order_number(int $storeId): string {
    return 'SO-' . $storeId . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Paginate helper — returns LIMIT and OFFSET.
 */
function paginate(int $defaultLimit = 20): array {
    $page  = max(1, (int) query('page', 1));
    $limit = max(1, min(100, (int) query('limit', $defaultLimit)));
    return [
        'limit'  => $limit,
        'offset' => ($page - 1) * $limit,
        'page'   => $page,
    ];
}
