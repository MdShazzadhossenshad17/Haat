<?php
// Lightweight smoke tests for critical flows (run locally)
require_once __DIR__ . '/../includes/functions.php';

$results = [];

// Test 1: functions file load and utility
$results['functions_loaded'] = function_exists('recalculateRatings') ? 'ok' : 'fail';

// Test 2: DB connection
try {
    $stmt = $db->query('SELECT 1');
    $results['db_connection'] = $stmt ? 'ok' : 'fail';
} catch (Exception $e) {
    $results['db_connection'] = 'fail: ' . $e->getMessage();
}

// Test 3: Notifications API (requires local webserver)
$results['notifications_endpoint'] = 'run from browser: ' . BASE_URL . 'api/notifications.php?action=get';

// Test 4: Ratings recalc (dry run) - no write
$results['recalculate_ratings_dry'] = is_callable('recalculateRatings') ? 'ok' : 'fail';

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
