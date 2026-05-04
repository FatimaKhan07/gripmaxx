<?php
include "../php/session_security.php";

header("Content-Type: application/json");
require_admin_session(true);

include "../php/settings_store.php";

$settings = load_app_settings();

echo json_encode([
    "status" => "success",
    "settings" => [
        "store_email" => $settings['store_email'],
        "shipping_option" => $settings['shipping_option'],
        "shipping_label" => $settings['shipping_label'],
        "shipping_cost" => $settings['shipping_cost']
    ]
]);

?>
