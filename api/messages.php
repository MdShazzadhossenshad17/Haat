<?php
// =========================================================
// HAAT! API — Messages
// GET  /api/messages.php                    list conversations
// GET  /api/messages.php?with=:user_id      get thread with user
// GET  /api/messages.php?order_id=:id       get order-linked thread
// POST /api/messages.php                    send message
// PUT  /api/messages.php?action=read&with=:user_id  mark as read
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = auth_required();
$action = query('action', '');
$with   = (int) query('with', 0);
$ordId  = (int) query('order_id', 0);

// ─── GET ─────────────────────────────────────────────────
if ($method === 'GET') {

    // Get specific thread
    if ($with) {
        $p    = paginate(50);
        $stmt = $db->prepare(
            "SELECT m.id, m.sender_id, m.receiver_id, m.sender_role,
                    m.message, m.is_read, m.created_at,
                    us.name AS sender_name, ur.name AS receiver_name
             FROM messages m
             JOIN users us ON us.id = m.sender_id
             JOIN users ur ON ur.id = m.receiver_id
             WHERE (m.sender_id = ? AND m.receiver_id = ?)
                OR (m.sender_id = ? AND m.receiver_id = ?)
             ORDER BY m.created_at ASC
             LIMIT {$p['limit']} OFFSET {$p['offset']}"
        );
        $stmt->execute([$user['id'], $with, $with, $user['id']]);

        // Mark incoming as read
        $db->prepare(
            'UPDATE messages SET is_read = 1
             WHERE sender_id = ? AND receiver_id = ? AND is_read = 0'
        )->execute([$with, $user['id']]);

        json_ok($stmt->fetchAll());
    }

    // Order-linked thread
    if ($ordId) {
        $stmt = $db->prepare(
            "SELECT m.id, m.sender_id, m.receiver_id, m.message, m.is_read,
                    m.created_at, us.name AS sender_name, m.sender_role
             FROM messages m
             JOIN users us ON us.id = m.sender_id
             WHERE m.order_id = ?
             ORDER BY m.created_at ASC"
        );
        $stmt->execute([$ordId]);
        json_ok($stmt->fetchAll());
    }

    // List conversations — distinct contacts
    $stmt = $db->prepare(
        "SELECT
            CASE WHEN m.sender_id = :uid THEN m.receiver_id ELSE m.sender_id END AS contact_id,
            u.name AS contact_name, u.role AS contact_role,
            MAX(m.created_at) AS last_message_at,
            SUM(m.receiver_id = :uid2 AND m.is_read = 0) AS unread_count
         FROM messages m
         JOIN users u ON u.id = (CASE WHEN m.sender_id = :uid3 THEN m.receiver_id ELSE m.sender_id END)
         WHERE m.sender_id = :uid4 OR m.receiver_id = :uid5
         GROUP BY contact_id, contact_name, contact_role
         ORDER BY last_message_at DESC"
    );
    $stmt->execute([
        ':uid'  => $user['id'],
        ':uid2' => $user['id'],
        ':uid3' => $user['id'],
        ':uid4' => $user['id'],
        ':uid5' => $user['id'],
    ]);
    json_ok($stmt->fetchAll());
}

// ─── POST — send message ─────────────────────────────────
if ($method === 'POST') {
    $data = require_body('receiver_id', 'message');

    $receiverId = (int) $data['receiver_id'];
    if ($receiverId === $user['id']) json_error('Cannot message yourself', 400);

    // Get receiver role
    $rStmt = $db->prepare('SELECT role FROM users WHERE id = ?');
    $rStmt->execute([$receiverId]);
    $receiver = $rStmt->fetch();
    if (!$receiver) json_error('Receiver not found', 404);

    $db->prepare(
        'INSERT INTO messages
         (order_id, sender_id, sender_role, receiver_id, receiver_role, message)
         VALUES (?,?,?,?,?,?)'
    )->execute([
        isset($data['order_id']) ? (int) $data['order_id'] : null,
        $user['id'],
        $user['role'],
        $receiverId,
        $receiver['role'],
        trim($data['message']),
    ]);

    // Notify receiver
    $db->prepare(
        'INSERT INTO notifications (user_id, title, message, type)
         VALUES (?, ?, ?, ?)'
    )->execute([
        $receiverId,
        'New Message from ' . $user['name'],
        substr(trim($data['message']), 0, 100),
        'message',
    ]);

    json_ok(['message' => 'Message sent'], 201);
}

// ─── PUT — mark as read ───────────────────────────────────
if ($method === 'PUT' && $action === 'read') {
    if (!$with) json_error('with (user_id) required', 422);
    $db->prepare(
        'UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0'
    )->execute([$with, $user['id']]);
    json_ok(['message' => 'Messages marked as read']);
}

json_error('Method not allowed', 405);
