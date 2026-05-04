<?php

include_once __DIR__ . "/runtime_config.php";

function load_database_config() {
    $localConfig = load_optional_php_array(__DIR__ . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "db_config.local.php");

    return [
        "host" => trim((string)get_env_value("GRIPMAXX_DB_HOST", $localConfig["host"] ?? "")),
        "port" => (int)get_env_value("GRIPMAXX_DB_PORT", $localConfig["port"] ?? 3306),
        "database" => trim((string)get_env_value("GRIPMAXX_DB_NAME", $localConfig["database"] ?? "")),
        "user" => trim((string)get_env_value("GRIPMAXX_DB_USER", $localConfig["user"] ?? "")),
        "password" => (string)get_env_value("GRIPMAXX_DB_PASSWORD", $localConfig["password"] ?? "")
    ];
}

function fail_database_connection($message) {
    error_log($message);

    if (!headers_sent()) {
        http_response_code(500);
    }

    exit("Service temporarily unavailable.");
}

$databaseConfig = load_database_config();

if (
    $databaseConfig["host"] === ""
    || $databaseConfig["database"] === ""
    || $databaseConfig["user"] === ""
) {
    fail_database_connection("GripMaxx database configuration is incomplete.");
}

$conn = @new mysqli(
    $databaseConfig["host"],
    $databaseConfig["user"],
    $databaseConfig["password"],
    $databaseConfig["database"],
    max(1, (int)$databaseConfig["port"])
);

if ($conn->connect_error) {
    fail_database_connection("GripMaxx database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>
