<?php
// Include database connection
include 'backend/db.php';

// Default admin credentials
$admin_username = 'admin';
$admin_email = 'admin@example.com';
$admin_password = 'admin123'; // This will be hashed 
$admin_role = 'Admin';

try {
    // Check if the users table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        // Create the users table if it doesn't exist
        $conn->exec("
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('Admin', 'Nurse') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "Users table created successfully.<br>";
    }
    
    // Check if admin user already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt->bindParam(':email', $admin_email);
    $stmt->execute();
    $admin_exists = $stmt->fetchColumn() > 0;
    
    if ($admin_exists) {
        echo "Admin user already exists.<br>";
    } else {
        // Hash the password
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        
        // Insert admin user
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
        $stmt->bindParam(':username', $admin_username);
        $stmt->bindParam(':email', $admin_email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':role', $admin_role);
        $stmt->execute();
        
        echo "Admin user created successfully.<br>";
    }
    
    // Create a nurse user as well
    $nurse_username = 'nurse';
    $nurse_email = 'nurse@example.com';
    $nurse_password = 'nurse123'; // This will be hashed
    $nurse_role = 'Nurse';
    
    // Check if nurse user already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt->bindParam(':email', $nurse_email);
    $stmt->execute();
    $nurse_exists = $stmt->fetchColumn() > 0;
    
    if ($nurse_exists) {
        echo "Nurse user already exists.<br>";
    } else {
        // Hash the password
        $hashed_password = password_hash($nurse_password, PASSWORD_DEFAULT);
        
        // Insert nurse user
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
        $stmt->bindParam(':username', $nurse_username);
        $stmt->bindParam(':email', $nurse_email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':role', $nurse_role);
        $stmt->execute();
        
        echo "Nurse user created successfully.<br>";
    }
    
    echo "<h3>Login Credentials:</h3>";
    echo "<strong>Admin:</strong><br>";
    echo "Email: " . $admin_email . "<br>";
    echo "Password: " . $admin_password . "<br><br>";
    
    echo "<strong>Nurse:</strong><br>";
    echo "Email: " . $nurse_email . "<br>";
    echo "Password: " . $nurse_password . "<br>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
?> 