<?php
require_once 'backend/db.php';

echo "<h1>Fixing Database Schema</h1>";

try {
    // Check if scheduled_date column exists in vaccinations table
    $stmt = $conn->query("SHOW COLUMNS FROM vaccinations LIKE 'scheduled_date'");
    $scheduled_date_exists = $stmt->rowCount() > 0;
    
    // Check if scheduled_time column exists in vaccinations table
    $stmt = $conn->query("SHOW COLUMNS FROM vaccinations LIKE 'scheduled_time'");
    $scheduled_time_exists = $stmt->rowCount() > 0;
    
    // Check if vaccine_name column exists in vaccinations table
    $stmt = $conn->query("SHOW COLUMNS FROM vaccinations LIKE 'vaccine_name'");
    $vaccine_name_exists = $stmt->rowCount() > 0;
    
    echo "<p>Current status:</p>";
    echo "<ul>";
    echo "<li>scheduled_date column exists: " . ($scheduled_date_exists ? "Yes" : "No") . "</li>";
    echo "<li>scheduled_time column exists: " . ($scheduled_time_exists ? "Yes" : "No") . "</li>";
    echo "<li>vaccine_name column exists: " . ($vaccine_name_exists ? "Yes" : "No") . "</li>";
    echo "</ul>";
    
    // Add missing columns if they don't exist
    if (!$scheduled_date_exists) {
        $conn->exec("ALTER TABLE vaccinations ADD COLUMN scheduled_date DATE NULL AFTER dose_number");
        echo "<p>Added scheduled_date column to vaccinations table.</p>";
    }
    
    if (!$scheduled_time_exists) {
        $conn->exec("ALTER TABLE vaccinations ADD COLUMN scheduled_time TIME NULL AFTER scheduled_date");
        echo "<p>Added scheduled_time column to vaccinations table.</p>";
    }
    
    if (!$vaccine_name_exists) {
        $conn->exec("ALTER TABLE vaccinations ADD COLUMN vaccine_name VARCHAR(255) NULL AFTER vaccine_id");
        echo "<p>Added vaccine_name column to vaccinations table.</p>";
        
        // Update vaccine_name values based on vaccine_id
        $conn->exec("
            UPDATE vaccinations v
            JOIN vaccines vac ON v.vaccine_id = vac.id
            SET v.vaccine_name = vac.name
            WHERE v.vaccine_name IS NULL
        ");
        echo "<p>Updated vaccine_name values based on vaccine_id.</p>";
    }
    
    // Check if vaccine_name column exists in appointment_vaccines table
    $stmt = $conn->query("SHOW COLUMNS FROM appointment_vaccines LIKE 'vaccine_name'");
    $av_vaccine_name_exists = $stmt->rowCount() > 0;
    
    if (!$av_vaccine_name_exists) {
        $conn->exec("ALTER TABLE appointment_vaccines ADD COLUMN vaccine_name VARCHAR(255) NULL AFTER vaccine_id");
        echo "<p>Added vaccine_name column to appointment_vaccines table.</p>";
        
        // Update vaccine_name values based on vaccine_id
        $conn->exec("
            UPDATE appointment_vaccines av
            JOIN vaccines vac ON av.vaccine_id = vac.id
            SET av.vaccine_name = vac.name
            WHERE av.vaccine_name IS NULL
        ");
        echo "<p>Updated vaccine_name values in appointment_vaccines based on vaccine_id.</p>";
    }
    
    echo "<h2>Database schema updated successfully!</h2>";
    echo "<p>The missing columns have been added to the tables.</p>";
    echo "<p><a href='child_profile.php?id=" . $_GET['id'] . "'>Return to Child Profile</a></p>";
    
} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 