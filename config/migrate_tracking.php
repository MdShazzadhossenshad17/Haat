<?php
/**
 * Migration: Order Tracking Events & Live Dispatch System
 */
require_once __DIR__ . '/database.php';

try {
    // 1. Add courier fields to orders if missing
    $orderCols = $db->query("SHOW COLUMNS FROM `orders`")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('courier_partner', $orderCols)) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `courier_partner` VARCHAR(100) DEFAULT 'Pathao Courier'");
    }
    if (!in_array('tracking_code', $orderCols)) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `tracking_code` VARCHAR(100) DEFAULT 'PTH-8849201'");
    }
    if (!in_array('estimated_delivery', $orderCols)) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `estimated_delivery` VARCHAR(100) DEFAULT '25-27 Sep 2026'");
    }

    // 2. Create order_tracking_events table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `order_tracking_events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `order_number` VARCHAR(100) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `actor` VARCHAR(150) NOT NULL,
            `location` VARCHAR(150) NULL,
            `status_key` VARCHAR(50) NOT NULL,
            `note` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`order_id`),
            INDEX (`order_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Clear and seed initial events for HAAT-2026-90412
    $order = $db->query("SELECT id, order_number, user_id FROM `orders` WHERE order_number = 'HAAT-2026-90412' LIMIT 1")->fetch();
    if ($order) {
        $db->exec("DELETE FROM `order_tracking_events` WHERE `order_id` = {$order['id']}");
        
        $ins = $db->prepare("INSERT INTO `order_tracking_events` (`order_id`, `order_number`, `title`, `actor`, `location`, `status_key`, `note`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $ins->execute([
            $order['id'],
            $order['order_number'],
            'Order Placed & Payment Confirmed',
            'HAAT Customer Checkout',
            'Online (Dhaka)',
            'pending',
            'Payment verified via bKash Merchant Gateway (Trx ID: BKASH-98234120). Order dispatched to respective artisan workshops.',
            date('Y-m-d H:i:s', strtotime('-18 hours'))
        ]);

        $ins->execute([
            $order['id'],
            $order['order_number'],
            'Artisan Crafting & Workshop Preparation',
            'Sonargaon Jamdani Kutir',
            'Narayanganj Workshop',
            'processing',
            'Master weaver inspected 84-count Jamdani handloom embroidery. Protected with moisture-barrier packaging.',
            date('Y-m-d H:i:s', strtotime('-6 hours'))
        ]);

        $ins->execute([
            $order['id'],
            $order['order_number'],
            'Organic Jar Sealing & Quality Assurance',
            'Sundarbans Wild Organics',
            'Satkhira Facility',
            'processing',
            'Khalisha wild honey bottled in sealed airtight glass jar with security seal applied.',
            date('Y-m-d H:i:s', strtotime('-3 hours'))
        ]);

        $ins->execute([
            $order['id'],
            $order['order_number'],
            'Handed Over to Delivery Partner',
            'Pathao Courier Logistics',
            'Narayanganj Regional Sorting Hub',
            'shipped',
            'Consignment #PTH-8849201 received by Pathao courier agent. Departed Narayanganj hub en route to Dhaka Central.',
            date('Y-m-d H:i:s', strtotime('-45 minutes'))
        ]);

        // Update overall order status to shipped to match latest event
        $db->exec("UPDATE `orders` SET `order_status` = 'shipped', `courier_partner` = 'Pathao Courier', `tracking_code` = 'PTH-8849201' WHERE `id` = {$order['id']}");
    }

    echo "Order tracking migration complete!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
