<?php
/**
 * HAATEX Proprietary Logistics & Dynamic Order Lifecycle Migration
 */
require_once __DIR__ . '/database.php';

echo "Starting HAATEX Logistics & Order Tracking Migration...\n";

// 1. Ensure 'logistics' role support and create HAATEX Logistics Admin Account
$stmt = $db->prepare("SELECT id FROM `users` WHERE `email` = ?");
$stmt->execute(['logistics@haat.com.bd']);
$logisticsUser = $stmt->fetch();

if (!$logisticsUser) {
    $ins = $db->prepare("INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`, `is_online`, `created_at`) 
        VALUES (?, ?, ?, ?, 'logistics', 'active', 1, NOW())");
    $ins->execute([
        'HAATEX Logistics Command Hub',
        'logistics@haat.com.bd',
        password_hash('Logistics@123', PASSWORD_DEFAULT),
        '01711009988'
    ]);
    echo "Created HAATEX Admin Logistics user: logistics@haat.com.bd / Logistics@123\n";
} else {
    $db->prepare("UPDATE `users` SET `role` = 'logistics', `name` = 'HAATEX Logistics Command Hub' WHERE `email` = ?")->execute(['logistics@haat.com.bd']);
    echo "Updated existing logistics account: logistics@haat.com.bd\n";
}

// 2. Create 'riders' table for HAATEX delivery fleet
$db->exec("CREATE TABLE IF NOT EXISTS `riders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(120) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `hub_zone` VARCHAR(100) NOT NULL,
    `vehicle_type` VARCHAR(50) DEFAULT 'Motorbike',
    `status` ENUM('active', 'on_delivery', 'off_duty') DEFAULT 'active',
    `active_deliveries` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

echo "Ensured `riders` table exists.\n";

// Seed default HAATEX Riders if empty
$riderCount = (int)$db->query("SELECT COUNT(*) FROM `riders`")->fetchColumn();
if ($riderCount === 0) {
    $riders = [
        ['Tanvir Rahman (Lead Courier)', '01819234567', 'Banani & Gulshan Hub (Dhaka North)', 'Motorbike', 'active'],
        ['Rakibul Hasan (Express Rider)', '01712345678', 'Dhanmondi & Mirpur Hub (Dhaka South)', 'Motorbike', 'active'],
        ['Mehedi Hasan (City Delivery)', '01912345679', 'Uttara & Airport Zone (Dhaka North)', 'Motorbike', 'active'],
        ['Shakil Ahmed (Artisan Hub Rider)', '01612345680', 'Narayanganj & Sonargaon Craft Hub', 'Covered Van', 'active']
    ];
    $rIns = $db->prepare("INSERT INTO `riders` (`name`, `phone`, `hub_zone`, `vehicle_type`, `status`) VALUES (?, ?, ?, ?, ?)");
    foreach ($riders as $r) {
        $rIns->execute($r);
    }
    echo "Seeded 4 default HAATEX delivery fleet riders.\n";
}

// 3. Update orders table with HAATEX columns
$orderCols = $db->query("SHOW COLUMNS FROM `orders`")->fetchAll(PDO::FETCH_COLUMN);

$colsToAdd = [
    'logistics_status' => "ALTER TABLE `orders` ADD COLUMN `logistics_status` VARCHAR(50) DEFAULT 'pending' AFTER `order_status`",
    'assigned_rider_id' => "ALTER TABLE `orders` ADD COLUMN `assigned_rider_id` INT NULL AFTER `courier_partner`",
    'assigned_rider_name' => "ALTER TABLE `orders` ADD COLUMN `assigned_rider_name` VARCHAR(150) NULL AFTER `assigned_rider_id`",
    'assigned_rider_phone' => "ALTER TABLE `orders` ADD COLUMN `assigned_rider_phone` VARCHAR(50) NULL AFTER `assigned_rider_name`",
    'pickup_requested_at' => "ALTER TABLE `orders` ADD COLUMN `pickup_requested_at` DATETIME NULL AFTER `estimated_delivery`",
    'logistics_accepted_at' => "ALTER TABLE `orders` ADD COLUMN `logistics_accepted_at` DATETIME NULL AFTER `pickup_requested_at`",
    'out_for_delivery_at' => "ALTER TABLE `orders` ADD COLUMN `out_for_delivery_at` DATETIME NULL AFTER `logistics_accepted_at`",
    'delivered_at' => "ALTER TABLE `orders` ADD COLUMN `delivered_at` DATETIME NULL AFTER `out_for_delivery_at`"
];

foreach ($colsToAdd as $colName => $sql) {
    if (!in_array($colName, $orderCols)) {
        $db->exec($sql);
        echo "Added column `orders.{$colName}`.\n";
    }
}

// 4. Update existing orders to use HAATEX branding and format
$orders = $db->query("SELECT id, order_number, order_status, tracking_code, courier_partner FROM `orders`")->fetchAll();
foreach ($orders as $ord) {
    $trackingCode = $ord['tracking_code'];
    if (empty($trackingCode) || strpos($trackingCode, 'PTH-') === 0) {
        $trackingCode = 'HTX-' . rand(100000, 999999);
    }
    
    $logisticsStatus = 'pending';
    if ($ord['order_status'] === 'delivered') $logisticsStatus = 'delivered';
    elseif ($ord['order_status'] === 'shipped') $logisticsStatus = 'out_for_delivery';
    elseif ($ord['order_status'] === 'processing') $logisticsStatus = 'pickup_requested';

    $up = $db->prepare("UPDATE `orders` SET 
        `courier_partner` = 'HAATEX (HAAT Express Logistics)',
        `tracking_code` = ?,
        `logistics_status` = ?,
        `assigned_rider_name` = COALESCE(`assigned_rider_name`, 'Tanvir Rahman (HAATEX Rider)'),
        `assigned_rider_phone` = COALESCE(`assigned_rider_phone`, '01819234567')
        WHERE `id` = ?");
    $up->execute([$trackingCode, $logisticsStatus, $ord['id']]);
}

// 5. Update order_tracking_events with HAATEX branding
$db->exec("UPDATE `order_tracking_events` SET `actor` = 'HAATEX Logistics Hub' WHERE `actor` LIKE '%Pathao%' OR `actor` LIKE '%Delivery Partner%'");
$db->exec("UPDATE `order_tracking_events` SET `note` = REPLACE(`note`, 'Pathao Courier', 'HAATEX Express Logistics')");

echo "HAATEX Migration completed successfully!\n";
