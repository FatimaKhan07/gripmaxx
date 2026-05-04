<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/image_upload_helper.php";
include "../php/settings_store.php";
include "../php/csrf.php";
validate_csrf_or_exit(false);

function redirect_variant_error($parentProductId, $message) {
    $target = $parentProductId > 0 ? "add_variant.php?id=".$parentProductId : "products.php";
    $separator = strpos($target, "?") === false ? "?" : "&";
    header("Location: ".$target.$separator."error=".urlencode($message));
    exit();
}

function is_valid_variant_size($value) {
    $size = trim((string)$value);

    if($size === '' || strlen($size) > 50){
        return false;
    }

    if(preg_match('/^[a-z0-9][a-z0-9 .()\\/-]*$/i', $size) !== 1){
        return false;
    }

    $blockedSizes = ["ass", "asdf", "test", "xxx", "null", "none", "n/a", "na"];
    return !in_array(strtolower($size), $blockedSizes, true);
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

$parentProductId = (int)($_POST['parent_product_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$size = trim($_POST['size'] ?? '');
$rawPrice = trim((string)($_POST['price'] ?? ''));
$rawStock = trim((string)($_POST['stock'] ?? ''));
$price = 0;
$stock = 0;
$shippingMode = normalize_shipping_mode($_POST['shipping_mode'] ?? 'default', 'default');
$shippingCost = $shippingMode === 'flat' ? normalize_shipping_cost($_POST['shipping_cost'] ?? 0) : 0;
$status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
$isPopular = isset($_POST['is_popular']) ? 1 : 0;
$image = '';
$description = trim($_POST['description'] ?? '');

if(
    $parentProductId <= 0 ||
    $name === '' ||
    $description === ''
){
    redirect_variant_error($parentProductId, "Please complete all required variant fields.");
}

if(!is_valid_variant_size($size)){
    redirect_variant_error($parentProductId, "Variant size is invalid. Use a real size label such as 250g, XL, or Red.");
}

if(!is_numeric($rawPrice) || (float)$rawPrice <= 0){
    redirect_variant_error($parentProductId, "Variant price must be a valid positive number.");
}

if(!preg_match('/^\d+$/', $rawStock)){
    redirect_variant_error($parentProductId, "Variant stock must be a valid non-negative whole number.");
}

$price = (float)$rawPrice;
$stock = (int)$rawStock;

if(product_identity_exists($conn, $name, $size)){
    redirect_variant_error($parentProductId, "A variant with the same product name and size already exists.");
}

$parentLookup = $conn->prepare("SELECT image FROM products WHERE id = ? LIMIT 1");

if(!$parentLookup){
    redirect_variant_error($parentProductId, "Unable to prepare the parent product lookup.");
}

$parentLookup->bind_param("i", $parentProductId);

if(!$parentLookup->execute()){
    $parentLookup->close();
    redirect_variant_error($parentProductId, "Unable to load the parent product.");
}

$parentResult = $parentLookup->get_result();
$parentProduct = $parentResult ? $parentResult->fetch_assoc() : null;
$parentLookup->close();

if(!$parentProduct){
    redirect_variant_error($parentProductId, "Parent product was not found.");
}

$image = trim($parentProduct['image'] ?? '');

$uploadResult = upload_product_image($_FILES['image_file'] ?? null, dirname(__DIR__) . DIRECTORY_SEPARATOR . "images", false);

if(!$uploadResult['success']){
    redirect_variant_error($parentProductId, $uploadResult['message'] ?? "Unable to upload the selected image.");
}

if(!empty($uploadResult['filename'])){
    $image = $uploadResult['filename'];
}

if($image === ''){
    redirect_variant_error($parentProductId, "Variant image is required.");
}

$stmt = $conn->prepare("INSERT INTO products (name, size, price, stock, shipping_mode, shipping_cost, status, is_popular, image, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if(!$stmt){
    if(!empty($uploadResult['filename'])){
        @unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . $uploadResult['filename']);
    }

    redirect_variant_error($parentProductId, "Unable to prepare the variant insert. Please try again.");
}

$stmt->bind_param("ssdisdsiss", $name, $size, $price, $stock, $shippingMode, $shippingCost, $status, $isPopular, $image, $description);

if(!$stmt->execute()){
    $stmt->close();

    if(!empty($uploadResult['filename'])){
        @unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . $uploadResult['filename']);
    }

    redirect_variant_error($parentProductId, "Unable to add the variant. Please try again.");
}

$stmt->close();
header("Location: edit_product.php?id=".$parentProductId);
exit();
?>
