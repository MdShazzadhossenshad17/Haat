<?php
/**
 * HAATEX Logistics Management API
 * HAAT - Proprietary Logistics Network
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if (!isLogistics()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. HAATEX Logistics privilege required.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
$payload = is_array($jsonData) ? array_merge($_REQUEST, $jsonData) : $_REQUEST;

$action = $payload['action'] ?? '';

// ==========================================
// 1. UPDATE ORDER LOGISTICS STATUS & DISPATCH
// ==========================================
if ($action === 'update_status') {
    $orderId = (int)($payload['order_id'] ?? 0);
    $newStatus = trim($payload['logistics_status'] ?? '');
    $riderId = (int)($payload['rider_id'] ?? 0);
    $location = trim($payload['location'] ?? 'HAATEX Central Hub (Tejgaon, Dhaka)');
    $note = trim($payload['note'] ?? '');

    if (!$orderId || !in_array($newStatus, ['pickup_requested', 'hub_received', 'out_for_delivery', 'delivered', 'cancelled'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID or status']);
        exit;
    }

    $ord = $db->query("SELECT * FROM `orders` WHERE `id` = {$orderId}")->fetch();
    if (!$ord) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    $riderName = $ord['assigned_rider_name'];
    $riderPhone = $ord['assigned_rider_phone'];

    if ($riderId > 0) {
        $rInfo = $db->query("SELECT * FROM `riders` WHERE `id` = {$riderId}")->fetch();
        if ($rInfo) {
            $riderName = $rInfo['name'];
            $riderPhone = $rInfo['phone'];
            $db->prepare("UPDATE `riders` SET `active_deliveries` = `active_deliveries` + 1 WHERE `id` = ?")->execute([$riderId]);
        }
    }

    $orderStatus = $ord['order_status'];
    $timestampField = null;

    if ($newStatus === 'hub_received') {
        $orderStatus = 'shipped';
        $timestampField = 'logistics_accepted_at';
        $defaultTitle = 'Package Received at HAATEX Sorting Hub';
        $defaultNote = $note ?: 'Package safely received from artisan workshop at HAATEX regional fulfillment center.';
    } elseif ($newStatus === 'out_for_delivery') {
        $orderStatus = 'shipped';
        $timestampField = 'out_for_delivery_at';
        $defaultTitle = 'Out for Delivery (HAATEX Express)';
        $defaultNote = $note ?: "Assigned to delivery rider {$riderName} ({$riderPhone}). Out for doorstep delivery.";
    } elseif ($newStatus === 'delivered') {
        $orderStatus = 'delivered';
        $timestampField = 'delivered_at';
        $defaultTitle = 'Delivered to Recipient';
        $defaultNote = $note ?: 'Package successfully handed over to recipient at delivery address.';
        $db->prepare("UPDATE `order_items` SET `vendor_status` = 'delivered' WHERE `order_id` = ?")->execute([$orderId]);
    } else {
        $defaultTitle = 'Logistics Status Updated';
        $defaultNote = $note ?: "Status updated to " . strtoupper($newStatus);
    }

    // Update orders table
    $timeSql = $timestampField ? ", `{$timestampField}` = NOW()" : "";
    $upStmt = $db->prepare("UPDATE `orders` SET 
        `logistics_status` = ?,
        `order_status` = ?,
        `assigned_rider_id` = ?,
        `assigned_rider_name` = ?,
        `assigned_rider_phone` = ?
        {$timeSql}
        WHERE `id` = ?");
    $upStmt->execute([$newStatus, $orderStatus, $riderId ?: $ord['assigned_rider_id'], $riderName, $riderPhone, $orderId]);

    // Log tracking event
    logOrderTrackingEvent(
        $orderId,
        $ord['order_number'],
        $defaultTitle,
        'HAATEX Logistics Hub',
        $location,
        $newStatus === 'hub_received' ? 'processing' : ($newStatus === 'out_for_delivery' ? 'shipped' : $newStatus),
        $defaultNote
    );

    // Notify Customer
    if (!empty($ord['user_id'])) {
        if ($newStatus === 'out_for_delivery') {
            createNotification(
                $ord['user_id'],
                "Out for Delivery: #{$ord['order_number']}",
                "Your package is out for delivery with HAATEX Rider {$riderName} ({$riderPhone}). Please keep your phone reachable.",
                "out_for_delivery",
                BASE_URL . "track-order.php?order=" . urlencode($ord['order_number']),
                null,
                $ord['order_number']
            );
        } elseif ($newStatus === 'delivered') {
            dismissOrderNotifications($ord['order_number']);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Order #{$ord['order_number']} updated to " . ucfirst(str_replace('_', ' ', $newStatus)),
        'logistics_status' => $newStatus,
        'order_status' => $orderStatus,
        'rider_name' => $riderName
    ]);
    exit;
}

// ==========================================
// 2. ADD / REGISTER NEW HAATEX RIDER
// ==========================================
if ($action === 'add_rider') {
    $name = trim($payload['name'] ?? '');
    $phone = trim($payload['phone'] ?? '');
    $hubZone = trim($payload['hub_zone'] ?? 'Dhaka Central Hub');
    $vehicleType = trim($payload['vehicle_type'] ?? 'Motorbike');

    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'error' => 'Rider name and phone number are required.']);
        exit;
    }

    $rIns = $db->prepare("INSERT INTO `riders` (`name`, `phone`, `hub_zone`, `vehicle_type`, `status`) VALUES (?, ?, ?, ?, 'active')");
    $rIns->execute([$name, $phone, $hubZone, $vehicleType]);

    echo json_encode([
        'success' => true,
        'rider_id' => $db->lastInsertId(),
        'message' => 'New HAATEX Delivery Rider registered successfully.'
    ]);
    exit;
}

// ==========================================
// 3. GET LOGISTICS REALTIME PIPELINE STATS
// ==========================================
if ($action === 'get_stats') {
    $totalOrders = (int)$db->query("SELECT COUNT(*) FROM `orders`")->fetchColumn();
    $pickupRequests = (int)$db->query("SELECT COUNT(*) FROM `orders` WHERE `logistics_status` = 'pickup_requested'")->fetchColumn();
    $inHub = (int)$db->query("SELECT COUNT(*) FROM `orders` WHERE `logistics_status` = 'hub_received'")->fetchColumn();
    $outForDelivery = (int)$db->query("SELECT COUNT(*) FROM `orders` WHERE `logistics_status` = 'out_for_delivery'")->fetchColumn();
    $deliveredToday = (int)$db->query("SELECT COUNT(*) FROM `orders` WHERE `logistics_status` = 'delivered'")->fetchColumn();
    $activeRiders = (int)$db->query("SELECT COUNT(*) FROM `riders` WHERE `status` = 'active'")->fetchColumn();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_orders' => $totalOrders,
            'pickup_requests' => $pickupRequests,
            'in_hub' => $inHub,
            'out_for_delivery' => $outForDelivery,
            'delivered_today' => $deliveredToday,
            'active_riders' => $activeRiders
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
exit;
