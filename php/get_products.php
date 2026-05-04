<?php

include "db.php";

header("Content-Type: application/json");

/* GET ALL PRODUCTS (NO FILTER) */

$result = $conn->query("SELECT * FROM products WHERE status = 'active'");
$products = [];

if(!$result){
    http_response_code(500);
    echo json_encode($products);
    exit();
}

while($row = $result->fetch_assoc()){
    $products[] = $row;
}

echo json_encode($products);

?>
