<?php

include "session_security.php";
start_secure_session();

include "db.php";
include_once "order_payment.php";

header("Content-Type: application/json");

$username = trim($_SESSION['username'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($username === '' || $userId <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Please login to view your orders."
    ]);
    exit();
}

$safeUsername = $conn->real_escape_string($username);

$result = $conn->query("
    SELECT
        orders.id,
        orders.customer_name,
        orders.phone,
        orders.address,
        orders.city,
        orders.pincode,
        orders.total,
        orders.payment_method,
        orders.payment_status,
        orders.status,
        orders.order_date,
        order_items.product_name,
        order_items.size,
        order_items.price,
        order_items.quantity
    FROM orders
    LEFT JOIN order_items
    ON orders.id = order_items.order_id
    WHERE orders.user_id = {$userId}
    ORDER BY orders.id DESC, order_items.product_name ASC, order_items.size ASC
");

$orders = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orderId = (int)$row['id'];

        if (!isset($orders[$orderId])) {
            $orders[$orderId] = [
                "customer_name" => $row['customer_name'],
                "phone" => $row['phone'],
                "address" => $row['address'],
                "city" => $row['city'],
                "pincode" => $row['pincode'],
                "total" => (float)$row['total'],
                "payment_method" => normalize_payment_method($row['payment_method'] ?? 'cod', null, 'cod'),
                "payment_status" => normalize_payment_status($row['payment_status'] ?? '', $row['payment_method'] ?? 'cod'),
                "status" => $row['status'],
                "order_date" => $row['order_date'],
                "order_date_iso" => !empty($row['order_date']) ? date(DATE_ATOM, strtotime($row['order_date'])) : null,
                "items" => []
            ];
        }

        if (!empty($row['product_name'])) {
            $orders[$orderId]["items"][] = [
                "product_name" => $row['product_name'],
                "size" => $row['size'],
                "price" => (float)$row['price'],
                "quantity" => (int)$row['quantity']
            ];
        }
    }
}

echo json_encode([
    "status" => "success",
    "orders" => array_values($orders)
]);

?>
