<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/image_upload_helper.php";
include "../php/settings_store.php";
include "../php/csrf.php";
validate_csrf_or_exit(false);

function redirect_add_product_error($message) {
    header("Location: add_product.php?error=".urlencode($message));
    exit();
}

function validate_non_negative_decimal($value) {
    $rawValue = trim((string)$value);

    if($rawValue === '' || !is_numeric($rawValue)){
        return null;
    }

    return (float)$rawValue;
}

function validate_non_negative_integer($value) {
    $rawValue = trim((string)$value);
    
    if($rawValue === '' || preg_match('/^\d+$/', $rawValue) !== 1){
        return null;
    }

    return (int)$rawValue;
}

function product_identity_exists($conn, $name, $size) {
    $checkStmt = $conn->prepare("SELECT id FROM products WHERE name = ? AND size = ? LIMIT 1");

    if (!$checkStmt) {
        return false;
    }

    $checkStmt->bind_param("ss", $name, $size);
    $executed = $checkStmt->execute();
    $result = $executed ? $checkStmt->get_result() : false;
    $exists = $result && $result->fetch_assoc();
    $checkStmt->close();

    return (bool)$exists;
}

$name = trim($_POST['name'] ?? '');
$size = trim($_POST['size'] ?? '');
$price = validate_non_negative_decimal($_POST['price'] ?? '');
$stock = validate_non_negative_integer($_POST['stock'] ?? '');
$shippingMode = normalize_shipping_mode($_POST['shipping_mode'] ?? 'default', 'default');
$shippingCost = $shippingMode === 'flat' ? normalize_shipping_cost($_POST['shipping_cost'] ?? 0) : 0;
$status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
$isPopular = isset($_POST['is_popular']) ? 1 : 0;
$continueToVariant = isset($_POST['continue_to_variant']);
$description = trim($_POST['description'] ?? '');

if($name === '' || $size === '' || $description === ''){
    redirect_add_product_error("Please complete all required product fields.");
}

if($price === null || $price < 0){
    redirect_add_product_error("Product price must be a valid non-negative number.");
}

if($stock === null){
    redirect_add_product_error("Product stock must be a valid non-negative whole number.");
}

if(product_identity_exists($conn, $name, $size)){
    redirect_add_product_error("A product with the same name and size already exists.");
}

$uploadResult = upload_product_image($_FILES['image_file'] ?? null, dirname(__DIR__) . DIRECTORY_SEPARATOR . "images", true);

if(!$uploadResult['success']){
    redirect_add_product_error($uploadResult['message'] ?? "Unable to upload the selected image.");
}

$image = $uploadResult['filename'];

$stmt = $conn->prepare("INSERT INTO products (name, size, price, stock, shipping_mode, shipping_cost, status, is_popular, image, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if(!$stmt){
    @unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . $image);
    redirect_add_product_error("Unable to prepare the product insert. Please try again.");
}

$stmt->bind_param("ssdisdsiss", $name, $size, $price, $stock, $shippingMode, $shippingCost, $status, $isPopular, $image, $description);

if(!$stmt->execute()){
    $stmt->close();
    @unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . $image);
    redirect_add_product_error("Unable to add the product. Please try again.");
}

$newProductId = $conn->insert_id;
$stmt->close();

if($continueToVariant && $newProductId > 0){
    header("Location: add_variant.php?id=".$newProductId);
    exit();
}

header("Location: products.php");
exit();
?>
