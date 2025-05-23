<?php
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Email');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';

try {
    // Get userEmail from header
    $userEmail = isset($_SERVER['HTTP_X_USER_EMAIL']) ? trim($_SERVER['HTTP_X_USER_EMAIL']) : null;

    if (!$userEmail) {
        throw new Exception('Unauthorized: Please log in.');
    }

    $email = $conn->real_escape_string($userEmail);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Unauthorized: Invalid email format.');
    }

    $sql = "SELECT profile_image FROM users WHERE email = '$email'";
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        throw new Exception('Unauthorized: User not found.');
    }

    $user = $result->fetch_assoc();
    $profile_image = $user['profile_image'] ? 'http://localhost/my-app-api/uploads/' . $user['profile_image'] : null;

    echo json_encode([
        'success' => true,
        'profile_image' => $profile_image
    ]);

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