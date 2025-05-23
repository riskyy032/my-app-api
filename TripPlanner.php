<?php
ob_start();

// Set CORS headers to allow the frontend to access the response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173'); // Match your frontend origin
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true'); // Allow cookies to be sent

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';

$request_time = date('Y-m-d H:i:s');

// Debug request, headers, and cookies
error_log("[$request_time] Request: Method={$_SERVER['REQUEST_METHOD']}, URI={$_SERVER['REQUEST_URI']}");
error_log("[$request_time] Headers: " . json_encode(getallheaders()));
error_log("[$request_time] Cookies received: " . json_encode($_COOKIE));
error_log("[$request_time] Raw input: " . file_get_contents('php://input'));

try {
    // Check if userEmail cookie exists and is not empty
    if (!isset($_COOKIE['userEmail'])) {
        error_log("[$request_time] Cookie 'userEmail' not set.");
        throw new Exception('Unauthorized: Please log in (cookie not set).');
    }

    $email = trim($_COOKIE['userEmail']);
    if (empty($email)) {
        error_log("[$request_time] Cookie 'userEmail' is empty.");
        throw new Exception('Unauthorized: Please log in (cookie empty).');
    }

    error_log("[$request_time] Received userEmail: $email");

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("[$request_time] Invalid email format: $email");
        throw new Exception('Unauthorized: Invalid email format.');
    }

    // Escape the email for safe database query
    $email = $conn->real_escape_string($email);

    // Verify the email exists in the database and get user_id
    $sql = "SELECT id FROM users WHERE email = '$email'";
    $result = $conn->query($sql);
    if (!$result) {
        error_log("[$request_time] Database query failed: " . $conn->error);
        throw new Exception('Database error: Unable to verify user.');
    }
    if ($result->num_rows === 0) {
        error_log("[$request_time] Email not found in database: $email");
        throw new Exception('Unauthorized: User not found.');
    }

    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    error_log("[$request_time] User logged in: email=$email, user_id=$user_id");

    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed.');
    }

    // Read JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }

    // Retrieve and sanitize form data
    $city = isset($data['city']) ? $conn->real_escape_string($data['city']) : '';
    $country = isset($data['country']) ? $conn->real_escape_string($data['country']) : '';
    $activities = isset($data['activities']) && is_array($data['activities']) ? $data['activities'] : [];
    $info_types = isset($data['info_types']) && is_array($data['info_types']) ? $data['info_types'] : [];

    // Validate inputs
    if (empty($city) || empty($country)) {
        throw new Exception('City and country are required.');
    }

    // Convert arrays to comma-separated strings
    $activities_str = $conn->real_escape_string(implode(',', $activities));
    $info_types_str = $conn->real_escape_string(implode(',', $info_types));

    // Verify database connection
    if (!$conn) {
        throw new Exception('Database connection not established.');
    }

    // Insert into trips table with the validated user_id
    $sql = "INSERT INTO trips (user_id, city, country_region, activity, info_trip)
            VALUES ('$user_id', '$city', '$country', '$activities_str', '$info_types_str')";
    
    if ($conn->query($sql) !== TRUE) {
        error_log("[$request_time] Failed to save trip: " . $conn->error);
        throw new Exception('Error saving trip: ' . $conn->error);
    }

    error_log("[$request_time] Trip saved for user_id=$user_id, city=$city");
    echo json_encode([
        'success' => true,
        'message' => 'Trip saved successfully.'
    ]);

    $conn->close();

} catch (Exception $e) {
    error_log("[$request_time] Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
?>