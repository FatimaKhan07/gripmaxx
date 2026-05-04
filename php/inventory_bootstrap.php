<?php

function run_schema_query($conn, $sql, $errorMessage) {
    if (!$conn->query($sql)) {
        throw new RuntimeException($errorMessage . " " . $conn->error);
    }
}

function table_column_exists($conn, $databaseName, $tableName, $columnName) {
    $safeDatabaseName = $conn->real_escape_string($databaseName);
    $safeTableName = $conn->real_escape_string($tableName);
    $safeColumnName = $conn->real_escape_string($columnName);
    $columnCheck = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '{$safeDatabaseName}'
        AND TABLE_NAME = '{$safeTableName}'
        AND COLUMN_NAME = '{$safeColumnName}'
    ");

    if (!$columnCheck) {
        return false;
    }

    $columnRow = $columnCheck->fetch_assoc();
    return (int)($columnRow['total'] ?? 0) > 0;
}

function product_column_exists($conn, $databaseName, $columnName) {
    return table_column_exists($conn, $databaseName, "products", $columnName);
}

function table_index_exists($conn, $databaseName, $tableName, $indexName) {
    $safeDatabaseName = $conn->real_escape_string($databaseName);
    $safeTableName = $conn->real_escape_string($tableName);
    $safeIndexName = $conn->real_escape_string($indexName);
    $indexCheck = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = '{$safeDatabaseName}'
        AND TABLE_NAME = '{$safeTableName}'
        AND INDEX_NAME = '{$safeIndexName}'
    ");

    if (!$indexCheck) {
        return false;
    }

    $indexRow = $indexCheck->fetch_assoc();
    return (int)($indexRow['total'] ?? 0) > 0;
}

function can_add_unique_product_identity_index($conn) {
    $duplicatesResult = $conn->query("
        SELECT name, size, COUNT(*) AS total
        FROM products
        GROUP BY name, size
        HAVING COUNT(*) > 1
        LIMIT 1
    ");

    if (!$duplicatesResult) {
        return false;
    }

    return $duplicatesResult->num_rows === 0;
}

function decimal_column_needs_upgrade($conn, $databaseName, $tableName, $columnName, $precision, $scale) {
    $safeDatabaseName = $conn->real_escape_string($databaseName);
    $safeTableName = $conn->real_escape_string($tableName);
    $safeColumnName = $conn->real_escape_string($columnName);
    $result = $conn->query("
        SELECT DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '{$safeDatabaseName}'
        AND TABLE_NAME = '{$safeTableName}'
        AND COLUMN_NAME = '{$safeColumnName}'
        LIMIT 1
    ");

    if (!$result || $result->num_rows === 0) {
        return false;
    }

    $row = $result->fetch_assoc();

    return strtolower((string)($row['DATA_TYPE'] ?? '')) !== 'decimal'
        || (int)($row['NUMERIC_PRECISION'] ?? 0) !== (int)$precision
        || (int)($row['NUMERIC_SCALE'] ?? 0) !== (int)$scale;
}

function ensure_order_schema($conn, $databaseName) {
    if (!table_column_exists($conn, $databaseName, "orders", "user_id")) {
        run_schema_query($conn, "ALTER TABLE orders ADD COLUMN user_id INT NULL AFTER id", "Unable to add orders.user_id.");
    }

    if (!table_column_exists($conn, $databaseName, "orders", "account_username")) {
        run_schema_query($conn, "ALTER TABLE orders ADD COLUMN account_username VARCHAR(100) NULL AFTER user_id", "Unable to add orders.account_username.");
    }

    if (!table_column_exists($conn, $databaseName, "orders", "payment_method")) {
        run_schema_query($conn, "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'cod' AFTER total", "Unable to add orders.payment_method.");
    }

    if (!table_column_exists($conn, $databaseName, "orders", "payment_status")) {
        run_schema_query($conn, "ALTER TABLE orders ADD COLUMN payment_status VARCHAR(40) NOT NULL DEFAULT 'Pending on Delivery' AFTER payment_method", "Unable to add orders.payment_status.");
    }

    if (!table_column_exists($conn, $databaseName, "order_items", "product_id")) {
        run_schema_query($conn, "ALTER TABLE order_items ADD COLUMN product_id INT NULL AFTER order_id", "Unable to add order_items.product_id.");
    }
}

function ensure_contact_schema($conn) {
    run_schema_query($conn, "
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            message TEXT NOT NULL,
            message_status VARCHAR(20) NOT NULL DEFAULT 'new',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", "Unable to create the contact_messages table.");

    $databaseResult = $conn->query("SELECT DATABASE() AS current_db");

    if(!$databaseResult){
        return;
    }

    $databaseRow = $databaseResult->fetch_assoc();
    $databaseName = $databaseRow['current_db'] ?? '';

    if($databaseName === ''){
        return;
    }

    if (!table_column_exists($conn, $databaseName, "contact_messages", "message_status")) {
        run_schema_query($conn, "ALTER TABLE contact_messages ADD COLUMN message_status VARCHAR(20) NOT NULL DEFAULT 'new' AFTER message", "Unable to add contact_messages.message_status.");
    }
}

function ensure_money_schema($conn, $databaseName) {
    if (decimal_column_needs_upgrade($conn, $databaseName, "products", "price", 10, 2)) {
        run_schema_query($conn, "ALTER TABLE products MODIFY price DECIMAL(10,2) DEFAULT NULL", "Unable to update products.price precision.");
    }

    if (decimal_column_needs_upgrade($conn, $databaseName, "products", "shipping_cost", 10, 2)) {
        run_schema_query($conn, "ALTER TABLE products MODIFY shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00", "Unable to update products.shipping_cost precision.");
    }

    if (decimal_column_needs_upgrade($conn, $databaseName, "orders", "total", 10, 2)) {
        run_schema_query($conn, "ALTER TABLE orders MODIFY total DECIMAL(10,2) DEFAULT NULL", "Unable to update orders.total precision.");
    }

    if (decimal_column_needs_upgrade($conn, $databaseName, "order_items", "price", 10, 2)) {
        run_schema_query($conn, "ALTER TABLE order_items MODIFY price DECIMAL(10,2) DEFAULT NULL", "Unable to update order_items.price precision.");
    }
}

function ensure_core_tables($conn) {
    run_schema_query($conn, "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(30) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_users_username (username),
            UNIQUE KEY uniq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", "Unable to create the users table.");

    run_schema_query($conn, "
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            size VARCHAR(50) NOT NULL,
            price DECIMAL(10,2) DEFAULT NULL,
            stock INT NOT NULL DEFAULT 0,
            shipping_mode VARCHAR(20) NOT NULL DEFAULT 'default',
            shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            is_popular TINYINT(1) NOT NULL DEFAULT 0,
            image VARCHAR(255) NOT NULL DEFAULT '',
            description TEXT NOT NULL,
            UNIQUE KEY uniq_products_name_size (name, size)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", "Unable to create the products table.");

    run_schema_query($conn, "
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            account_username VARCHAR(100) NULL,
            customer_name VARCHAR(120) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            address VARCHAR(255) NOT NULL,
            city VARCHAR(100) NOT NULL,
            pincode VARCHAR(20) NOT NULL,
            total DECIMAL(10,2) DEFAULT NULL,
            payment_method VARCHAR(20) NOT NULL DEFAULT 'cod',
            payment_status VARCHAR(40) NOT NULL DEFAULT 'Pending on Delivery',
            status VARCHAR(20) NOT NULL DEFAULT 'Pending',
            order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_orders_user_id (user_id),
            KEY idx_orders_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", "Unable to create the orders table.");

    run_schema_query($conn, "
        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NULL,
            product_name VARCHAR(190) NOT NULL,
            size VARCHAR(50) NOT NULL,
            price DECIMAL(10,2) DEFAULT NULL,
            quantity INT NOT NULL DEFAULT 1,
            KEY idx_order_items_order_id (order_id),
            KEY idx_order_items_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", "Unable to create the order_items table.");
}

function ensure_inventory_schema($conn) {
    ensure_core_tables($conn);
    $databaseResult = $conn->query("SELECT DATABASE() AS current_db");

    if (!$databaseResult) {
        throw new RuntimeException("Unable to determine the current database.");
    }

    $databaseRow = $databaseResult->fetch_assoc();
    $databaseName = $databaseRow['current_db'] ?? '';

    if ($databaseName === '') {
        throw new RuntimeException("No active database was selected.");
    }

    if (!product_column_exists($conn, $databaseName, "stock")) {
        run_schema_query($conn, "ALTER TABLE products ADD COLUMN stock INT NOT NULL DEFAULT 0", "Unable to add products.stock.");
    }

    if (!product_column_exists($conn, $databaseName, "status")) {
        run_schema_query($conn, "ALTER TABLE products ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'", "Unable to add products.status.");
    }

    if (!product_column_exists($conn, $databaseName, "is_popular")) {
        run_schema_query($conn, "ALTER TABLE products ADD COLUMN is_popular TINYINT(1) NOT NULL DEFAULT 0", "Unable to add products.is_popular.");
    }

    if (!product_column_exists($conn, $databaseName, "shipping_mode")) {
        run_schema_query($conn, "ALTER TABLE products ADD COLUMN shipping_mode VARCHAR(20) NOT NULL DEFAULT 'default'", "Unable to add products.shipping_mode.");
    }

    if (!product_column_exists($conn, $databaseName, "shipping_cost")) {
        run_schema_query($conn, "ALTER TABLE products ADD COLUMN shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00", "Unable to add products.shipping_cost.");
    }

    if (!table_index_exists($conn, $databaseName, "products", "uniq_products_name_size") && can_add_unique_product_identity_index($conn)) {
        run_schema_query($conn, "ALTER TABLE products ADD UNIQUE KEY uniq_products_name_size (name, size)", "Unable to add the product identity index.");
    }

    ensure_order_schema($conn, $databaseName);
    ensure_contact_schema($conn);
    ensure_money_schema($conn, $databaseName);
}

?>
