<?php
// =========================================================
// HAAT! API — Images (serve BLOB images from DB)
// GET /api/images.php?type=product&id=1&n=1
// GET /api/images.php?type=store_logo&id=1
// GET /api/images.php?type=store_banner&id=1
// GET /api/images.php?type=brand_logo&id=1
// =========================================================

require_once __DIR__ . '/config.php';
// No boot() — we output binary, not JSON

$type = $_GET['type'] ?? '';
$id   = (int) ($_GET['id'] ?? 0);
$n    = (int) ($_GET['n']  ?? 1); // image number for products (1,2,3)

if (!$id) {
    http_response_code(400);
    exit('Missing id');
}

$db = getDB();

switch ($type) {

    // ── Product images ────────────────────────────────────
    case 'product':
        $col      = "image_$n";
        $mimeCol  = "image_{$n}_mime_type";
        if (!in_array($n, [1, 2, 3])) {
            http_response_code(400);
            exit('Invalid image number');
        }
        $stmt = $db->prepare("SELECT $col AS img, $mimeCol AS mime FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        break;

    // ── Store logo ────────────────────────────────────────
    case 'store_logo':
        $stmt = $db->prepare('SELECT logo AS img, logo_mime_type AS mime FROM stores WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        break;

    // ── Store banner ──────────────────────────────────────
    case 'store_banner':
        $stmt = $db->prepare('SELECT banner AS img, banner_mime_type AS mime FROM stores WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        break;

    // ── Brand logo ────────────────────────────────────────
    case 'brand_logo':
        $stmt = $db->prepare('SELECT logo AS img, logo_mime_type AS mime FROM brands WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        break;

    default:
        http_response_code(400);
        exit('Unknown image type');
}

if (!$row || empty($row['img'])) {
    // Return a 1×1 transparent PNG placeholder
    http_response_code(404);
    header('Content-Type: image/png');
    $blank = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==');
    echo $blank;
    exit;
}

$mime = $row['mime'] ?: 'image/jpeg';

// Cache headers (images rarely change)
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400'); // 1 day
header('Content-Length: ' . strlen($row['img']));
echo $row['img'];
exit;
