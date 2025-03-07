<?php
// filepath: /c:/xampp/htdocs/Child-Immunization-v2/get_inventory_item.php
session_start();

// Basic error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly to users

// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Include database connection
include 'backend/db.php';

// Get item details based on ID
if (isset($_GET['id'])) {
    $itemId = $_GET['id'];
    try {
        // Make sure to use the exact column names from your database
        $stmt = $conn->prepare("SELECT id, name, batch_number, quantity, expiry_date FROM inventory WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            // Format date for HTML date input if needed
            $item['expiry_date'] = date('Y-m-d', strtotime($item['expiry_date']));
            echo json_encode($item);
        } else {
            echo json_encode(['error' => 'Item not found']);
        }
    } catch (PDOException $e) {
        // Log error for server-side debugging
        error_log("Database error in get_inventory_item.php: " . $e->getMessage());
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No item ID provided']);
}