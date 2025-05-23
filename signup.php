<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$data = json_decode(file_get_contents("php://input"));

$firstName = $data->first_name;
$lastName = $data->last_name;
$email = $data->email;
$password = password_hash($data->password, PASSWORD_DEFAULT);
$securityQuestion = $data->security_question;
$securityAnswer = $data->security_answer;

// Prepare statement including security question and answer
$stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, security_question, security_answer) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $firstName, $lastName, $email, $password, $securityQuestion, $securityAnswer);

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($stmt->execute()) {
    echo json_encode(["message" => "User registered successfully"]);
} else {
    echo json_encode(["message" => "Error: " . $conn->error]);
}

$conn->close();
?>
