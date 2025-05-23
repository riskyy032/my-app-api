<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get raw JSON input
$data = json_decode(file_get_contents("php://input"));
$email = $data->email ?? null;

if (!$email) {
    echo json_encode(["error" => "Email is required"]);
    exit();
}

// Check if email exists and get the question and answer
$query = "SELECT security_question, security_answer FROM users WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        "security_question" => $row["security_question"],
        "security_answer" => $row["security_answer"] // ⚠️ Remove in production if needed
    ]);
} else {
    echo json_encode(["error" => "Email not found"]);
}

$stmt->close();
$conn->close();
?>
