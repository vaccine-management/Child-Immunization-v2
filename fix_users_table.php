<?php
// Include database connection
include 'backend/db.php';

try {
    // Check if profile_image column exists in users table
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    
    if ($stmt->rowCount() == 0) {
        // profile_image column doesn't exist, add it
        $conn->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL");
        echo "SUCCESS: Added profile_image column to users table.";
    } else {
        echo "INFO: profile_image column already exists in users table.";
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?> 