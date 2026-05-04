<?php
include "../php/session_security.php";
include "../php/csrf.php";

start_admin_session();
send_admin_no_store_headers();
$errorMessage = trim($_GET['error'] ?? '');

if(($_SESSION['admin_logged_in'] ?? false) === true){
header("Location: dashboard.php");
exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Login - GripMaxx</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../css/admin.css">
</head>

<body>

<div style="display:flex;justify-content:center;align-items:center;height:100vh;background:#111;">

<div style="background:#1c1c1c;padding:40px;border-radius:10px;width:360px;box-shadow:0 0 15px rgba(0,0,0,0.5);">

<a href="../index.html" style="display:block;width:max-content;margin:0 auto 15px;">
<img src="../images/logo.png" style="height:40px;display:block;">
</a>

<h2 style="text-align:center;margin-bottom:30px;">Admin Login</h2>

<?php if($errorMessage !== ''){ ?>
<div class="settings-alert error-alert"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php } ?>

<form action="login_process.php" method="POST" class="admin-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

<input type="text" name="username" placeholder="Admin Username" required>

<input type="password" name="password" placeholder="Password" required>

<button class="admin-btn btn-primary" style="width:100%;margin-top:10px;">
Login
</button>

</form>

</div>

</div>


</body>
</html>
