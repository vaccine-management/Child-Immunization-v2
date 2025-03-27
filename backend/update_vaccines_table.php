<?php
require_once 'db.php';

try {
    // Start transaction
    $conn->beginTransaction();

    // Check if columns exist before adding them
    $columns = $conn->query("SHOW COLUMNS FROM vaccines")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('quantity', $columns)) {
        $conn->exec("ALTER TABLE vaccines ADD COLUMN quantity INT DEFAULT 0");
    }
    if (!in_array('batch_number', $columns)) {
        $conn->exec("ALTER TABLE vaccines ADD COLUMN batch_number VARCHAR(50)");
    }
    if (!in_array('expiry_date', $columns)) {
        $conn->exec("ALTER TABLE vaccines ADD COLUMN expiry_date DATE");
    }

    // Check if inventory table exists before trying to migrate data
    $tables = $conn->query("SHOW TABLES LIKE 'inventory'")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($tables)) {
        // Disable foreign key checks temporarily
        $conn->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Migrate data from inventory to vaccines
        $conn->exec("UPDATE vaccines v 
                     INNER JOIN inventory i ON v.id = i.vaccine_id 
                     SET v.quantity = i.quantity,
                         v.batch_number = i.batch_number,
                         v.expiry_date = i.expiry_date");

        // Drop the inventory table
        $conn->exec("DROP TABLE IF EXISTS inventory");

        // Re-enable foreign key checks
        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    // Commit transaction
    $conn->commit();

    echo "Successfully updated vaccines table!";
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
?> 