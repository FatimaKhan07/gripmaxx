<?php

function get_env_value($name, $default = null) {
    $value = getenv($name);

    if ($value !== false) {
        return $value;
    }

    if (array_key_exists($name, $_ENV)) {
        return $_ENV[$name];
    }

    if (array_key_exists($name, $_SERVER)) {
        return $_SERVER[$name];
    }

    return $default;
}

function env_value_exists($name) {
    return get_env_value($name, null) !== null;
}

function load_optional_php_array($path) {
    if (!is_string($path) || trim($path) === '' || !file_exists($path)) {
        return [];
    }

    $loaded = include $path;
    return is_array($loaded) ? $loaded : [];
}

?>
