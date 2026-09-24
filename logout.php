<?php
/**
 * Logout Script
 * HAAT Multi-Vendor Marketplace
 */
require_once __DIR__ . '/includes/functions.php';

session_unset();
session_destroy();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

setFlash('success', 'You have been safely signed out. Thank you for visiting HAAT!');
header('Location: ' . BASE_URL);
exit;
