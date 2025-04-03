<?php
// Include database connection
include 'backend/db.php';

try {
    // Check if appointment_vaccines table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'appointment_vaccines'");
    
    if ($stmt->rowCount() == 0) {
        // Create the appointment_vaccines table
        $conn->exec("
        CREATE TABLE appointment_vaccines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            vaccine_id INT NOT NULL,
            vaccine_name VARCHAR(255) NOT NULL, 
            dose_number INT NOT NULL,
            status ENUM('scheduled', 'administered', 'missed') DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_appointment (appointment_id),
            UNIQUE KEY unique_appt_vaccine (appointment_id, vaccine_id, dose_number),
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
            FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE CASCADE
        )");
        echo "SUCCESS: Created appointment_vaccines table.<br>";
    } else {
        echo "INFO: appointment_vaccines table already exists.<br>";
    }
    
    // Check if vaccinations table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'vaccinations'");
    
    if ($stmt->rowCount() == 0) {
        // Create the vaccinations table
        $conn->exec("
        CREATE TABLE vaccinations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            child_id VARCHAR(50) NOT NULL,
            vaccine_id INT,
            vaccine_name VARCHAR(255) NOT NULL,
            dose_number INT NOT NULL,
            scheduled_date DATE NOT NULL,
            scheduled_time TIME,
            administered_date DATE,
            status ENUM('Scheduled', 'Administered', 'Missed', 'Rescheduled', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
            administered_by INT,
            notes TEXT,
            adverse_events TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (child_id) REFERENCES children(child_id) ON DELETE CASCADE,
            FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE SET NULL,
            FOREIGN KEY (administered_by) REFERENCES users(id) ON DELETE SET NULL
        )");
        echo "SUCCESS: Created vaccinations table.<br>";
    } else {
        echo "INFO: vaccinations table already exists.<br>";
    }

    // Let's also check the appointments table while we're at it
    $stmt = $conn->query("SHOW TABLES LIKE 'appointments'");
    
    if ($stmt->rowCount() == 0) {
        // Create the appointments table
        $conn->exec("
        CREATE TABLE appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            child_id VARCHAR(50),
            scheduled_date DATE NOT NULL,
            scheduled_time TIME DEFAULT '09:00:00',
            status ENUM('scheduled', 'completed', 'missed', 'rescheduled', 'cancelled') NOT NULL DEFAULT 'scheduled',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_child_date (child_id, scheduled_date),
            FOREIGN KEY (child_id) REFERENCES children(child_id) ON DELETE CASCADE
        )");
        echo "SUCCESS: Created appointments table.<br>";
    } else {
        echo "INFO: appointments table already exists.<br>";
    }

    echo "<br>Fix completed. You should now be able to access the dashboard.";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?> 