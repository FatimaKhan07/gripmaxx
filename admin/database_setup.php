<?php

include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/inventory_bootstrap.php";
include "../php/csrf.php";

$successMessage = "";
$errorMessage = "";

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validate_csrf_or_exit(false);

    try {
        ensure_inventory_schema($conn);
        $successMessage = "Database setup completed successfully.";
    } catch (Throwable $throwable) {
        $errorMessage = "Unable to complete the database setup. Please verify your hosting database permissions.";
        error_log("GripMaxx database setup failed: " . $throwable->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Database Setup - GripMaxx Admin</title>
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
<a href="orders.php">Orders</a>
<a href="products.php">Products</a>
<a href="users.php">Customers</a>
<a href="contact_messages.php">Contact Messages</a>
<a href="settings.php">Settings</a>
<a href="database_setup.php" class="active">Database Setup</a>
<a href="#" id="logoutBtn">Logout</a>
</div>

<div class="main-content">
<h1>Database Setup</h1>
<p class="settings-copy">Run this once after moving to a new hosting database so GripMaxx can create or upgrade the required tables and columns.</p>

<?php if ($successMessage !== "") { ?>
<div class="settings-alert success-alert"><?php echo htmlspecialchars($successMessage); ?></div>
<?php } ?>

<?php if ($errorMessage !== "") { ?>
<div class="settings-alert error-alert"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php } ?>

<div class="settings-card settings-card-wide">
<h2>Run Database Setup</h2>
<form method="POST" class="admin-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<button type="submit" class="admin-btn btn-primary">Run Setup</button>
</form>
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

<script src="../js/admin.js"></script>
</body>
</html>
