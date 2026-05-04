<?php

include "../php/session_security.php";
include "../php/csrf.php";

start_admin_session();
header("Content-Type: application/json");

if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'){
http_response_code(405);
echo json_encode([
    "status" => "error",
    "message" => "Invalid request method."
]);
exit();
}

validate_csrf_or_exit(true);
destroy_current_session();

echo json_encode([
    "status" => "success"
]);
exit();

?>
