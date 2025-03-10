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

// Check if an ID was provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No user ID specified for deletion.";
    header("Location: users.php");
    exit();
}

$userId = $_GET['id'];

// Don't allow deletion of the current user
if ($_SESSION['user']['id'] == $userId) {
    $_SESSION['error_message'] = "You cannot delete your own account.";
    header("Location: users.php");
    exit();
}

try {
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error_message'] = "User not found.";
        header("Location: users.php");
        exit();
    }
    
    // Delete the user
    $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $deleteStmt->execute([$userId]);
    
    if ($deleteStmt->rowCount() > 0) {
        $_SESSION['success_message'] = "User '" . $user['username'] . "' has been deleted successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to delete user.";
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}

// Redirect back to users page
header("Location: users.php");
exit();
?> 