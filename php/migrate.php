<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line.";
    exit(1);
}

include_once __DIR__ . "/db.php";
include_once __DIR__ . "/inventory_bootstrap.php";

ensure_inventory_schema($conn);

echo "GripMaxx database migration completed successfully." . PHP_EOL;

?>
