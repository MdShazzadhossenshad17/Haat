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
 $isSeller = isSeller();

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
        // Mark as read (only if this seller actually owns the conversation)
        $upd = $db->prepare("UPDATE `messages` SET `is_read` = 1 WHERE `seller_id` = ? AND `sender_id` = ? AND `receiver_id` = ? AND `is_read` = 0");
        $upd->execute([$sellerId, $customerId, $currentUser['id']]);

        // Fetch message stream between artisan seller and this customer
        $msgStmt = $db->prepare("SELECT m.*, u.name as sender_name, u.avatar as sender_avatar 
            FROM `messages` m 
            JOIN `users` u ON m.sender_id = u.id 
            WHERE m.seller_id = ? AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
            ORDER BY m.id ASC");
        $msgStmt->execute([$sellerId, $currentUser['id'], $customerId, $customerId, $currentUser['id']]);
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

        $unreadTotal = (int)$db->query("SELECT COUNT(*) FROM `messages` WHERE `seller_id` = {$currentSeller['id']} AND `receiver_id` = {$currentUser['id']} AND `is_read` = 0")->fetchColumn();

        echo json_encode([
            'success' => true,
            'role' => 'seller',
            'unread_total' => $unreadTotal,
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

    $unreadTotal = (int)$db->query("SELECT COUNT(*) FROM `messages` WHERE `receiver_id` = {$currentUser['id']} AND `is_read` = 0")->fetchColumn();

    echo json_encode([
        'success' => true,
        'role' => 'customer',
        'unread_total' => $unreadTotal,
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

    if (empty($orderId)) {
        // Auto-link to latest order between customer and seller if order_number was not passed
        if (isset($payload['customer_id']) && $currentSeller) {
            $cId = (int)$payload['customer_id'];
            $sId = (int)$currentSeller['id'];
            $stmt = $db->prepare("SELECT o.id FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.user_id = ? ORDER BY o.id DESC LIMIT 1");
            $stmt->execute([$sId, $cId]);
            $autoOrd = $stmt->fetchColumn();
            $orderId = $autoOrd ?: null;
        } elseif (isset($sellerId)) {
            $cId = (int)$currentUser['id'];
            $sId = (int)$sellerId;
            $stmt = $db->prepare("SELECT o.id FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.user_id = ? ORDER BY o.id DESC LIMIT 1");
            $stmt->execute([$sId, $cId]);
            $autoOrd = $stmt->fetchColumn();
            $orderId = $autoOrd ?: null;
        }
    }

    // A) SELLER SENDING MESSAGE TO CUSTOMER
    if (isset($payload['customer_id']) && $currentSeller) {
        $customerId = (int)$payload['customer_id'];
        if (!$customerId) {
            echo json_encode(['success' => false, 'error' => 'Customer ID required']);
            exit;
        }

        // Prevent seller from messaging themselves as a customer
        if ($isSeller && $currentSeller && (int)$currentUser['id'] === (int)$currentSeller['user_id'] && (int)$currentUser['id'] === $customerId) {
            echo json_encode(['success' => false, 'error' => 'Invalid customer target']);
            exit;
        }

        // Validate customer exists
        $cStmt = $db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $cStmt->execute([$customerId]);
        if (!$cStmt->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Customer not found']);
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

    // Prevent a seller account from messaging its own store/profile
    $curSeller = currentSeller();
    if (isSeller() && $curSeller && (int)$curSeller['id'] === (int)$sellerId) {
        echo json_encode(['success' => false, 'error' => 'Sellers cannot message their own store.']);
        exit;
    }

    // Prevent non-owners from acting as another user: ensure sender is not impersonating the seller
    if ((int)$currentUser['id'] === (int)$seller['seller_user_id']) {
        // If the current user is the seller's user, block (already handled above)
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
        WHERE (
            u.id IN (SELECT o.user_id FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = {$sellerId})
            OR u.id IN (SELECT sender_id FROM messages WHERE seller_id = {$sellerId} AND receiver_id = {$sellerUserId})
            OR u.id IN (SELECT receiver_id FROM messages WHERE seller_id = {$sellerId} AND sender_id = {$sellerUserId})
        )
        ORDER BY (last_message_time IS NOT NULL) DESC, last_message_time DESC, u.id DESC
    ");
    $conversations = $convStmt ? $convStmt->fetchAll() : [];

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

    // Fetch Logistics Conversation Metadata for Seller
    $logisticsUser = $db->query("SELECT id, name, COALESCE(is_online, 1) as is_online FROM `users` WHERE `role` = 'logistics' LIMIT 1")->fetch();
    $logisticsId = $logisticsUser ? (int)$logisticsUser['id'] : 7;

    $logMsgStmt = $db->prepare("SELECT message, created_at FROM `messages` WHERE seller_id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) ORDER BY id DESC LIMIT 1");
    $logMsgStmt->execute([$sellerId, $sellerUserId, $logisticsId, $logisticsId, $sellerUserId]);
    $lastLogMsg = $logMsgStmt->fetch();

    $logUnreadStmt = $db->prepare("SELECT COUNT(*) FROM `messages` WHERE seller_id = ? AND receiver_id = ? AND is_read = 0");
    $logUnreadStmt->execute([$sellerId, $sellerUserId]);
    $logUnreadCount = (int)$logUnreadStmt->fetchColumn();

    $logisticsConv = [
        'is_logistics' => true,
        'name' => 'HAATEX Logistics Hub Desk',
        'subtitle' => 'Official Delivery & Pickup Partner',
        'is_online' => true,
        'last_message' => $lastLogMsg['message'] ?? 'Direct courier dispatch and workshop pickup desk',
        'last_message_time' => !empty($lastLogMsg['created_at']) ? date('h:i A', strtotime($lastLogMsg['created_at'])) : '',
        'unread_count' => $logUnreadCount
    ];

    echo json_encode([
        'success' => true,
        'unread_total' => $unreadTotal + $logUnreadCount,
        'logistics' => $logisticsConv,
        'conversations' => $formattedList
    ]);
    exit;
}

// ============================================================
// 4. FETCH CUSTOMER'S LIVE CONVERSATION LIST (FOR REALTIME POLLING)
// ============================================================
if ($action === 'customer_conversations') {
    $uid = (int)$currentUser['id'];

    $convStmt = $db->query("
        SELECT s.id as seller_id, s.shop_name, s.shop_logo, s.district,
               COALESCE(s.is_online, u.is_online, 0) as is_online,
               u.id as seller_user_id, u.name as artisan_name,
               (SELECT o.order_number FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = s.id AND o.user_id = {$uid} ORDER BY o.id DESC LIMIT 1) as order_number,
               (SELECT oi.product_name FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.seller_id = s.id AND o.user_id = {$uid} ORDER BY o.id DESC LIMIT 1) as product_name,
               (SELECT p.featured_image FROM orders o JOIN order_items oi ON oi.order_id = o.id LEFT JOIN products p ON oi.product_id = p.id WHERE oi.seller_id = s.id AND o.user_id = {$uid} ORDER BY o.id DESC LIMIT 1) as featured_image,
               (SELECT message FROM messages WHERE seller_id = s.id AND ((sender_id = {$uid} AND receiver_id = s.user_id) OR (sender_id = s.user_id AND receiver_id = {$uid})) ORDER BY id DESC LIMIT 1) as last_message,
               (SELECT created_at FROM messages WHERE seller_id = s.id AND ((sender_id = {$uid} AND receiver_id = s.user_id) OR (sender_id = s.user_id AND receiver_id = {$uid})) ORDER BY id DESC LIMIT 1) as last_message_time,
               (SELECT COUNT(*) FROM messages WHERE seller_id = s.id AND sender_id = s.user_id AND receiver_id = {$uid} AND is_read = 0) as unread_count
        FROM sellers s
        JOIN users u ON s.user_id = u.id
        WHERE s.id IN (SELECT oi.seller_id FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE o.user_id = {$uid})
           OR s.id IN (SELECT seller_id FROM messages WHERE sender_id = {$uid} OR receiver_id = {$uid})
        ORDER BY (last_message_time IS NOT NULL) DESC, last_message_time DESC, s.id ASC
    ");
    $conversations = $convStmt ? $convStmt->fetchAll() : [];

    $unreadTotal = (int)$db->query("SELECT COUNT(*) FROM messages WHERE receiver_id = {$uid} AND is_read = 0")->fetchColumn();

    $formattedList = [];
    foreach ($conversations as $c) {
        $formattedList[] = [
            'seller_id' => (int)$c['seller_id'],
            'shop_name' => $c['shop_name'],
            'artisan_name' => $c['artisan_name'],
            'district' => $c['district'],
            'is_online' => ((int)$c['is_online'] === 1),
            'order_number' => $c['order_number'] ?? '',
            'product_name' => $c['product_name'] ?? '',
            'featured_image' => $c['featured_image'] ?? '',
            'last_message' => $c['last_message'] ?? 'Click to chat...',
            'last_message_time' => !empty($c['last_message_time']) ? date('h:i A', strtotime($c['last_message_time'])) : '',
            'unread_count' => (int)$c['unread_count']
        ];
    }

    // Fetch Logistics Conversation Metadata for Customer
    $logisticsUser = $db->query("SELECT id, name, COALESCE(is_online, 1) as is_online FROM `users` WHERE `role` = 'logistics' LIMIT 1")->fetch();
    $logisticsId = $logisticsUser ? (int)$logisticsUser['id'] : 7;

    $logMsgStmt = $db->prepare("SELECT message, created_at FROM `messages` WHERE (seller_id IS NULL OR seller_id = 0) AND (sender_id = ? OR receiver_id = ?) ORDER BY id DESC LIMIT 1");
    $logMsgStmt->execute([$uid, $uid]);
    $lastLogMsg = $logMsgStmt->fetch();

    $logUnreadStmt = $db->prepare("SELECT COUNT(*) FROM `messages` WHERE receiver_id = ? AND (seller_id IS NULL OR seller_id = 0) AND is_read = 0");
    $logUnreadStmt->execute([$uid]);
    $logUnreadCount = (int)$logUnreadStmt->fetchColumn();

    $logisticsConv = [
        'is_logistics' => true,
        'name' => 'HAATEX',
        'subtitle' => 'Delivery Partner',
        'is_online' => true,
        'last_message' => $lastLogMsg['message'] ?? 'Direct courier and dispatch coordination desk',
        'last_message_time' => !empty($lastLogMsg['created_at']) ? date('h:i A', strtotime($lastLogMsg['created_at'])) : '',
        'unread_count' => $logUnreadCount
    ];

    echo json_encode([
        'success' => true,
        'unread_total' => $unreadTotal,
        'logistics' => $logisticsConv,
        'conversations' => $formattedList
    ]);
    exit;
}

// ============================================================
// 5. GET LOGISTICS CHAT MESSAGES
// ============================================================
if ($action === 'get_logistics_chat') {
    $isStaff = (isLogistics() || isAdmin());
    $targetSellerId = (int)($payload['seller_id'] ?? 0);
    $targetCustomerId = (int)($payload['customer_id'] ?? 0);

    // CASE A: CHAT WITH ARTISAN SELLER
    if ($targetSellerId > 0 || (isSeller() && $currentSeller && empty($targetCustomerId))) {
        $sId = $targetSellerId > 0 ? $targetSellerId : (int)$currentSeller['id'];
        
        $sUserStmt = $db->prepare("SELECT s.*, u.id as seller_user_id, u.name as artisan_name, COALESCE(u.is_online, s.is_online, 0) as is_online FROM `sellers` s JOIN `users` u ON s.user_id = u.id WHERE s.id = ?");
        $sUserStmt->execute([$sId]);
        $sellerInfo = $sUserStmt->fetch();
        $sellerUserId = $sellerInfo ? (int)$sellerInfo['seller_user_id'] : 0;

        $msgStmt = $db->prepare("SELECT m.*, u.name as sender_name 
            FROM `messages` m 
            JOIN `users` u ON m.sender_id = u.id 
            WHERE m.seller_id = ? 
              AND (m.sender_id = ? OR m.receiver_id = ?)
            ORDER BY m.id ASC");
        $msgStmt->execute([$sId, $sellerUserId, $sellerUserId]);
        $messages = $msgStmt->fetchAll();

        // Mark unread as read
        if ($isStaff) {
            $db->prepare("UPDATE `messages` SET `is_read` = 1 WHERE `seller_id` = ? AND `sender_id` = ? AND `is_read` = 0")
                ->execute([$sId, $sellerUserId]);
        } elseif (isSeller()) {
            $db->prepare("UPDATE `messages` SET `is_read` = 1 WHERE `seller_id` = ? AND `receiver_id` = ? AND `is_read` = 0")
                ->execute([$sId, $currentUser['id']]);
        }

        $formatted = [];
        foreach ($messages as $m) {
            $isMe = $isStaff ? ((int)$m['sender_id'] !== (int)$sellerUserId) : ((int)$m['sender_id'] === (int)$currentUser['id']);
            $formatted[] = [
                'id' => (int)$m['id'],
                'is_me' => $isMe,
                'sender_name' => $m['sender_name'],
                'message' => $m['message'],
                'time' => date('h:i A', strtotime($m['created_at'])),
                'date' => date('d M Y', strtotime($m['created_at']))
            ];
        }

        echo json_encode([
            'success' => true,
            'type' => 'seller',
            'seller' => $sellerInfo ? [
                'id' => (int)$sellerInfo['id'],
                'shop_name' => $sellerInfo['shop_name'],
                'artisan_name' => $sellerInfo['artisan_name'],
                'district' => $sellerInfo['district'],
                'phone' => $sellerInfo['phone'] ?? '',
                'is_online' => ((int)$sellerInfo['is_online'] === 1)
            ] : null,
            'messages' => $formatted
        ]);
        exit;
    }

    // CASE B: CHAT WITH CUSTOMER (Default)
    $customerId = $targetCustomerId ?: (int)$currentUser['id'];

    $msgStmt = $db->prepare("SELECT m.*, u.name as sender_name 
        FROM `messages` m 
        JOIN `users` u ON m.sender_id = u.id 
        WHERE (m.seller_id IS NULL OR m.seller_id = 0) 
          AND (m.sender_id = ? OR m.receiver_id = ?)
        ORDER BY m.id ASC");
    $msgStmt->execute([$customerId, $customerId]);
    $messages = $msgStmt->fetchAll();

    // Mark unread as read in logistics channel
    if ($isStaff) {
        $db->prepare("UPDATE `messages` SET `is_read` = 1 WHERE `sender_id` = ? AND (seller_id IS NULL OR seller_id = 0) AND `is_read` = 0")
            ->execute([$customerId]);
    } else {
        $db->prepare("UPDATE `messages` SET `is_read` = 1 WHERE `receiver_id` = ? AND (seller_id IS NULL OR seller_id = 0) AND `is_read` = 0")
            ->execute([$currentUser['id']]);
    }

    $formatted = [];
    foreach ($messages as $m) {
        $isMe = $isStaff ? ((int)$m['sender_id'] !== (int)$customerId) : ((int)$m['sender_id'] === (int)$currentUser['id']);
        $formatted[] = [
            'id' => (int)$m['id'],
            'is_me' => $isMe,
            'sender_name' => $m['sender_name'],
            'message' => $m['message'],
            'time' => date('h:i A', strtotime($m['created_at'])),
            'date' => date('d M Y', strtotime($m['created_at']))
        ];
    }

    echo json_encode([
        'success' => true,
        'type' => 'customer',
        'messages' => $formatted
    ]);
    exit;
}

// ============================================================
// 6. SEND LOGISTICS CHAT MESSAGE
// ============================================================
if ($action === 'send_logistics_chat') {
    $msg = trim($payload['message'] ?? '');
    if (empty($msg)) {
        echo json_encode(['success' => false, 'error' => 'Message text cannot be empty']);
        exit;
    }

    $logisticsUser = $db->query("SELECT id, name FROM `users` WHERE `role` = 'logistics' LIMIT 1")->fetch();
    $logisticsId = $logisticsUser ? (int)$logisticsUser['id'] : 7;

    $isStaff = (isLogistics() || isAdmin());
    $targetSellerId = (int)($payload['seller_id'] ?? 0);
    $targetCustomerId = (int)($payload['customer_id'] ?? 0);

    // If Seller is sending to Logistics Hub
    if (isSeller() && $currentSeller && !$isStaff) {
        $senderId = (int)$currentUser['id'];
        $receiverId = $logisticsId;
        $sellerId = (int)$currentSeller['id'];
        
        $ins = $db->prepare("INSERT INTO `messages` (`sender_id`, `receiver_id`, `seller_id`, `message`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, 0, NOW())");
        $ins->execute([$senderId, $receiverId, $sellerId, $msg]);
        $newMsgId = $db->lastInsertId();

        echo json_encode([
            'success' => true,
            'message_id' => (int)$newMsgId,
            'message' => 'Message sent to HAATEX Logistics'
        ]);
        exit;
    }

    // If Logistics/Staff is sending to Seller Workshop
    if ($isStaff && $targetSellerId > 0) {
        $sUserStmt = $db->prepare("SELECT user_id FROM `sellers` WHERE id = ?");
        $sUserStmt->execute([$targetSellerId]);
        $sellerUserId = (int)$sUserStmt->fetchColumn();

        if (!$sellerUserId) {
            echo json_encode(['success' => false, 'error' => 'Seller recipient not found']);
            exit;
        }

        $ins = $db->prepare("INSERT INTO `messages` (`sender_id`, `receiver_id`, `seller_id`, `message`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, 0, NOW())");
        $ins->execute([$currentUser['id'], $sellerUserId, $targetSellerId, $msg]);
        $newMsgId = $db->lastInsertId();

        echo json_encode([
            'success' => true,
            'message_id' => (int)$newMsgId,
            'message' => 'Message sent to Artisan Workshop'
        ]);
        exit;
    }

    // Default: Customer <-> Logistics
    if ($isStaff) {
        $senderId = (int)$currentUser['id'];
        $receiverId = $targetCustomerId;
    } else {
        $senderId = (int)$currentUser['id'];
        $receiverId = $logisticsId;
    }

    if (!$receiverId) {
        echo json_encode(['success' => false, 'error' => 'Recipient not found']);
        exit;
    }

    $ins = $db->prepare("INSERT INTO `messages` (`sender_id`, `receiver_id`, `seller_id`, `message`, `is_read`, `created_at`) VALUES (?, ?, NULL, ?, 0, NOW())");
    $ins->execute([$senderId, $receiverId, $msg]);
    $newMsgId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'message_id' => (int)$newMsgId,
        'message' => 'Message sent successfully'
    ]);
    exit;
}

// ============================================================
// 7. FETCH LOGISTICS LIVE CONVERSATIONS (FOR DESK POLLING)
// ============================================================
if ($action === 'logistics_conversations' && (isLogistics() || isAdmin())) {
    // 1. Fetch Customers
    $chatUsersStmt = $db->query("SELECT DISTINCT u.id, u.name, u.email, u.phone, u.avatar, COALESCE(u.is_online, 0) as is_online,
        (SELECT message FROM messages WHERE (seller_id IS NULL OR seller_id = 0) AND (sender_id = u.id OR receiver_id = u.id) ORDER BY id DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE (seller_id IS NULL OR seller_id = 0) AND (sender_id = u.id OR receiver_id = u.id) ORDER BY id DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages WHERE (seller_id IS NULL OR seller_id = 0) AND sender_id = u.id AND is_read = 0) as unread_count
        FROM users u 
        WHERE u.role = 'customer'
        ORDER BY (last_message_time IS NOT NULL) DESC, last_message_time DESC LIMIT 25");
    $customers = $chatUsersStmt->fetchAll();

    $formattedCust = [];
    foreach ($customers as $c) {
        $formattedCust[] = [
            'id' => (int)$c['id'],
            'name' => $c['name'],
            'phone' => $c['phone'] ?? '',
            'avatar' => $c['avatar'],
            'is_online' => ((int)$c['is_online'] === 1),
            'last_message' => $c['last_message'] ?? 'Buyer Account',
            'last_message_time' => !empty($c['last_message_time']) ? date('h:i A', strtotime($c['last_message_time'])) : '',
            'unread_count' => (int)$c['unread_count']
        ];
    }

    // 2. Fetch Sellers
    $sellersStmt = $db->query("SELECT s.id, s.shop_name, s.shop_logo, s.district, s.phone as shop_phone,
        COALESCE(s.is_online, u.is_online, 0) as is_online, u.id as user_id, u.name as artisan_name,
        (SELECT message FROM messages WHERE seller_id = s.id AND (sender_id = u.id OR receiver_id = u.id) ORDER BY id DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE seller_id = s.id AND (sender_id = u.id OR receiver_id = u.id) ORDER BY id DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages WHERE seller_id = s.id AND sender_id = u.id AND is_read = 0) as unread_count
        FROM sellers s
        JOIN users u ON s.user_id = u.id
        ORDER BY (last_message_time IS NOT NULL) DESC, last_message_time DESC, s.id ASC");
    $sellersList = $sellersStmt->fetchAll();

    $formattedSellers = [];
    foreach ($sellersList as $s) {
        $formattedSellers[] = [
            'id' => (int)$s['id'],
            'user_id' => (int)$s['user_id'],
            'shop_name' => $s['shop_name'],
            'artisan_name' => $s['artisan_name'],
            'district' => $s['district'],
            'phone' => $s['shop_phone'] ?? '',
            'is_online' => ((int)$s['is_online'] === 1),
            'last_message' => $s['last_message'] ?? 'Workshop Hub',
            'last_message_time' => !empty($s['last_message_time']) ? date('h:i A', strtotime($s['last_message_time'])) : '',
            'unread_count' => (int)$s['unread_count']
        ];
    }

    echo json_encode([
        'success' => true,
        'customers' => $formattedCust,
        'sellers' => $formattedSellers,
        'conversations' => $formattedCust
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;


