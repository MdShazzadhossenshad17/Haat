<?php
// =========================================================
// HAAT! API — Notifications
// GET /api/notifications.php               list own notifications
// GET /api/notifications.php?action=count  get unread count (for badge)
// PUT /api/notifications.php?id=:id        mark single as read
// PUT /api/notifications.php?action=read_all  mark all as read
// DELETE /api/notifications.php?id=:id     delete notification
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = auth_required();
$id     = (int) query('id', 0);
$action = query('action', '');

// ─── GET ─────────────────────────────────────────────────
if ($method === 'GET') {

    // Unread count only (for bell badge)
    if ($action === 'count') {
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS unread FROM notifications WHERE user_id = ? AND is_read = 0'
        );
        $stmt->execute([$user['id']]);
        json_ok($stmt->fetch());
    }

    $p      = paginate(20);
    $type   = query('type', '');
    $unread = query('unread', '');

    $where  = ['user_id = ?'];
    $params = [$user['id']];
    if ($type)   { $where[] = 'type = ?';    $params[] = $type; }
    if ($unread) { $where[] = 'is_read = 0'; }

    $whereStr = 'WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare(
        "SELECT id, order_id, title, message, type, is_read, created_at
         FROM notifications
         $whereStr
         ORDER BY created_at DESC
         LIMIT {$p['limit']} OFFSET {$p['offset']}"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $cStmt = $db->prepare("SELECT COUNT(*) FROM notifications $whereStr");
    $cStmt->execute($params);

    $unreadStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $unreadStmt->execute([$user['id']]);

    json_ok([
        'notifications' => $rows,
        'total'         => (int) $cStmt->fetchColumn(),
        'unread_count'  => (int) $unreadStmt->fetchColumn(),
        'page'          => $p['page'],
    ]);
}

// ─── PUT — mark as read ───────────────────────────────────
if ($method === 'PUT') {

    if ($action === 'read_all') {
        $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
           ->execute([$user['id']]);
        json_ok(['message' => 'All notifications marked as read']);
    }

    if (!$id) json_error('id required', 422);
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
       ->execute([$id, $user['id']]);
    json_ok(['message' => 'Notification marked as read']);
}

// ─── DELETE ───────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) json_error('id required', 422);
    $stmt = $db->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    if ($stmt->rowCount() === 0) json_error('Notification not found', 404);
    json_ok(['message' => 'Notification deleted']);
}

json_error('Method not allowed', 405);
