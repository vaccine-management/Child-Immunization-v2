<?php
require_once 'db.php';

// Backup users, children, appointments, and password_resets if they exist
try {
    // Check if users table exists and create a backup
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        $conn->exec("CREATE TABLE IF NOT EXISTS users_backup AS SELECT * FROM users");
        echo "Users table backed up.<br>";
    }
    
    // Check if children table exists and create a backup
    $stmt = $conn->query("SHOW TABLES LIKE 'children'");
    if ($stmt->rowCount() > 0) {
        $conn->exec("CREATE TABLE IF NOT EXISTS children_backup AS SELECT * FROM children");
        echo "Children table backed up.<br>";
    }
    
    // Check if appointments table exists and create a backup
    $stmt = $conn->query("SHOW TABLES LIKE 'appointments'");
    if ($stmt->rowCount() > 0) {
        $conn->exec("CREATE TABLE IF NOT EXISTS appointments_backup AS SELECT * FROM appointments");
        echo "Appointments table backed up.<br>";
    }
    
    // Check if password_resets table exists and create a backup
    $stmt = $conn->query("SHOW TABLES LIKE 'password_resets'");
    if ($stmt->rowCount() > 0) {
        $conn->exec("CREATE TABLE IF NOT EXISTS password_resets_backup AS SELECT * FROM password_resets");
        echo "Password resets table backed up.<br>";
    }
    
    // Check if sms_logs table exists and create a backup
    $stmt = $conn->query("SHOW TABLES LIKE 'sms_logs'");
    if ($stmt->rowCount() > 0) {
        $conn->exec("CREATE TABLE IF NOT EXISTS sms_logs_backup AS SELECT * FROM sms_logs");
        echo "SMS logs table backed up.<br>";
    }
} catch (PDOException $e) {
    echo "Error creating backups: " . $e->getMessage() . "<br>";
}

// Try to drop tables in reverse order (dependent tables first)
try {
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Drop existing redundant or outdated tables
    $tables_to_drop = [
        'sms_logs',
        'generated_reports',
        'appointment_vaccines',
        'vaccinations',
        'appointments',
        'inventory',
        'vaccine_schedule',
        'vaccines'
    ];
    
    foreach ($tables_to_drop as $table) {
        try {
            $conn->exec("DROP TABLE IF EXISTS $table");
            echo "Table $table dropped (if existed).<br>";
        } catch (PDOException $e) {
            echo "Error dropping table $table: " . $e->getMessage() . "<br>";
        }
    }
    
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
} catch (PDOException $e) {
    echo "Error during table drop operations: " . $e->getMessage() . "<br>";
}

// Create tables in order (parent tables first)
try {
    // Only create users table if it doesn't exist
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("
        -- 1. Users table
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('Admin', 'Nurse') NOT NULL,
            profile_image VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "Users table created.<br>";
        
        // Restore data from backup if it exists
        $stmt = $conn->query("SHOW TABLES LIKE 'users_backup'");
        if ($stmt->rowCount() > 0) {
            $conn->exec("INSERT INTO users SELECT * FROM users_backup");
            echo "Users data restored from backup.<br>";
        }
    } else {
        // Check if profile_image column exists in users table
        try {
            $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
            if ($stmt->rowCount() == 0) {
                // profile_image column doesn't exist, add it
                $conn->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL");
                echo "Added profile_image column to users table.<br>";
            }
        } catch (PDOException $e) {
            echo "Error checking/adding profile_image column: " . $e->getMessage() . "<br>";
        }
    }
    
    // Only create children table if it doesn't exist
    $stmt = $conn->query("SHOW TABLES LIKE 'children'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("
        -- 2. Children table
        CREATE TABLE children (
            child_id VARCHAR(50) PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            date_of_birth DATE NOT NULL,
            gender ENUM('Male', 'Female') NOT NULL,
            birth_weight DECIMAL(5,2) NOT NULL,
            place_of_birth VARCHAR(50) NOT NULL,
            guardian_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(100),
            address TEXT NOT NULL,
            birth_complications TEXT,
            allergies TEXT,
            previous_vaccinations TEXT,
            registration_date DATE DEFAULT CURRENT_DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        echo "Children table created.<br>";
        
        // Restore data from backup if it exists
        $stmt = $conn->query("SHOW TABLES LIKE 'children_backup'");
        if ($stmt->rowCount() > 0) {
            $conn->exec("INSERT INTO children SELECT * FROM children_backup");
            echo "Children data restored from backup.<br>";
        }
    }
    
    // Only create password_resets table if it doesn't exist
    $stmt = $conn->query("SHOW TABLES LIKE 'password_resets'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("
        -- 3. Password resets table
        CREATE TABLE password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires DATETIME NOT NULL
        )");
        echo "Password resets table created.<br>";
        
        // Restore data from backup if it exists
        $stmt = $conn->query("SHOW TABLES LIKE 'password_resets_backup'");
        if ($stmt->rowCount() > 0) {
            $conn->exec("INSERT INTO password_resets SELECT * FROM password_resets_backup");
            echo "Password resets data restored from backup.<br>";
        }
    }
    
    // Create consolidated vaccines table
    $conn->exec("
    -- 4. Vaccines table (consolidated with inventory)
    CREATE TABLE vaccines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        max_doses INT NOT NULL,
        target_disease VARCHAR(255) NOT NULL,
        description TEXT,
        administration_method VARCHAR(100),
        dosage VARCHAR(50),
        manufacturer VARCHAR(255),
        storage_requirements TEXT,
        contraindications TEXT,
        side_effects TEXT,
        batch_number VARCHAR(255) NOT NULL,
        quantity INT NOT NULL DEFAULT 0,
        expiry_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_batch (name, batch_number)
    )");
    echo "Vaccines table created.<br>";
    
    // Create vaccine schedule table
    $conn->exec("
    -- 5. Vaccine schedule table
    CREATE TABLE vaccine_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vaccine_id INT NOT NULL,
        dose_number INT NOT NULL,
        age_unit ENUM('days', 'weeks', 'months', 'years') NOT NULL,
        age_value INT NOT NULL,
        is_required BOOLEAN DEFAULT TRUE,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_vaccine_dose (vaccine_id, dose_number),
        FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE CASCADE
    )");
    echo "Vaccine schedule table created.<br>";
    
    // Create inventory table
    $conn->exec("
    -- 6. Inventory table
    CREATE TABLE inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vaccine_id INT NOT NULL,
        batch_number VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        expiry_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE CASCADE,
        UNIQUE KEY unique_batch (vaccine_id, batch_number)
    )");
    echo "Inventory table created.<br>";
    
    // Only create appointments table if it doesn't exist
    $stmt = $conn->query("SHOW TABLES LIKE 'appointments'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("
        -- 7. Appointments table
        CREATE TABLE appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            child_id VARCHAR(50),
            scheduled_date DATE NOT NULL,
            status ENUM('scheduled', 'completed', 'partially_completed', 'missed', 'rescheduled') DEFAULT 'scheduled',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_child_date (child_id, scheduled_date),
            FOREIGN KEY (child_id) REFERENCES children(child_id) ON DELETE CASCADE
        )");
        echo "Appointments table created.<br>";
        
        // Restore data from backup if it exists
        $stmt = $conn->query("SHOW TABLES LIKE 'appointments_backup'");
        if ($stmt->rowCount() > 0) {
            $conn->exec("INSERT INTO appointments SELECT * FROM appointments_backup");
            echo "Appointments data restored from backup.<br>";
        }
    }
    
    // Create appointment vaccines table
    $conn->exec("
    -- 8. Appointment vaccines table
    CREATE TABLE appointment_vaccines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NOT NULL,
        vaccine_id INT NOT NULL,
        dose_number INT NOT NULL,
        status ENUM('scheduled', 'completed', 'missed') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_appointment (appointment_id),
        UNIQUE KEY unique_appt_vaccine (appointment_id, vaccine_id, dose_number),
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
        FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE CASCADE
    )");
    echo "Appointment vaccines table created.<br>";
    
    // Create vaccinations table
    $conn->exec("
    -- 9. Vaccinations table
    CREATE TABLE vaccinations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        child_id VARCHAR(50) NOT NULL,
        vaccine_id INT NOT NULL,
        dose_number INT NOT NULL,
        appointment_id INT,
        administered_date DATE NOT NULL,
        administered_by INT NOT NULL,
        administration_site VARCHAR(50),
        notes TEXT,
        side_effects TEXT,
        status ENUM('Administered', 'Missed', 'Cancelled') DEFAULT 'Administered',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_child_vaccine_dose (child_id, vaccine_id, dose_number),
        FOREIGN KEY (child_id) REFERENCES children(child_id) ON DELETE CASCADE,
        FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE RESTRICT,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
        FOREIGN KEY (administered_by) REFERENCES users(id) ON DELETE RESTRICT
    )");
    echo "Vaccinations table created.<br>";
    
    // Create reports table
    $conn->exec("
    -- 10. Generated reports table
    CREATE TABLE generated_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_name VARCHAR(255) NOT NULL,
        type VARCHAR(50) NOT NULL,
        generated_by INT NOT NULL,
        generated_date DATETIME NOT NULL,
        file_path VARCHAR(255),
        FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE RESTRICT
    )");
    echo "Generated reports table created.<br>";
    
    // Create or update SMS logs table
    $conn->exec("
    -- 11. SMS logs table
    CREATE TABLE sms_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        child_id VARCHAR(50) NULL,
        vaccine_id INT NULL,
        dose_number INT NULL,
        recipient VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        response TEXT NULL,
        status ENUM('success', 'failed') DEFAULT 'success',
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        message_type ENUM('registration', 'reminder', 'missed', 'rescheduled') NOT NULL,
        FOREIGN KEY (child_id) REFERENCES children(child_id) ON DELETE SET NULL,
        FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE SET NULL,
        INDEX idx_child (child_id),
        INDEX idx_recipient (recipient),
        INDEX idx_sent_at (sent_at),
        INDEX idx_message_type (message_type),
        INDEX idx_status (status)
    )");
    echo "SMS logs table created.<br>";
    
    // Restore data from backup if it exists
    $stmt = $conn->query("SHOW TABLES LIKE 'sms_logs_backup'");
    if ($stmt->rowCount() > 0) {
        // Need to update the schema before restoring data
        echo "SMS logs backup exists but not restored automatically due to schema changes.<br>";
    }
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "<br>";
}

// Populate the vaccines table with initial data
try {
    $conn->exec("
    INSERT INTO vaccines (name, max_doses, target_disease, description, administration_method, dosage) VALUES
    ('BCG', 1, 'Tuberculosis', 'Bacillus Calmette-Guerin', 'intradermal injection', '0.05ml'),
    ('Polio Vaccine', 4, 'Poliomyelitis', 'Oral Polio Vaccine', 'oral', '2 drops'),
    ('Pentavalent', 3, 'Diphtheria, Pertussis, Tetanus, Hepatitis B, Haemophilus Influenza', 'Pentavalent Vaccine (or Hexaxim/Infanrix)', 'intramuscular injection', '0.5ml'),
    ('Pneumococcal', 3, 'Pneumococcal disease', 'Pneumococcal Vaccine (Prevenar 13)', 'intramuscular injection', '0.5ml'),
    ('Rotavirus', 3, 'Rotavirus infection', 'Rota Virus Vaccine (Rotarix)', 'oral', '1.5ml'),
    ('Vitamin A', 5, 'Vitamin A deficiency', 'Vitamin A supplementation', 'oral', '100,000/200,000 IU'),
    ('Flu', 2, 'Influenza', 'Influenza Vaccine', 'injection', NULL),
    ('Measles-Rubella', 2, 'Measles and Rubella', 'Measles Rubella Vaccine', 'subcutaneous injection', '0.5ml'),
    ('Meningococcal', 2, 'Meningococcal disease', 'Meningococcal ACYW Conjugate Vaccine', 'injection', NULL),
    ('Deworming', 4, 'Intestinal worms', 'Deworming with Albendazole', 'oral', '200mg'),
    ('Hepatitis-A', 1, 'Hepatitis A', 'Hepatitis A Vaccine', 'injection', NULL),
    ('Varicella', 1, 'Chickenpox', 'Varicella (Chickenpox) Vaccine', 'injection', NULL),
    ('MMR', 2, 'Measles, Mumps, and Rubella', 'Measles, Mumps, Rubella Vaccine', 'injection', NULL),
    ('Typhoid', 1, 'Typhoid fever', 'Typhoid Vaccine', 'injection', NULL)
    ");
    echo "Vaccines data inserted.<br>";
} catch (PDOException $e) {
    echo "Error inserting vaccines data: " . $e->getMessage() . "<br>";
}

// Set up the vaccine schedule
try {
    $conn->exec("
    INSERT INTO vaccine_schedule (vaccine_id, dose_number, age_unit, age_value, notes)
    SELECT 
        v.id as vaccine_id,
        s.dose_number,
        s.age_unit,
        s.age_value,
        s.notes
    FROM 
    (
        -- BCG at birth
        SELECT 'BCG' as name, 1 as dose_number, 'days' as age_unit, 0 as age_value, 'Administered in left forearm' as notes
        
        -- Polio doses
        UNION SELECT 'Polio Vaccine', 1, 'days', 0, 'Oral administration'
        UNION SELECT 'Polio Vaccine', 2, 'weeks', 6, 'Oral administration'
        UNION SELECT 'Polio Vaccine', 3, 'weeks', 10, 'Oral administration'
        UNION SELECT 'Polio Vaccine', 4, 'weeks', 14, 'Oral administration'
        
        -- Pentavalent doses
        UNION SELECT 'Pentavalent', 1, 'weeks', 6, 'Administered in left outer thigh'
        UNION SELECT 'Pentavalent', 2, 'weeks', 10, 'Administered in left outer thigh'
        UNION SELECT 'Pentavalent', 3, 'weeks', 14, 'Administered in left outer thigh'
        
        -- Pneumococcal doses
        UNION SELECT 'Pneumococcal', 1, 'weeks', 6, 'Administered in right outer thigh'
        UNION SELECT 'Pneumococcal', 2, 'weeks', 10, 'Administered in right outer thigh'
        UNION SELECT 'Pneumococcal', 3, 'weeks', 14, 'Administered in right outer thigh'
        
        -- Rotavirus doses
        UNION SELECT 'Rotavirus', 1, 'weeks', 6, 'Oral administration'
        UNION SELECT 'Rotavirus', 2, 'weeks', 10, 'Oral administration'
        UNION SELECT 'Rotavirus', 3, 'weeks', 14, 'Oral administration'
        
        -- Vitamin A doses
        UNION SELECT 'Vitamin A', 1, 'months', 6, 'Oral administration'
        UNION SELECT 'Vitamin A', 2, 'months', 12, 'Oral administration'
        UNION SELECT 'Vitamin A', 3, 'months', 18, 'Oral administration'
        UNION SELECT 'Vitamin A', 4, 'years', 2, NULL
        
        -- Flu doses
        UNION SELECT 'Flu', 1, 'months', 7, NULL
        UNION SELECT 'Flu', 2, 'years', 2, 'Annual vaccination'
        
        -- Measles-Rubella doses
        UNION SELECT 'Measles-Rubella', 1, 'months', 9, 'Administered in right upper arm'
        UNION SELECT 'Measles-Rubella', 2, 'months', 18, 'Administered in right upper arm'
        
        -- Meningococcal doses
        UNION SELECT 'Meningococcal', 1, 'months', 10, 'Protects against strains A, C, Y, and W'
        UNION SELECT 'Meningococcal', 2, 'months', 15, NULL
        
        -- Deworming doses
        UNION SELECT 'Deworming', 1, 'months', 12, 'Oral administration'
        UNION SELECT 'Deworming', 2, 'months', 18, 'Oral administration'
        UNION SELECT 'Deworming', 3, 'years', 2, NULL
        
        -- Hepatitis-A dose
        UNION SELECT 'Hepatitis-A', 1, 'months', 12, NULL
        
        -- Varicella dose
        UNION SELECT 'Varicella', 1, 'months', 12, NULL
        
        -- MMR doses
        UNION SELECT 'MMR', 1, 'months', 15, NULL
        UNION SELECT 'MMR', 2, 'months', 18, NULL
        
        -- Typhoid dose
        UNION SELECT 'Typhoid', 1, 'years', 2, NULL
    ) as s
    JOIN vaccines v ON v.name = s.name
    ");
    echo "Vaccine schedule data inserted.<br>";
} catch (PDOException $e) {
    echo "Error inserting vaccine schedule data: " . $e->getMessage() . "<br>";
}

// Drop backup tables if requested
if (false) { // Set to true when you want to clean up backups
    try {
        $conn->exec("DROP TABLE IF EXISTS users_backup");
        $conn->exec("DROP TABLE IF EXISTS children_backup");
        $conn->exec("DROP TABLE IF EXISTS appointments_backup");
        $conn->exec("DROP TABLE IF EXISTS password_resets_backup");
        $conn->exec("DROP TABLE IF EXISTS sms_logs_backup");
        echo "Backup tables dropped.<br>";
    } catch (PDOException $e) {
        echo "Error dropping backup tables: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>Database schema updated successfully!</h2>";
echo "<p>Please run this file to implement the schema changes.</p>";
?> 