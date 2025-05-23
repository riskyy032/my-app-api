<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once 'db.php'; // Include the database connection

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input["email"]) || !isset($input["password"])) {
    echo json_encode(["success" => false, "error" => "Invalid input"]);
    exit();
}

$email = trim($input["email"]);
$password = trim($input["password"]);

// Maximum attempts and lockout duration
$max_attempts = 5;
$lockout_minutes = 5;

// Check login attempts
$stmt = $conn->prepare("SELECT attempt_count, lockout_time, last_attempt FROM login_attempts WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$attempt_result = $stmt->get_result();

$login_attempts = 0;
$lockout_time = null;
$current_time = time();

if ($attempt_result->num_rows > 0) {
    $row = $attempt_result->fetch_assoc();
    $login_attempts = (int)$row["attempt_count"];
    $lockout_time = $row["lockout_time"] ? strtotime($row["lockout_time"]) : null;

    // Check if lockout period has expired
    if ($lockout_time && $current_time >= $lockout_time) {
        // Reset attempts
        $stmt = $conn->prepare("UPDATE login_attempts SET attempt_count = 0, lockout_time = NULL, last_attempt = NOW() WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $login_attempts = 0;
    }
}

// Check if user is locked out
if ($login_attempts >= $max_attempts && $lockout_time && $current_time < $lockout_time) {
    $remaining_seconds = $lockout_time - $current_time;
    $remaining_minutes = ceil($remaining_seconds / 60);
    echo json_encode(["success" => false, "error" => "Too many login attempts. Please try again in $remaining_minutes minute(s)."]);
    $conn->close();
    exit();
}

// Verify user credentials
$stmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "No account with that email exists."]);
    $conn->close();
    exit();
}

$row = $user_result->fetch_assoc();
$hashedPassword = $row["password"];

if (password_verify($password, $hashedPassword)) {
    // Successful login, reset attempts
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    echo json_encode(["success" => true]);
} else {
    // Failed login, increment attempts
    $login_attempts++;
    error_log("Attempt count for $email: $login_attempts"); // Debug log

    if ($attempt_result->num_rows > 0) {
        // Update existing record
        $new_lockout_time = $login_attempts >= $max_attempts ? date('Y-m-d H:i:s', $current_time + ($lockout_minutes * 60)) : null;
        $stmt = $conn->prepare("UPDATE login_attempts SET attempt_count = ?, lockout_time = ?, last_attempt = NOW() WHERE email = ?");
        $stmt->bind_param("iss", $login_attempts, $new_lockout_time, $email);
    } else {
        // Insert new record
        $new_lockout_time = $login_attempts >= $max_attempts ? date('Y-m-d H:i:s', $current_time + ($lockout_minutes * 60)) : null;
        $stmt = $conn->prepare("INSERT INTO login_attempts (email, attempt_count, lockout_time, last_attempt) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sis", $email, $login_attempts, $new_lockout_time);
    }
    if (!$stmt->execute()) {
        error_log("Query failed: " . $stmt->error); // Log query errors
    }

    if ($login_attempts >= $max_attempts) {
        echo json_encode(["success" => false, "error" => "Too many login attempts. Please try again in $lockout_minutes minute(s)."]);
    } else {
        echo json_encode(["success" => false, "error" => "Incorrect password. Attempt $login_attempts of $max_attempts."]);
    }
}

$conn->close();
?>
