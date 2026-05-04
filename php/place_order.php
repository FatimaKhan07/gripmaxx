<?php

include "session_security.php";
start_secure_session();

include "db.php";
include "settings_store.php";
include_once "order_payment.php";
include "csrf.php";

header("Content-Type: application/json");
validate_csrf_or_exit(true);

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$pincode = trim($_POST['pincode'] ?? '');
$paymentMethod = 'cod';
$codConfirmation = (int)($_POST['cod_confirmation'] ?? 0);
$accountUsername = trim($_SESSION['username'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

$cart = json_decode($_POST['cart'] ?? '[]', true);

function order_field_length_between($value, $min, $max) {
    $length = strlen(trim((string)$value));
    return $length >= $min && $length <= $max;
}

if ($userId <= 0 || $accountUsername === '') {
    echo json_encode(["status" => "error", "message" => "Please login again before placing the order."]);
    exit();
}

if ($name === '' || $phone === '' || $address === '' || $city === '' || $pincode === '' || !is_array($cart) || count($cart) === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid order details."]);
    exit();
}

if (!order_field_length_between($name, 3, 120)) {
    echo json_encode(["status" => "error", "message" => "Please enter a valid full name."]);
    exit();
}

if (!preg_match('/^[0-9]{10}$/', $phone)) {
    echo json_encode(["status" => "error", "message" => "Phone number must be 10 digits."]);
    exit();
}

if (!order_field_length_between($address, 5, 255) || preg_match('/[a-z0-9]/i', $address) !== 1) {
    echo json_encode(["status" => "error", "message" => "Please enter a valid address."]);
    exit();
}

if (!order_field_length_between($city, 3, 100) || preg_match('/^[a-zA-Z][a-zA-Z .-]{1,99}$/', $city) !== 1) {
    echo json_encode(["status" => "error", "message" => "Please enter a valid city."]);
    exit();
}

if (!preg_match('/^[0-9]{6}$/', $pincode)) {
    echo json_encode(["status" => "error", "message" => "Pincode must be 6 digits."]);
    exit();
}

$settings = load_app_settings();
$paymentStatus = get_default_payment_status($paymentMethod);

if ($paymentMethod === 'cod' && $codConfirmation !== 1) {
    echo json_encode(["status" => "error", "message" => "Please confirm that your COD contact details are correct before placing the order."]);
    exit();
}

$shippingCost = count($cart) > 0 ? (float)($settings['shipping_cost'] ?? 0) : 0.0;
$calculatedSubtotal = 0;
$validatedItems = [];

$conn->begin_transaction();

try {
    foreach ($cart as $item) {

        $productId = (int)($item['id'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 0);

        if ($productId <= 0) {
            throw new Exception("Invalid product in cart.");
        }

        if ($quantity <= 0) {
            throw new Exception("Invalid quantity selected for one of the products.");
        }

        $productResult = $conn->query("SELECT id, name, size, price, stock, status, shipping_mode, shipping_cost FROM products WHERE id = {$productId} FOR UPDATE");

        if (!$productResult || $productResult->num_rows === 0) {
            throw new Exception("A product in your cart is no longer available.");
        }

        $product = $productResult->fetch_assoc();

        if (($product['status'] ?? 'inactive') !== 'active') {
            throw new Exception($product['name']." (".$product['size'].") is no longer available.");
        }

        if ((int)$product['stock'] < $quantity) {
            throw new Exception($product['name']." (".$product['size'].") has only ".$product['stock']." left in stock.");
        }

        $calculatedSubtotal += ((float)$product['price'] * $quantity);
        $validatedItems[] = [
            "id" => (int)$product['id'],
            "name" => $product['name'],
            "size" => $product['size'],
            "price" => (float)$product['price'],
            "shipping_mode" => $product['shipping_mode'] ?? 'default',
            "shipping_cost" => (float)($product['shipping_cost'] ?? 0),
            "quantity" => $quantity
        ];
    }

    $shippingCost = calculate_order_shipping($validatedItems, $settings);
    $total = $calculatedSubtotal + $shippingCost;

    $orderStmt = $conn->prepare("
        INSERT INTO orders
        (user_id, account_username, customer_name, phone, address, city, pincode, total, payment_method, payment_status, status, order_date)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
    ");

    if (!$orderStmt) {
        throw new Exception("Unable to place the order.");
    }

    $orderStmt->bind_param("issssssdss", $userId, $accountUsername, $name, $phone, $address, $city, $pincode, $total, $paymentMethod, $paymentStatus);

    if (!$orderStmt->execute()) {
        $orderStmt->close();
        throw new Exception("Unable to place the order.");
    }

    $order_id = $conn->insert_id;
    $orderStmt->close();

    $orderItemStmt = $conn->prepare("
        INSERT INTO order_items
        (order_id, product_id, product_name, size, price, quantity)
        VALUES
        (?, ?, ?, ?, ?, ?)
    ");

    if (!$orderItemStmt) {
        throw new Exception("Unable to save the order items.");
    }

    foreach ($validatedItems as $item) {
        $productName = $item['name'];
        $size = $item['size'];
        $price = (float)$item['price'];
        $quantity = $item['quantity'];
        $productId = $item['id'];

        $orderItemStmt->bind_param("iissdi", $order_id, $productId, $productName, $size, $price, $quantity);

        if (!$orderItemStmt->execute()) {
            $orderItemStmt->close();
            throw new Exception("Unable to save the order items.");
        }

        if (!$conn->query("UPDATE products SET stock = stock - {$quantity} WHERE id = {$productId}")) {
            throw new Exception("Unable to update stock.");
        }
    }

    $orderItemStmt->close();

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Order placed successfully.",
        "total" => $total,
        "payment_method" => $paymentMethod,
        "payment_status" => $paymentStatus
    ]);

} catch (Exception $exception) {

    $conn->rollback();
    echo json_encode([
        "status" => "error",
        "message" => $exception->getMessage()
    ]);
}

?>
