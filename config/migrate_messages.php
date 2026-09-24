<?php
/**
 * Messages Table Migration & Seed
 * HAAT - Messenger Live Chat System
 */
require_once __DIR__ . '/database.php';
$pdo = getDBConnection();

$sql = "CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT DEFAULT NULL,
    `sender_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `seller_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`seller_id`) REFERENCES `sellers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$pdo->exec($sql);
echo "1. Messages table created successfully!\n";

// Seed sample conversation for customer (User 5) with sellers of Order 1
// Seller 1 (User 2, Al-Amin Mia, Sonargaon Jamdani Kutir)
// Seller 3 (User 4, Mizanur Rahman, Sundarbans Wild Organics)

$count = $pdo->query("SELECT COUNT(*) FROM `messages`")->fetchColumn();
if ($count == 0) {
    $ins = $pdo->prepare("INSERT INTO `messages` (`order_id`, `sender_id`, `receiver_id`, `seller_id`, `message`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Conversation with Seller 1 (Sonargaon Jamdani Kutir)
    $ins->execute([
        1, // Order ID
        2, // Sender: Al-Amin Mia (Seller)
        5, // Receiver: Tanvir Hossain (Customer)
        1, // Seller ID
        "Assalamu Alaikum brother Tanvir! We have received your order #HAAT-2026-90412 for the Royal Dhakai Jamdani Saree. Our master weavers are inspecting the zari embroidery before packaging.",
        1,
        date('Y-m-d H:i:s', strtotime('-2 hours'))
    ]);

    $ins->execute([
        1, // Order ID
        5, // Sender: Tanvir Hossain (Customer)
        2, // Receiver: Al-Amin Mia (Seller)
        1, // Seller ID
        "Walaikum Assalam! Thank you Al-Amin bhai. Please ensure water-resistant packaging as it will be delivered outside Dhaka.",
        1,
        date('Y-m-d H:i:s', strtotime('-1 hour 30 mins'))
    ]);

    $ins->execute([
        1, // Order ID
        2, // Sender: Al-Amin Mia (Seller)
        5, // Receiver: Tanvir Hossain (Customer)
        1, // Seller ID
        "Absolutely! We always use double bubble wrap and genuine traditional muslin protection. It has been handed over to courier dispatch.",
        0, // Unread
        date('Y-m-d H:i:s', strtotime('-15 mins'))
    ]);

    // Conversation with Seller 3 (Sundarbans Wild Organics)
    $ins->execute([
        1, // Order ID
        4, // Sender: Mizanur Rahman (Seller)
        5, // Receiver: Tanvir Hossain (Customer)
        3, // Seller ID
        "Hello Tanvir! Your Pure Sundarbans Wild Honey (1000g) is packed in an airtight glass jar with sealed protective carton. Freshly harvested from the forest!",
        1,
        date('Y-m-d H:i:s', strtotime('-4 hours'))
    ]);

    $ins->execute([
        1, // Order ID
        5, // Sender: Tanvir Hossain (Customer)
        4, // Receiver: Mizanur Rahman (Seller)
        3, // Seller ID
        "Great, looking forward to tasting authentic Sundarbans honey. Thank you!",
        1,
        date('Y-m-d H:i:s', strtotime('-3 hours'))
    ]);

    echo "2. Sample live messaging threads seeded successfully!\n";
}

echo "All migration tasks done.\n";
