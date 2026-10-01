<?php
// =========================================================
// HAAT! API — Payments
// GET /api/payments.php?order_id=:id   get payment for order
// PUT /api/payments.php?order_id=:id   submit reference (customer)
// PUT /api/payments.php?order_id=:id&action=verify  verify (admin)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db      = getDB();
$method  = $_SERVER['REQUEST_METHOD'];
$user    = auth_required();
$orderId = (int) query('order_id', 0);
$action  = query('action', '');

if (!$orderId) json_error('order_id required', 422);

// ─── GET ─────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT p.*, o.user_id FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.order_id = ?'
    );
    $stmt->execute([$orderId]);
    $pay = $stmt->fetch();
    if (!$pay) json_error('Payment not found', 404);

    if ($user['role'] !== 'admin' && (int) $pay['user_id'] !== $user['id']) {
        json_error('Forbidden', 403);
    }
    unset($pay['user_id']);
    json_ok($pay);
}

// ─── PUT — submit reference or verify ────────────────────
if ($method === 'PUT') {

    if ($action === 'verify') {
        // Admin verifies payment
        role_required('admin');
        $db->prepare(
            "UPDATE payments SET status = 'verified', paid_at = NOW() WHERE order_id = ?"
        )->execute([$orderId]);
        $db->prepare(
            "UPDATE orders SET order_status = 'confirmed' WHERE id = ?"
        )->execute([$orderId]);

        // Notify customer
        $cusStmt = $db->prepare('SELECT user_id FROM orders WHERE id = ?');
        $cusStmt->execute([$orderId]);
        $cusRow = $cusStmt->fetch();
        if ($cusRow) {
            $db->prepare(
                'INSERT INTO notifications (user_id, order_id, title, message, type)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $cusRow['user_id'], $orderId,
                'Payment Verified',
                'Your payment has been verified and your order is confirmed.',
                'payment_verified',
            ]);
        }
        json_ok(['message' => 'Payment verified']);
    }

    // Customer submits transaction reference
    $data = get_body();

    // Ownership check
    $ownStmt = $db->prepare('SELECT user_id FROM orders WHERE id = ?');
    $ownStmt->execute([$orderId]);
    $orderRow = $ownStmt->fetch();
    if (!$orderRow || (int) $orderRow['user_id'] !== $user['id']) json_error('Forbidden', 403);

    $ref = $data['transaction_reference'] ?? null;
    if (!$ref) json_error('transaction_reference required', 422);

    $db->prepare(
        "UPDATE payments SET transaction_reference = ?, status = 'submitted' WHERE order_id = ?"
    )->execute([$ref, $orderId]);

    json_ok(['message' => 'Payment reference submitted — pending verification']);
}

json_error('Method not allowed', 405);
