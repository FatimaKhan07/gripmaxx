<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/csrf.php";

$errorMessage = trim($_GET['error'] ?? '');
?>

<!DOCTYPE html>
<html>
<head>
<title>Add Product - GripMaxx</title>
<meta charset="UTF-8">
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
<h1>Add Product</h1>
<p>Create the first purchasable version of a product. You can continue straight into variant setup after this product is saved.</p>
</div>
<div class="admin-actions">
<a href="products.php" class="admin-btn btn-primary">Back to Products</a>
</div>
</div>

<?php if($errorMessage !== ''){ ?>
<div class="settings-alert error-alert product-editor-alert"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php } ?>

<form method="POST" action="insert_product.php" class="admin-form product-editor-form" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

<div class="product-editor-layout">

<div class="product-editor-main">

<div class="admin-panel-card">
<h3>Product Details</h3>
<p>Set the shared product identity and the first size or model customers can buy.</p>

<div class="form-grid">
<div class="form-field">
<label for="productName">Product Name</label>
<input id="productName" type="text" name="name" placeholder="GripMaxx Gym Chalk" required>
</div>

<div class="form-field">
<label for="productSize">First Variant Size</label>
<input id="productSize" type="text" name="size" placeholder="100g" required>
</div>

<div class="form-field">
<label for="productPrice">Price</label>
<input id="productPrice" type="number" step="0.01" min="0" name="price" placeholder="299.00" required>
</div>

<div class="form-field">
<label for="productStock">Available Stock</label>
<input id="productStock" type="number" name="stock" placeholder="25" min="0" required>
</div>

<div class="form-field full-width">
<label for="productDescription">Product Description</label>
<textarea id="productDescription" name="description" placeholder="Product description. Use line breaks if you want multiple points." required></textarea>
</div>
</div>
</div>

<div class="admin-panel-card">
<h3>Storefront Controls</h3>
<p>Choose how this item appears to customers and how shipping should be calculated.</p>

<div class="form-grid">
<div class="form-field">
<label for="shippingMode">Shipping Rule</label>
<select id="shippingMode" name="shipping_mode" required>
<option value="default">Use Store Default Shipping</option>
<option value="free">Free Shipping for This Product</option>
<option value="flat">Custom Flat Shipping</option>
</select>
</div>

<div class="form-field">
<label for="shippingCost">Custom Shipping Amount</label>
<input id="shippingCost" type="number" name="shipping_cost" placeholder="0.00" min="0" step="0.01" value="0.00" required>
<span class="field-hint">Used only when custom flat shipping is selected.</span>
</div>

<div class="form-field">
<label for="productStatus">Storefront Status</label>
<select id="productStatus" name="status" required>
<option value="active">Active</option>
<option value="inactive">Inactive</option>
</select>
</div>

<div class="form-field">
<label>Product Badge</label>
<label class="checkbox-row">
<input type="checkbox" name="is_popular" value="1">
<span>Mark as popular</span>
</label>
</div>
</div>
</div>

</div>

<div class="product-editor-side">

<div class="admin-panel-card">
<h3>Product Image</h3>
<p>Upload the image customers will see for this product version.</p>

<div class="form-field image-upload-field">
<label for="productImage">Image File</label>
<input id="productImage" type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp,.gif" required>
</div>
</div>

<div class="admin-panel-card variant-next-card">
<h3>Add Variant Next</h3>
<p>After saving this product, continue directly to the variant screen to add another size, color, or model under the same product name.</p>

<label class="checkbox-row variant-next-option">
<input type="checkbox" name="continue_to_variant" value="1">
<span>Open Add Variant after saving</span>
</label>
</div>

<div class="form-actions product-editor-actions">
<button class="admin-btn btn-primary">
Add Product
</button>
<a href="products.php" class="admin-btn btn-secondary">Cancel</a>
</div>

</div>

</div>

</form>

</div>

</div>

<script src="../js/admin.js"></script>

</body>
</html>
