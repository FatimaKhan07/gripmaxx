<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";

function fetch_count($conn, $sql) {
    $result = $conn->query($sql);

    if(!$result){
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int)($row['total'] ?? 0);
}

function fetch_amount($conn, $sql) {
    $result = $conn->query($sql);

    if(!$result){
        return 0;
    }

    $row = $result->fetch_assoc();
    return (float)($row['total'] ?? 0);
}

$totalProducts = fetch_count($conn, "SELECT COUNT(*) AS total FROM products");
$totalOrders = fetch_count($conn, "SELECT COUNT(*) AS total FROM orders");
$totalUsers = fetch_count($conn, "SELECT COUNT(*) AS total FROM users");
$totalRevenue = fetch_amount($conn, "SELECT COALESCE(SUM(total), 0) AS total FROM orders WHERE status <> 'Cancelled'");
$pendingOrders = fetch_count($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'Pending'");
$cancelledOrders = fetch_count($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'Cancelled'");

$statusCounts = [
    "Pending" => 0,
    "Processing" => 0,
    "Shipped" => 0,
    "Delivered" => 0,
    "Cancelled" => 0
];

$statusResult = $conn->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status");

if($statusResult){
    while($row = $statusResult->fetch_assoc()){
        $status = $row['status'] ?? '';

        if(isset($statusCounts[$status])){
            $statusCounts[$status] = (int)$row['total'];
        }
    }
}

$recentOrders = [];
$recentOrdersResult = $conn->query("
    SELECT id, customer_name, status, total, order_date
    FROM orders
    ORDER BY id DESC
    LIMIT 6
");

if($recentOrdersResult){
    while($row = $recentOrdersResult->fetch_assoc()){
        $recentOrders[] = $row;
    }
}

$dailyOrders = [];
$dailyRevenue = [];
$trendResult = $conn->query("
    SELECT DATE(order_date) AS order_day,
           COUNT(*) AS order_count,
           COALESCE(SUM(CASE WHEN status <> 'Cancelled' THEN total ELSE 0 END), 0) AS revenue
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(order_date)
");

if($trendResult){
    while($row = $trendResult->fetch_assoc()){
        $dailyOrders[$row['order_day']] = (int)$row['order_count'];
        $dailyRevenue[$row['order_day']] = (float)$row['revenue'];
    }
}

$trendDays = [];
$maxDailyOrders = 1;

for($i = 6; $i >= 0; $i--){
    $dayKey = date("Y-m-d", strtotime("-".$i." days"));
    $orderCount = $dailyOrders[$dayKey] ?? 0;
    $maxDailyOrders = max($maxDailyOrders, $orderCount);
    $trendDays[] = [
        "label" => date("d M", strtotime($dayKey)),
        "orders" => $orderCount,
        "revenue" => $dailyRevenue[$dayKey] ?? 0
    ];
}

$summaryCards = [
    ["label" => "Total Products", "value" => $totalProducts, "tone" => "blue"],
    ["label" => "Total Orders", "value" => $totalOrders, "tone" => "green"],
    ["label" => "Total Users", "value" => $totalUsers, "tone" => "yellow"],
    ["label" => "Total Revenue", "value" => "Rs.".number_format($totalRevenue, 2), "tone" => "purple"],
    ["label" => "Pending Orders", "value" => $pendingOrders, "tone" => "orange"],
    ["label" => "Cancelled Orders", "value" => $cancelledOrders, "tone" => "red"]
];
?>

<!DOCTYPE html>
<html>
<head>
<title>GripMaxx Admin</title>
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

<a href="dashboard.php" class="active">Dashboard</a>
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
<h1>Dashboard</h1>
<p>Track store performance, order activity, and recent customer purchases from one place.</p>
</div>
</div>

<div class="analytics-grid">
<?php foreach($summaryCards as $card){ ?>
<div class="analytics-card analytics-<?php echo htmlspecialchars($card['tone']); ?>">
<span><?php echo htmlspecialchars($card['label']); ?></span>
<strong><?php echo htmlspecialchars((string)$card['value']); ?></strong>
</div>
<?php } ?>
</div>

<div class="dashboard-insights">

<div class="admin-panel-card">
<h3>Order Status</h3>
<p>Current order lifecycle distribution.</p>
<div class="status-list">
<?php foreach($statusCounts as $status => $count){ ?>
<?php $percentage = $totalOrders > 0 ? round(($count / $totalOrders) * 100) : 0; ?>
<div class="status-row">
<div>
<strong><?php echo htmlspecialchars($status); ?></strong>
<span><?php echo $count; ?> orders</span>
</div>
<div class="status-meter">
<span style="width:<?php echo $percentage; ?>%;"></span>
</div>
<em><?php echo $percentage; ?>%</em>
</div>
<?php } ?>
</div>
</div>

<div class="admin-panel-card">
<h3>Last 7 Days</h3>
<p>Simple order trend for quick review.</p>
<div class="trend-bars">
<?php foreach($trendDays as $day){ ?>
<?php $height = $day['orders'] > 0 ? max(12, round(($day['orders'] / $maxDailyOrders) * 100)) : 4; ?>
<div class="trend-day">
<div class="trend-bar-wrap">
<span class="trend-bar" style="height:<?php echo $height; ?>%;"></span>
</div>
<strong><?php echo (int)$day['orders']; ?></strong>
<small><?php echo htmlspecialchars($day['label']); ?></small>
</div>
<?php } ?>
</div>
</div>

</div>

<div class="admin-panel-card">
<div class="admin-page-header compact-header">
<div>
<h3>Recent Orders</h3>
<p>Latest customer orders for quick admin follow-up.</p>
</div>
<a href="orders.php" class="admin-btn btn-primary">View All Orders</a>
</div>

<div class="table-responsive">
<table class="orders-table dashboard-table">
<tr>
<th>ID</th>
<th>Customer</th>
<th>Status</th>
<th>Total</th>
<th>Date</th>
</tr>

<?php if(empty($recentOrders)){ ?>
<tr>
<td colspan="5">No orders yet.</td>
</tr>
<?php } ?>

<?php foreach($recentOrders as $order){ ?>
<tr>
<td>#<?php echo (int)$order['id']; ?></td>
<td><?php echo htmlspecialchars($order['customer_name']); ?></td>
<td>
<span class="status-badge <?php echo ($order['status'] ?? '') === 'Cancelled' ? 'status-inactive' : (($order['status'] ?? '') === 'Delivered' ? 'status-active' : 'status-popular'); ?>">
<?php echo htmlspecialchars($order['status']); ?>
</span>
</td>
<td class="price-cell">Rs.<?php echo htmlspecialchars(number_format((float)$order['total'], 2)); ?></td>
<td><?php echo htmlspecialchars($order['order_date']); ?></td>
</tr>
<?php } ?>
</table>
</div>
</div>

</div>

</div>

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
