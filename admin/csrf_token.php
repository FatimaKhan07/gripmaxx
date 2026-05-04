<?php
include "../php/session_security.php";
include "../php/csrf.php";

start_admin_session();
header("Content-Type: application/json");

echo json_encode([
    "status" => "success",
    "csrf_token" => get_csrf_token()
]);

?>
