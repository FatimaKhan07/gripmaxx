<?php

include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/csrf.php";
include_once "../php/order_payment.php";
validate_csrf_or_exit(false);

$orderId = (int)($_POST['order_id'] ?? 0);
$requestedStatus = trim($_POST['payment_status'] ?? '');

if ($orderId <= 0) {
    header("Location: orders.php?error=" . urlencode("Invalid payment status update request."));
    exit();
}

if (!$conn->begin_transaction()) {
    header("Location: orders.php?error=" . urlencode("Unable to start the payment update. Please try again."));
    exit();
}

$errorMessage = "";

try {
    $orderResult = $conn->query("SELECT id, status, payment_method, payment_status FROM orders WHERE id = {$orderId} FOR UPDATE");

    if (!$orderResult || $orderResult->num_rows === 0) {
        throw new Exception("Order not found.");
    }

    $order = $orderResult->fetch_assoc();
    $orderStatus = trim((string)($order['status'] ?? 'Pending'));
    $paymentMethod = normalize_payment_method($order['payment_method'] ?? 'cod', null, 'cod');
    $currentPaymentStatus = normalize_payment_status($order['payment_status'] ?? '', $paymentMethod);
    $allowedStatuses = get_payment_status_options_for_method($paymentMethod);

    if (!in_array($requestedStatus, $allowedStatuses, true)) {
        throw new Exception("Invalid payment status for this order.");
    }

    $paymentStatus = $requestedStatus;
    $currentlyReservesStock = order_reserves_stock($orderStatus, $paymentMethod, $currentPaymentStatus);
    $updatedReservesStock = order_reserves_stock($orderStatus, $paymentMethod, $paymentStatus);

    if ($currentlyReservesStock !== $updatedReservesStock) {
        $itemsResult = $conn->query("
            SELECT product_id, product_name, size, quantity
            FROM order_items
            WHERE order_id = {$orderId}
        ");

        if (!$itemsResult) {
            throw new Exception("Unable to load order items.");
        }

        while ($item = $itemsResult->fetch_assoc()) {
            $productId = (int)($item['product_id'] ?? 0);
            $safeProductName = $conn->real_escape_string($item['product_name'] ?? '');
            $safeSize = $conn->real_escape_string($item['size'] ?? '');
            $quantity = max(0, (int)($item['quantity'] ?? 0));

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

            if (!$currentlyReservesStock && $updatedReservesStock) {
                if ($currentStock < $quantity) {
                    throw new Exception("Not enough stock remains to verify this payment.");
                }

                if (!$conn->query("UPDATE products SET stock = stock - {$quantity} WHERE id = {$resolvedProductId}")) {
                    throw new Exception("Unable to reserve stock for the verified payment.");
                }
            }

            if ($currentlyReservesStock && !$updatedReservesStock) {
                if (!$conn->query("UPDATE products SET stock = stock + {$quantity} WHERE id = {$resolvedProductId}")) {
                    throw new Exception("Unable to restore stock after payment status change.");
                }
            }
        }
    }

    $safePaymentStatus = $conn->real_escape_string($paymentStatus);

    if (!$conn->query("UPDATE orders SET payment_status = '{$safePaymentStatus}' WHERE id = {$orderId}")) {
        throw new Exception("Unable to update payment status.");
    }

    if (!$conn->commit()) {
        throw new Exception("Unable to save the payment status update.");
    }
} catch (Exception $exception) {
    $conn->rollback();
    $errorMessage = $exception->getMessage();
}

if ($errorMessage !== "") {
    header("Location: orders.php?error=" . urlencode($errorMessage));
    exit();
}

header("Location: orders.php");
exit();

?>
