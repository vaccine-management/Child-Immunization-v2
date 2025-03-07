<?php
// filepath: /c:/xampp/htdocs/Child-Immunization-v2/update_inventory_item.php
session_start();

// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Include database connection
include 'backend/db.php';

// Handle item update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $itemId = $_POST['id'];
    $name = trim($_POST['name']);
    $batch_number = trim($_POST['batch_number']);
    $quantity = trim($_POST['quantity']);
    $expiry_date = trim($_POST['expiry_date']);

    if (empty($name) || empty($batch_number) || empty($quantity) || empty($expiry_date)) {
        echo json_encode(['error' => 'All fields are required']);
        exit();
    }

    try {
        $stmt = $conn->prepare("UPDATE inventory SET name = ?, batch_number = ?, quantity = ?, expiry_date = ? WHERE id = ?");
        $stmt->execute([$name, $batch_number, $quantity, $expiry_date, $itemId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => 'Item updated successfully']);
        } else {
            echo json_encode(['success' => 'No changes made']); // Still successful even if no changes
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}