<?php
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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

    $sql = "SELECT id, first_name, last_name, email, profile_image FROM users WHERE email = '$email'";
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        throw new Exception('Unauthorized: User not found.');
    }

    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $user_data = [
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'profile_image' => $user['profile_image'] ? 'http://localhost/my-app-api/uploads/' . $user['profile_image'] : null
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB

            // Validate file type and size
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
            }
            if ($file['size'] > $max_size) {
                throw new Exception('File size exceeds 5MB limit.');
            }

            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generate unique filename
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new Exception('Failed to upload image.');
            }

            // Delete old profile image if it exists
            if ($user['profile_image'] && file_exists($upload_dir . $user['profile_image'])) {
                unlink($upload_dir . $user['profile_image']);
            }

            // Update database with new image path
            $sql = "UPDATE users SET profile_image = '$new_filename' WHERE id = '$user_id'";
            if (!$conn->query($sql)) {
                throw new Exception('Failed to update profile image: ' . $conn->error);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Profile image updated successfully.',
                'profile_image' => 'http://localhost/my-app-api/uploads/' . $new_filename
            ]);
            $conn->close();
            ob_end_flush();
            exit;
        } else {
            throw new Exception('No file uploaded or upload error.');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $sql = "DELETE FROM trips WHERE user_id = '$user_id'";
        if (!$conn->query($sql)) {
            throw new Exception('Error deleting trip history: ' . $conn->error);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Trip history cleared successfully.'
        ]);
        $conn->close();
        ob_end_flush();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed.');
    }

    $sql = "SELECT id, created_at, city, country_region, activity, info_trip 
            FROM trips 
            WHERE user_id = '$user_id' 
            ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Database error: Unable to fetch trips.');
    }

    $trips = [];
    while ($row = $result->fetch_assoc()) {
        $trips[] = [
            'id' => $row['id'],
            'date' => $row['created_at'],
            'destination' => $row['city'] . ', ' . $row['country_region'],
            'activity' => $row['activity'],
            'info_trip' => $row['info_trip'] ?: 'N/A'
        ];
    }

    echo json_encode([
        'success' => true,
        'user' => $user_data,
        'trips' => $trips
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