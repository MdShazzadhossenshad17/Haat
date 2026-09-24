<?php
require_once __DIR__ . '/../includes/functions.php';
requireSeller();

if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    header('Location: ' . BASE_URL . 'seller/?delete_product=' . $delId);
    exit;
}

header('Location: ' . BASE_URL . 'seller/#products');
exit;
