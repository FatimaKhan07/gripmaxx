<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/csrf.php";
validate_csrf_or_exit(false);

$messageId = (int)($_POST['message_id'] ?? 0);
$messageStatus = trim($_POST['message_status'] ?? '');
$allowedStatuses = ["new", "replied", "closed"];

if($messageId <= 0 || !in_array($messageStatus, $allowedStatuses, true)){
    header("Location: contact_messages.php?error=".urlencode("Invalid message update request."));
    exit();
}

$stmt = $conn->prepare("UPDATE contact_messages SET message_status = ? WHERE id = ?");

if(!$stmt){
    header("Location: contact_messages.php?error=".urlencode("Unable to prepare the message update."));
    exit();
}

$stmt->bind_param("si", $messageStatus, $messageId);

if(!$stmt->execute()){
    $stmt->close();
    header("Location: contact_messages.php?error=".urlencode("Unable to update the message status."));
    exit();
}

$stmt->close();
header("Location: contact_messages.php?success=".urlencode("Message status updated successfully."));
exit();
?>
