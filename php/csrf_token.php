<?php

include "csrf.php";

header("Content-Type: application/json");

echo json_encode([
    "status" => "success",
    "csrf_token" => get_csrf_token()
]);

?>
