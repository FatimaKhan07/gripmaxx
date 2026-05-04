<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";

$searchTerm = trim($_GET['search'] ?? '');
$whereSql = '';
$bindTypes = '';
$bindValues = [];

if($searchTerm !== ''){
    $whereSql = "WHERE users.username LIKE ? OR users.email LIKE ?";
    $searchLike = "%".$searchTerm."%";
    $bindTypes = 'ss';
    $bindValues = [$searchLike, $searchLike];
}

$customers = [];
$totalCustomers = 0;
$customersWithOrders = 0;
$totalOrders = 0;

$stmt = $conn->prepare("
    SELECT
        users.id,
        users.username,
        users.email,
        (
            SELECT COUNT(DISTINCT orders_lookup.id)
            FROM orders AS orders_lookup
            WHERE orders_lookup.user_id = users.id
            OR LOWER(TRIM(COALESCE(orders_lookup.account_username, ''))) = LOWER(TRIM(users.username))
            OR LOWER(TRIM(COALESCE(orders_lookup.customer_name, ''))) = LOWER(TRIM(users.username))
        ) AS total_orders,
        (
            SELECT MAX(orders_lookup.order_date)
            FROM orders AS orders_lookup
            WHERE orders_lookup.user_id = users.id
            OR LOWER(TRIM(COALESCE(orders_lookup.account_username, ''))) = LOWER(TRIM(users.username))
            OR LOWER(TRIM(COALESCE(orders_lookup.customer_name, ''))) = LOWER(TRIM(users.username))
        ) AS last_order_date
    FROM users
    {$whereSql}
    ORDER BY users.id DESC
");

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
            $customerOrders = (int)($row['total_orders'] ?? 0);

            $customers[] = $row;
            $totalCustomers++;
            $totalOrders += $customerOrders;

            if($customerOrders > 0){
                $customersWithOrders++;
            }
        }
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Customers - GripMaxx Admin</title>
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
<a href="users.php" class="active">Customers</a>
<a href="contact_messages.php">Contact Messages</a>
<a href="settings.php">Settings</a>
<a href="#" id="logoutBtn">Logout</a>

</div>

<div class="main-content">

<div class="admin-page-header">
<div>
<h1>Customers</h1>
<p>Review registered customers, search quickly, and understand who is actively placing orders.</p>
</div>
</div>

<div class="products-overview">
<div class="overview-card">
<span>Filtered Customers</span>
<strong><?php echo $totalCustomers; ?></strong>
</div>
<div class="overview-card">
<span>With Orders</span>
<strong><?php echo $customersWithOrders; ?></strong>
</div>
<div class="overview-card">
<span>Total Orders</span>
<strong><?php echo $totalOrders; ?></strong>
</div>
</div>

<form method="GET" action="users.php" class="admin-filter-form">
<div class="filter-field filter-field-wide">
<label for="customerSearch">Search</label>
<input id="customerSearch" type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by username or email">
</div>

<div class="filter-actions">
<button type="submit" class="admin-btn btn-primary">Search</button>
<a href="users.php" class="admin-btn btn-secondary">Reset</a>
</div>
</form>

<?php if(empty($customers)){ ?>
<div class="admin-empty-state">
<h3>No customers found</h3>
<p>There are no customer records matching the current search.</p>
</div>
<?php } else { ?>
<div class="table-responsive">
<table class="orders-table dashboard-table">
<tr>
<th>ID</th>
<th>Customer</th>
<th>Email</th>
<th>Orders</th>
<th>Last Order</th>
</tr>

<?php foreach($customers as $row){ ?>
<tr>
<td>#<?php echo (int)$row['id']; ?></td>
<td>
<div class="table-primary-cell">
<strong><?php echo htmlspecialchars($row['username'] ?? ''); ?></strong>
<span><?php echo (int)($row['total_orders'] ?? 0) > 0 ? 'Returning customer' : 'No orders yet'; ?></span>
</div>
</td>
<td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
<td><?php echo (int)($row['total_orders'] ?? 0); ?></td>
<td><?php echo !empty($row['last_order_date']) ? htmlspecialchars($row['last_order_date']) : 'No orders yet'; ?></td>
</tr>
<?php } ?>

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
