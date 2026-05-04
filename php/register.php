<?php

include "session_security.php";
start_secure_session();

include "db.php";
include "csrf.php";

header("Content-Type: application/json");
validate_csrf_or_exit(true);

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $email === '' || $password === '') {
    echo json_encode(["status"=>"error", "message"=>"Missing required fields."]);
    exit();
}

if(!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)){
    echo json_encode(["status"=>"error", "message"=>"Username must be 3 to 30 characters and use only letters, numbers, or underscores."]);
    exit();
}

if(!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190){
    echo json_encode(["status"=>"error", "message"=>"Please enter a valid email address."]);
    exit();
}

if(strlen($password) < 6 || strlen($password) > 72){
    echo json_encode(["status"=>"error", "message"=>"Password must be between 6 and 72 characters."]);
    exit();
}

$check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");

if(!$check){
    echo json_encode(["status"=>"error", "message"=>"Unable to process registration right now."]);
    exit();
}

$check->bind_param("ss", $username, $email);

if(!$check->execute()){
    $check->close();
    echo json_encode(["status"=>"error", "message"=>"Unable to process registration right now."]);
    exit();
}

$checkResult = $check->get_result();

if($checkResult && $checkResult->num_rows > 0){
    $check->close();
    echo json_encode(["status"=>"exists"]);
    exit();
}

$check->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username,email,password) VALUES (?, ?, ?)");

if(!$stmt){
    echo json_encode(["status"=>"error", "message"=>"Unable to create the account right now."]);
    exit();
}

$stmt->bind_param("sss", $username, $email, $hashedPassword);

if($stmt->execute()){
    $stmt->close();
    echo json_encode(["status"=>"success"]);
}else{
    $stmt->close();
    echo json_encode(["status"=>"error"]);
}

?>
