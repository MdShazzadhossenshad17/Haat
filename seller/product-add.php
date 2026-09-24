<?php
require_once __DIR__ . '/../includes/functions.php';
requireSeller();
header('Location: ' . BASE_URL . 'seller/#add-product');
exit;
