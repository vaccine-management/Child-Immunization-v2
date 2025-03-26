<?php
require_once 'backend/db.php';

echo "<h1>Vaccinations Table Structure</h1>";

try {
    $stmt = $conn->query("DESCRIBE vaccinations");
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Also check if there are any rows in the table
    $count = $conn->query("SELECT COUNT(*) FROM vaccinations")->fetchColumn();
    echo "<p>Number of rows in vaccinations table: " . $count . "</p>";
    
    // Check if scheduled_date and scheduled_time columns exist
    $stmt = $conn->query("SHOW COLUMNS FROM vaccinations LIKE 'scheduled_date'");
    $scheduled_date_exists = $stmt->rowCount() > 0;
    
    $stmt = $conn->query("SHOW COLUMNS FROM vaccinations LIKE 'scheduled_time'");
    $scheduled_time_exists = $stmt->rowCount() > 0;
    
    echo "<p>scheduled_date column exists: " . ($scheduled_date_exists ? "Yes" : "No") . "</p>";
    echo "<p>scheduled_time column exists: " . ($scheduled_time_exists ? "Yes" : "No") . "</p>";
    
} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 