<?php
// Define root path for includes
define('ROOT_PATH', dirname(__FILE__) . '/../');

session_start();

// Include the auth check file
require_once ROOT_PATH . 'includes/auth_check.php';

// Only allow admins to access this page
checkAdminRole();

// Include the database connection file
require_once ROOT_PATH . 'backend/db.php';

// Prepare response array
$response = [
    'success' => false,
    'message' => ''
];

// Check if an ID was provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $response['message'] = "No user ID specified for deletion.";
    echo json_encode($response);
    exit();
}

$userId = $_GET['id'];

// Don't allow deletion of the current user
if ($_SESSION['user']['id'] == $userId) {
    $response['message'] = "You cannot delete your own account.";
    echo json_encode($response);
    exit();
}

try {
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $response['message'] = "User not found.";
        echo json_encode($response);
        exit();
    }
    
    // Delete the user
    $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $deleteStmt->execute([$userId]);
    
    if ($deleteStmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = "User '" . $user['username'] . "' has been deleted successfully.";
    } else {
        $response['message'] = "Failed to delete user.";
    }
    
} catch (PDOException $e) {
    $response['message'] = "Database error: " . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>