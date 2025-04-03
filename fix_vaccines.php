<?php
require_once 'backend/db.php';

echo "<h1>Fixing Vaccination Records</h1>";

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Count total vaccination records
    $stmt = $conn->query("SELECT COUNT(*) FROM vaccinations");
    $totalVaccinations = $stmt->fetchColumn();
    
    // Count records with missing vaccine_id
    $stmt = $conn->query("SELECT COUNT(*) FROM vaccinations WHERE vaccine_id IS NULL OR vaccine_id = 0");
    $missingVaccineIds = $stmt->fetchColumn();
    
    echo "<p>Total vaccination records: $totalVaccinations</p>";
    echo "<p>Records with missing vaccine_id: $missingVaccineIds</p>";
    
    if ($missingVaccineIds > 0) {
        // Update all vaccination records to set the correct vaccine_id based on vaccine_name
        $stmt = $conn->prepare("
            UPDATE vaccinations v
            SET v.vaccine_id = (
                SELECT id 
                FROM vaccines 
                WHERE name = v.vaccine_name
                LIMIT 1
            )
            WHERE v.vaccine_name IS NOT NULL 
            AND (v.vaccine_id IS NULL OR v.vaccine_id = 0)
        ");
        
        $stmt->execute();
        $updatedRows = $stmt->rowCount();
        
        echo "<p>Updated $updatedRows vaccination records with correct vaccine_id values.</p>";
        
        // Update appointment_vaccines records too
        $stmt = $conn->prepare("
            UPDATE appointment_vaccines av
            SET av.vaccine_id = (
                SELECT id 
                FROM vaccines 
                WHERE name = av.vaccine_name
                LIMIT 1
            )
            WHERE av.vaccine_name IS NOT NULL 
            AND (av.vaccine_id IS NULL OR av.vaccine_id = 0)
        ");
        
        $stmt->execute();
        $updatedAppointmentVaccines = $stmt->rowCount();
        
        echo "<p>Updated $updatedAppointmentVaccines appointment_vaccines records with correct vaccine_id values.</p>";
    }
    
    // Commit transaction
    $conn->commit();
    
    echo "<h2>Database fix completed successfully!</h2>";
    echo "<p>Return to <a href='child_profile.php?id=" . $_GET['id'] . "'>Child Profile</a></p>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 