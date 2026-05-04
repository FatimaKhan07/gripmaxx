<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/settings_store.php";
include "../php/csrf.php";

$productId = (int)($_GET['id'] ?? 0);
$successMessage = trim($_GET['success'] ?? '');
$errorMessage = trim($_GET['error'] ?? '');

if($productId <= 0){
header("Location: products.php");
exit();
}

$stmt = $conn->prepare("SELECT id, name, size, price, stock, shipping_mode, shipping_cost, status, is_popular, image, description FROM products WHERE id = ?");

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
<title>Edit Product - GripMaxx</title>
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
<a href="products.php" class="active">Products</a>
<a href="users.php">Customers</a>
<a href="contact_messages.php">Contact Messages</a>
<a href="settings.php">Settings</a>
<a href="#" id="logoutBtn">Logout</a>
</div>

<div class="main-content">

<div class="admin-page-header">
<div>
<h1>Edit Product</h1>
<p>Update the product details, storefront state, and inventory from this page. Any active changes will reflect on the storefront automatically.</p>
</div>

<div class="admin-actions">
<a href="products.php" class="admin-btn btn-primary">Back to Products</a>
<a href="add_variant.php?id=<?php echo (int)$product['id']; ?>" class="admin-btn btn-secondary">Add Variant</a>
</div>
</div>

<?php if($successMessage !== ''){ ?>
<div class="settings-alert success-alert"><?php echo htmlspecialchars($successMessage); ?></div>
<?php } ?>

<?php if($errorMessage !== ''){ ?>
<div class="settings-alert error-alert"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php } ?>

<form method="POST" action="update_product.php" class="edit-layout" enctype="multipart/form-data">
<input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
<input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($product['image']); ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

<div class="edit-main">
<div class="admin-panel-card">
<h2>Product Details</h2>
<p>Keep the main product information accurate so the storefront and admin panel stay in sync.</p>

<div class="form-grid">
<div class="form-field">
<label for="productName">Product Name</label>
<input id="productName" type="text" name="name" placeholder="Product Name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
</div>

<div class="form-field">
<label for="productSize">Primary Size</label>
<input id="productSize" type="text" name="size" placeholder="Size (e.g. 100g)" value="<?php echo htmlspecialchars($product['size']); ?>" required>
</div>

<div class="form-field">
<label for="productPrice">Price</label>
<input id="productPrice" type="number" step="0.01" min="0" name="price" placeholder="Price" value="<?php echo htmlspecialchars($product['price']); ?>" required>
</div>

<div class="form-field">
<label for="productStock">Available Stock</label>
<input id="productStock" type="number" name="stock" placeholder="Available Stock" min="0" value="<?php echo (int)$product['stock']; ?>" required>
</div>

<div class="form-field">
<label for="productShippingMode">Shipping Rule</label>
<select id="productShippingMode" name="shipping_mode" required>
<option value="default" <?php echo ($product['shipping_mode'] ?? 'default') === 'default' ? 'selected' : ''; ?>>Use Store Default Shipping</option>
<option value="free" <?php echo ($product['shipping_mode'] ?? 'default') === 'free' ? 'selected' : ''; ?>>Free Shipping for This Product</option>
<option value="flat" <?php echo ($product['shipping_mode'] ?? 'default') === 'flat' ? 'selected' : ''; ?>>Custom Flat Shipping</option>
</select>
<div class="field-hint">Default uses the store-wide shipping rule from admin settings.</div>
</div>

<div class="form-field">
<label for="productShippingCost">Custom Shipping Amount</label>
<input id="productShippingCost" type="number" name="shipping_cost" placeholder="Custom Shipping Amount" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format((float)($product['shipping_cost'] ?? 0), 2, '.', '')); ?>" required>
<div class="field-hint">Only used when the shipping rule is set to custom flat shipping.</div>
</div>

<div class="form-field">
<label for="productStatus">Storefront Status</label>
<select id="productStatus" name="status" required>
<option value="active" <?php echo ($product['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
<option value="inactive" <?php echo ($product['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
</select>
<div class="field-hint">Inactive products are hidden from the storefront and checkout validation.</div>
</div>

<div class="form-field">
<label>Highlight</label>
<div class="checkbox-row">
<input type="checkbox" name="is_popular" value="1" <?php echo !empty($product['is_popular']) ? 'checked' : ''; ?>>
<span>Mark this product as popular</span>
</div>
</div>

<div class="form-field full-width">
<label for="productImage">Replace Product Image</label>
<input id="productImage" type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp,.gif">
<div class="field-hint">Leave this empty to keep the current image. Upload a JPG, PNG, WEBP, or GIF to replace it.</div>
</div>

<div class="form-field full-width">
<label for="productDescription">Description</label>
<textarea id="productDescription" name="description" placeholder="Product description. Use line breaks if you want multiple points." required><?php echo htmlspecialchars($product['description']); ?></textarea>
</div>
</div>
</div>

<div class="form-actions">
<button class="admin-btn btn-primary" type="submit">Save Changes</button>
<a href="products.php" class="admin-btn btn-danger">Cancel</a>
</div>
</div>

<div class="edit-side">
<div class="admin-panel-card image-preview-card">
<h3>Current Image</h3>
<img src="../images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="image-preview">
<p>The storefront currently uses this image for the selected variant.</p>
</div>

<div class="admin-panel-card">
<h3>Quick Summary</h3>
<div class="meta-list">
<div class="meta-item">
<span>Product ID</span>
<strong><?php echo (int)$product['id']; ?></strong>
</div>
<div class="meta-item">
<span>Current Status</span>
<strong><?php echo htmlspecialchars(ucfirst($product['status'] ?? 'active')); ?></strong>
</div>
<div class="meta-item">
<span>Popular Badge</span>
<strong><?php echo !empty($product['is_popular']) ? 'Enabled' : 'Disabled'; ?></strong>
</div>
<div class="meta-item">
<span>Current Stock</span>
<strong><?php echo (int)$product['stock']; ?></strong>
</div>
<div class="meta-item">
<span>Current Price</span>
<strong>Rs.<?php echo htmlspecialchars($product['price']); ?></strong>
</div>
<div class="meta-item">
<span>Shipping</span>
<strong>
<?php
$shippingPreview = get_product_shipping_config($product);
echo htmlspecialchars($shippingPreview['label']);
?>
</strong>
</div>
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
