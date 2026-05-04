<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/settings_store.php";
include "../php/csrf.php";

$settings = load_app_settings();
$successMessage = "";
$errorMessage = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
validate_csrf_or_exit(false);

$action = $_POST['action'] ?? '';

if($action === 'password'){

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if($currentPassword === '' || $newPassword === '' || $confirmPassword === ''){
        $errorMessage = "Please fill in all password fields.";
    }
    else if(!password_verify($currentPassword, $settings['admin_password_hash'])){
        $errorMessage = "Current password is incorrect.";
    }
    else if(strlen($newPassword) < 6){
        $errorMessage = "New password must be at least 6 characters.";
    }
    else if($newPassword !== $confirmPassword){
        $errorMessage = "New password and confirm password do not match.";
    }
    else{
        $settings['admin_password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);

        if(save_app_settings($settings)){
            $successMessage = "Admin password updated successfully.";
        } else {
            $errorMessage = "Unable to save the new password.";
        }
    }
}

if($action === 'email'){

    $storeEmail = trim($_POST['store_email'] ?? '');

    if($storeEmail === ''){
        $errorMessage = "Store email is required.";
    }
    else if(!filter_var($storeEmail, FILTER_VALIDATE_EMAIL)){
        $errorMessage = "Please enter a valid store email.";
    }
    else{
        $settings['store_email'] = $storeEmail;

        if(save_app_settings($settings)){
            $successMessage = "Store email updated successfully.";
        } else {
            $errorMessage = "Unable to save the store email.";
        }
    }
}

if($action === 'shipping'){

    $shippingOption = normalize_shipping_mode($_POST['shipping_option'] ?? 'free', 'free');
    $shippingCost = $shippingOption === 'flat' ? normalize_shipping_cost($_POST['shipping_cost'] ?? 0) : 0;

    $settings['shipping_option'] = $shippingOption;
    $settings['shipping_cost'] = $shippingCost;
    $settings['shipping_label'] = get_shipping_label_from_mode($shippingOption, $shippingCost);

    if(save_app_settings($settings)){
        $successMessage = "Shipping settings updated successfully.";
    } else {
        $errorMessage = "Unable to save the shipping settings.";
    }
}

if($action === 'smtp'){

    $smtpHost = trim($_POST['smtp_host'] ?? '');
    $smtpPort = normalize_smtp_port($_POST['smtp_port'] ?? 587, $_POST['smtp_encryption'] ?? 'tls');
    $smtpEncryption = normalize_smtp_encryption($_POST['smtp_encryption'] ?? 'tls', 'tls');
    $smtpUsername = trim($_POST['smtp_username'] ?? '');
    $smtpPassword = $_POST['smtp_password'] ?? '';
    $smtpFromEmail = trim($_POST['smtp_from_email'] ?? '');
    $smtpFromName = trim($_POST['smtp_from_name'] ?? '');

    if($smtpHost === ''){
        $errorMessage = "SMTP host is required.";
    }
    else if(!filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL)){
        $errorMessage = "Please enter a valid SMTP from email address.";
    }
    else if($smtpFromName === ''){
        $errorMessage = "SMTP from name is required.";
    }
    else{
        $settings['smtp_host'] = $smtpHost;
        $settings['smtp_port'] = $smtpPort;
        $settings['smtp_encryption'] = $smtpEncryption;
        $settings['smtp_username'] = $smtpUsername;

        if($smtpPassword !== ''){
            $settings['smtp_password'] = $smtpPassword;
        }

        $settings['smtp_from_email'] = $smtpFromEmail;
        $settings['smtp_from_name'] = $smtpFromName;

        if(save_app_settings($settings)){
            $successMessage = "SMTP reply settings updated successfully.";
        } else {
            $errorMessage = "Unable to save the SMTP reply settings.";
        }
    }
}

$settings = load_app_settings();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Settings - GripMaxx Admin</title>
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
<a href="settings.php" class="active">Settings</a>
<a href="database_setup.php">Database Setup</a>
<a href="#" id="logoutBtn">Logout</a>

</div>

<div class="main-content">

<h1>Settings</h1>

<?php if($successMessage !== ""){ ?>
<div class="settings-alert success-alert"><?php echo htmlspecialchars($successMessage); ?></div>
<?php } ?>

<?php if($errorMessage !== ""){ ?>
<div class="settings-alert error-alert"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php } ?>

<div class="settings-grid">

<div class="settings-card">
<h2>Change Admin Password</h2>
<form class="admin-form" method="POST">
<input type="hidden" name="action" value="password">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="password" name="current_password" placeholder="Current Password" required>
<input type="password" name="new_password" placeholder="New Password" required>
<input type="password" name="confirm_password" placeholder="Confirm Password" required>
<button type="submit" class="admin-btn btn-primary">Update Password</button>
</form>
</div>

<div class="settings-card">
<h2>Store Email</h2>
<form class="admin-form" method="POST">
<input type="hidden" name="action" value="email">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="email" name="store_email" value="<?php echo htmlspecialchars($settings['store_email']); ?>" required>
<button type="submit" class="admin-btn btn-primary">Update Email</button>
</form>
</div>

<div class="settings-card settings-card-wide">
<h2>Shipping Settings</h2>
<p class="settings-copy">Set the global shipping rule here. Products can use this default rule, be made free shipping, or carry their own custom flat shipping amount from the product editor.</p>
<form class="admin-form" method="POST">
<input type="hidden" name="action" value="shipping">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<select name="shipping_option" required>
<option value="free" <?php echo ($settings['shipping_option'] ?? 'free') === 'free' ? 'selected' : ''; ?>>Free Shipping</option>
<option value="flat" <?php echo ($settings['shipping_option'] ?? 'free') === 'flat' ? 'selected' : ''; ?>>Flat Shipping</option>
</select>
<input type="number" name="shipping_cost" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($settings['shipping_cost'] ?? 0)); ?>" placeholder="Flat shipping amount">
<p class="field-hint">This amount is applied once when the cart includes products set to use the store default shipping rule.</p>
<button type="submit" class="admin-btn btn-primary">Save Settings</button>
</form>
</div>

<div class="settings-card settings-card-wide">
<h2>SMTP Reply Settings</h2>
<p class="settings-copy">Configure a real SMTP mailbox here so admins can send replies to customer messages directly from the admin panel. These sensitive values are now stored outside the tracked app settings file, and environment variables can override them in production.</p>
<form class="admin-form" method="POST">
<input type="hidden" name="action" value="smtp">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" placeholder="SMTP Host (example: smtp.gmail.com)" required>
<input type="number" name="smtp_port" min="1" max="65535" value="<?php echo htmlspecialchars((string)($settings['smtp_port'] ?? 587)); ?>" placeholder="SMTP Port" required>
<select name="smtp_encryption" required>
<option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
<option value="ssl" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
<option value="none" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : ''; ?>>None</option>
</select>
<input type="text" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" placeholder="SMTP Username">
<input type="password" name="smtp_password" value="" placeholder="SMTP Password or App Password (leave blank to keep current)">
<input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? $settings['store_email']); ?>" placeholder="From Email" required>
<input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'GripMaxx'); ?>" placeholder="From Name" required>
<p class="field-hint">For Gmail or similar providers, use an app password instead of your normal login password.</p>
<button type="submit" class="admin-btn btn-primary">Save SMTP Settings</button>
</form>
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
