<?php

include "session_security.php";
start_secure_session();

header("Content-Type: application/json");

$isAuthenticated = isset($_SESSION['user_id'], $_SESSION['username']);

echo json_encode([
    "status" => "success",
    "authenticated" => $isAuthenticated,
    "user" => $isAuthenticated ? [
        "id" => (int)$_SESSION['user_id'],
        "username" => (string)$_SESSION['username']
    ] : null
]);

?>
