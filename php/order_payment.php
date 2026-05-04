<?php

function get_order_payment_methods() {
    return ["cod"];
}

function normalize_payment_method($value, $settings = null, $default = "cod") {
    $normalizedValue = strtolower(trim((string)$value));
    $allowedMethods = get_order_payment_methods();

    if (!in_array($normalizedValue, $allowedMethods, true)) {
        return $default;
    }

    return $normalizedValue;
}

function get_default_payment_status($paymentMethod) {
    return "Pending on Delivery";
}

function get_payment_status_options() {
    return [
        "Pending on Delivery",
        "Paid",
        "Failed"
    ];
}

function get_payment_status_options_for_method($paymentMethod) {
    return [
        "Pending on Delivery",
        "Paid",
        "Failed"
    ];
}

function normalize_payment_status($status, $paymentMethod = "cod") {
    $normalizedStatus = trim((string)$status);
    $allowedStatuses = get_payment_status_options();

    if (!in_array($normalizedStatus, $allowedStatuses, true)) {
        return get_default_payment_status($paymentMethod);
    }

    return $normalizedStatus;
}

function order_reserves_stock($orderStatus, $paymentMethod, $paymentStatus) {
    $normalizedOrderStatus = trim((string)$orderStatus);

    if ($normalizedOrderStatus === "Cancelled") {
        return false;
    }

    return true;
}

?>
