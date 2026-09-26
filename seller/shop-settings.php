<?php
require_once __DIR__ . '/../includes/functions.php';
requireSeller();

$seller = currentSeller();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_shop_settings'])) {
    $shopName = trim($_POST['shop_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $division = trim($_POST['division'] ?? '');
    $bkashNumber = trim($_POST['bkash_number'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $bankAcc = trim($_POST['bank_account_no'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (!empty($shopName)) {
        $up = $db->prepare("UPDATE `sellers` SET 
            `shop_name` = ?, `phone` = ?, `address` = ?, `district` = ?, 
            `division` = ?, `bkash_number` = ?, `bank_name` = ?, 
            `bank_account_no` = ?, `description` = ?, `allow_self_purchase` = ? 
            WHERE `id` = ?");
        $up->execute([
            $shopName, $phone, $address, $district, 
            $division, $bkashNumber, $bankName, 
            $bankAcc, $desc, isset($_POST['allow_self_purchase']) ? 1 : 0, $seller['id']
        ]);
        setFlash('success', 'Workshop profile and payout details updated successfully.');
    }
}

header('Location: ' . BASE_URL . 'seller/#settings');
exit;
