<?php
ob_start();

// Set CORS headers to allow the frontend to access the response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173'); // Match your frontend origin
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
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

    // Fetch user details (id, first_name, last_name, email)
    $sql = "SELECT id, first_name, last_name, email FROM users WHERE email = '$email'";
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
    $user_data = [
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email']
    ];
    error_log("[$request_time] User logged in: email=$email, user_id=$user_id");

    // Handle DELETE request for clearing trip history
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $sql = "DELETE FROM trips WHERE user_id = '$user_id'";
        if ($conn->query($sql) !== TRUE) {
            error_log("[$request_time] Failed to delete trips: " . $conn->error);
            throw new Exception('Error deleting trip history: ' . $conn->error);
        }

        error_log("[$request_time] All trips deleted for user_id=$user_id");
        echo json_encode([
            'success' => true,
            'message' => 'Trip history cleared successfully.'
        ]);
        $conn->close();
        ob_end_flush();
        exit;
    }

    // Handle GET request for fetching user data and trips
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed.');
    }

    // Fetch trips for the user
    $sql = "SELECT id, created_at, city, country_region, activity, info_trip 
            FROM trips 
            WHERE user_id = '$user_id' 
            ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if (!$result) {
        error_log("[$request_time] Database query failed: " . $conn->error);
        throw new Exception('Database error: Unable to fetch trips.');
    }

    $trips = [];
    while ($row = $result->fetch_assoc()) {
        $trips[] = [
            'id' => $row['id'],
            'date' => $row['created_at'],
            'destination' => $row['city'] . ', ' . $row['country_region'],
            'activity' => $row['activity'],
            'info_trip' => $row['info_trip'] ?: 'N/A' // Handle NULL info_trip
        ];
    }

    error_log("[$request_time] Fetched " . count($trips) . " trips for user_id=$user_id");
    echo json_encode([
        'success' => true,
        'user' => $user_data,
        'trips' => $trips
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