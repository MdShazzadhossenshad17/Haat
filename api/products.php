<?php
// =========================================================
// HAAT! API — Products
// GET    /api/products.php                    list (public, filtered)
// GET    /api/products.php?slug=:slug         single by slug (public)
// GET    /api/products.php?id=:id             single by id (public)
// GET    /api/products.php?action=featured    featured products (public)
// GET    /api/products.php?action=mine        seller's own products (seller)
// POST   /api/products.php                    create product (seller)
// PUT    /api/products.php?id=:id             update product (seller/admin)
// DELETE /api/products.php?id=:id             delete product (seller/admin)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = (int) query('id', 0);
$slug   = query('slug', '');
$action = query('action', '');

// ─────────────────────────────────────────────────────────
// GET — products (public and seller)
// ─────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Featured products
    if ($action === 'featured') {
        $limit = (int) query('limit', 8);
        $stmt  = $db->prepare(
            "SELECT p.id, p.name, p.slug, p.price, p.sale_price,
                    p.store_id, s.store_name,
                    p.subcategory_id, sc.name AS subcategory_name,
                    p.is_featured,
                    COALESCE(i.quantity, 0) AS stock
             FROM products p
             JOIN stores s       ON s.id = p.store_id
             JOIN subcategories sc ON sc.id = p.subcategory_id
             LEFT JOIN inventory i ON i.product_id = p.id
             WHERE p.is_featured = 1 AND s.status = 'approved'
             ORDER BY p.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) $r = _product_urls($r);
        json_ok($rows);
    }

    // Seller: own products
    if ($action === 'mine') {
        $user = role_required('seller');
        $sid  = $user['store_id'];
        $p    = paginate(20);

        $stmt = $db->prepare(
            "SELECT p.id, p.name, p.slug, p.sku, p.price, p.sale_price,
                    p.subcategory_id, sc.name AS subcategory_name,
                    p.collection_id, p.brand_id,
                    p.variant_1_name, p.variant_1_value,
                    p.variant_2_name, p.variant_2_value,
                    p.variant_3_name, p.variant_3_value,
                    p.is_featured, p.created_at,
                    COALESCE(i.quantity, 0) AS stock,
                    COALESCE(i.reserved_quantity, 0) AS reserved
             FROM products p
             JOIN subcategories sc ON sc.id = p.subcategory_id
             LEFT JOIN inventory i ON i.product_id = p.id
             WHERE p.store_id = ?
             ORDER BY p.created_at DESC
             LIMIT {$p['limit']} OFFSET {$p['offset']}"
        );
        $stmt->execute([$sid]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) $r = _product_urls($r);
        json_ok(['products' => $rows, 'page' => $p['page']]);
    }

    // Single by slug
    if ($slug) {
        $stmt = $db->prepare(
            "SELECT p.id, p.name, p.slug, p.sku, p.description,
                    p.price, p.sale_price,
                    p.store_id, s.store_name, s.store_slug,
                    p.subcategory_id, sc.name AS subcategory_name,
                    c.name AS category_name,
                    p.brand_id, b.name AS brand_name,
                    p.collection_id, col.name AS collection_name,
                    p.variant_1_name, p.variant_1_value,
                    p.variant_2_name, p.variant_2_value,
                    p.variant_3_name, p.variant_3_value,
                    p.is_featured, p.created_at,
                    COALESCE(i.quantity, 0) AS stock,
                    COALESCE(i.reserved_quantity, 0) AS reserved,
                    ROUND(AVG(r.rating), 1) AS avg_rating,
                    COUNT(r.id) AS review_count
             FROM products p
             JOIN stores s             ON s.id  = p.store_id
             JOIN subcategories sc     ON sc.id = p.subcategory_id
             JOIN categories c         ON c.id  = sc.category_id
             LEFT JOIN brands b        ON b.id  = p.brand_id
             LEFT JOIN collections col ON col.id = p.collection_id
             LEFT JOIN inventory i     ON i.product_id = p.id
             LEFT JOIN product_reviews r ON r.product_id = p.id
             WHERE p.slug = ? AND s.status = 'approved'
             GROUP BY p.id"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if (!$row) json_error('Product not found', 404);
        json_ok(_product_urls($row));
    }

    // Single by id
    if ($id) {
        $stmt = $db->prepare(
            "SELECT p.id, p.name, p.slug, p.sku, p.description,
                    p.price, p.sale_price, p.store_id, s.store_name,
                    p.is_featured,
                    COALESCE(i.quantity, 0) AS stock
             FROM products p
             JOIN stores s ON s.id = p.store_id
             LEFT JOIN inventory i ON i.product_id = p.id
             WHERE p.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_error('Product not found', 404);
        json_ok(_product_urls($row));
    }

    // List / search (public)
    $p       = paginate(20);
    $where   = ["s.status = 'approved'"];
    $params  = [];

    $storeId     = (int) query('store_id', 0);
    $subcatId    = (int) query('subcategory_id', 0);
    $catId       = (int) query('category_id', 0);
    $brandId     = (int) query('brand_id', 0);
    $collId      = (int) query('collection_id', 0);
    $search      = query('search', '');
    $minPrice    = query('min_price', '');
    $maxPrice    = query('max_price', '');
    $sortBy      = query('sort', 'newest'); // newest, price_asc, price_desc, popular

    if ($storeId)   { $where[] = 'p.store_id = ?';         $params[] = $storeId; }
    if ($subcatId)  { $where[] = 'p.subcategory_id = ?';   $params[] = $subcatId; }
    if ($catId)     { $where[] = 'sc.category_id = ?';     $params[] = $catId; }
    if ($brandId)   { $where[] = 'p.brand_id = ?';         $params[] = $brandId; }
    if ($collId)    { $where[] = 'p.collection_id = ?';    $params[] = $collId; }
    if ($search)    { $where[] = 'MATCH(p.name, p.description) AGAINST (? IN BOOLEAN MODE)'; $params[] = "$search*"; }
    if ($minPrice)  { $where[] = 'COALESCE(p.sale_price, p.price) >= ?'; $params[] = (float) $minPrice; }
    if ($maxPrice)  { $where[] = 'COALESCE(p.sale_price, p.price) <= ?'; $params[] = (float) $maxPrice; }

    $orderMap = [
        'newest'     => 'p.created_at DESC',
        'price_asc'  => 'COALESCE(p.sale_price, p.price) ASC',
        'price_desc' => 'COALESCE(p.sale_price, p.price) DESC',
        'popular'    => 'review_count DESC',
    ];
    $order = $orderMap[$sortBy] ?? 'p.created_at DESC';

    $whereStr = 'WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare(
        "SELECT p.id, p.name, p.slug, p.price, p.sale_price,
                p.store_id, s.store_name, s.store_slug,
                p.subcategory_id, sc.name AS subcategory_name,
                p.brand_id, b.name AS brand_name,
                p.is_featured,
                COALESCE(i.quantity, 0) AS stock,
                ROUND(AVG(r.rating), 1) AS avg_rating,
                COUNT(r.id) AS review_count
         FROM products p
         JOIN stores s             ON s.id = p.store_id
         JOIN subcategories sc     ON sc.id = p.subcategory_id
         LEFT JOIN brands b        ON b.id = p.brand_id
         LEFT JOIN inventory i     ON i.product_id = p.id
         LEFT JOIN product_reviews r ON r.product_id = p.id
         $whereStr
         GROUP BY p.id
         ORDER BY $order
         LIMIT {$p['limit']} OFFSET {$p['offset']}"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) $r = _product_urls($r);

    $cStmt = $db->prepare(
        "SELECT COUNT(DISTINCT p.id) FROM products p
         JOIN stores s ON s.id = p.store_id
         JOIN subcategories sc ON sc.id = p.subcategory_id
         $whereStr"
    );
    $cStmt->execute($params);
    $total = (int) $cStmt->fetchColumn();

    json_ok(['products' => $rows, 'total' => $total, 'page' => $p['page']]);
}

// ─────────────────────────────────────────────────────────
// POST — create product (seller)
// Accepts multipart/form-data for image uploads
// ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $user = role_required('seller');
    $sid  = $user['store_id'];
    if (!$sid) {
        $sStmt = $db->prepare('SELECT id FROM stores WHERE user_id = ?');
        $sStmt->execute([$user['id']]);
        $sid = $sStmt->fetchColumn();
        if ($sid) {
            $_SESSION['store_id'] = (int) $sid;
            $sid = (int) $sid;
        }
    }
    $b = get_body();
    if (!$sid && !empty($b['store_id'])) {
        $sid = (int) $b['store_id'];
    }
    if (!$sid) json_error('No store found — create a store first', 403);

    $name      = trim($_POST['name'] ?? body('name', ''));
    $subcatId  = (int) ($_POST['subcategory_id'] ?? body('subcategory_id', 101));
    $price     = (float) ($_POST['price'] ?? body('price', 0));

    if (!$name || !$subcatId || !$price) {
        json_error('name, subcategory_id, and price are required', 422);
    }

    $slug = make_slug($name);
    // Ensure unique slug
    $chk = $db->prepare('SELECT id FROM products WHERE slug = ?');
    $chk->execute([$slug]);
    if ($chk->fetch()) $slug .= '-' . time();

    $sku = $_POST['sku'] ?? body('sku', 'SKU-' . strtoupper(bin2hex(random_bytes(4))));

    // Images
    $img = [];
    for ($n = 1; $n <= 3; $n++) {
        $img["image_$n"]           = null;
        $img["image_{$n}_mime"]    = null;
        if (!empty($_FILES["image_$n"])) {
            $img["image_$n"]        = file_get_contents($_FILES["image_$n"]['tmp_name']);
            $img["image_{$n}_mime"] = $_FILES["image_$n"]['type'];
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO products
         (store_id, subcategory_id, brand_id, collection_id,
          name, slug, sku, description, price, sale_price,
          image_1, image_1_mime_type, image_2, image_2_mime_type,
          image_3, image_3_mime_type,
          variant_1_name, variant_1_value,
          variant_2_name, variant_2_value,
          variant_3_name, variant_3_value,
          is_featured)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $sid, $subcatId,
        $_POST['brand_id']        ?? body('brand_id', null),
        $_POST['collection_id']   ?? body('collection_id', null),
        $name, $slug, $sku,
        $_POST['description']     ?? body('description', 'Verified merchant product in HAAT catalogue.'),
        $price,
        $_POST['sale_price']      ?? body('sale_price', null),
        $img['image_1'],  $img['image_1_mime'],
        $img['image_2'],  $img['image_2_mime'],
        $img['image_3'],  $img['image_3_mime'],
        $_POST['variant_1_name']  ?? body('variant_1_name', 'Color'),
        $_POST['variant_1_value'] ?? body('variant_1_value', 'Standard'),
        $_POST['variant_2_name']  ?? body('variant_2_name', null),
        $_POST['variant_2_value'] ?? body('variant_2_value', null),
        $_POST['variant_3_name']  ?? body('variant_3_name', null),
        $_POST['variant_3_value'] ?? body('variant_3_value', null),
        (int) ($_POST['is_featured'] ?? body('is_featured', 1)),
    ]);
    $productId = (int) $db->lastInsertId();

    // Create inventory row
    $qty = (int) ($_POST['quantity'] ?? body('quantity', body('stock', 25)));
    $db->prepare('INSERT INTO inventory (product_id, quantity) VALUES (?, ?) ON DUPLICATE KEY UPDATE quantity = ?')->execute([$productId, $qty, $qty]);

    json_ok(['message' => 'Product created', 'id' => $productId, 'slug' => $slug], 201);
}

// ─────────────────────────────────────────────────────────
// PUT — update product (seller or admin)
// ─────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $user = auth_required();
    if (!$id) json_error('id required', 422);

    // Ownership check (seller only owns their products)
    if ($user['role'] === 'seller') {
        $own = $db->prepare('SELECT id FROM products WHERE id = ? AND store_id = ?');
        $own->execute([$id, $user['store_id']]);
        if (!$own->fetch()) json_error('Product not found or not yours', 404);
    } elseif ($user['role'] !== 'admin') {
        json_error('Forbidden', 403);
    }

    $data   = get_body();
    $fields = [];
    $params = [];

    $updatable = ['name','description','price','sale_price','subcategory_id','brand_id',
                  'collection_id','is_featured',
                  'variant_1_name','variant_1_value','variant_2_name','variant_2_value',
                  'variant_3_name','variant_3_value'];
    foreach ($updatable as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "$f = ?";
            $params[] = $data[$f];
        }
    }
    if (!$fields) json_error('Nothing to update', 422);
    $params[] = $id;
    $db->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    json_ok(['message' => 'Product updated']);
}

// ─────────────────────────────────────────────────────────
// DELETE (seller or admin)
// ─────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $user = auth_required();
    if (!$id) json_error('id required', 422);

    if ($user['role'] === 'seller') {
        $sid = $user['store_id'];
        if (!$sid) {
            $sStmt = $db->prepare('SELECT id FROM stores WHERE user_id = ?');
            $sStmt->execute([$user['id']]);
            $sid = (int) $sStmt->fetchColumn();
        }
        $stmt = $db->prepare('DELETE FROM products WHERE id = ? AND store_id = ?');
        $stmt->execute([$id, $sid]);
    } elseif ($user['role'] === 'admin') {
        $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        json_error('Forbidden', 403);
    }
    if ($stmt->rowCount() === 0) json_error('Product not found or not yours', 404);
    json_ok(['message' => 'Product deleted']);
}

json_error('Method not allowed', 405);

// Helper
function _product_urls(array $p): array {
    $id = $p['id'];
    $base = api_base_url();
    $p['image_1_url'] = "$base/images.php?type=product&id=$id&n=1";
    $p['image_2_url'] = "$base/images.php?type=product&id=$id&n=2";
    $p['image_3_url'] = "$base/images.php?type=product&id=$id&n=3";
    return $p;
}
