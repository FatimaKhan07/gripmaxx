<?php
include "../php/session_security.php";

header("Content-Type: application/json");
require_admin_session(true);

include "../php/settings_store.php";
include "../php/csrf.php";
validate_csrf_or_exit(true);

$settings = load_app_settings();
$action = $_POST['action'] ?? '';

if($action === 'password'){

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if($currentPassword === '' || $newPassword === '' || $confirmPassword === ''){
        echo json_encode(["status" => "error", "message" => "Please fill in all password fields."]);
        exit();
    }

    if(!password_verify($currentPassword, $settings['admin_password_hash'])){
        echo json_encode(["status" => "error", "message" => "Current password is incorrect."]);
        exit();
    }

    if(strlen($newPassword) < 6){
        echo json_encode(["status" => "error", "message" => "New password must be at least 6 characters."]);
        exit();
    }

    if($newPassword !== $confirmPassword){
        echo json_encode(["status" => "error", "message" => "New password and confirm password do not match."]);
        exit();
    }

    $settings['admin_password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);

    if(save_app_settings($settings)){
        echo json_encode(["status" => "success", "message" => "Admin password updated successfully."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Unable to save the new password."]);
    }

    exit();
}

if($action === 'email'){

    $storeEmail = trim($_POST['store_email'] ?? '');

    if($storeEmail === ''){
        echo json_encode(["status" => "error", "message" => "Store email is required."]);
        exit();
    }

    if(!filter_var($storeEmail, FILTER_VALIDATE_EMAIL)){
        echo json_encode(["status" => "error", "message" => "Please enter a valid store email."]);
        exit();
    }

    $settings['store_email'] = $storeEmail;

    if(save_app_settings($settings)){
        echo json_encode(["status" => "success", "message" => "Store email updated successfully."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Unable to save the store email."]);
    }

    exit();
}

if($action === 'shipping'){

    $rawShippingOption = $_POST['shipping_option'] ?? 'free';
    $rawShippingCost = $_POST['shipping_cost'] ?? 0;
    $shippingOption = normalize_shipping_mode($rawShippingOption, 'free');
    $shippingCost = $shippingOption === 'flat' ? normalize_shipping_cost($rawShippingCost) : 0;

    $settings['shipping_option'] = $shippingOption;
    $settings['shipping_cost'] = $shippingCost;
    $settings['shipping_label'] = get_shipping_label_from_mode($shippingOption, $shippingCost);

    if(save_app_settings($settings)){
        echo json_encode([
            "status" => "success",
            "message" => "Shipping settings updated successfully.",
            "shipping_label" => $settings['shipping_label'],
            "shipping_cost" => $settings['shipping_cost']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Unable to save the shipping settings."]);
    }

    exit();
}

http_response_code(400);
echo json_encode(["status" => "error", "message" => "Invalid settings action."]);

?>
