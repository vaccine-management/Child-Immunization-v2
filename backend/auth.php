<?php
session_start(); // Start the session at the very beginning
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'immunization_system';
$user = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['email']) || !isset($data['password']) || !isset($data['role'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input data.']);
        exit;
    }

    $email = $data['email'];
    $inputPassword = $data['password'];
    $role = $data['role'];

    // Fetch user from the database
    $stmt = $conn->prepare("SELECT id, email, password, role FROM users WHERE email = :email AND role = :role");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':role', $role);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify password (plain text comparison in this example)
    if ($user && $inputPassword === $user['password']) {
        // Set session variables
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ];

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful!',
            'user' => $_SESSION['user']
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid email, password, or role.'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
exit;
?>