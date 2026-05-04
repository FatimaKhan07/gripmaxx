<?php

include_once __DIR__ . "/runtime_config.php";
include_once __DIR__ . "/order_payment.php";

function get_secret_app_settings_keys() {
    return [
        "admin_username",
        "admin_password_hash",
        "smtp_host",
        "smtp_port",
        "smtp_encryption",
        "smtp_username",
        "smtp_password",
        "smtp_from_email",
        "smtp_from_name"
    ];
}

function get_app_setting_env_map() {
    return [
        "GRIPMAXX_ADMIN_USERNAME" => "admin_username",
        "GRIPMAXX_ADMIN_PASSWORD_HASH" => "admin_password_hash",
        "GRIPMAXX_STORE_EMAIL" => "store_email",
        "GRIPMAXX_SHIPPING_OPTION" => "shipping_option",
        "GRIPMAXX_SHIPPING_COST" => "shipping_cost",
        "GRIPMAXX_SMTP_HOST" => "smtp_host",
        "GRIPMAXX_SMTP_PORT" => "smtp_port",
        "GRIPMAXX_SMTP_ENCRYPTION" => "smtp_encryption",
        "GRIPMAXX_SMTP_USERNAME" => "smtp_username",
        "GRIPMAXX_SMTP_PASSWORD" => "smtp_password",
        "GRIPMAXX_SMTP_FROM_EMAIL" => "smtp_from_email",
        "GRIPMAXX_SMTP_FROM_NAME" => "smtp_from_name"
    ];
}

function get_env_overridden_setting_keys() {
    $overriddenKeys = [];

    foreach (get_app_setting_env_map() as $envName => $settingKey) {
        if (env_value_exists($envName)) {
            $overriddenKeys[$settingKey] = true;
        }
    }

    return $overriddenKeys;
}

function normalize_shipping_mode($mode, $default = "free") {
    $allowedModes = ["free", "flat", "default"];
    return in_array($mode, $allowedModes, true) ? $mode : $default;
}

function normalize_shipping_cost($cost) {
    $floatCost = (float)$cost;
    return max(0, $floatCost);
}

function normalize_smtp_encryption($value, $default = "tls") {
    $allowed = ["none", "tls", "ssl"];
    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, $allowed, true) ? $normalized : $default;
}

function normalize_smtp_port($port, $encryption = "tls") {
    $normalizedPort = (int)$port;

    if ($normalizedPort > 0 && $normalizedPort <= 65535) {
        return $normalizedPort;
    }

    return normalize_smtp_encryption($encryption, "tls") === "ssl" ? 465 : 587;
}

function format_money($amount) {
    return number_format((float)$amount, 2, ".", "");
}

function get_shipping_label_from_mode($mode, $cost) {
    $normalizedMode = normalize_shipping_mode($mode, "free");
    $normalizedCost = normalize_shipping_cost($cost);

    if ($normalizedMode !== "flat" || $normalizedCost <= 0) {
        return "Free Shipping";
    }

    return "Rs." . format_money($normalizedCost) . " Flat Shipping";
}

function get_settings_file_path() {
    return __DIR__ . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "app_settings.php";
}

function get_secret_settings_file_path() {
    $configuredPath = trim((string)get_env_value("GRIPMAXX_SECRET_SETTINGS_FILE", ""));

    if ($configuredPath !== "") {
        return $configuredPath;
    }

    return __DIR__ . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "app_secrets.php";
}

function get_legacy_settings_file_path() {
    return __DIR__ . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "app_settings.json";
}

function ensure_settings_directory_protection($directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $htaccessPath = $directory . DIRECTORY_SEPARATOR . ".htaccess";
    $protectionRules = "Require all denied\nDeny from all\n";

    if (!file_exists($htaccessPath) || file_get_contents($htaccessPath) !== $protectionRules) {
        file_put_contents($htaccessPath, $protectionRules, LOCK_EX);
    }
}

function get_default_app_settings() {
    return [
        "admin_username" => "",
        "admin_password_hash" => "",
        "store_email" => "support@gripmaxx.com",
        "shipping_option" => "free",
        "shipping_label" => get_shipping_label_from_mode("free", 0),
        "shipping_cost" => 0,
        "smtp_host" => "",
        "smtp_port" => 587,
        "smtp_encryption" => "tls",
        "smtp_username" => "",
        "smtp_password" => "",
        "smtp_from_email" => "support@gripmaxx.com",
        "smtp_from_name" => "GripMaxx"
    ];
}

function split_public_and_secret_settings($settings) {
    $secretKeys = get_secret_app_settings_keys();
    $publicSettings = [];
    $secretSettings = [];

    foreach ((array)$settings as $key => $value) {
        if (in_array($key, $secretKeys, true)) {
            $secretSettings[$key] = $value;
            continue;
        }

        $publicSettings[$key] = $value;
    }

    return [
        "public" => $publicSettings,
        "secret" => $secretSettings
    ];
}

function apply_app_setting_env_overrides($settings) {
    $normalized = is_array($settings) ? $settings : [];
    $envMap = get_app_setting_env_map();

    foreach ($envMap as $envName => $settingKey) {
        if (!env_value_exists($envName)) {
            continue;
        }

        $normalized[$settingKey] = get_env_value($envName);
    }

    return $normalized;
}

function has_configured_admin_credentials($settings = null) {
    $normalizedSettings = is_array($settings) ? normalize_app_settings($settings) : load_app_settings();
    $adminUsername = trim((string)($normalizedSettings["admin_username"] ?? ""));
    $adminPasswordHash = (string)($normalizedSettings["admin_password_hash"] ?? "");
    $passwordInfo = password_get_info($adminPasswordHash);

    return $adminUsername !== ""
        && $adminPasswordHash !== ""
        && !empty($passwordInfo["algo"]);
}

function normalize_app_settings($settings) {
    $defaults = get_default_app_settings();

    if (!is_array($settings)) {
        return $defaults;
    }

    $normalized = array_merge($defaults, $settings);
    $normalized["shipping_option"] = normalize_shipping_mode($normalized["shipping_option"] ?? "free", "free");
    $normalized["shipping_cost"] = normalize_shipping_cost($normalized["shipping_cost"] ?? 0);
    $normalized["shipping_label"] = get_shipping_label_from_mode($normalized["shipping_option"], $normalized["shipping_cost"]);
    $normalized["smtp_host"] = trim((string)($normalized["smtp_host"] ?? ""));
    $normalized["smtp_encryption"] = normalize_smtp_encryption($normalized["smtp_encryption"] ?? "tls", "tls");
    $normalized["smtp_port"] = normalize_smtp_port($normalized["smtp_port"] ?? 587, $normalized["smtp_encryption"]);
    $normalized["smtp_username"] = trim((string)($normalized["smtp_username"] ?? ""));
    $normalized["smtp_password"] = (string)($normalized["smtp_password"] ?? "");
    $normalized["smtp_from_email"] = trim((string)($normalized["smtp_from_email"] ?? $normalized["store_email"] ?? "support@gripmaxx.com"));
    $normalized["smtp_from_name"] = trim((string)($normalized["smtp_from_name"] ?? "GripMaxx"));

    if ($normalized["smtp_from_email"] === "") {
        $normalized["smtp_from_email"] = trim((string)($normalized["store_email"] ?? "support@gripmaxx.com"));
    }

    if ($normalized["smtp_from_name"] === "") {
        $normalized["smtp_from_name"] = "GripMaxx";
    }

    return $normalized;
}

function load_app_settings() {
    $filePath = get_settings_file_path();
    ensure_settings_directory_protection(dirname($filePath));
    $secretPath = get_secret_settings_file_path();
    ensure_settings_directory_protection(dirname($secretPath));
    $loaded = [];

    if (file_exists($filePath)) {
        $legacyPath = get_legacy_settings_file_path();

        if (file_exists($legacyPath)) {
            @unlink($legacyPath);
        }

        $loaded = load_optional_php_array($filePath);
    }

    $secretSettings = load_optional_php_array($secretPath);

    if (file_exists($filePath) && is_array($loaded)) {
        $legacySecretValues = [];

        foreach (get_secret_app_settings_keys() as $secretKey) {
            if (!array_key_exists($secretKey, $loaded)) {
                continue;
            }

            $secretValue = $loaded[$secretKey];

            if ($secretValue === "" || $secretValue === null) {
                continue;
            }

            if (!array_key_exists($secretKey, $secretSettings) || $secretSettings[$secretKey] === "" || $secretSettings[$secretKey] === null) {
                $legacySecretValues[$secretKey] = $secretValue;
            }
        }

        if (!empty($legacySecretValues)) {
            $loaded = array_merge($loaded, $legacySecretValues);
            save_app_settings($loaded);
            $loaded = load_optional_php_array($filePath);
            $secretSettings = load_optional_php_array($secretPath);
        }
    }

    $legacyPath = get_legacy_settings_file_path();

    if (file_exists($legacyPath)) {
        $raw = file_get_contents($legacyPath);
        $decoded = json_decode($raw, true);
        $normalized = normalize_app_settings($decoded);
        save_app_settings($normalized);
        @unlink($legacyPath);
        return $normalized;
    }

    return normalize_app_settings(apply_app_setting_env_overrides(array_merge(
        get_default_app_settings(),
        $loaded,
        $secretSettings
    )));
}

function save_app_settings($settings) {
    $filePath = get_settings_file_path();
    $directory = dirname($filePath);
    ensure_settings_directory_protection($directory);
    $secretPath = get_secret_settings_file_path();
    ensure_settings_directory_protection(dirname($secretPath));

    $normalized = normalize_app_settings($settings);
    $splitSettings = split_public_and_secret_settings($normalized);
    $envOverriddenKeys = get_env_overridden_setting_keys();

    foreach ($splitSettings["secret"] as $key => $value) {
        if (isset($envOverriddenKeys[$key])) {
            unset($splitSettings["secret"][$key]);
        }
    }

    $publicPayload = "<?php\nreturn " . var_export($splitSettings["public"], true) . ";\n";
    $secretPayload = "<?php\nreturn " . var_export($splitSettings["secret"], true) . ";\n";
    $legacyPath = get_legacy_settings_file_path();
    $savedPublic = file_put_contents($filePath, $publicPayload, LOCK_EX) !== false;
    $savedSecret = file_put_contents($secretPath, $secretPayload, LOCK_EX) !== false;
    $saved = $savedPublic && $savedSecret;

    if ($saved && file_exists($legacyPath)) {
        @unlink($legacyPath);
    }

    return $saved;
}

function get_shipping_config($option) {
    $mode = normalize_shipping_mode($option, "free");
    $cost = $mode === "flat" ? 50 : 0;

    return [
        "mode" => $mode,
        "label" => get_shipping_label_from_mode($mode, $cost),
        "cost" => $cost
    ];
}

function get_product_shipping_config($product, $settings = null) {
    $settings = is_array($settings) ? normalize_app_settings($settings) : load_app_settings();
    $productMode = normalize_shipping_mode($product["shipping_mode"] ?? "default", "default");
    $productCost = normalize_shipping_cost($product["shipping_cost"] ?? 0);

    if ($productMode === "free") {
        return [
            "mode" => "free",
            "label" => "Free Shipping",
            "cost" => 0
        ];
    }

    if ($productMode === "flat") {
        return [
            "mode" => "flat",
            "label" => get_shipping_label_from_mode("flat", $productCost),
            "cost" => $productCost
        ];
    }

    return [
        "mode" => "default",
        "label" => $settings["shipping_label"],
        "cost" => normalize_shipping_cost($settings["shipping_cost"] ?? 0)
    ];
}

function calculate_order_shipping($items, $settings = null) {
    $settings = is_array($settings) ? normalize_app_settings($settings) : load_app_settings();
    $usesDefaultShipping = false;
    $totalShipping = 0;

    if (!is_array($items)) {
        return 0;
    }

    foreach ($items as $item) {
        $quantity = max(0, (int)($item["quantity"] ?? 0));
        $shippingConfig = get_product_shipping_config($item, $settings);

        if ($quantity <= 0) {
            continue;
        }

        if ($shippingConfig["mode"] === "flat") {
            $totalShipping += normalize_shipping_cost($shippingConfig["cost"] ?? 0) * $quantity;
            continue;
        }

        if ($shippingConfig["mode"] === "default") {
            $usesDefaultShipping = true;
        }
    }

    if ($usesDefaultShipping && normalize_shipping_mode($settings["shipping_option"] ?? "free", "free") === "flat") {
        $totalShipping += normalize_shipping_cost($settings["shipping_cost"] ?? 0);
    }

    return $totalShipping;
}

?>
