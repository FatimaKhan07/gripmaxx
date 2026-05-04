<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/csrf.php";

$successMessage = trim($_GET['success'] ?? '');
$errorMessage = trim($_GET['error'] ?? '');
$allowedSorts = [
    "id" => "id",
    "name" => "name",
    "stock" => "stock",
    "status" => "status"
];
$allowedDirections = [
    "asc" => "ASC",
    "desc" => "DESC"
];

$sort = $_GET['sort'] ?? 'id';
$direction = $_GET['direction'] ?? 'desc';
$searchTerm = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status_filter'] ?? '');
$stockFilter = trim($_GET['stock_filter'] ?? '');
$popularFilter = trim($_GET['popular_filter'] ?? '');

if(!isset($allowedSorts[$sort])){
    $sort = 'id';
}

if(!isset($allowedDirections[$direction])){
    $direction = 'desc';
}

$allowedStatusFilters = ["active", "inactive"];
$allowedStockFilters = ["in_stock", "low_stock", "out_of_stock"];
$allowedPopularFilters = ["popular", "normal"];

if(!in_array($statusFilter, $allowedStatusFilters, true)){
    $statusFilter = '';
}

if(!in_array($stockFilter, $allowedStockFilters, true)){
    $stockFilter = '';
}

if(!in_array($popularFilter, $allowedPopularFilters, true)){
    $popularFilter = '';
}

$whereClauses = [];
$bindTypes = '';
$bindValues = [];

if($searchTerm !== ''){
    $whereClauses[] = "(name LIKE ? OR size LIKE ?)";
    $searchLike = "%".$searchTerm."%";
    $bindTypes .= 'ss';
    $bindValues[] = $searchLike;
    $bindValues[] = $searchLike;
}

if($statusFilter !== ''){
    $whereClauses[] = "status = ?";
    $bindTypes .= 's';
    $bindValues[] = $statusFilter;
}

if($stockFilter === 'in_stock'){
    $whereClauses[] = "stock > 0";
}

if($stockFilter === 'low_stock'){
    $whereClauses[] = "stock > 0 AND stock <= 5";
}

if($stockFilter === 'out_of_stock'){
    $whereClauses[] = "stock = 0";
}

if($popularFilter === 'popular'){
    $whereClauses[] = "is_popular = 1";
}

if($popularFilter === 'normal'){
    $whereClauses[] = "is_popular = 0";
}

$whereSql = !empty($whereClauses) ? "WHERE ".implode(" AND ", $whereClauses) : "";
$orderBy = $allowedSorts[$sort] . " " . $allowedDirections[$direction] . ", id ASC";
$stmt = $conn->prepare("SELECT * FROM products {$whereSql} ORDER BY {$orderBy}");
$products = [];
$totalProducts = 0;
$activeProducts = 0;
$inactiveProducts = 0;
$popularProducts = 0;
$lowStockProducts = 0;

if($stmt){
    if($bindTypes !== ''){
        $params = array_merge([$bindTypes], $bindValues);
        $references = [];

        foreach($params as $index => $value){
            $references[$index] = &$params[$index];
        }

        call_user_func_array([$stmt, "bind_param"], $references);
    }

    if($stmt->execute()){
        $result = $stmt->get_result();

        while($row = $result->fetch_assoc()){
            $products[] = $row;
            $totalProducts++;

            if(($row['status'] ?? 'active') === 'active'){
                $activeProducts++;
            } else {
                $inactiveProducts++;
            }

            if(!empty($row['is_popular'])){
                $popularProducts++;
            }

            if((int)($row['stock'] ?? 0) > 0 && (int)($row['stock'] ?? 0) <= 5){
                $lowStockProducts++;
            }
        }
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Products - GripMaxx Admin</title>
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
<a href="products.php" class="active">Products</a>
<a href="users.php">Customers</a>
<a href="contact_messages.php">Contact Messages</a>
<a href="settings.php">Settings</a>
<a href="#" id="logoutBtn">Logout</a>

</div>

<div class="main-content">

<?php if($successMessage !== ''){ ?>
<div class="settings-alert success-alert"><?php echo htmlspecialchars($successMessage); ?></div>
<?php } ?>

<?php if($errorMessage !== ''){ ?>
<div class="settings-alert error-alert"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php } ?>

<div class="admin-page-header">
<div>
<h1>Products</h1>
<p>Organize inventory with focused filters so the team can isolate storefront state, low-stock items, and product groups quickly.</p>
</div>

<div class="admin-actions">
<a href="add_product.php" class="admin-btn btn-primary">Add Product</a>
</div>
</div>

<div class="products-overview">
<div class="overview-card">
<span>Filtered Products</span>
<strong><?php echo $totalProducts; ?></strong>
</div>
<div class="overview-card">
<span>Active</span>
<strong><?php echo $activeProducts; ?></strong>
</div>
<div class="overview-card">
<span>Inactive</span>
<strong><?php echo $inactiveProducts; ?></strong>
</div>
<div class="overview-card">
<span>Popular</span>
<strong><?php echo $popularProducts; ?></strong>
</div>
<div class="overview-card">
<span>Low Stock</span>
<strong><?php echo $lowStockProducts; ?></strong>
</div>
</div>

<form method="GET" action="products.php" class="admin-filter-form">
<div class="filter-field filter-field-wide">
<label for="productSearch">Search</label>
<input id="productSearch" type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by product name or size">
</div>

<div class="filter-field">
<label for="statusFilter">Storefront</label>
<select id="statusFilter" name="status_filter">
<option value="">All</option>
<option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
<option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
</select>
</div>

<div class="filter-field">
<label for="stockFilter">Stock</label>
<select id="stockFilter" name="stock_filter">
<option value="">All</option>
<option value="in_stock" <?php echo $stockFilter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
<option value="low_stock" <?php echo $stockFilter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
<option value="out_of_stock" <?php echo $stockFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
</select>
</div>

<div class="filter-field">
<label for="popularFilter">Badge</label>
<select id="popularFilter" name="popular_filter">
<option value="">All</option>
<option value="popular" <?php echo $popularFilter === 'popular' ? 'selected' : ''; ?>>Popular</option>
<option value="normal" <?php echo $popularFilter === 'normal' ? 'selected' : ''; ?>>Normal</option>
</select>
</div>

<div class="filter-field">
<label for="sortBy">Sort By</label>
<select id="sortBy" name="sort">
<option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>ID</option>
<option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name</option>
<option value="stock" <?php echo $sort === 'stock' ? 'selected' : ''; ?>>Stock</option>
<option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Status</option>
</select>
</div>

<div class="filter-field">
<label for="sortDirection">Direction</label>
<select id="sortDirection" name="direction">
<option value="asc" <?php echo $direction === 'asc' ? 'selected' : ''; ?>>Ascending</option>
<option value="desc" <?php echo $direction === 'desc' ? 'selected' : ''; ?>>Descending</option>
</select>
</div>

<div class="filter-actions">
<button type="submit" class="admin-btn btn-primary">Apply Filters</button>
<a href="products.php" class="admin-btn btn-secondary">Reset</a>
</div>
</form>

<?php if(empty($products)){ ?>
<div class="admin-empty-state">
<h3>No products found</h3>
<p>There are no products matching the current organization filters.</p>
</div>
<?php } else { ?>
<div class="table-responsive">
<table class="orders-table dashboard-table products-table">

<tr>
<th>ID</th>
<th>Product</th>
<th>Storefront</th>
<th>Popular</th>
<th>Stock</th>
<th>Stock Status</th>
<th>Update Stock</th>
<th>Price</th>
<th>Actions</th>
</tr>

<?php foreach($products as $row): ?>
<tr>
<td><?php echo (int)$row['id']; ?></td>
<td>
    <div class="product-cell">
        <img src="../images/<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="product-thumb">
        <div class="product-meta">
            <strong><?php echo htmlspecialchars($row['name']); ?></strong>
            <span><?php echo htmlspecialchars($row['size']); ?></span>
            <a href="edit_product.php?id=<?php echo (int)$row['id']; ?>" class="variant-inline-link">Edit Product</a>
        </div>
    </div>
</td>
<td>
    <span class="status-badge <?php echo ($row['status'] ?? 'active') === 'active' ? 'status-active' : 'status-inactive'; ?>">
        <?php echo htmlspecialchars(ucfirst($row['status'] ?? 'active')); ?>
    </span>
</td>
<td>
    <span class="status-badge <?php echo !empty($row['is_popular']) ? 'status-popular' : 'status-muted'; ?>">
        <?php echo !empty($row['is_popular']) ? 'Popular' : 'Normal'; ?>
    </span>
</td>
<td><?php echo (int)$row['stock']; ?></td>
<td>
    <span class="status-badge <?php echo (int)$row['stock'] > 0 ? 'status-active' : 'status-inactive'; ?>">
        <?php echo (int)$row['stock'] > 0 ? ((int)$row['stock'] <= 5 ? 'Low Stock' : 'In Stock') : 'Out of Stock'; ?>
    </span>
</td>
<td>
    <form method="POST" action="update_stock.php" class="stock-form">
        <input type="hidden" name="product_id" value="<?php echo (int)$row['id']; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="number" name="stock" value="<?php echo (int)$row['stock']; ?>" min="0">
        <button type="submit" class="admin-btn btn-primary">Save</button>
    </form>
</td>
<td class="price-cell">Rs.<?php echo htmlspecialchars($row['price']); ?></td>
<td>
    <div class="action-group">
        <a href="edit_product.php?id=<?php echo (int)$row['id']; ?>" class="admin-btn btn-primary">Edit</a>
    </div>
</td>
</tr>
<?php endforeach; ?>

</table>
</div>
<?php } ?>

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

</body>

<script src="../js/admin.js"></script>

</html>
