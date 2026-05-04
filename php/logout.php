<?php

include "session_security.php";
start_secure_session();
include "csrf.php";

header("Content-Type: application/json");
validate_csrf_or_exit(true);

destroy_current_session();

echo json_encode([
    "status" => "success"
]);

?>
