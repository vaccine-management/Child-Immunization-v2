<?php
// Script to create SMS logs table
require_once 'db.php';

try {
    // Create SMS logs table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS sms_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vaccination_id INT NULL,
            recipient VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            response TEXT NULL,
            status ENUM('success', 'failed') DEFAULT 'success',
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (vaccination_id),
            INDEX (recipient),
            INDEX (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    echo "SMS logs table created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 