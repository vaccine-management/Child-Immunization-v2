<?php
require_once 'backend/db.php';

echo "<h1>Fixing Database Schema</h1>";

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Check if administered_by column exists in vaccinations table and has NOT NULL constraint
    $stmt = $conn->query("SHOW COLUMNS FROM vaccinations LIKE 'administered_by'");
    $administered_by_column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($administered_by_column && strpos($administered_by_column['Null'], 'NO') !== false) {
        // Modify administered_by to allow NULL for scheduled vaccines that haven't been administered yet
        $conn->exec("ALTER TABLE vaccinations MODIFY administered_by INT NULL");
        echo "<p>Modified administered_by column to allow NULL values for scheduled vaccines.</p>";
    }
    
    // Count vaccinations with NULL vaccine_id
    $stmt = $conn->query("SELECT COUNT(*) FROM vaccinations WHERE vaccine_id IS NULL");
    $missing_vaccine_ids = $stmt->fetchColumn();
    
    if ($missing_vaccine_ids > 0) {
        // Update all vaccinations to set vaccine_id based on vaccine_name
        $stmt = $conn->prepare("
            UPDATE vaccinations v
            SET v.vaccine_id = (
                SELECT id 
                FROM vaccines 
                WHERE name = v.vaccine_name
                LIMIT 1
            )
            WHERE v.vaccine_name IS NOT NULL 
            AND v.vaccine_id IS NULL
        ");
        
        $stmt->execute();
        $updatedVaccinations = $stmt->rowCount();
        
        echo "<p>Updated $updatedVaccinations vaccination records with correct vaccine_id values.</p>";
    }
    
    // Count appointment_vaccines with NULL vaccine_id
    $stmt = $conn->query("SELECT COUNT(*) FROM appointment_vaccines WHERE vaccine_id IS NULL");
    $missing_av_vaccine_ids = $stmt->fetchColumn();
    
    if ($missing_av_vaccine_ids > 0) {
        // Update all appointment_vaccines to set vaccine_id based on vaccine_name
        $stmt = $conn->prepare("
            UPDATE appointment_vaccines av
            SET av.vaccine_id = (
                SELECT id 
                FROM vaccines 
                WHERE name = av.vaccine_name
                LIMIT 1
            )
            WHERE av.vaccine_name IS NOT NULL 
            AND av.vaccine_id IS NULL
        ");
        
        $stmt->execute();
        $updatedAppointmentVaccines = $stmt->rowCount();
        
        echo "<p>Updated $updatedAppointmentVaccines appointment_vaccines records with correct vaccine_id values.</p>";
    }
    
    // Check for administered vaccines with NULL administered_by
    $stmt = $conn->query("SELECT COUNT(*) FROM vaccinations WHERE status = 'Administered' AND administered_by IS NULL");
    $missing_admin = $stmt->fetchColumn();
    
    if ($missing_admin > 0) {
        // Get a valid admin user id
        $stmt = $conn->query("SELECT id FROM users WHERE role = 'Nurse' OR role = 'Admin' LIMIT 1");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            // Update administered vaccines to set a default administrator
            $stmt = $conn->prepare("
                UPDATE vaccinations 
                SET administered_by = :admin_id
                WHERE status = 'Administered' AND administered_by IS NULL
            ");
            
            $stmt->execute([':admin_id' => $admin['id']]);
            $updatedAdmins = $stmt->rowCount();
            
            echo "<p>Updated $updatedAdmins administered vaccines with a default administrator.</p>";
        } else {
            echo "<p>Warning: No admin user found to set as default administrator for vaccines.</p>";
        }
    }
    
    // Commit all changes
    $conn->commit();
    
    echo "<h2>Database fix completed successfully!</h2>";
    
    // Check vaccinations table
    echo "<h3>Checking Vaccinations Table Status:</h3>";
    
    // Count scheduled vaccinations
    $stmt = $conn->query("SELECT COUNT(*) FROM vaccinations WHERE status = 'Scheduled'");
    $scheduled_count = $stmt->fetchColumn();
    
    // Count administered vaccinations
    $stmt = $conn->query("SELECT COUNT(*) FROM vaccinations WHERE status = 'Administered'");
    $administered_count = $stmt->fetchColumn();
    
    echo "<p>Total scheduled vaccinations: $scheduled_count</p>";
    echo "<p>Total administered vaccinations: $administered_count</p>";
    
    // Provide a link back to the child profile if an ID was provided
    if (isset($_GET['id'])) {
        echo "<p>Return to <a href='child_profile.php?id=" . $_GET['id'] . "'>Child Profile</a></p>";
    } else {
        echo "<p>Return to <a href='children.php'>Children List</a></p>";
    }
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 