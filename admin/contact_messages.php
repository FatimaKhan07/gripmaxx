<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/csrf.php";

$allowedStatuses = ["new", "replied", "closed"];
$statusFilter = trim($_GET['status'] ?? '');
$searchTerm = trim($_GET['search'] ?? '');
$successMessage = trim($_GET['success'] ?? '');
$errorMessage = trim($_GET['error'] ?? '');

if(!in_array($statusFilter, $allowedStatuses, true)){
    $statusFilter = '';
}

$whereClauses = [];
$bindTypes = '';
$bindValues = [];

if($statusFilter !== ''){
    $whereClauses[] = "message_status = ?";
    $bindTypes .= 's';
    $bindValues[] = $statusFilter;
}

if($searchTerm !== ''){
    $whereClauses[] = "(name LIKE ? OR email LIKE ? OR message LIKE ?)";
    $searchLike = "%".$searchTerm."%";
    $bindTypes .= 'sss';
    $bindValues[] = $searchLike;
    $bindValues[] = $searchLike;
    $bindValues[] = $searchLike;
}

$whereSql = !empty($whereClauses) ? "WHERE ".implode(" AND ", $whereClauses) : "";
$messages = [];
$totalMessages = 0;
$newMessages = 0;
$repliedMessages = 0;
$closedMessages = 0;

$stmt = $conn->prepare("
    SELECT id, name, email, message, created_at, message_status
    FROM contact_messages
    {$whereSql}
    ORDER BY created_at DESC, id DESC
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
            $messages[] = $row;
            $totalMessages++;

            if(($row['message_status'] ?? 'new') === 'replied'){
                $repliedMessages++;
            } elseif(($row['message_status'] ?? 'new') === 'closed'){
                $closedMessages++;
            } else {
                $newMessages++;
            }
        }
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Contact Messages - GripMaxx Admin</title>
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
<a href="orders.php">Orders</a>
<a href="products.php">Products</a>
<a href="users.php">Customers</a>
<a href="contact_messages.php" class="active">Contact Messages</a>
<a href="settings.php">Settings</a>
<a href="#" id="logoutBtn">Logout</a>
</div>

<div class="main-content">

<div class="admin-page-header">
<div>
<h1>Contact Messages</h1>
<p>Review incoming customer questions, reply from the registered email address, and track whether each conversation is still open.</p>
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
<span>Filtered Messages</span>
<strong><?php echo $totalMessages; ?></strong>
</div>
<div class="overview-card">
<span>New</span>
<strong><?php echo $newMessages; ?></strong>
</div>
<div class="overview-card">
<span>Replied</span>
<strong><?php echo $repliedMessages; ?></strong>
</div>
<div class="overview-card">
<span>Closed</span>
<strong><?php echo $closedMessages; ?></strong>
</div>
</div>

<form method="GET" action="contact_messages.php" class="admin-filter-form">
<div class="filter-field filter-field-wide">
<label for="messageSearch">Search</label>
<input id="messageSearch" type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by customer name, email, or message">
</div>

<div class="filter-field">
<label for="messageStatus">Status</label>
<select id="messageStatus" name="status">
<option value="">All</option>
<?php foreach($allowedStatuses as $statusOption){ ?>
<option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $statusFilter === $statusOption ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($statusOption)); ?></option>
<?php } ?>
</select>
</div>

<div class="filter-actions">
<button type="submit" class="admin-btn btn-primary">Apply Filters</button>
<a href="contact_messages.php" class="admin-btn btn-secondary">Reset</a>
</div>
</form>

<?php if(empty($messages)){ ?>
<div class="admin-empty-state">
<h3>No messages found</h3>
<p>There are no customer messages matching the current filters.</p>
</div>
<?php } else { ?>
<div class="message-grid">
<?php foreach($messages as $messageRow){ ?>
<article class="message-card">
<div class="message-card-header">
<div>
<h3><?php echo htmlspecialchars($messageRow['name']); ?></h3>
<p><?php echo htmlspecialchars($messageRow['email']); ?></p>
</div>
<span class="status-badge message-status-<?php echo htmlspecialchars($messageRow['message_status'] ?? 'new'); ?>">
<?php echo htmlspecialchars(ucfirst($messageRow['message_status'] ?? 'new')); ?>
</span>
</div>

<p class="message-card-date"><?php echo htmlspecialchars($messageRow['created_at']); ?></p>
<p class="message-card-body"><?php echo nl2br(htmlspecialchars($messageRow['message'])); ?></p>

<form method="POST" action="send_message_reply.php" class="message-reply-form">
<input type="hidden" name="message_id" value="<?php echo (int)$messageRow['id']; ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="text" name="reply_subject" value="Reply from GripMaxx" placeholder="Reply subject" required>
<textarea name="reply_message" placeholder="Write your reply to this customer" required></textarea>
<div class="message-card-actions">
<button type="submit" class="admin-btn btn-primary">Send Reply</button>
</div>
</form>

<div class="message-card-actions">
<button
type="button"
class="admin-btn btn-secondary copy-email-btn"
data-email="<?php echo htmlspecialchars($messageRow['email'], ENT_QUOTES, 'UTF-8'); ?>"
>
Copy Email
</button>

<form method="POST" action="update_message_status.php" class="inline-status-form">
<input type="hidden" name="message_id" value="<?php echo (int)$messageRow['id']; ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<select name="message_status" onchange="this.form.submit()">
<?php foreach($allowedStatuses as $statusOption){ ?>
<option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo ($messageRow['message_status'] ?? 'new') === $statusOption ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($statusOption)); ?></option>
<?php } ?>
</select>
</form>
</div>
</article>
<?php } ?>
</div>
<?php } ?>

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

<script>
document.querySelectorAll(".copy-email-btn").forEach(function(button) {
button.addEventListener("click", function() {
const email = this.getAttribute("data-email") || "";

if(email === ""){
return;
}

function markCopied() {
const originalLabel = button.textContent;
button.textContent = "Copied";

window.setTimeout(function() {
button.textContent = originalLabel;
}, 1600);
}

if(navigator.clipboard && navigator.clipboard.writeText) {
navigator.clipboard.writeText(email).then(markCopied).catch(function() {
const tempInput = document.createElement("input");
tempInput.value = email;
document.body.appendChild(tempInput);
tempInput.select();
document.execCommand("copy");
document.body.removeChild(tempInput);
markCopied();
});
return;
}

const tempInput = document.createElement("input");
tempInput.value = email;
document.body.appendChild(tempInput);
tempInput.select();
document.execCommand("copy");
document.body.removeChild(tempInput);
markCopied();
});
});
</script>

<script src="../js/admin.js"></script>

</html>
