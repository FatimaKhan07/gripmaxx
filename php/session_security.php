<?php

function configure_secure_session_cookie($cookiePath = '/') {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(0, $cookiePath . '; samesite=Lax', '', $isHttps, true);
    }
}

function get_admin_session_cookie_path() {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/admin');
    $adminPosition = strpos($scriptName, '/admin/');

    if ($adminPosition === false) {
        return '/admin';
    }

    return substr($scriptName, 0, $adminPosition + 6);
}

function start_secure_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    configure_secure_session_cookie('/');

    session_start();
}

function start_admin_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('GRIPMAXX_ADMINSESSID');
    configure_secure_session_cookie(get_admin_session_cookie_path());
    session_start();
}

function regenerate_session_safely() {
    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['last_regenerated_at'] = time();
}

function regenerate_admin_session_safely() {
    start_admin_session();
    session_regenerate_id(true);
    $_SESSION['last_regenerated_at'] = time();
}

function enforce_session_refresh_window() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        start_secure_session();
    }

    $lastRegeneratedAt = (int)($_SESSION['last_regenerated_at'] ?? 0);

    if ($lastRegeneratedAt <= 0 || (time() - $lastRegeneratedAt) >= 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regenerated_at'] = time();
    }
}

function destroy_current_session() {
    start_secure_session();
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), "", [
                "expires" => time() - 42000,
                "path" => $params["path"],
                "domain" => $params["domain"],
                "secure" => $params["secure"],
                "httponly" => $params["httponly"],
                "samesite" => $params["samesite"] ?? "Lax"
            ]);
        } else {
            setcookie(
                session_name(),
                "",
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
    }

    session_destroy();
}

function send_admin_no_store_headers() {
    if (headers_sent()) {
        return;
    }

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
}

function require_admin_session($isJsonResponse = false) {
    start_admin_session();
    send_admin_no_store_headers();

    if (($_SESSION['admin_logged_in'] ?? false) !== true) {
        if ($isJsonResponse) {
            http_response_code(401);
            header("Content-Type: application/json");
            echo json_encode(["status" => "error", "message" => "Unauthorized"]);
            exit();
        }

        header("Location: login.php");
        exit();
    }

    $lastAdminActivityAt = (int)($_SESSION['admin_last_activity_at'] ?? 0);

    if ($lastAdminActivityAt > 0 && (time() - $lastAdminActivityAt) > 7200) {
        destroy_current_session();

        if ($isJsonResponse) {
            http_response_code(401);
            header("Content-Type: application/json");
            echo json_encode(["status" => "error", "message" => "Admin session expired. Please login again."]);
            exit();
        }

        header("Location: login.php");
        exit();
    }

    enforce_session_refresh_window();
    $_SESSION['admin_last_activity_at'] = time();
}

?>
