<?php
// Start output buffering
ob_start();

// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set CORS headers to allow the frontend to access the response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Default response
$response = ['success' => false, 'error' => 'Script execution failed'];

try {
    // Include database connection inside try block to catch errors
    if (!file_exists('db.php')) {
        throw new Exception('Database connection file not found');
    }
    include 'db.php';

    // Check if userEmail cookie exists and is not empty
    if (!isset($_COOKIE['userEmail'])) {
        throw new Exception('Unauthorized: Please log in (cookie not set).');
    }

    $email = trim($_COOKIE['userEmail']);
    if (empty($email)) {
        throw new Exception('Unauthorized: Please log in (cookie empty).');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Unauthorized: Invalid email format.');
    }

    // Verify database connection
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn ? $conn->connect_error : 'Connection object is null'));
    }

    // Escape the email for safe database query
    $email = $conn->real_escape_string($email);

    // Verify the email exists in the database and get user_id
    $sql = "SELECT id FROM users WHERE email = '$email'";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Database error: Unable to verify user - ' . $conn->error);
    }
    if ($result->num_rows === 0) {
        throw new Exception('Unauthorized: User not found.');
    }

    $user = $result->fetch_assoc();
    $user_id = $user['id'];

    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed.');
    }

    // Retrieve and sanitize form data
    $title = isset($_POST['title']) ? $conn->real_escape_string($_POST['title']) : '';
    $details = isset($_POST['details']) ? $conn->real_escape_string($_POST['details']) : '';

    // Validate inputs
    if (empty($title) || empty($details)) {
        throw new Exception('Title and details are required.');
    }

    // Handle image upload
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            throw new Exception('Upload directory does not exist.');
        }
        if (!is_writable($uploadDir)) {
            throw new Exception('Upload directory is not writable.');
        }

        $imageName = uniqid() . '-' . basename($_FILES['image']['name']);
        $imagePath = $uploadDir . $imageName;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
            throw new Exception('Failed to upload image: ' . $_FILES['image']['error']);
        }
        $image_path = $conn->real_escape_string($imagePath);
    }

    // Insert into travel_logs table
    $sql = "INSERT INTO travel_logs (user_id, title, details, image_path, created_at)
            VALUES ('$user_id', '$title', '$details', '$image_path', NOW())";
    
    if ($conn->query($sql) !== TRUE) {
        throw new Exception('Error saving travel log: ' . $conn->error);
    }

    $response = ['success' => true, 'message' => 'Travel log saved successfully.'];

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code(400);
} finally {
    // Ensure a JSON response is always sent
    echo json_encode($response);
    if (isset($conn)) {
        $conn->close();
    }
}

// Flush the output buffer without clearing it
ob_end_flush();
?>