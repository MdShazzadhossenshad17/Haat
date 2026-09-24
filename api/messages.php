<?php
/**
 * Live Messaging API Endpoint
 * HAAT - Meta Messenger Live Chat System
 * Supports both Customer-to-Seller and Seller-to-Customer real-time messaging
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$currentUser = currentUser();
$currentSeller = currentSeller();

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
$payload = is_array($jsonData) ? array_merge($_REQUEST, $jsonData) : $_REQUEST;

$action = $payload['action'] ?? 'get';

// ============================================================
// 1. GET MESSAGES FOR A CONVERSATION
// ============================================================
if ($action === 'get') {
    // A) SELLER-TO-CUSTOMER CONVERSATION VIEW
    if (isset($payload['customer_id']) && $currentSeller) {
        $customerId = (int)$payload['customer_id'];
        $sellerId = (int)$currentSeller['id'];

        // Get customer user details
        $cStmt = $db->prepare("SELECT id, name, email, phone, avatar, COALESCE(is_online, 0) as is_online FROM `users` WHERE id = ?");
        $cStmt->execute([$customerId]);
        $customer = $cStmt->fetch();

        if (!$customer) {
            echo json_encode(['success' => false, 'error' => 'Customer not found']);
            exit;
        }

        // Mark unread messages sent by customer to seller as read
        $upd = $db->prepare("UPDATE `messages` SET `is_read` = 1 WHERE `seller_id` = ? AND `sender_id` = ? AND `receiver_id` = ? AND `is_read` = 0");
        $upd->execute([$sellerId, $customerId, $currentUser['id']]);

        // Fetch message stream between artisan seller and this customer
        $msgStmt = $db->prepare("SELECT m.*, u.name as sender_name, u.avatar as sender_avatar 
            FROM `messages` m 
            JOIN `users` u ON m.sender_id = u.id 
            WHERE m.seller_id = ? AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
            ORDER BY m.id ASC");
        $msgStmt->execute([
            $sellerId,
            $currentUser['id'],
            $customerId,
            $customerId,
            $currentUser['id']
        ]);
        $messages = $msgStmt->fetchAll();

        $formatted = [];
        foreach ($messages as $m) {
            $formatted[] = [
                'id' => (int)$m['id'],
                'order_id' => $m['order_id'],
                'is_me' => ((int)$m['sender_id'] === (int)$currentUser['id']),
                'sender_name' => $m['sender_name'],
                'message' => $m['message'],
                'time' => date('h:i A', strtotime($m['created_at'])),
                'date' => date('d M Y', strtotime($m['created_at'])),
                'is_read' => (bool)$m['is_read']
            ];
        }

        echo json_encode([
            'success' => true,
            'role' => 'seller',
            'customer' => [
                'id' => (int)$customer['id'],
                'name' => $customer['name'],
                'phone' => $customer['phone'] ?? '',
                'avatar' => $customer['avatar'],
                'is_online' => ((int)($customer['is_online'] ?? 0) === 1)
            ],
            'messages' => $formatted
        ]);
        exit;
    }

    // B) CUSTOMER-TO-SELLER CONVERSATION VIEW (Standard)
    $sellerId = (int)($payload['seller_id'] ?? ($currentSeller ? $currentSeller['id'] : 0));
    if (!$sellerId) {
        echo json_encode(['success' => false, 'error' => 'Missing seller ID']);
        exit;
    }

    // Get seller user_id and online status
    $sStmt = $db->prepare("SELECT s.*, u.id as seller_user_id, u.name as artisan_name, u.avatar, COALESCE(s.is_online, u.is_online, 0) as is_online FROM `sellers` s JOIN `users` u ON s.user_id = u.id WHERE s.id = ?");
    $sStmt->execute([$sellerId]);
    $seller = $sStmt->fetch();

    if (!$seller) {
        echo json_encode(['success' => false, 'error' => 'Seller not found']);
        exit;
    }

    // Mark messages as read where sender is seller and receiver is current user
    $upd = $db->prepare("UPDATE `messages` SET `is_read` = 1 WHERE `seller_id` = ? AND `sender_id` = ? AND `receiver_id` = ? AND `is_read` = 0");
    $upd->execute([$sellerId, $seller['seller_user_id'], $currentUser['id']]);

    // Fetch message stream
    $msgStmt = $db->prepare("SELECT m.*, u.name as sender_name, u.avatar as sender_avatar 
        FROM `messages` m 
        JOIN `users` u ON m.sender_id = u.id 
        WHERE m.seller_id = ? AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        ORDER BY m.id ASC");
    $msgStmt->execute([
        $sellerId,
        $currentUser['id'],
        $seller['seller_user_id'],
        $seller['seller_user_id'],
        $currentUser['id']
    ]);
    $messages = $msgStmt->fetchAll();

    $formatted = [];
    foreach ($messages as $m) {
        $formatted[] = [
            'id' => (int)$m['id'],
            'order_id' => $m['order_id'],
            'is_me' => ((int)$m['sender_id'] === (int)$currentUser['id']),
            'sender_name' => $m['sender_name'],
            'message' => $m['message'],
            'time' => date('h:i A', strtotime($m['created_at'])),
            'date' => date('d M Y', strtotime($m['created_at'])),
            'is_read' => (bool)$m['is_read']
        ];
    }

    echo json_encode([
        'success' => true,
        'role' => 'customer',
        'seller' => [
            'id' => (int)$seller['id'],
            'shop_name' => $seller['shop_name'],
            'is_online' => ((int)($seller['is_online'] ?? 0) === 1),
            'artisan_name' => $seller['artisan_name'],
            'district' => $seller['district'],
            'avatar' => $seller['avatar']
        ],
        'messages' => $formatted
    ]);
    exit;
}

// ============================================================
// 2. SEND MESSAGE
// ============================================================
if ($action === 'send') {
    $text = trim($payload['message'] ?? '');
    $orderNumber = trim($payload['order_number'] ?? '');

    if (empty($text)) {
        echo json_encode(['success' => false, 'error' => 'Message text cannot be empty']);
        exit;
    }

    $orderId = null;
    if (!empty($orderNumber)) {
        $ordStmt = $db->prepare("SELECT id FROM `orders` WHERE `order_number` = ? LIMIT 1");
        $ordStmt->execute([$orderNumber]);
        $orderId = $ordStmt->fetchColumn() ?: null;
    }

    // A) SELLER SENDING MESSAGE TO CUSTOMER
    if (isset($payload['customer_id']) && $currentSeller) {
        $customerId = (int)$payload['customer_id'];
        if (!$customerId) {
            echo json_encode(['success' => false, 'error' => 'Customer ID required']);
            exit;
        }

        $ins = $db->prepare("INSERT INTO `messages` (`order_id`, `sender_id`, `receiver_id`, `seller_id`, `message`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        $ins->execute([
            $orderId,
            $currentUser['id'],
            $customerId,
            $currentSeller['id'],
            $text
        ]);
        $msgId = $db->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => [
                'id' => (int)$msgId,
                'is_me' => true,
                'sender_name' => $currentUser['name'],
                'message' => $text,
                'time' => date('h:i A'),
                'date' => date('d M Y')
            ]
        ]);
        exit;
    }

    // B) CUSTOMER SENDING MESSAGE TO SELLER
    $sellerId = (int)($payload['seller_id'] ?? 0);
    if (!$sellerId) {
        echo json_encode(['success' => false, 'error' => 'Seller ID is required']);
        exit;
    }

    // Get seller user_id
    $sStmt = $db->prepare("SELECT s.*, u.id as seller_user_id, u.name as artisan_name FROM `sellers` s JOIN `users` u ON s.user_id = u.id WHERE s.id = ?");
    $sStmt->execute([$sellerId]);
    $seller = $sStmt->fetch();

    if (!$seller) {
        echo json_encode(['success' => false, 'error' => 'Seller not found']);
        exit;
    }

    // Insert message
    $ins = $db->prepare("INSERT INTO `messages` (`order_id`, `sender_id`, `receiver_id`, `seller_id`, `message`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, 0, NOW())");
    $ins->execute([
        $orderId,
        $currentUser['id'],
        $seller['seller_user_id'],
        $sellerId,
        $text
    ]);
    $msgId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => [
            'id' => (int)$msgId,
            'is_me' => true,
            'sender_name' => $currentUser['name'],
            'message' => $text,
            'time' => date('h:i A'),
            'date' => date('d M Y')
        ]
    ]);
    exit;
}

// ============================================================
// 3. FETCH SELLER'S LIVE CONVERSATION LIST (FOR REALTIME POLLING)
// ============================================================
if ($action === 'seller_conversations' && $currentSeller) {
    $sellerId = (int)$currentSeller['id'];
    $sellerUserId = (int)$currentUser['id'];

    $convStmt = $db->query("
        SELECT u.id as customer_id, u.name as customer_name, u.phone as customer_phone, u.avatar as customer_avatar,
               COALESCE(u.is_online, 0) as is_online,
               (SELECT o.order_number FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = {$sellerId} AND o.user_id = u.id ORDER BY o.id DESC LIMIT 1) as order_number,
               (SELECT oi.product_name FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = {$sellerId} AND o.user_id = u.id ORDER BY o.id DESC LIMIT 1) as product_name,
               (SELECT p.featured_image FROM orders o JOIN order_items oi ON oi.order_id = o.id LEFT JOIN products p ON oi.product_id = p.id WHERE oi.seller_id = {$sellerId} AND o.user_id = u.id ORDER BY o.id DESC LIMIT 1) as featured_image,
               (SELECT message FROM messages WHERE seller_id = {$sellerId} AND ((sender_id = {$sellerUserId} AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = {$sellerUserId})) ORDER BY id DESC LIMIT 1) as last_message,
               (SELECT created_at FROM messages WHERE seller_id = {$sellerId} AND ((sender_id = {$sellerUserId} AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = {$sellerUserId})) ORDER BY id DESC LIMIT 1) as last_message_time,
               (SELECT COUNT(*) FROM messages WHERE seller_id = {$sellerId} AND sender_id = u.id AND receiver_id = {$sellerUserId} AND is_read = 0) as unread_count
        FROM users u
        WHERE u.role = 'customer' AND (
            u.id IN (SELECT o.user_id FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = {$sellerId})
            OR u.id IN (SELECT sender_id FROM messages WHERE seller_id = {$sellerId} AND receiver_id = {$sellerUserId})
            OR u.id IN (SELECT receiver_id FROM messages WHERE seller_id = {$sellerId} AND sender_id = {$sellerUserId})
        )
        ORDER BY (last_message_time IS NOT NULL) DESC, last_message_time DESC, u.id DESC
    ");
    $conversations = $convStmt->fetchAll();

    $unreadTotal = (int)$db->query("SELECT COUNT(*) FROM messages WHERE seller_id = {$sellerId} AND receiver_id = {$sellerUserId} AND is_read = 0")->fetchColumn();

    $formattedList = [];
    foreach ($conversations as $c) {
        $formattedList[] = [
            'customer_id' => (int)$c['customer_id'],
            'customer_name' => $c['customer_name'],
            'customer_phone' => $c['customer_phone'] ?? '',
            'customer_avatar' => $c['customer_avatar'],
            'is_online' => ((int)$c['is_online'] === 1),
            'order_number' => $c['order_number'] ?? '',
            'product_name' => $c['product_name'] ?? '',
            'featured_image' => $c['featured_image'] ?? '',
            'last_message' => $c['last_message'] ?? 'Start chatting...',
            'last_message_time' => !empty($c['last_message_time']) ? date('h:i A', strtotime($c['last_message_time'])) : '',
            'unread_count' => (int)$c['unread_count']
        ];
    }

    echo json_encode([
        'success' => true,
        'unread_total' => $unreadTotal,
        'conversations' => $formattedList
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;

