<?php

include_once __DIR__ . "/runtime_config.php";
include_once __DIR__ . "/settings_store.php";

function get_login_throttle_file_path() {
    return __DIR__ . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "login_throttle.php";
}

function normalize_login_throttle_scope($scope) {
    $normalized = strtolower(trim((string)$scope));
    return $normalized !== '' ? $normalized : 'user';
}

function normalize_login_identifier($value) {
    $normalized = strtolower(trim((string)$value));
    return preg_replace('/[^a-z0-9_@.\-]/', '', $normalized);
}

function get_login_throttle_client_ip() {
    $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));

    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        $candidate = trim($parts[0]);

        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    $remoteAddress = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($remoteAddress, FILTER_VALIDATE_IP) ? $remoteAddress : 'unknown';
}

function build_login_throttle_key($scope, $identifier) {
    return normalize_login_throttle_scope($scope)
        . '|'
        . normalize_login_identifier($identifier)
        . '|'
        . get_login_throttle_client_ip();
}

function read_login_throttle_state() {
    $filePath = get_login_throttle_file_path();
    ensure_settings_directory_protection(dirname($filePath));
    $loaded = load_optional_php_array($filePath);

    return isset($loaded['records']) && is_array($loaded['records'])
        ? $loaded['records']
        : [];
}

function write_login_throttle_state($records) {
    $filePath = get_login_throttle_file_path();
    ensure_settings_directory_protection(dirname($filePath));
    $payload = "<?php\nreturn " . var_export([
        'records' => $records
    ], true) . ";\n";

    return file_put_contents($filePath, $payload, LOCK_EX) !== false;
}

function prune_login_throttle_records($records, $now = null) {
    $currentTime = $now ?: time();
    $pruned = [];

    foreach ((array)$records as $key => $record) {
        $lockedUntil = (int)($record['locked_until'] ?? 0);
        $lastAttemptAt = (int)($record['last_attempt_at'] ?? 0);

        if ($lockedUntil > $currentTime || ($lastAttemptAt > 0 && ($currentTime - $lastAttemptAt) <= 86400)) {
            $pruned[$key] = [
                'attempts' => max(0, (int)($record['attempts'] ?? 0)),
                'locked_until' => $lockedUntil,
                'last_attempt_at' => $lastAttemptAt
            ];
        }
    }

    return $pruned;
}

function get_login_throttle_status($scope, $identifier) {
    $rawRecords = read_login_throttle_state();
    $records = prune_login_throttle_records($rawRecords);
    $key = build_login_throttle_key($scope, $identifier);
    $record = $records[$key] ?? [
        'attempts' => 0,
        'locked_until' => 0,
        'last_attempt_at' => 0
    ];

    if (($record['locked_until'] ?? 0) <= time()) {
        $record['locked_until'] = 0;
    }

    if ($records !== $rawRecords) {
        write_login_throttle_state($records);
    }

    return $record;
}

function record_login_throttle_failure($scope, $identifier, $maxAttempts = 5, $lockSeconds = 300) {
    $records = prune_login_throttle_records(read_login_throttle_state());
    $key = build_login_throttle_key($scope, $identifier);
    $record = $records[$key] ?? [
        'attempts' => 0,
        'locked_until' => 0,
        'last_attempt_at' => 0
    ];

    $record['attempts'] = max(0, (int)$record['attempts']) + 1;
    $record['last_attempt_at'] = time();

    if ($record['attempts'] >= $maxAttempts) {
        $record['locked_until'] = time() + max(60, (int)$lockSeconds);
    }

    $records[$key] = $record;
    write_login_throttle_state($records);

    return $record;
}

function clear_login_throttle_failures($scope, $identifier) {
    $records = prune_login_throttle_records(read_login_throttle_state());
    $key = build_login_throttle_key($scope, $identifier);

    if (isset($records[$key])) {
        unset($records[$key]);
        write_login_throttle_state($records);
    }
}

?>
