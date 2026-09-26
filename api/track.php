<?php
/**
 * Live Order Tracking API
 * HAAT Multi-Vendor Marketplace
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
$payload = is_array($jsonData) ? array_merge($_REQUEST, $jsonData) : $_REQUEST;

$action = $payload['action'] ?? 'get';
$orderNumber = trim($payload['order'] ?? $payload['order_number'] ?? '');

if (empty($orderNumber)) {
    echo json_encode(['success' => false, 'error' => 'Order number is required']);
    exit;
}

// Fetch order
$stmt = $db->prepare("SELECT * FROM `orders` WHERE `order_number` = ? LIMIT 1");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

if ($action === 'get' && !canAccessOrder($order)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You are not authorized to view this order.']);
    exit;
}

// 1. GET LIVE TRACKING DATA
if ($action === 'get') {
    // Fetch items with seller info
    $itStmt = $db->prepare("SELECT oi.*, s.shop_name, s.district as seller_district, p.featured_image 
        FROM `order_items` oi 
        JOIN `sellers` s ON oi.seller_id = s.id 
        LEFT JOIN `products` p ON oi.product_id = p.id 
        WHERE oi.order_id = ?");
    $itStmt->execute([$order['id']]);
    $items = $itStmt->fetchAll();

    // Fetch tracking events
    $evStmt = $db->prepare("SELECT * FROM `order_tracking_events` WHERE `order_id` = ? ORDER BY id DESC");
    $evStmt->execute([$order['id']]);
    $events = $evStmt->fetchAll();

    $formattedEvents = [];
    foreach ($events as $ev) {
        $formattedEvents[] = [
            'id' => (int)$ev['id'],
            'title' => $ev['title'],
            'actor' => $ev['actor'],
            'location' => $ev['location'],
            'status_key' => $ev['status_key'],
            'note' => $ev['note'],
            'time' => date('h:i A', strtotime($ev['created_at'])),
            'date' => date('d M Y', strtotime($ev['created_at'])),
            'timestamp' => strtotime($ev['created_at'])
        ];
    }

    echo json_encode([
        'success' => true,
        'order' => [
            'id' => (int)$order['id'],
            'order_number' => $order['order_number'],
            'order_status' => $order['order_status'],
            'logistics_status' => $order['logistics_status'] ?? 'pending',
            'assigned_rider_name' => $order['assigned_rider_name'] ?? '',
            'assigned_rider_phone' => $order['assigned_rider_phone'] ?? '',
            'payment_status' => $order['payment_status'],
            'payment_method' => strtoupper($order['payment_method']),
            'courier_partner' => $order['courier_partner'] ?? 'HAATEX (HAAT Express Logistics)',
            'tracking_code' => $order['tracking_code'] ?? 'HTX-884920',
            'estimated_delivery' => $order['estimated_delivery'] ?? '25-27 Sep 2026',
            'delivered_date' => !empty($order['delivered_date']) ? date('d M Y, h:i A', strtotime($order['delivered_date'])) : '',
            'grand_total' => formatPrice($order['grand_total']),
            'recipient' => [
                'name' => $order['shipping_name'],
                'phone' => $order['shipping_phone'],
                'address' => $order['shipping_address'],
                'district' => $order['district'],
                'division' => $order['division']
            ]
        ],
        'items' => array_map(function($it) {
            return [
                'product_name' => $it['product_name'],
                'shop_name' => $it['shop_name'],
                'price' => formatPrice($it['price']),
                'quantity' => (int)$it['quantity'],
                'subtotal' => formatPrice($it['subtotal']),
                'vendor_status' => $it['vendor_status'] ?? 'processing',
                'featured_image' => $it['featured_image'] ?? ''
            ];
        }, $items),
        'events' => $formattedEvents,
        'last_updated' => time()
    ]);
    exit;
}

// 2. LIVE STATUS UPDATE (From Seller, Delivery Partner, or Admin)
if ($action === 'push_update') {
    if (!canUpdateOrderTracking($order)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not authorized to update this order.']);
        exit;
    }
    $statusKey = trim($payload['status_key'] ?? ''); // 'pending', 'processing', 'shipped', 'delivered'
    $title = trim($payload['title'] ?? '');
    $actor = trim($payload['actor'] ?? 'Delivery Partner');
    $location = trim($payload['location'] ?? 'Distribution Center');
    $note = trim($payload['note'] ?? '');
    $courier = trim($payload['courier_partner'] ?? ($order['courier_partner'] ?? 'Pathao Courier'));

    if (empty($statusKey) || empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Status and title required']);
        exit;
    }

    // Insert new tracking event
    $ins = $db->prepare("INSERT INTO `order_tracking_events` (`order_id`, `order_number`, `title`, `actor`, `location`, `status_key`, `note`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $ins->execute([
        $order['id'],
        $order['order_number'],
        $title,
        $actor,
        $location,
        $statusKey,
        $note
    ]);

    // Update overall order status
    $up = $db->prepare("UPDATE `orders` SET `order_status` = ?, `courier_partner` = ? WHERE `id` = ?");
    $up->execute([$statusKey, $courier, $order['id']]);

    // Also update order_items vendor status if passed
    if ($statusKey === 'delivered' || $statusKey === 'shipped') {
        $db->prepare("UPDATE `order_items` SET `vendor_status` = ? WHERE `order_id` = ?")->execute([$statusKey, $order['id']]);
    }

    if ($statusKey === 'shipped' || $statusKey === 'delivered' || $statusKey === 'cancelled') {
        // Once shipped or completed, dismiss all order notifications as requested
        dismissOrderNotifications($order['order_number']);
    } else {
        // Crafting / preparation tracking update
        $notifTitle = "Crafting in Progress: #{$order['order_number']}";
        $notifMsg = "{$title}: {$note}" . ($location ? " (Location: {$location})" : "");
        createNotification(
            $order['user_id'],
            $notifTitle,
            $notifMsg,
            "order_{$statusKey}",
            BASE_URL . "track-order.php?order=" . urlencode($order['order_number']),
            null,
            $order['order_number']
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tracking event registered successfully',
        'status_key' => $statusKey
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
