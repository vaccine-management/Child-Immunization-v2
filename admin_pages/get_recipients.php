<?php
// Turn off PHP error reporting for JSON output
error_reporting(0);
ini_set('display_errors', 0);

// Define root path for includes
define('ROOT_PATH', dirname(__FILE__) . '/../');

// Set content type to JSON at the very beginning
header('Content-Type: application/json');

try {
    // Include the auth check file
    require_once ROOT_PATH . 'includes/auth_check.php';

    // Include the database connection file
    require_once ROOT_PATH . 'backend/db.php';

    // Check if user has admin role
    if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role']) !== 'admin') {
        echo json_encode(['error' => 'Unauthorized access']);
        exit();
    }

    // Query to get all children with their guardian information
    $query = "SELECT child_id, full_name, guardian_name, phone FROM children WHERE phone IS NOT NULL AND phone != ''";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return as JSON
    echo json_encode($recipients);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
