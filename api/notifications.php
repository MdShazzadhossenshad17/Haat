<?php
/**
 * Real-Time Notifications API
 * HAAT Marketplace
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$currentUser = currentUser();
$userId = (int)$currentUser['id'];
$action = $_GET['action'] ?? ($_POST['action'] ?? 'get');

if ($action === 'get') {
    $unreadCount = getUserUnreadNotificationCount($userId);
    $notifications = getUserNotifications($userId, 15);

    $formatted = [];
    foreach ($notifications as $n) {
        $formatted[] = [
            'id' => (int)$n['id'],
            'title' => $n['title'],
            'message' => $n['message'],
            'type' => $n['type'],
            'link' => $n['link'] ?: BASE_URL . 'customer/#orders',
            'is_read' => (bool)$n['is_read'],
            'time' => date('h:i A', strtotime($n['created_at'])),
            'date' => date('d M Y', strtotime($n['created_at']))
        ];
    }

    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'notifications' => $formatted
    ]);
    exit;
}

if ($action === 'mark_read') {
    $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `id` = ? AND `user_id` = ?");
        $stmt->execute([$id, $userId]);
    } else {
        $stmt = $db->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `user_id` = ?");
        $stmt->execute([$userId]);
    }

    $unreadCount = getUserUnreadNotificationCount($userId);
    echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
