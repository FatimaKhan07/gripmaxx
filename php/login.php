<?php

include "session_security.php";
start_secure_session();

include "db.php";
include "csrf.php";
include "login_throttle.php";

header("Content-Type: application/json");
validate_csrf_or_exit(true);

function user_login_failure_response($status, $message = "") {
    echo json_encode([
        "status" => $status,
        "message" => $message
    ]);
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    user_login_failure_response("error", "Missing credentials.");
}

if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
    user_login_failure_response("error", "Please enter a valid username.");
}

if (strlen($password) < 6 || strlen($password) > 72) {
    user_login_failure_response("error", "Please enter a valid password.");
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    user_login_failure_response("error", "Invalid request method.");
}

$throttleStatus = get_login_throttle_status("user", $username);
$lockedUntil = (int)($throttleStatus['locked_until'] ?? 0);

if ($lockedUntil > time()) {
    user_login_failure_response("throttled", "Too many login attempts. Please wait before trying again.");
}

$stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? LIMIT 1");

if(!$stmt){
    user_login_failure_response("error", "Unable to process login right now.");
}

$stmt->bind_param("s", $username);

if(!$stmt->execute()){
    $stmt->close();
    user_login_failure_response("error", "Unable to process login right now.");
}

$result = $stmt->get_result();

if($result && $result->num_rows > 0){
    $user = $result->fetch_assoc();

    if(password_verify($password, $user['password'])){
        clear_login_throttle_failures("user", $username);
        regenerate_session_safely();
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $stmt->close();

        echo json_encode([
            "status" => "success",
            "user" => [
                "id" => (int)$user['id'],
                "username" => $user['username']
            ]
        ]);
    } else {
        record_login_throttle_failure("user", $username);
        $stmt->close();
        user_login_failure_response("wrongpassword");
    }

} else {
    record_login_throttle_failure("user", $username);
    $stmt->close();
    user_login_failure_response("nouser");
}

?>
