<?php

include "../php/session_security.php";
include "../php/csrf.php";

include "../php/settings_store.php";
include "../php/login_throttle.php";
start_admin_session();
validate_csrf_or_exit(false);

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$settings = load_app_settings();
$adminUser = $settings['admin_username'];
$adminPasswordHash = $settings['admin_password_hash'];
$throttleStatus = get_login_throttle_status('admin', $username);
$lockedUntil = (int)($throttleStatus['locked_until'] ?? 0);

if(!has_configured_admin_credentials($settings)){
header("Location: login.php?error=".urlencode("Admin login is not configured. Restore the settings file or reconfigure admin access."));
exit();
}

if($lockedUntil > time()){
header("Location: login.php?error=".urlencode("Too many failed attempts. Please wait before trying again."));
exit();
}

if($username === $adminUser && password_verify($password, $adminPasswordHash)){

regenerate_admin_session_safely();
clear_login_throttle_failures('admin', $username);
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = $adminUser;
$_SESSION['admin_last_activity_at'] = time();

header("Location: dashboard.php");
exit();

}else{

record_login_throttle_failure('admin', $username);

header("Location: login.php?error=".urlencode("Invalid login credentials."));
exit();

}

?>
