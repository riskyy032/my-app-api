<?php
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';

try {
    if (!isset($_COOKIE['userEmail']) || empty(trim($_COOKIE['userEmail']))) {
        throw new Exception('Unauthorized: Please log in.');
    }

    $email = $conn->real_escape_string(trim($_COOKIE['userEmail']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Unauthorized: Invalid email format.');
    }

    $sql = "SELECT id FROM users WHERE email = '$email'";
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        throw new Exception('Unauthorized: User not found.');
    }

    $user_id = $result->fetch_assoc()['id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed.');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data.');
    }

    $city = isset($data['city']) ? $conn->real_escape_string($data['city']) : '';
    $country = isset($data['country']) ? $conn->real_escape_string($data['country']) : '';
    $activities = isset($data['activities']) && is_array($data['activities']) ? $data['activities'] : [];
    $info_types = isset($data['info_types']) && is_array($data['info_types']) ? $data['info_types'] : [];

    if (empty($city) || empty($country)) {
        throw new Exception('City and country are required.');
    }

    $activities_str = $conn->real_escape_string(implode(',', $activities));
    $info_types_str = $conn->real_escape_string(implode(',', $info_types));

    if (!$conn) {
        throw new Exception('Database connection not established.');
    }

    $sql = "INSERT INTO trips (user_id, city, country_region, activity, info_trip)
            VALUES ('$user_id', '$city', '$country', '$activities_str', '$info_types_str')";
    
    if (!$conn->query($sql)) {
        throw new Exception('Error saving trip: ' . $conn->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Trip saved successfully.'
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