<?php
// Include database connection
require_once 'backend/db.php';

echo "<h1>Password Verification Debug</h1>";

// Test passwords
$plainPasswords = [
    'admin' => 'admin123',
    'nurse' => 'nurse123'
];

// Check the current hashes in the database
$stmt = $conn->query("SELECT id, username, email, password, role FROM users WHERE email IN ('admin@example.com', 'nurse@example.com')");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Users in Database</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Current Password Hash</th><th>Hash Length</th></tr>";

foreach ($users as $user) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($user['id']) . "</td>";
    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
    echo "<td>" . htmlspecialchars($user['role']) . "</td>";
    echo "<td>" . htmlspecialchars($user['password']) . "</td>";
    echo "<td>" . strlen($user['password']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test verification with current hashes
echo "<h2>Verification with Current Hashes</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Username</th><th>Plain Password</th><th>Verification Result</th></tr>";

foreach ($users as $user) {
    $username = $user['username'];
    $plainPassword = $plainPasswords[$username] ?? 'unknown';
    $verification = password_verify($plainPassword, $user['password']) ? 'SUCCESS' : 'FAILED';
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($username) . "</td>";
    echo "<td>" . htmlspecialchars($plainPassword) . "</td>";
    echo "<td style='color: " . ($verification === 'SUCCESS' ? 'green' : 'red') . "'>" . $verification . "</td>";
    echo "</tr>";
}
echo "</table>";

// Generate new hashes
echo "<h2>Generate New Hashes</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Username</th><th>Plain Password</th><th>New Hash</th><th>Hash Length</th><th>Verification</th></tr>";

foreach ($plainPasswords as $username => $plainPassword) {
    $newHash = password_hash($plainPassword, PASSWORD_BCRYPT);
    $verification = password_verify($plainPassword, $newHash) ? 'SUCCESS' : 'FAILED';
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($username) . "</td>";
    echo "<td>" . htmlspecialchars($plainPassword) . "</td>";
    echo "<td>" . htmlspecialchars($newHash) . "</td>";
    echo "<td>" . strlen($newHash) . "</td>";
    echo "<td style='color: " . ($verification === 'SUCCESS' ? 'green' : 'red') . "'>" . $verification . "</td>";
    echo "</tr>";
}
echo "</table>";

// SQL update statements for new hashes
echo "<h2>SQL UPDATE Statements for New Hashes</h2>";
echo "<p>Copy and run these in MySQL Workbench to fix the passwords:</p>";
echo "<pre>";
foreach ($plainPasswords as $username => $plainPassword) {
    $newHash = password_hash($plainPassword, PASSWORD_BCRYPT);
    echo "UPDATE users SET password = '" . $newHash . "' WHERE username = '" . $username . "';\n";
}
echo "</pre>";

// Option to fix passwords directly
echo "<h2>Fix Passwords Directly</h2>";
echo "<form method='post'>";
echo "<input type='hidden' name='fix_passwords' value='1'>";
echo "<button type='submit' style='background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;'>Update Password Hashes</button>";
echo "</form>";

// Process the fix if requested
if (isset($_POST['fix_passwords'])) {
    try {
        $conn->beginTransaction();
        
        foreach ($plainPasswords as $username => $plainPassword) {
            $newHash = password_hash($plainPassword, PASSWORD_BCRYPT);
            $updateStmt = $conn->prepare("UPDATE users SET password = :password WHERE username = :username");
            $updateStmt->execute([
                ':password' => $newHash,
                ':username' => $username
            ]);
        }
        
        $conn->commit();
        echo "<p style='color: green; font-weight: bold;'>Password hashes updated successfully! Try logging in again.</p>";
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<p style='color: red; font-weight: bold;'>Error updating passwords: " . $e->getMessage() . "</p>";
    }
}
?> 