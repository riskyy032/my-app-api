<?php
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = isset($data['email']) ? $conn->real_escape_string($data['email']) : '';
    $new_password = isset($data['new_password']) ? $data['new_password'] : '';

    if (empty($email) || empty($new_password)) {
        throw new Exception("Email and new password are required");
    }

    $sql = "SELECT id FROM users WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET password = '$hashed_password' WHERE email = '$email'";
        
        if ($conn->query($update_sql) === TRUE) {
            echo json_encode([
                'success' => true,
                'message' => 'Password updated successfully'
            ]);
        } else {
            throw new Exception("Error updating password: " . $conn->error);
        }
    } else {
        throw new Exception("User not found");
    }

    $conn->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
?>