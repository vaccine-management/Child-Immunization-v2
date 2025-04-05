<?php
date_default_timezone_set('Africa/Nairobi');
// Database connection details
$host = 'localhost';
$dbname = 'immunization_system';
$user = 'root'; 
$password_db = ''; 

try {
    // Connect to MySQL
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $password_db);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>