<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Include PHPMailer and database config
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // make sure PHPMailer is installed via Composer
require 'config.php'; // contains your database connection

// Get input
$data = json_decode(file_get_contents("php://input"));
$email = $data->email ?? '';

if (empty($email)) {
    echo json_encode(["message" => "Email is required."]);
    exit;
}

// Check if email exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(["message" => "If the email exists, a code has been sent."]);
    exit;
}

// Generate 6-digit code
$code = rand(100000, 999999);
$expires_at = date("Y-m-d H:i:s", strtotime("+10 minutes"));

// Store code in password_resets
$stmt = $conn->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE code = ?, expires_at = ?");
$stmt->bind_param("sssss", $email, $code, $expires_at, $code, $expires_at);
$stmt->execute();

// Send email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';             // Gmail SMTP server
    $mail->SMTPAuth = true;
    $mail->Username = 'ianleradd@email.com';    // Your Gmail address
    $mail->Password = 'pteghucuemwbyzeo';              // ⚠️ This must be an App Password, not your Gmail login
    $mail->SMTPSecure = 'tls';                  // Encryption method
    $mail->Port = 587;

    $mail->setFrom('ianleradd@email.com', 'COMPASS Support');  // Match your Gmail address
    $mail->addAddress($email);                                // The recipient (user input)
    $mail->Subject = 'Your Password Reset Code';
    $mail->Body = "Your password reset code is: $code";

    $mail->send();
    echo json_encode(["message" => "If the email exists, a code has been sent."]);
} catch (Exception $e) {
    echo json_encode(["message" => "Mailer Error: {$mail->ErrorInfo}"]);
}

