<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/csrf.php";
include_once "../php/order_payment.php";

$successMessage = trim($_GET['success'] ?? '');
$errorMessage = trim($_GET['error'] ?? '');
$allowedStatuses = ["Pending", "Processing", "Shipped", "Delivered", "Cancelled"];
$statusFilter = trim($_GET['status'] ?? '');
$searchTerm = trim($_GET['search'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

if(!in_array($statusFilter, $allowedStatuses, true)){
    $statusFilter = '';
}

if($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1){
    $dateFrom = '';
}

if($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1){
    $dateTo = '';
}

$whereClauses = [];
$bindTypes = '';
$bindValues = [];

if($statusFilter !== ''){
    $whereClauses[] = "orders.status = ?";
    $bindTypes .= 's';
    $bindValues[] = $statusFilter;
}

if($searchTerm !== ''){
    $whereClauses[] = "(orders.customer_name LIKE ? OR orders.phone LIKE ? OR CAST(orders.id AS CHAR) LIKE ?)";
    $searchLike = "%".$searchTerm."%";
    $bindTypes .= 'sss';
    $bindValues[] = $searchLike;
    $bindValues[] = $searchLike;
    $bindValues[] = $searchLike;
}

if($dateFrom !== ''){
    $whereClauses[] = "DATE(orders.order_date) >= ?";
    $bindTypes .= 's';
    $bindValues[] = $dateFrom;
}

if($dateTo !== ''){
    $whereClauses[] = "DATE(orders.order_date) <= ?";
    $bindTypes .= 's';
    $bindValues[] = $dateTo;
}

$whereSql = !empty($whereClauses) ? "WHERE ".implode(" AND ", $whereClauses) : "";
$orders = [];
$totalOrders = 0;
$pendingOrders = 0;
$cancelledOrders = 0;
$revenueTotal = 0.0;

$stmt = $conn->prepare("
    SELECT
        orders.id,
        orders.customer_name,
        orders.phone,
        orders.address,
        orders.city,
        orders.pincode,
        orders.status,
        orders.total,
        orders.payment_method,
        orders.payment_status,
        orders.order_date,
        order_items.product_name,
        order_items.size,
        order_items.quantity
    FROM orders
    LEFT JOIN order_items
    ON orders.id = order_items.order_id
    {$whereSql}
    ORDER BY orders.id DESC, order_items.product_name ASC, order_items.size ASC
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
            $orderId = (int)$row['id'];

            if(!isset($orders[$orderId])){
                $orders[$orderId] = [
                    "id" => $orderId,
                    "customer_name" => $row['customer_name'],
                    "phone" => $row['phone'],
                    "address" => trim(($row['address'] ?? '').", ".($row['city'] ?? '')." - ".($row['pincode'] ?? '')),
                    "status" => $row['status'],
                    "total" => (float)($row['total'] ?? 0),
                    "payment_method" => normalize_payment_method($row['payment_method'] ?? 'cod', null, 'cod'),
                    "payment_status" => normalize_payment_status($row['payment_status'] ?? '', $row['payment_method'] ?? 'cod'),
                    "order_date" => $row['order_date'],
                    "items" => []
                ];

                $totalOrders++;

                if(($row['status'] ?? '') === 'Pending'){
                    $pendingOrders++;
                }

                if(($row['status'] ?? '') === 'Cancelled'){
                    $cancelledOrders++;
                } else {
                    $revenueTotal += (float)($row['total'] ?? 0);
                }
            }

            if(!empty($row['product_name'])){
                $orders[$orderId]["items"][] = [
                    "product_name" => $row['product_name'],
                    "size" => $row['size'],
                    "quantity" => (int)($row['quantity'] ?? 0)
                ];
            }
        }
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Orders - GripMaxx Admin</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../css/admin.css">
</head>

<body>

<div class="admin-header">
<div class="header-left">
<a href="../index.html">
<img src="../images/logo.png" alt="GripMaxx Logo">
</a>
</div>
</div>

<div class="admin-container">

<div class="sidebar">
<h2 class="sidebar-title">Admin Panel</h2>

<a href="dashboard.php">Dashboard</a>
<a href="orders.php" class="active">Orders</a>
<a href="products.php">Products</a>
<a href="users.php">Customers</a>
<a href="contact_messages.php">Contact Messages</a>
<a href="settings.php">Settings</a>
<a href="#" id="logoutBtn">Logout</a>
</div>

<div class="main-content">

<div class="admin-page-header">
<div>
<h1>Orders</h1>
<p>Review every order, narrow the list quickly, and update lifecycle status without losing visibility of what the customer purchased.</p>
</div>
</div>

<?php if($successMessage !== ''){ ?>
<div class="settings-alert success-alert"><?php echo htmlspecialchars($successMessage); ?></div>
<?php } ?>

<?php if($errorMessage !== ''){ ?>
<div class="settings-alert error-alert"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php } ?>

<div class="products-overview">
<div class="overview-card">
<span>Filtered Orders</span>
<strong><?php echo $totalOrders; ?></strong>
</div>
<div class="overview-card">
<span>Pending</span>
<strong><?php echo $pendingOrders; ?></strong>
</div>
<div class="overview-card">
<span>Cancelled</span>
<strong><?php echo $cancelledOrders; ?></strong>
</div>
<div class="overview-card">
<span>Active Revenue</span>
<strong>Rs.<?php echo htmlspecialchars(number_format($revenueTotal, 2)); ?></strong>
</div>
</div>

<form method="GET" action="orders.php" class="admin-filter-form">
<div class="filter-field">
<label for="orderSearch">Search</label>
<input id="orderSearch" type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Order ID, customer, or phone">
</div>

<div class="filter-field">
<label for="orderStatus">Status</label>
<select id="orderStatus" name="status">
<option value="">All Statuses</option>
<?php foreach($allowedStatuses as $statusOption){ ?>
<option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $statusFilter === $statusOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusOption); ?></option>
<?php } ?>
</select>
</div>

<div class="filter-field">
<label for="dateFrom">Date From</label>
<input id="dateFrom" type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
</div>

<div class="filter-field">
<label for="dateTo">Date To</label>
<input id="dateTo" type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
</div>

<div class="filter-actions">
<button type="submit" class="admin-btn btn-primary">Apply Filters</button>
<a href="orders.php" class="admin-btn btn-secondary">Reset</a>
</div>
</form>

<?php if(empty($orders)){ ?>
<div class="admin-empty-state">
<h3>No orders found</h3>
<p>There are no orders matching the current filters.</p>
</div>
<?php } else { ?>
<div class="table-responsive">
<table class="orders-table dashboard-table">
<tr>
<th>ID</th>
<th>Customer</th>
<th>Contact</th>
<th>Status</th>
<th>Payment</th>
<th>Total</th>
<th>Date</th>
<th>Details</th>
</tr>

<?php $orderIndex = 0; ?>
<?php foreach($orders as $order){ ?>
<?php $orderIndex++; ?>
<tr class="order-row" data-order="order<?php echo $orderIndex; ?>">
<td>#<?php echo (int)$order['id']; ?></td>
<td>
<div class="table-primary-cell">
<strong><?php echo htmlspecialchars($order['customer_name'] ?? ''); ?></strong>
<span><?php echo htmlspecialchars($order['address'] ?? ''); ?></span>
</div>
</td>
<td><?php echo htmlspecialchars($order['phone'] ?? ''); ?></td>
<td>
<form method="POST" action="update_status.php" class="inline-status-form" onclick="event.stopPropagation();">
<input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<select name="status" onchange="this.form.submit()">
<?php foreach($allowedStatuses as $statusOption){ ?>
<option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo ($order['status'] ?? '') === $statusOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusOption); ?></option>
<?php } ?>
</select>
</form>
</td>
<td>
<div class="payment-summary-cell">
<strong>Cash on Delivery</strong>
<span><?php echo htmlspecialchars($order['payment_status'] ?? ''); ?></span>
</div>
</td>
<td class="price-cell">Rs.<?php echo htmlspecialchars(number_format((float)$order['total'], 2)); ?></td>
<td><?php echo htmlspecialchars($order['order_date'] ?? ''); ?></td>
<td><button type="button" class="toggle-link">View Items</button></td>
</tr>
<tr class="order-items" id="order<?php echo $orderIndex; ?>">
<td colspan="8">
<div class="order-detail-panel">
<div class="order-detail-meta">
<div class="order-detail-block">
<span>Delivery Address</span>
<strong><?php echo htmlspecialchars($order['address'] ?? ''); ?></strong>
</div>
<div class="order-detail-block">
<span>Customer Phone</span>
<strong><?php echo htmlspecialchars($order['phone'] ?? ''); ?></strong>
</div>
<div class="order-detail-block">
<span>Payment Method</span>
<strong>Cash on Delivery</strong>
</div>
<div class="order-detail-block">
<span>Payment Status</span>
<form method="POST" action="update_payment_status.php" class="inline-payment-form">
<input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<select name="payment_status" onchange="this.form.submit()">
<?php foreach(get_payment_status_options_for_method($order['payment_method'] ?? 'cod') as $paymentStatusOption){ ?>
<option value="<?php echo htmlspecialchars($paymentStatusOption); ?>" <?php echo ($order['payment_status'] ?? '') === $paymentStatusOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($paymentStatusOption); ?></option>
<?php } ?>
</select>
</form>
</div>
</div>

<div class="order-item-list">
<?php foreach($order['items'] as $item){ ?>
<div class="order-item-card">
<strong><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></strong>
<span><?php echo htmlspecialchars($item['size'] ?? ''); ?></span>
<em>Qty: <?php echo (int)$item['quantity']; ?></em>
</div>
<?php } ?>
</div>
</div>
</td>
</tr>
<?php } ?>

</table>
</div>
<?php } ?>

</div>

</div>

<script>
document.querySelectorAll(".order-row").forEach(function(row) {
row.addEventListener("click", function(){
const target = this.getAttribute("data-order");
const itemsRow = document.getElementById(target);

if(!itemsRow){
return;
}

itemsRow.style.display = itemsRow.style.display === "table-row" ? "none" : "table-row";
});
});
</script>

<div id="logoutModal" class="modal-overlay">
<div class="modal-box">
<h3>Logout Confirmation</h3>
<p>Are you sure you want to logout?</p>
<div class="modal-actions">
<button id="cancelLogout" class="modal-cancel">Cancel</button>
<button id="confirmLogout" class="modal-confirm">Logout</button>
</div>
</div>
</div>

</body>

<script src="../js/admin.js"></script>

</html>
