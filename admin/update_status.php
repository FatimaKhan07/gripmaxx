<?php

include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/csrf.php";
include_once "../php/order_payment.php";
validate_csrf_or_exit(false);

$order_id = (int)($_POST['order_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$allowedStatuses = ["Pending", "Processing", "Shipped", "Delivered", "Cancelled"];

if ($order_id <= 0 || !in_array($status, $allowedStatuses, true)) {
    header("Location: orders.php?error=".urlencode("Invalid order status update request."));
    exit();
}

$errorMessage = "";

if(!$conn->begin_transaction()){
    header("Location: orders.php?error=".urlencode("Unable to start the order update. Please try again."));
    exit();
}

try {
    $orderResult = $conn->query("SELECT id, status, payment_method, payment_status FROM orders WHERE id = {$order_id} FOR UPDATE");

    if (!$orderResult || $orderResult->num_rows === 0) {
        throw new Exception("Order not found.");
    }

    $order = $orderResult->fetch_assoc();
    $currentStatus = $order['status'] ?? 'Pending';
    $paymentMethod = normalize_payment_method($order['payment_method'] ?? 'cod', null, 'cod');
    $paymentStatus = normalize_payment_status($order['payment_status'] ?? '', $paymentMethod);
    $currentReservesStock = order_reserves_stock($currentStatus, $paymentMethod, $paymentStatus);
    $updatedReservesStock = order_reserves_stock($status, $paymentMethod, $paymentStatus);

    if ($currentStatus !== $status && $currentReservesStock !== $updatedReservesStock) {
        $itemsResult = $conn->query("
            SELECT product_id, product_name, size, quantity
            FROM order_items
            WHERE order_id = {$order_id}
        ");

        if (!$itemsResult) {
            throw new Exception("Unable to load order items.");
        }

        while ($item = $itemsResult->fetch_assoc()) {
            $productId = (int)($item['product_id'] ?? 0);
            $safeProductName = $conn->real_escape_string($item['product_name'] ?? '');
            $safeSize = $conn->real_escape_string($item['size'] ?? '');
            $quantity = max(0, (int)$item['quantity']);

            if ($quantity <= 0) {
                continue;
            }

            if ($productId > 0) {
                $productResult = $conn->query("
                    SELECT id, stock
                    FROM products
                    WHERE id = {$productId}
                    LIMIT 1
                    FOR UPDATE
                ");
            } else {
                // Backward compatibility for older order_items rows without product_id.
                $productResult = $conn->query("
                    SELECT id, stock
                    FROM products
                    WHERE name = '{$safeProductName}'
                    AND size = '{$safeSize}'
                    LIMIT 1
                    FOR UPDATE
                ");
            }

            if (!$productResult || $productResult->num_rows === 0) {
                throw new Exception("Unable to match a product for stock adjustment.");
            }

            $product = $productResult->fetch_assoc();
            $resolvedProductId = (int)$product['id'];
            $currentStock = (int)$product['stock'];

            if ($currentReservesStock && !$updatedReservesStock) {
                if (!$conn->query("UPDATE products SET stock = stock + {$quantity} WHERE id = {$resolvedProductId}")) {
                    throw new Exception("Unable to restore stock for a cancelled order.");
                }
            }

            if (!$currentReservesStock && $updatedReservesStock) {
                if ($currentStock < $quantity) {
                    throw new Exception("Not enough stock to reopen this cancelled order.");
                }

                if (!$conn->query("UPDATE products SET stock = stock - {$quantity} WHERE id = {$resolvedProductId}")) {
                    throw new Exception("Unable to reserve stock for the updated order.");
                }
            }
        }
    }

    $safeStatus = $conn->real_escape_string($status);
    $sql = "UPDATE orders SET status='{$safeStatus}' WHERE id={$order_id}";

    if (!$conn->query($sql)) {
        throw new Exception("Unable to update order status.");
    }

    if (!$conn->commit()) {
        throw new Exception("Unable to save the order status update.");
    }
} catch (Exception $exception) {
    $conn->rollback();
    $errorMessage = $exception->getMessage();
}

if($errorMessage !== ""){
    header("Location: orders.php?error=".urlencode($errorMessage));
    exit();
}

header("Location: orders.php");
exit();

?>
