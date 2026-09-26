<?php
require_once __DIR__ . '/../includes/functions.php';

// Redirect to unified Admin Sellers management tab
requireAdmin();
header('Location: ' . BASE_URL . 'admin/#sellers');
exit;
