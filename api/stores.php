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
            "SELECT s.id, s.user_id, s.store_name, s.store_slug, s.district, s.division,
                    s.status, s.is_published, s.created_at,
                    s.delivery_charge, s.free_delivery, s.auto_greeting, s.verification_status,
                    s.verification_documents, s.rejection_reason,
                    (s.logo IS NOT NULL) AS has_logo, (s.banner IS NOT NULL) AS has_banner,
                    u.name AS owner_name, u.email AS owner_email
             FROM stores s
             JOIN users u ON u.id = s.user_id
             $where
             ORDER BY s.created_at DESC
             LIMIT {$p['limit']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        $allStores = $stmt->fetchAll();
        foreach ($allStores as &$as) {
            if (!empty($as['verification_documents']) && is_string($as['verification_documents'])) {
                $as['verification_documents'] = json_decode($as['verification_documents'], true);
            }
            $as = _add_store_urls($as);
        }
        json_ok(['stores' => $allStores, 'page' => $p['page']]);
    }

    // Seller: get own store
    if ($action === 'mine') {
        $user = role_required('seller');
        $stmt = $db->prepare(
            'SELECT id, user_id, store_name, store_slug, description, address, district,
                    division, postal_code, latitude, longitude, status, is_published,
                    delivery_charge, free_delivery, auto_greeting, verification_status,
                    verification_documents, rejection_reason,
                    (logo IS NOT NULL) AS has_logo, (banner IS NOT NULL) AS has_banner,
                    created_at, updated_at
             FROM stores WHERE user_id = ?'
        );
        $stmt->execute([$user['id']]);
        $store = $stmt->fetch();
        if (!$store) json_error('Store not found — create one first', 404);
        if (!empty($store['verification_documents']) && is_string($store['verification_documents'])) {
            $store['verification_documents'] = json_decode($store['verification_documents'], true);
        }
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
                s.delivery_charge, s.free_delivery, s.auto_greeting, s.verification_status,
                s.verification_documents, s.rejection_reason,
                (s.logo IS NOT NULL) AS has_logo, (s.banner IS NOT NULL) AS has_banner,
                s.latitude, s.longitude $distCol
         FROM stores s
         $whereStr
         $orderBy
         LIMIT {$p['limit']} OFFSET {$p['offset']}"
    );
    $stmt->execute($params);
    $stores = $stmt->fetchAll();
    foreach ($stores as &$s) {
        if (!empty($s['verification_documents']) && is_string($s['verification_documents'])) {
            $s['verification_documents'] = json_decode($s['verification_documents'], true);
        }
        $s = _add_store_urls($s);
    }
    json_ok(['stores' => $stores, 'page' => $p['page']]);
}

// ─────────────────────────────────────────────────────────
// POST — create or update store (seller only)
// Accepts multipart/form-data for logo/banner uploads
// ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $user = role_required('seller');

    // ── Submit Verification Credentials ───────────────────────
    if ($action === 'submit_verification') {
        $chk = $db->prepare('SELECT id FROM stores WHERE user_id = ?');
        $chk->execute([$user['id']]);
        $storeId = (int) $chk->fetchColumn();

        if (!$storeId && !empty($_POST['store_id'])) $storeId = (int) $_POST['store_id'];
        if (!$storeId && !empty($_POST['id'])) $storeId = (int) $_POST['id'];
        $body = get_body();
        if (!$storeId && !empty($body['store_id'])) $storeId = (int) $body['store_id'];
        if (!$storeId && !empty($user['store_id'])) $storeId = (int) $user['store_id'];

        if (!$storeId) json_error('No store found to submit verification for', 404);

        $docs = $body['verification_documents'] ?? [];
        if (empty($docs) && is_array($_POST)) {
            $docs = [
                'trade_license_no'  => trim($_POST['trade_license_no'] ?? ''),
                'trade_license_doc' => trim($_POST['trade_license_doc'] ?? ''),
                'nid_no'            => trim($_POST['nid_no'] ?? ''),
                'nid_doc_front'     => trim($_POST['nid_doc_front'] ?? ''),
                'nid_doc_back'      => trim($_POST['nid_doc_back'] ?? ''),
                'tin_no'            => trim($_POST['tin_no'] ?? ''),
                'tin_doc'           => trim($_POST['tin_doc'] ?? ''),
                'bank_doc'          => trim($_POST['bank_doc'] ?? ''),
                'notes'             => trim($_POST['notes'] ?? ''),
            ];
        }
        $docsJson = json_encode($docs, JSON_UNESCAPED_UNICODE);

        $stmt = $db->prepare("UPDATE stores SET verification_status = 'pending', verification_documents = ?, rejection_reason = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$docsJson, $storeId]);

        $fStmt = $db->prepare('SELECT id, user_id, store_name, status, verification_status, verification_documents FROM stores WHERE id = ?');
        $fStmt->execute([$storeId]);
        $updated = $fStmt->fetch();
        if (!empty($updated['verification_documents']) && is_string($updated['verification_documents'])) {
            $updated['verification_documents'] = json_decode($updated['verification_documents'], true);
        }

        json_ok(['message' => 'Verification credentials submitted for admin review', 'verification_status' => 'pending', 'store' => $updated]);
    }

    $isUpdate = ($action === 'update') || (!empty($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT');

    // Check existing store
    $chk = $db->prepare('SELECT id FROM stores WHERE user_id = ?');
    $chk->execute([$user['id']]);
    $existingStoreId = (int) $chk->fetchColumn();

    // ── Update existing store ───────────────────────────────
    if ($isUpdate || ($existingStoreId > 0 && $action !== 'create')) {
        $storeId = $existingStoreId;
        if (!$storeId && !empty($_POST['store_id'])) {
            $storeId = (int) $_POST['store_id'];
        }
        if (!$storeId && !empty($_POST['id'])) {
            $storeId = (int) $_POST['id'];
        }
        if (!$storeId) json_error('No store found to update', 404);

        $fields = [];
        $params = [];

        $updatable = [
            'store_name', 'store_slug', 'description', 'address',
            'district', 'division', 'postal_code', 'latitude', 'longitude',
            'delivery_charge', 'free_delivery', 'auto_greeting', 'is_published'
        ];
        foreach ($updatable as $col) {
            if (isset($_POST[$col])) {
                $fields[] = "$col = ?";
                $val = $_POST[$col];
                if ($col === 'free_delivery' || $col === 'is_published') $val = (int) $val;
                elseif ($col === 'delivery_charge') $val = (float) $val;
                $params[] = $val;
            }
        }

        // Checkbox: If delivery_charge is passed but free_delivery was unchecked by user
        if (isset($_POST['delivery_charge']) && !isset($_POST['free_delivery'])) {
            $fields[] = "free_delivery = ?";
            $params[] = 0;
        }

        // Store Logo File (Multipart)
        if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $logoBytes = file_get_contents($_FILES['logo']['tmp_name']);
            $logoMime  = $_FILES['logo']['type'] ?: 'image/jpeg';
            $fields[]  = 'logo = ?';
            $params[]  = $logoBytes;
            $fields[]  = 'logo_mime_type = ?';
            $params[]  = $logoMime;
        } elseif (!empty($_POST['logo_base64'])) {
            $raw = $_POST['logo_base64'];
            if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/', $raw, $m)) {
                $fields[] = 'logo = ?';
                $params[] = base64_decode($m[2]);
                $fields[] = 'logo_mime_type = ?';
                $params[] = $m[1];
            }
        }

        // Store Banner File (Multipart)
        if (!empty($_FILES['banner']['tmp_name']) && is_uploaded_file($_FILES['banner']['tmp_name'])) {
            $bannerBytes = file_get_contents($_FILES['banner']['tmp_name']);
            $bannerMime  = $_FILES['banner']['type'] ?: 'image/jpeg';
            $fields[]    = 'banner = ?';
            $params[]    = $bannerBytes;
            $fields[]    = 'banner_mime_type = ?';
            $params[]    = $bannerMime;
        } elseif (!empty($_POST['banner_base64'])) {
            $raw = $_POST['banner_base64'];
            if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/', $raw, $m)) {
                $fields[] = 'banner = ?';
                $params[] = base64_decode($m[2]);
                $fields[] = 'banner_mime_type = ?';
                $params[] = $m[1];
            }
        }

        if (!empty($fields)) {
            $params[] = $storeId;
            $db->prepare('UPDATE stores SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        }

        $fStmt = $db->prepare(
            'SELECT id, user_id, store_name, store_slug, description, address, district,
                    division, postal_code, latitude, longitude, status, is_published,
                    delivery_charge, free_delivery, auto_greeting, verification_status,
                    (logo IS NOT NULL) AS has_logo, (banner IS NOT NULL) AS has_banner,
                    created_at, updated_at
             FROM stores WHERE id = ?'
        );
        $fStmt->execute([$storeId]);
        $updated = $fStmt->fetch();
        $updated = _add_store_urls($updated);

        json_ok(['message' => 'Store profile, delivery settings, and visual media saved successfully', 'store' => $updated]);
    }

    // ── Create store ────────────────────────────────────────
    if ($existingStoreId > 0) json_error('You already have a store', 409);

    $name = trim($_POST['store_name'] ?? body('store_name', ''));
    if (!$name) json_error('store_name is required', 422);

    $slug = make_slug($name);

    $slugChk = $db->prepare('SELECT id FROM stores WHERE store_slug = ?');
    $slugChk->execute([$slug]);
    if ($slugChk->fetch()) $slug .= '-' . $user['id'];

    $logo = $logMime = $banner = $banMime = null;
    if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $logo    = file_get_contents($_FILES['logo']['tmp_name']);
        $logMime = $_FILES['logo']['type'] ?: 'image/jpeg';
    } elseif (!empty($_POST['logo_base64'])) {
        $raw = $_POST['logo_base64'];
        if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/', $raw, $m)) {
            $logo    = base64_decode($m[2]);
            $logMime = $m[1];
        }
    }

    if (!empty($_FILES['banner']['tmp_name']) && is_uploaded_file($_FILES['banner']['tmp_name'])) {
        $banner  = file_get_contents($_FILES['banner']['tmp_name']);
        $banMime = $_FILES['banner']['type'] ?: 'image/jpeg';
    } elseif (!empty($_POST['banner_base64'])) {
        $raw = $_POST['banner_base64'];
        if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/', $raw, $m)) {
            $banner  = base64_decode($m[2]);
            $banMime = $m[1];
        }
    }

    $body = get_body();
    $stmt = $db->prepare(
        'INSERT INTO stores (user_id, store_name, store_slug, description,
          logo, logo_mime_type, banner, banner_mime_type,
          address, district, division, postal_code, latitude, longitude, status, is_published,
          delivery_charge, free_delivery, auto_greeting, verification_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'], $name, $slug,
        $_POST['description']     ?? body('description'),
        $logo, $logMime, $banner, $banMime,
        $_POST['address']         ?? body('address'),
        $_POST['district']        ?? body('district'),
        $_POST['division']        ?? body('division', 'Dhaka'),
        $_POST['postal_code']     ?? body('postal_code', '1205'),
        $_POST['latitude']        ?? body('latitude', 23.75),
        $_POST['longitude']       ?? body('longitude', 90.39),
        'approved', 1,
        (float) ($_POST['delivery_charge'] ?? body('delivery_charge', 60)),
        (int)   ($_POST['free_delivery']   ?? body('free_delivery', 0)),
        $_POST['auto_greeting']   ?? body('auto_greeting', null),
        'unverified'
    ]);
    $newId = (int) $db->lastInsertId();
    $_SESSION['store_id'] = $newId;

    $fStmt = $db->prepare('SELECT id, user_id, store_name, store_slug, description, address, district, division, postal_code, status, is_published, delivery_charge, free_delivery, auto_greeting, verification_status, (logo IS NOT NULL) AS has_logo, (banner IS NOT NULL) AS has_banner FROM stores WHERE id = ?');
    $fStmt->execute([$newId]);
    $created = _add_store_urls($fStmt->fetch());

    json_ok(['message' => 'Store created successfully', 'id' => $newId, 'slug' => $slug, 'store' => $created], 201);
}

// ─────────────────────────────────────────────────────────
// PUT — update store or admin approve/reject
// ─────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $user = auth_required();

    // Admin approve/reject/suspend/revoke
    if (in_array($action, ['approve', 'reject', 'suspend', 'revoke', 'unverify'])) {
        if ($user['role'] !== 'admin') json_error('Only administrators can verify/approve stores', 403);
        if (!$id) json_error('id required', 422);

        $body   = get_body();
        $reason = trim($body['reason'] ?? $body['rejection_reason'] ?? query('reason', ''));

        if ($action === 'approve') {
            $db->prepare("UPDATE stores SET status = 'approved', verification_status = 'verified', rejection_reason = NULL, updated_at = NOW() WHERE id = ?")
               ->execute([$id]);
            json_ok(['message' => 'Store officially verified and approved']);
        } elseif ($action === 'reject') {
            $r = $reason ?: 'Verification documents rejected. Please re-submit valid credentials.';
            $db->prepare("UPDATE stores SET verification_status = 'rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$r, $id]);
            json_ok(['message' => 'Store verification rejected']);
        } elseif ($action === 'revoke' || $action === 'unverify') {
            $db->prepare("UPDATE stores SET verification_status = 'unverified', updated_at = NOW() WHERE id = ?")
               ->execute([$id]);
            json_ok(['message' => 'Store verification revoked']);
        } elseif ($action === 'suspend') {
            $db->prepare("UPDATE stores SET status = 'suspended', updated_at = NOW() WHERE id = ?")
               ->execute([$id]);
            json_ok(['message' => 'Store suspended']);
        }
    }

    // Seller updates own store
    role_required('seller');
    $storeId = $user['store_id'];
    if (!$storeId) json_error('No store found', 404);

    $data   = get_body();
    $fields = [];
    $params = [];
    foreach (['description','address','district','division','postal_code','latitude','longitude','is_published','delivery_charge','free_delivery','auto_greeting'] as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $params[] = $data[$f];
        }
    }
    if (isset($data['store_name'])) {
        $fields[] = 'store_name = ?';
        $params[] = $data['store_name'];
    }
    if (isset($data['store_slug'])) {
        $fields[] = 'store_slug = ?';
        $params[] = $data['store_slug'];
    }

    if (!empty($data['logo_base64'])) {
        $raw = preg_replace('#^data:image/\w+;base64,#i', '', $data['logo_base64']);
        $fields[] = 'logo = ?';
        $params[] = base64_decode($raw);
        $fields[] = 'logo_mime_type = ?';
        $params[] = $data['logo_mime'] ?? 'image/jpeg';
    }
    if (!empty($data['banner_base64'])) {
        $raw = preg_replace('#^data:image/\w+;base64,#i', '', $data['banner_base64']);
        $fields[] = 'banner = ?';
        $params[] = base64_decode($raw);
        $fields[] = 'banner_mime_type = ?';
        $params[] = $data['banner_mime'] ?? 'image/jpeg';
    }

    if (!$fields) json_error('Nothing to update', 422);
    $params[] = $storeId;
    $db->prepare('UPDATE stores SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

    $fStmt = $db->prepare('SELECT id, user_id, store_name, store_slug, description, address, district, division, postal_code, status, is_published, delivery_charge, free_delivery, auto_greeting, verification_status, (logo IS NOT NULL) AS has_logo, (banner IS NOT NULL) AS has_banner, updated_at FROM stores WHERE id = ?');
    $fStmt->execute([$storeId]);
    $updated = _add_store_urls($fStmt->fetch());

    json_ok(['message' => 'Store updated', 'store' => $updated]);
}

json_error('Method not allowed', 405);

// ─────────────────────────────────────────────────────────
// Helper: append image URLs to a store array
// ─────────────────────────────────────────────────────────
function _add_store_urls(array $store): array {
    $id = $store['id'] ?? 0;
    $base = api_base_url();
    $v = !empty($store['updated_at']) ? strtotime($store['updated_at']) : time();
    $store['has_logo']   = !empty($store['has_logo']) || !empty($store['logo']);
    $store['has_banner'] = !empty($store['has_banner']) || !empty($store['banner']);
    $store['logo_url']   = "$base/images.php?type=store_logo&id=$id&v=$v";
    $store['banner_url'] = "$base/images.php?type=store_banner&id=$id&v=$v";
    return $store;
}
