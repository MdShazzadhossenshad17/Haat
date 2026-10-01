<?php
// =========================================================
// HAAT! API — Orders
// GET  /api/orders.php              list own orders (customer)
// GET  /api/orders.php?id=:id       single order detail
// GET  /api/orders.php?action=all   all orders (admin)
// POST /api/orders.php              place order (customer)
// PUT  /api/orders.php?id=:id       cancel order (customer / update status admin)
// =========================================================

require_once __DIR__ . '/helpers.php';
boot();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = auth_required();
$id     = (int) query('id', 0);
$action = query('action', '');

// ─────────────────────────────────────────────────────────
// GET
// ─────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Admin: all orders
    if ($action === 'all') {
        role_required('admin');
        $p      = paginate(20);
        $status = query('status', '');
        $where  = $status ? "WHERE o.order_status = ?" : '';
        $params = $status ? [$status] : [];

        $stmt = $db->prepare(
            "SELECT o.id, o.order_number, o.grand_total, o.order_status,
                    o.shipping_name, o.division, o.district, o.created_at,
                    u.name AS customer_name, u.email AS customer_email
             FROM orders o
             JOIN users u ON u.id = o.user_id
             $where
             ORDER BY o.created_at DESC
             LIMIT {$p['limit']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        json_ok(['orders' => $stmt->fetchAll(), 'page' => $p['page']]);
    }

    // Single order detail
    if ($id) {
        $stmt = $db->prepare(
            "SELECT o.*, u.name AS customer_name, u.email AS customer_email
             FROM orders o
             JOIN users u ON u.id = o.user_id
             WHERE o.id = ?"
        );
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) json_error('Order not found', 404);

        // Allow only the owner or admin
        if ($user['role'] !== 'admin' && (int) $order['user_id'] !== $user['id']) {
            json_error('Forbidden', 403);
        }

        // Seller sub-orders with items
        $soStmt = $db->prepare(
            "SELECT so.id, so.seller_order_number, so.status,
                    so.subtotal, so.shipping_cost, so.seller_total,
                    s.store_name, s.store_slug
             FROM seller_orders so
             JOIN stores s ON s.id = so.store_id
             WHERE so.order_id = ?"
        );
        $soStmt->execute([$id]);
        $sellerOrders = $soStmt->fetchAll();

        foreach ($sellerOrders as &$so) {
            $itemStmt = $db->prepare(
                'SELECT oi.*, "/HAAT!/api/images.php?type=product&id=" AS img_base
                 FROM order_items oi WHERE oi.seller_order_id = ?'
            );
            $itemStmt->execute([$so['id']]);
            $items = $itemStmt->fetchAll();
            foreach ($items as &$it) {
                $it['image_url'] = "/HAAT!/api/images.php?type=product&id={$it['product_id']}&n=1";
            }
            $so['items'] = $items;
        }

        // Payment
        $payStmt = $db->prepare('SELECT method, status, amount, paid_at FROM payments WHERE order_id = ?');
        $payStmt->execute([$id]);
        $order['payment']       = $payStmt->fetch() ?: null;
        $order['seller_orders'] = $sellerOrders;

        json_ok($order);
    }

    // Customer: list own orders
    $p      = paginate(10);
    $status = query('status', '');
    $where  = ["o.user_id = ?"];
    $params = [$user['id']];
    if ($status) { $where[] = 'o.order_status = ?'; $params[] = $status; }

    $whereStr = 'WHERE ' . implode(' AND ', $where);
    $stmt = $db->prepare(
        "SELECT o.id, o.order_number, o.grand_total, o.order_status,
                o.shipping_cost, o.discount_amount, o.created_at
         FROM orders o
         $whereStr
         ORDER BY o.created_at DESC
         LIMIT {$p['limit']} OFFSET {$p['offset']}"
    );
    $stmt->execute($params);

    $cStmt = $db->prepare("SELECT COUNT(*) FROM orders o $whereStr");
    $cStmt->execute($params);

    json_ok(['orders' => $stmt->fetchAll(), 'total' => (int) $cStmt->fetchColumn(), 'page' => $p['page']]);
}

// ─────────────────────────────────────────────────────────
// POST — place order
// Body: { address_id (or shipping fields), coupon_code?,
//         payment_method, items: [{product_id, quantity, variant_name?, variant_value?}] }
// ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    role_required('customer');
    $data = require_body('payment_method');

    $payMethod = $data['payment_method'];
    $items     = $data['items'] ?? [];

    // If no items in body, use cart
    if (!$items) {
        $cartStmt = $db->prepare(
            "SELECT c.product_id, c.quantity, c.variant_name, c.variant_value
             FROM cart c WHERE c.user_id = ?"
        );
        $cartStmt->execute([$user['id']]);
        $items = $cartStmt->fetchAll();
    }
    if (!$items) json_error('Cart is empty', 400);

    // Resolve shipping address
    if (!empty($data['address_id'])) {
        $addrStmt = $db->prepare('SELECT * FROM customer_addresses WHERE id = ? AND user_id = ?');
        $addrStmt->execute([(int) $data['address_id'], $user['id']]);
        $addr = $addrStmt->fetch();
        if (!$addr) json_error('Address not found', 404);
        $shipName    = $addr['name'];
        $shipPhone   = $addr['phone'];
        $shipAddress = $addr['address'];
        $shipDistrict= $addr['district'];
        $shipDivision= $addr['division'];
        $shipPostal  = $addr['postal_code'] ?? '';
    } else {
        // Inline address fields
        $shipName    = $data['shipping_name']    ?? '';
        $shipPhone   = $data['shipping_phone']   ?? '';
        $shipAddress = $data['shipping_address'] ?? '';
        $shipDistrict= $data['district']         ?? '';
        $shipDivision= $data['division']         ?? '';
        $shipPostal  = $data['postal_code']      ?? '';
        if (!$shipName || !$shipPhone || !$shipAddress || !$shipDistrict || !$shipDivision) {
            json_error('Shipping address required', 422);
        }
    }

    // ── Validate all items & calculate totals ──────────────
    $validated   = [];
    $totalAmount = 0;

    foreach ($items as $item) {
        $pid = (int) $item['product_id'];
        $qty = max(1, (int) $item['quantity']);

        $pStmt = $db->prepare(
            "SELECT p.id, p.name, p.price, p.sale_price, p.store_id,
                    COALESCE(i.quantity - i.reserved_quantity, 0) AS available
             FROM products p
             LEFT JOIN inventory i ON i.product_id = p.id
             WHERE p.id = ?"
        );
        $pStmt->execute([$pid]);
        $product = $pStmt->fetch();
        if (!$product) json_error("Product #$pid not found", 404);
        if ($product['available'] < $qty) {
            json_error("Insufficient stock for: {$product['name']}", 400);
        }

        $unitPrice  = (float) ($product['sale_price'] ?: $product['price']);
        $subtotal   = $unitPrice * $qty;
        $totalAmount += $subtotal;

        $validated[] = [
            'product_id'    => $pid,
            'product_name'  => $product['name'],
            'store_id'      => (int) $product['store_id'],
            'quantity'      => $qty,
            'unit_price'    => $unitPrice,
            'subtotal'      => $subtotal,
            'variant_name'  => $item['variant_name']  ?? null,
            'variant_value' => $item['variant_value'] ?? null,
        ];
    }

    // ── Coupon ─────────────────────────────────────────────
    $couponId      = null;
    $discountAmount = 0;
    if (!empty($data['coupon_code'])) {
        $cStmt = $db->prepare(
            "SELECT * FROM coupons
             WHERE code = ? AND is_active = 1
               AND (expiry_date IS NULL OR expiry_date > NOW())
               AND (usage_limit IS NULL OR used_count < usage_limit)
               AND min_order <= ?"
        );
        $cStmt->execute([strtoupper(trim($data['coupon_code'])), $totalAmount]);
        $coupon = $cStmt->fetch();
        if ($coupon) {
            $couponId = (int) $coupon['id'];
            if ($coupon['discount_type'] === 'percent') {
                $discountAmount = $totalAmount * ($coupon['discount_value'] / 100);
                if ($coupon['max_discount']) $discountAmount = min($discountAmount, (float) $coupon['max_discount']);
            } else {
                $discountAmount = min((float) $coupon['discount_value'], $totalAmount);
            }
        }
    }

    $shippingCost = (float) ($data['shipping_cost'] ?? 0);
    $grandTotal   = $totalAmount - $discountAmount + $shippingCost;
    $orderNumber  = generate_order_number();

    // ── Begin transaction ──────────────────────────────────
    $db->beginTransaction();
    try {
        // Insert order
        $oStmt = $db->prepare(
            'INSERT INTO orders
             (order_number, user_id, coupon_id, total_amount, shipping_cost,
              discount_amount, grand_total, shipping_name, shipping_phone,
              shipping_address, district, division, postal_code, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $oStmt->execute([
            $orderNumber, $user['id'], $couponId,
            round($totalAmount, 2), round($shippingCost, 2),
            round($discountAmount, 2), round($grandTotal, 2),
            $shipName, $shipPhone, $shipAddress,
            $shipDistrict, $shipDivision, $shipPostal,
            $data['notes'] ?? null,
        ]);
        $orderId = (int) $db->lastInsertId();

        // Group items by store → seller_orders
        $storeGroups = [];
        foreach ($validated as $v) {
            $storeGroups[$v['store_id']][] = $v;
        }

        foreach ($storeGroups as $storeId => $storeItems) {
            $storeSubtotal = array_sum(array_column($storeItems, 'subtotal'));
            $soNumber      = generate_seller_order_number($storeId);

            $soStmt = $db->prepare(
                'INSERT INTO seller_orders
                 (order_id, store_id, seller_order_number, subtotal, seller_total)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $soStmt->execute([$orderId, $storeId, $soNumber,
                round($storeSubtotal, 2), round($storeSubtotal, 2)]);
            $soId = (int) $db->lastInsertId();

            foreach ($storeItems as $item) {
                $db->prepare(
                    'INSERT INTO order_items
                     (seller_order_id, product_id, product_name,
                      variant_name, variant_value, unit_price, quantity, subtotal)
                     VALUES (?,?,?,?,?,?,?,?)'
                )->execute([
                    $soId, $item['product_id'], $item['product_name'],
                    $item['variant_name'], $item['variant_value'],
                    $item['unit_price'], $item['quantity'], $item['subtotal'],
                ]);

                // Reserve inventory
                $db->prepare(
                    'UPDATE inventory
                     SET reserved_quantity = reserved_quantity + ?
                     WHERE product_id = ?'
                )->execute([$item['quantity'], $item['product_id']]);
            }

            // Notify seller
            $sellerStmt = $db->prepare('SELECT user_id FROM stores WHERE id = ?');
            $sellerStmt->execute([$storeId]);
            $sellerRow = $sellerStmt->fetch();
            if ($sellerRow) {
                $db->prepare(
                    'INSERT INTO notifications (user_id, order_id, title, message, type)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $sellerRow['user_id'], $orderId,
                    'New Order Received',
                    "You have a new order #{$soNumber} — ৳" . number_format($storeSubtotal, 2),
                    'new_order',
                ]);
            }
        }

        // Create payment record
        $db->prepare(
            'INSERT INTO payments (order_id, method, amount) VALUES (?, ?, ?)'
        )->execute([$orderId, $payMethod, round($grandTotal, 2)]);

        // Update coupon usage
        if ($couponId) {
            $db->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?')->execute([$couponId]);
        }

        // Clear cart
        $db->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$user['id']]);

        // Notify customer
        $db->prepare(
            'INSERT INTO notifications (user_id, order_id, title, message, type)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $user['id'], $orderId,
            'Order Placed Successfully',
            "Your order #{$orderNumber} for ৳" . number_format($grandTotal, 2) . " has been placed.",
            'order_placed',
        ]);

        $db->commit();

        json_ok([
            'message'      => 'Order placed successfully',
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'grand_total'  => round($grandTotal, 2),
        ], 201);

    } catch (Exception $e) {
        $db->rollBack();
        json_error('Order failed: ' . $e->getMessage(), 500);
    }
}

// ─────────────────────────────────────────────────────────
// PUT — cancel order (customer) / update status (admin)
// ─────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) json_error('Order id required', 422);

    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) json_error('Order not found', 404);

    $data = get_body();

    if ($user['role'] === 'customer') {
        // Customer can only cancel pending orders
        if ((int) $order['user_id'] !== $user['id']) json_error('Forbidden', 403);
        if ($order['order_status'] !== 'pending') json_error('Only pending orders can be cancelled', 400);
        $db->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?")->execute([$id]);
        json_ok(['message' => 'Order cancelled']);
    }

    if ($user['role'] === 'admin') {
        $newStatus = $data['order_status'] ?? null;
        if (!$newStatus) json_error('order_status required', 422);
        $db->prepare('UPDATE orders SET order_status = ? WHERE id = ?')->execute([$newStatus, $id]);
        json_ok(['message' => 'Order status updated']);
    }

    json_error('Forbidden', 403);
}

json_error('Method not allowed', 405);
