<?php
include "../php/session_security.php";
require_admin_session(false);

include "../php/db.php";
include "../php/csrf.php";
include "../php/settings_store.php";
include "../php/mail_helper.php";
validate_csrf_or_exit(false);

$messageId = (int)($_POST['message_id'] ?? 0);
$subject = trim($_POST['reply_subject'] ?? '');
$replyMessage = trim($_POST['reply_message'] ?? '');

if ($messageId <= 0 || $subject === '' || $replyMessage === '') {
    header("Location: contact_messages.php?error=" . urlencode("Reply subject and message are required."));
    exit();
}

$messageStmt = $conn->prepare("SELECT id, name, email FROM contact_messages WHERE id = ? LIMIT 1");

if (!$messageStmt) {
    header("Location: contact_messages.php?error=" . urlencode("Unable to prepare the message lookup."));
    exit();
}

$messageStmt->bind_param("i", $messageId);

if (!$messageStmt->execute()) {
    $messageStmt->close();
    header("Location: contact_messages.php?error=" . urlencode("Unable to load the selected message."));
    exit();
}

$messageResult = $messageStmt->get_result();
$messageRow = $messageResult ? $messageResult->fetch_assoc() : null;
$messageStmt->close();

if (!$messageRow) {
    header("Location: contact_messages.php?error=" . urlencode("Selected message was not found."));
    exit();
}

$settings = load_app_settings();

if (!is_smtp_configured($settings)) {
    header("Location: contact_messages.php?error=" . urlencode("SMTP reply settings are incomplete. Configure them in Admin Settings first."));
    exit();
}

$errorMessage = '';
$replySent = send_smtp_email(
    $messageRow['email'] ?? '',
    $messageRow['name'] ?? '',
    $subject,
    $replyMessage,
    $settings,
    $errorMessage
);

if (!$replySent) {
    header("Location: contact_messages.php?error=" . urlencode($errorMessage !== '' ? $errorMessage : "Unable to send the reply email."));
    exit();
}

$statusStmt = $conn->prepare("UPDATE contact_messages SET message_status = 'replied' WHERE id = ?");

if ($statusStmt) {
    $statusStmt->bind_param("i", $messageId);
    $statusStmt->execute();
    $statusStmt->close();
}

header("Location: contact_messages.php?success=" . urlencode("Reply sent successfully."));
exit();
?>
