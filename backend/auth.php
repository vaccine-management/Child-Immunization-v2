<?php
include '../backend/db.php';

// Get the JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$response = ['status' => 'error', 'message' => ''];

try {
    // Validate required fields
    if (empty($data['email']) || empty($data['password']) || empty($data['role'])) {
        throw new Exception('All fields are required');
    }

    $email = $data['email'];
    $password = $data['password'];
    $role = $data['role'];

    // Add debugging
    error_log("Login attempt - Email: $email, Role: $role");

    // Fetch user by email and role
    $stmt = $conn->prepare("SELECT id, email, password, role, username FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug user data from database
    error_log("Database returned user data: " . print_r($user, true));

    if ($user && $user['password'] === $password) { // Plain text password comparison
        error_log("User authenticated successfully");
        session_start();
        
        // Set session data
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'username' => $user['username'] // Store the username
        ];

        // Debug session data
        error_log("Session data set: " . print_r($_SESSION['user'], true));

        $response['status'] = 'success';
        $response['message'] = 'Login successful';
        $response['user'] = [
            'username' => $user['username'],
            'role' => $user['role']
        ];
    } else {
        error_log("Authentication failed for email: $email and role: $role");
        $response['message'] = 'Invalid email, password, or role';
    }
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Set headers and return response
header('Content-Type: application/json');
echo json_encode($response);
?>