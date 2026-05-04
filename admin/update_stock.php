<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/csrf.php";
validate_csrf_or_exit(false);

function validate_non_negative_integer($value) {
    $rawValue = trim((string)$value);

    if($rawValue === '' || preg_match('/^\d+$/', $rawValue) !== 1){
        return null;
    }

    return (int)$rawValue;
}

$productId = (int)($_POST['product_id'] ?? 0);
$stock = validate_non_negative_integer($_POST['stock'] ?? '');

if($productId <= 0){
    header("Location: products.php?error=".urlencode("Invalid product selected for stock update."));
    exit();
}

if($stock === null){
    header("Location: products.php?error=".urlencode("Stock must be a valid non-negative whole number."));
    exit();
}

$checkStmt = $conn->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");

if(!$checkStmt){
    header("Location: products.php?error=".urlencode("Unable to prepare the stock update."));
    exit();
}

$checkStmt->bind_param("i", $productId);

if(!$checkStmt->execute()){
    $checkStmt->close();
    header("Location: products.php?error=".urlencode("Unable to verify the selected product."));
    exit();
}

$checkResult = $checkStmt->get_result();
$productExists = $checkResult && $checkResult->fetch_assoc();
$checkStmt->close();

if(!$productExists){
    header("Location: products.php?error=".urlencode("Selected product was not found."));
    exit();
}

$stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");

if(!$stmt){
    header("Location: products.php?error=".urlencode("Unable to prepare the stock update."));
    exit();
}

$stmt->bind_param("ii", $stock, $productId);

if(!$stmt->execute()){
    $stmt->close();
    header("Location: products.php?error=".urlencode("Unable to update stock. Please try again."));
    exit();
}

$stmt->close();
header("Location: products.php");
exit();

?>
