<?php
// Include database connection
include 'backend/db.php';

// Check if the users table exists
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        echo "The users table does not exist in the database.<br>";
    } else {
        echo "The users table exists in the database.<br>";
        
        // Check if there are any users in the table
        $stmt = $conn->query("SELECT COUNT(*) FROM users");
        $count = $stmt->fetchColumn();
        echo "There are $count users in the database.<br>";
        
        if ($count > 0) {
            // List all users
            echo "<h3>Users in the database:</h3>";
            $stmt = $conn->query("SELECT id, username, email, role FROM users");
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['role']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
} catch (PDOException $e) {
    echo "Error checking users table: " . $e->getMessage() . "<br>";
}

// Check database connection details
echo "<h3>Database Connection Details:</h3>";
echo "Host: " . $host . "<br>";
echo "Database: " . $dbname . "<br>";
echo "User: " . $user . "<br>";
echo "Password: " . ($password_db ? "[Set]" : "[Empty]") . "<br>";
?> 