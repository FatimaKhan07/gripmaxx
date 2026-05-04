<?php

function ensure_session_started() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        include_once __DIR__ . "/session_security.php";
        start_secure_session();
    }
}

function get_csrf_token() {
    ensure_session_started();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input_field() {
    $token = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function is_valid_csrf_token($token) {
    ensure_session_started();
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($token) || $token === '' || !is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function validate_csrf_or_exit($isJsonResponse = false) {
    $token = $_POST['csrf_token'] ?? '';

    if (is_valid_csrf_token($token)) {
        return;
    }

    http_response_code(403);

    if ($isJsonResponse) {
        header("Content-Type: application/json");
        echo json_encode([
            "status" => "error",
            "message" => "Invalid or missing CSRF token."
        ]);
        exit();
    }

    echo "Invalid or missing CSRF token.";
    exit();
}

?>
