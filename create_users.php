<?php
// Include database connection
require_once 'backend/db.php';

// Function to create a user
function createUser($conn, $username, $email, $password, $role) {
    try {
        // Check if user already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->rowCount() > 0) {
            return "User with email {$email} already exists.";
        }
        
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert the new user
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password, role)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([$username, $email, $hashedPassword, $role]);
        
        return "User {$username} with role {$role} created successfully!";
    } catch (PDOException $e) {
        return "Error creating user: " . $e->getMessage();
    }
}

// Create an admin user
$adminResult = createUser(
    $conn,
    'admin',                // Username
    'admin@example.com',    // Email
    'admin123',             // Password
    'Admin'                 // Role
);

// Create a nurse user
$nurseResult = createUser(
    $conn,
    'nurse',                // Username
    'nurse@example.com',    // Email
    'nurse123',             // Password
    'Nurse'                 // Role
);

// Output results
echo "<html>
<head>
    <title>Create Users</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .card { background-color: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        p { line-height: 1.6; }
        .success { color: green; }
        .error { color: red; }
        .login-info { background-color: #f0f8ff; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .login-info h3 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class='card'>
        <h1>User Creation Results</h1>
        <p class='" . (strpos($adminResult, 'successfully') !== false ? 'success' : 'error') . "'>{$adminResult}</p>
        <p class='" . (strpos($nurseResult, 'successfully') !== false ? 'success' : 'error') . "'>{$nurseResult}</p>
        
        <div class='login-info'>
            <h3>Login Information</h3>
            <table>
                <tr>
                    <th>Role</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Password</th>
                </tr>
                <tr>
                    <td>Admin</td>
                    <td>admin</td>
                    <td>admin@example.com</td>
                    <td>admin123</td>
                </tr>
                <tr>
                    <td>Nurse</td>
                    <td>nurse</td>
                    <td>nurse@example.com</td>
                    <td>nurse123</td>
                </tr>
            </table>
        </div>
        
        <p><a href='login.php'>Go to Login Page</a></p>
    </div>
</body>
</html>";
?> 