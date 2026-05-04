<?php

include "session_security.php";
start_secure_session();

include "db.php";
include "csrf.php";

header("Content-Type: application/json");
validate_csrf_or_exit(true);

if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'){
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method."
    ]);
    exit();
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if($name === '' || $email === '' || $message === ''){
    echo json_encode([
        "status" => "error",
        "message" => "Please fill in all contact fields."
    ]);
    exit();
}

if(strlen($name) < 2 || strlen($name) > 120){
    echo json_encode([
        "status" => "error",
        "message" => "Please enter a valid name."
    ]);
    exit();
}

if(!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190){
    echo json_encode([
        "status" => "error",
        "message" => "Please enter a valid email address."
    ]);
    exit();
}

if(strlen($message) < 10 || strlen($message) > 5000){
    echo json_encode([
        "status" => "error",
        "message" => "Message should be between 10 and 5000 characters."
    ]);
    exit();
}

$stmt = $conn->prepare("INSERT INTO contact_messages (name, email, message, created_at) VALUES (?, ?, ?, NOW())");

if(!$stmt){
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Unable to prepare your message. Please try again."
    ]);
    exit();
}

$stmt->bind_param("sss", $name, $email, $message);

if(!$stmt->execute()){
    $stmt->close();
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Unable to send your message right now. Please try again."
    ]);
    exit();
}

$stmt->close();

echo json_encode([
    "status" => "success",
    "message" => "Message sent successfully."
]);

?>
