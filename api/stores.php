<?php
// =========================================================
// HAAT! API — Stores
// GET  /api/stores.php               list approved stores (public)
// GET  /api/stores.php?slug=:slug    get by slug (public)
// GET  /api/stores.php?id=:id        get by id (public)
// GET  /api/stores.php?action=mine   get own store (seller)
// GET  /api/stores.php?action=all    list all stores (admin)
// POST /api/stores.php               create store (seller)
// PUT  /api/stores.php               update own store (seller)
// PUT  /api/stores.php?action=approve&id=:id  (admin)
// PUT  /api/stores.php?action=reject&id=:id   (admin)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$slug   = query('slug', '');
$id     = (int) query('id', 0);
$action = query('action', '');

// ─────────────────────────────────────────────────────────
// GET — stores (public or auth)
// ─────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Admin: list all stores
    if ($action === 'all') {
        role_required('admin');
        $p      = paginate(20);
        $status = query('status', '');
        $where  = $status ? 'WHERE s.status = ?' : '';
        $params = $status ? [$status] : [];

        $stmt = $db->prepare(
            "SELECT s.id, s.store_name, s.store_slug, s.district, s.division,
                    s.status, s.is_published, s.created_at,
                    u.name AS owner_name, u.email AS owner_email
             FROM stores s
             JOIN users u ON u.id = s.user_id
             $where
             ORDER BY s.created_at DESC
             LIMIT {$p['limit']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        json_ok(['stores' => $stmt->fetchAll(), 'page' => $p['page']]);
    }

    // Seller: get own store
    if ($action === 'mine') {
        $user = role_required('seller');
        $stmt = $db->prepare(
            'SELECT id, store_name, store_slug, description, address, district,
                    division, postal_code, latitude, longitude, status, is_published,
                    created_at, updated_at
             FROM stores WHERE user_id = ?'
        );
        $stmt->execute([$user['id']]);
        $store = $stmt->fetch();
        if (!$store) json_error('Store not found — create one first', 404);
        $store = _add_store_urls($store);
        json_ok($store);
    }

    // Get by slug (public)
    if ($slug) {
        $stmt = $db->prepare(
            "SELECT s.id, s.store_name, s.store_slug, s.description,
                    s.address, s.district, s.division, s.status, s.is_published,
                    s.latitude, s.longitude, s.created_at,
                    u.name AS owner_name
             FROM stores s
             JOIN users u ON u.id = s.user_id
             WHERE s.store_slug = ? AND s.status = 'approved'"
        );
        $stmt->execute([$slug]);
        $store = $stmt->fetch();
        if (!$store) json_error('Store not found', 404);
        $store = _add_store_urls($store);
        json_ok($store);
    }

    // Get by id (public)
    if ($id) {
        $stmt = $db->prepare(
            "SELECT id, store_name, store_slug, description,
                    address, district, division, status, latitude, longitude
             FROM stores WHERE id = ? AND status = 'approved'"
        );
        $stmt->execute([$id]);
        $store = $stmt->fetch();
        if (!$store) json_error('Store not found', 404);
        json_ok(_add_store_urls($store));
    }

    // List approved stores (public), optional geo sort
    $p      = paginate(20);
    $div    = query('division', '');
    $dist   = query('district', '');
    $search = query('search', '');
    $lat    = (float) query('lat', 0);
    $lon    = (float) query('lon', 0);

    $where  = ["s.status = 'approved'", "s.is_published = 1"];
    $params = [];
    if ($div)    { $where[] = 's.division = ?';            $params[] = $div; }
    if ($dist)   { $where[] = 's.district = ?';            $params[] = $dist; }
    if ($search) { $where[] = 's.store_name LIKE ?';       $params[] = "%$search%"; }

    $distCol  = ($lat && $lon)
        ? ", haversine_distance($lat, $lon, s.latitude, s.longitude) AS distance_km"
        : '';
    $orderBy  = ($lat && $lon) ? 'ORDER BY distance_km ASC' : 'ORDER BY s.created_at DESC';
    $whereStr = 'WHERE ' . implode(' AND ', $where);

    $stmt = $db->prepare(
        "SELECT s.id, s.user_id, s.store_name, s.store_slug, s.description,
                s.address, s.district, s.division, s.status, s.is_published,
                s.latitude, s.longitude $distCol
         FROM stores s
         $whereStr
         $orderBy
         LIMIT {$p['limit']} OFFSET {$p['offset']}"
    );
    $stmt->execute($params);
    $stores = $stmt->fetchAll();
    foreach ($stores as &$s) {
        $s = _add_store_urls($s);
    }
    json_ok(['stores' => $stores, 'page' => $p['page']]);
}

// ─────────────────────────────────────────────────────────
// POST — create store (seller only, one store per seller)
// Accepts multipart/form-data for logo/banner uploads
// ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $user = role_required('seller');

    // Check already has store
    $chk = $db->prepare('SELECT id FROM stores WHERE user_id = ?');
    $chk->execute([$user['id']]);
    if ($chk->fetch()) json_error('You already have a store', 409);

    $name = trim($_POST['store_name'] ?? body('store_name', ''));
    if (!$name) json_error('store_name is required', 422);

    $slug = make_slug($name);

    // Check slug unique
    $slugChk = $db->prepare('SELECT id FROM stores WHERE store_slug = ?');
    $slugChk->execute([$slug]);
    if ($slugChk->fetch()) $slug .= '-' . $user['id'];

    $logo = $logMime = $banner = $banMime = null;
    if (!empty($_FILES['logo'])) {
        $logo    = file_get_contents($_FILES['logo']['tmp_name']);
        $logMime = $_FILES['logo']['type'];
    }
    if (!empty($_FILES['banner'])) {
        $banner  = file_get_contents($_FILES['banner']['tmp_name']);
        $banMime = $_FILES['banner']['type'];
    }

    $body = get_body();
    $stmt = $db->prepare(
        'INSERT INTO stores (user_id, store_name, store_slug, description,
          logo, logo_mime_type, banner, banner_mime_type,
          address, district, division, postal_code, latitude, longitude, status, is_published)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'], $name, $slug,
        $_POST['description'] ?? body('description'),
        $logo, $logMime, $banner, $banMime,
        $_POST['address']     ?? body('address'),
        $_POST['district']    ?? body('district'),
        $_POST['division']    ?? body('division'),
        $_POST['postal_code'] ?? body('postal_code'),
        $_POST['latitude']    ?? body('latitude'),
        $_POST['longitude']   ?? body('longitude'),
        'approved', 1
    ]);
    $newId = (int) $db->lastInsertId();
    $_SESSION['store_id'] = $newId;

    json_ok(['message' => 'Store created successfully', 'id' => $newId, 'slug' => $slug], 201);
}

// ─────────────────────────────────────────────────────────
// PUT — update store or admin approve/reject
// ─────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $user = auth_required();

    // Admin approve/reject
    if (in_array($action, ['approve', 'reject', 'suspend']) && $user['role'] === 'admin') {
        if (!$id) json_error('id required', 422);
        $statusMap = ['approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended'];
        $db->prepare('UPDATE stores SET status = ? WHERE id = ?')
           ->execute([$statusMap[$action], $id]);
        json_ok(['message' => 'Store ' . $action . 'd']);
    }

    // Seller updates own store
    role_required('seller');
    $storeId = $user['store_id'];
    if (!$storeId) json_error('No store found', 404);

    $data   = get_body();
    $fields = [];
    $params = [];
    foreach (['description','address','district','division','postal_code','latitude','longitude','is_published'] as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $params[] = $data[$f];
        }
    }
    if (isset($data['store_name'])) {
        $fields[] = 'store_name = ?';
        $params[] = $data['store_name'];
    }
    if (!$fields) json_error('Nothing to update', 422);
    $params[] = $storeId;
    $db->prepare('UPDATE stores SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    json_ok(['message' => 'Store updated']);
}

json_error('Method not allowed', 405);

// ─────────────────────────────────────────────────────────
// Helper: append image URLs to a store array
// ─────────────────────────────────────────────────────────
function _add_store_urls(array $store): array {
    $id = $store['id'];
    $base = api_base_url();
    $store['logo_url']   = "$base/images.php?type=store_logo&id=$id";
    $store['banner_url'] = "$base/images.php?type=store_banner&id=$id";
    return $store;
}
