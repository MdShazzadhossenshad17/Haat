<?php
require_once __DIR__ . '/../includes/functions.php';

// Redirect to unified Admin HAATEX Logistics management tab
requireAdmin();
header('Location: ' . BASE_URL . 'admin/#haatex');
exit;
