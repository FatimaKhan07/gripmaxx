<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/settings_store.php";
include "../php/csrf.php";

$productId = (int)($_GET['id'] ?? 0);
$errorMessage = trim($_GET['error'] ?? '');

if($productId <= 0){
header("Location: products.php");
exit();
}

$stmt = $conn->prepare("SELECT id, name, price, shipping_mode, shipping_cost, status, is_popular, image, description FROM products WHERE id = ?");

if(!$stmt){
header("Location: products.php");
exit();
}

$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();
$product = $result ? $result->fetch_assoc() : null;
$stmt->close();

if(!$product){
header("Location: products.php");
exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Add Variant - GripMaxx</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../css/admin.css">
</head>

<body>

<div class="admin-header">
<div class="header-left">
<a href="../index.html">
<img src="../images/logo.png">
</a>
</div>
</div>

<div class="admin-container">

<div class="sidebar">
<h2 class="sidebar-title">Admin Panel</h2>

<a href="dashboard.php">Dashboard</a>
<a href="orders.php">Orders</a>
<a href="products.php">Products</a>
<a href="users.php">Customers</a>
<a href="contact_messages.php">Contact Messages</a>
<a href="settings.php">Settings</a>
<a href="#" id="logoutBtn">Logout</a>
</div>

<div class="main-content">

<div class="admin-page-header">
<div>
<h1>Add Variant</h1>
<p>Create another purchasable version for <strong><?php echo htmlspecialchars($product['name']); ?></strong> while keeping the product family consistent.</p>
</div>
<div class="admin-actions">
<a href="products.php" class="admin-btn btn-primary">Back to Products</a>
<a href="edit_product.php?id=<?php echo (int)$product['id']; ?>" class="admin-btn btn-primary">View Parent</a>
</div>
</div>

<?php if($errorMessage !== ''){ ?>
<div class="settings-alert error-alert product-editor-alert"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php } ?>

<form method="POST" action="insert_variant.php" class="admin-form product-editor-form" enctype="multipart/form-data">

<input type="hidden" name="parent_product_id" value="<?php echo (int)$product['id']; ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

<div class="product-editor-layout">

<div class="product-editor-main">

<div class="admin-panel-card parent-product-card">
<div class="parent-product-summary">
<img src="../images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
<div>
<span>Parent Product</span>
<strong><?php echo htmlspecialchars($product['name']); ?></strong>
<p>New variants reuse this product family. Image and description are prefilled so you can adjust only what changes.</p>
</div>
</div>
</div>

<div class="admin-panel-card">
<h3>Variant Details</h3>
<p>Change the size, color, or model label that separates this variant from the parent product.</p>

<div class="form-grid">
<div class="form-field">
<label for="variantName">Product Name</label>
<input id="variantName" type="text" name="name" placeholder="Product Name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
</div>

<div class="form-field">
<label for="variantSize">Variant Size</label>
<input id="variantSize" type="text" name="size" placeholder="250g, XL, or Red" required>
</div>

<div class="form-field">
<label for="variantPrice">Price</label>
<input id="variantPrice" type="number" step="0.01" min="0.01" name="price" placeholder="Price" value="<?php echo htmlspecialchars($product['price']); ?>" required>
</div>

<div class="form-field">
<label for="variantStock">Available Stock</label>
<input id="variantStock" type="number" name="stock" placeholder="Available Stock" min="0" step="1" required>
</div>

<div class="form-field full-width">
<label for="variantDescription">Variant Description</label>
<textarea id="variantDescription" name="description" placeholder="Variant description. You can keep this similar or customize it for the variant." required><?php echo htmlspecialchars($product['description']); ?></textarea>
</div>
</div>
</div>

<div class="admin-panel-card">
<h3>Storefront Controls</h3>
<p>These values start from the parent product and can be changed for this specific variant.</p>

<div class="form-grid">
<div class="form-field">
<label for="variantShippingMode">Shipping Rule</label>
<select id="variantShippingMode" name="shipping_mode" required>
<option value="default" <?php echo ($product['shipping_mode'] ?? 'default') === 'default' ? 'selected' : ''; ?>>Use Store Default Shipping</option>
<option value="free" <?php echo ($product['shipping_mode'] ?? 'default') === 'free' ? 'selected' : ''; ?>>Free Shipping for This Product</option>
<option value="flat" <?php echo ($product['shipping_mode'] ?? 'default') === 'flat' ? 'selected' : ''; ?>>Custom Flat Shipping</option>
</select>
</div>

<div class="form-field">
<label for="variantShippingCost">Custom Shipping Amount</label>
<input id="variantShippingCost" type="number" name="shipping_cost" placeholder="0.00" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format((float)($product['shipping_cost'] ?? 0), 2, '.', '')); ?>" required>
<span class="field-hint">Used only when custom flat shipping is selected.</span>
</div>

<div class="form-field">
<label for="variantStatus">Storefront Status</label>
<select id="variantStatus" name="status" required>
<option value="active" <?php echo ($product['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
<option value="inactive" <?php echo ($product['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
</select>
</div>

<div class="form-field">
<label>Variant Badge</label>
<label class="checkbox-row">
<input type="checkbox" name="is_popular" value="1" <?php echo !empty($product['is_popular']) ? 'checked' : ''; ?>>
<span>Mark as popular</span>
</label>
</div>
</div>
</div>

</div>

<div class="product-editor-side">

<div class="admin-panel-card image-preview-card">
<h3>Variant Image</h3>
<p>Upload a new image only if this variant needs its own photo.</p>
<img src="../images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="image-preview">

<div class="form-field image-upload-field">
<label for="variantImage">Optional Image File</label>
<input id="variantImage" type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp,.gif">
<span class="field-hint">Leave empty to use the parent product image.</span>
</div>
</div>

<div class="form-actions product-editor-actions">
<button class="admin-btn btn-primary">
Add Variant
</button>
<a href="products.php" class="admin-btn btn-secondary">Cancel</a>
</div>

</div>

</div>

</form>

</div>

</div>

<div id="logoutModal" class="modal-overlay">

<div class="modal-box">

<h3>Logout Confirmation</h3>

<p>Are you sure you want to logout?</p>

<div class="modal-actions">

<button id="cancelLogout" class="modal-cancel">
Cancel
</button>

<button id="confirmLogout" class="modal-confirm">
Logout
</button>

</div>

</div>

</div>

<script src="../js/admin.js"></script>

</body>
</html>
