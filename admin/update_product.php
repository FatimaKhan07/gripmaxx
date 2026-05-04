<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/image_upload_helper.php";
include "../php/settings_store.php";
include "../php/csrf.php";
validate_csrf_or_exit(false);

function redirect_edit_product_error($productId, $message) {
    if($productId <= 0){
        header("Location: products.php?error=".urlencode($message));
        exit();
    }

    header("Location: edit_product.php?id=".$productId."&error=".urlencode($message));
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

function get_safe_image_basename($filename) {
    $trimmed = trim((string)$filename);

    if($trimmed === ''){
        return '';
    }

    $basename = basename($trimmed);

    if($basename !== $trimmed){
        return '';
    }

    return preg_match('/^[a-z0-9._-]+$/i', $basename) === 1 ? $basename : '';
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

$productId = (int)($_POST['product_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$size = trim($_POST['size'] ?? '');
$price = validate_non_negative_decimal($_POST['price'] ?? '');
$stock = validate_non_negative_integer($_POST['stock'] ?? '');
$shippingMode = normalize_shipping_mode($_POST['shipping_mode'] ?? 'default', 'default');
$shippingCost = $shippingMode === 'flat' ? normalize_shipping_cost($_POST['shipping_cost'] ?? 0) : 0;
$status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
$isPopular = isset($_POST['is_popular']) ? 1 : 0;
$image = '';
$description = trim($_POST['description'] ?? '');
$newVariants = trim($_POST['new_variants'] ?? '');
$originalName = '';
$existingImage = '';

if(
    $productId <= 0 ||
    $name === '' ||
    $size === '' ||
    $description === ''
){
    redirect_edit_product_error($productId, "Please complete all required product fields.");
}

if($price === null || $price < 0){
    redirect_edit_product_error($productId, "Product price must be a valid non-negative number.");
}

if($stock === null){
    redirect_edit_product_error($productId, "Product stock must be a valid non-negative whole number.");
}

$productLookup = $conn->prepare("SELECT name, image FROM products WHERE id = ? LIMIT 1");

if(!$productLookup){
    redirect_edit_product_error($productId, "Unable to prepare the product lookup.");
}

$productLookup->bind_param("i", $productId);

if(!$productLookup->execute()){
    $productLookup->close();
    redirect_edit_product_error($productId, "Unable to load the product before updating.");
}

$lookupResult = $productLookup->get_result();
$productRow = $lookupResult ? $lookupResult->fetch_assoc() : null;
$originalName = trim($productRow['name'] ?? '');
$existingImage = get_safe_image_basename($productRow['image'] ?? '');
$image = $existingImage;
$productLookup->close();

if(!$productRow){
    redirect_edit_product_error($productId, "Product was not found.");
}

$uploadResult = upload_product_image($_FILES['image_file'] ?? null, dirname(__DIR__) . DIRECTORY_SEPARATOR . "images", false);

if(!$uploadResult['success']){
    header("Location: edit_product.php?id=".$productId."&error=".urlencode($uploadResult['message'] ?? 'Unable to upload the selected image.'));
    exit();
}

$uploadedNewImage = !empty($uploadResult['filename']);

if(!empty($uploadResult['filename'])){
    
    $image = $uploadResult['filename'];
}

if($image === ''){
    redirect_edit_product_error($productId, "Product image is required.");
}

$oldImage = $existingImage;
$errorMessage = "";

if(!$conn->begin_transaction()){
    if($uploadedNewImage){
        @unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . $image);
    }

    redirect_edit_product_error($productId, "Unable to start the product update. Please try again.");
}

try {
    $stmt = $conn->prepare("UPDATE products SET name = ?, size = ?, price = ?, stock = ?, shipping_mode = ?, shipping_cost = ?, status = ?, is_popular = ?, image = ?, description = ? WHERE id = ?");

    if(!$stmt){
        throw new Exception("Unable to prepare the product update.");
    }

    $stmt->bind_param("ssdisdsissi", $name, $size, $price, $stock, $shippingMode, $shippingCost, $status, $isPopular, $image, $description, $productId);

    if(!$stmt->execute()){
        $stmt->close();
        throw new Exception("Unable to update the product.");
    }

    $stmt->close();

    if($newVariants !== ''){
        $variantLines = preg_split("/\r\n|\n|\r/", $newVariants);
        $checkStmt = $conn->prepare("SELECT id FROM products WHERE name = ? AND size = ? LIMIT 1");
        $insertStmt = $conn->prepare("INSERT INTO products (name, size, price, stock, shipping_mode, shipping_cost, status, is_popular, image, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if(!$checkStmt || !$insertStmt){
            if($checkStmt){
                $checkStmt->close();
            }

            if($insertStmt){
                $insertStmt->close();
            }

            throw new Exception("Unable to prepare the variant update.");
        }

        foreach($variantLines as $line){
            $line = trim($line);

            if($line === ''){
                continue;
            }

            $parts = array_map('trim', explode('|', $line));

            if(count($parts) < 3){
                throw new Exception("Each new variant must use the format size|price|stock.");
            }

            $variantSize = $parts[0];
            $variantPrice = validate_non_negative_decimal($parts[1]);
            $variantStock = validate_non_negative_integer($parts[2]);

            if(!is_valid_variant_size($variantSize) || $variantPrice === null || $variantPrice <= 0){
                throw new Exception("One of the new variants has an invalid size or price.");
            }

            if($variantStock === null){
                throw new Exception("One of the new variants has an invalid stock value.");
            }

            $checkStmt->bind_param("ss", $name, $variantSize);

            if(!$checkStmt->execute()){
                $checkStmt->close();
                $insertStmt->close();
                throw new Exception("Unable to check for an existing variant.");
            }

            $existingVariant = $checkStmt->get_result();

            if($existingVariant && $existingVariant->fetch_assoc()){
                continue;
            }

            $insertStmt->bind_param("ssdisdsiss", $name, $variantSize, $variantPrice, $variantStock, $shippingMode, $shippingCost, $status, $isPopular, $image, $description);

            if(!$insertStmt->execute()){
                $checkStmt->close();
                $insertStmt->close();
                throw new Exception("Unable to add one of the new variants.");
            }
        }

        $checkStmt->close();
        $insertStmt->close();
    }

    if($uploadedNewImage){
        $namesToSync = array_values(array_unique(array_filter([$originalName, $name])));
        $syncConditions = [];
        $syncParams = [];
        $syncTypes = "s";

        foreach($namesToSync as $productName){
            $syncConditions[] = "name = ?";
            $syncParams[] = $productName;
            $syncTypes .= "s";
        }

        if(!empty($syncConditions)){
            $syncSql = "UPDATE products SET image = ? WHERE " . implode(" OR ", $syncConditions);
            $syncStmt = $conn->prepare($syncSql);

            if(!$syncStmt){
                throw new Exception("Unable to prepare the product image sync.");
            }

            $bindValues = array_merge([$syncTypes, $image], $syncParams);
            $bindReferences = [];

            foreach($bindValues as $index => $value){
                $bindReferences[$index] = &$bindValues[$index];
            }

            call_user_func_array([$syncStmt, "bind_param"], $bindReferences);

            if(!$syncStmt->execute()){
                $syncStmt->close();
                throw new Exception("Unable to sync the new product image.");
            }

            $syncStmt->close();
        }
    }

    if(!$conn->commit()){
        throw new Exception("Unable to save the product update.");
    }
} catch (Exception $exception) {
    $conn->rollback();
    $errorMessage = $exception->getMessage();
}

if($errorMessage !== ""){
    if($uploadedNewImage){
        @unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . $image);
    }

    redirect_edit_product_error($productId, $errorMessage);
}

if($uploadedNewImage && $oldImage !== '' && $oldImage !== $image){
    $oldImagePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . $oldImage;

    if(file_exists($oldImagePath)){
        @unlink($oldImagePath);
    }
}

header("Location: edit_product.php?id=".$productId."&success=".urlencode("Product updated successfully."));
exit();
?>
