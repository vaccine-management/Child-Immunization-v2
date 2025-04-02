<?php
session_start();
require_once 'backend/db.php';

$log = [];
$status = "pending";

// Function to add log message
function addLog($message, $type = 'info') {
    global $log;
    $log[] = ['type' => $type, 'message' => $message];
}

// Test database connection
try {
    $conn->query("SELECT 1");
    addLog("✅ Database connection successful", "success");
} catch (PDOException $e) {
    addLog("❌ Database connection failed: " . $e->getMessage(), "error");
    $status = "failed";
}

// Check if users table exists
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        addLog("✅ Users table exists", "success");
    } else {
        addLog("❌ Users table does not exist", "error");
        $status = "failed";
    }
} catch (PDOException $e) {
    addLog("❌ Error checking users table: " . $e->getMessage(), "error");
    $status = "failed";
}

// Get current users from the database
$users = [];
try {
    $stmt = $conn->query("SELECT id, username, email, password, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    addLog("Found " . count($users) . " users in the database", "success");
    
    if (count($users) === 0) {
        addLog("❌ No users found in the database. Creating default users...", "warning");
        $status = "no_users";
    }
} catch (PDOException $e) {
    addLog("❌ Error fetching users: " . $e->getMessage(), "error");
    $status = "failed";
}

// Function to create or update users
function updateUser($conn, $username, $email, $password, $role) {
    global $log;
    
    try {
        // Check if user exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        $userId = $checkStmt->fetchColumn();
        
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        if ($userId) {
            // Update existing user
            $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $hashedPassword, $role, $userId]);
            addLog("Updated user $username ($email) with role $role", "success");
        } else {
            // Create new user
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword, $role]);
            addLog("Created new user $username ($email) with role $role", "success");
        }
        
        return true;
    } catch (PDOException $e) {
        addLog("❌ Error updating user $email: " . $e->getMessage(), "error");
        return false;
    }
}

// Handle fix users request
if (isset($_POST['fix_users'])) {
    try {
        $conn->beginTransaction();
        
        // Create or update admin user
        $adminSuccess = updateUser(
            $conn,
            'admin',
            'admin@example.com',
            'admin123',
            'Admin'
        );
        
        // Create or update nurse user
        $nurseSuccess = updateUser(
            $conn,
            'nurse',
            'nurse@example.com',
            'nurse123',
            'Nurse'
        );
        
        if ($adminSuccess && $nurseSuccess) {
            $conn->commit();
            $status = "fixed";
            addLog("✅ Users successfully fixed!", "success");
        } else {
            $conn->rollBack();
            $status = "failed";
            addLog("❌ Failed to fix users", "error");
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $status = "failed";
        addLog("❌ Error: " . $e->getMessage(), "error");
    }
}

// Handle test login
if (isset($_POST['test_login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    try {
        // Find user by email and role
        $stmt = $conn->prepare("SELECT id, username, email, password, role FROM users WHERE email = ? AND role = ?");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            addLog("❌ Login failed: No user found with email '$email' and role '$role'", "error");
        } else {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Success
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'username' => $user['username']
                ];
                $status = "login_success";
                addLog("✅ Login successful as {$user['username']} ({$user['role']})", "success");
            } else {
                addLog("❌ Login failed: Invalid password for user '$email'", "error");
                addLog("Password hash in DB: " . substr($user['password'], 0, 10) . "...", "info");
            }
        }
    } catch (PDOException $e) {
        addLog("❌ Login error: " . $e->getMessage(), "error");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Login System</title>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --dark: #1f2937;
            --medium: #4b5563;
            --light: #f3f4f6;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f9fafb;
            color: #111827;
            line-height: 1.5;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        h1, h2, h3 {
            color: var(--dark);
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .logs {
            margin: 20px 0;
            max-height: 300px;
            overflow-y: auto;
            background: #f1f5f9;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .log {
            padding: 6px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .log:last-child {
            border-bottom: none;
        }
        
        .log.info {
            color: var(--medium);
        }
        
        .log.success {
            color: var(--success);
        }
        
        .log.warning {
            color: var(--warning);
        }
        
        .log.error {
            color: var(--error);
        }
        
        .button {
            display: inline-block;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .button:hover {
            background-color: var(--primary-dark);
        }
        
        .button.success {
            background-color: var(--success);
        }
        
        .button.warning {
            background-color: var(--warning);
        }
        
        form {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .user-table th,
        .user-table td {
            border: 1px solid #e5e7eb;
            padding: 8px 12px;
            text-align: left;
        }
        
        .user-table th {
            background-color: #f9fafb;
        }
        
        .next-steps {
            background-color: #ecfdf5;
            border-left: 4px solid var(--success);
            padding: 15px;
            margin-top: 20px;
        }
        
        .credential-box {
            background-color: #f0f9ff;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .credential-item {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e7ff;
        }
        
        .credential-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
    </style>
</head>
<body>
    <h1>Child Immunization System - Login Fix Tool</h1>
    
    <div class="card">
        <h2>System Status</h2>
        <div class="logs">
            <?php foreach ($log as $entry): ?>
                <div class="log <?php echo $entry['type']; ?>">
                    <?php echo $entry['message']; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($status === "failed"): ?>
            <p>There are issues with your system that need to be fixed before proceeding.</p>
        <?php endif; ?>
        
        <?php if ($status === "fixed"): ?>
            <div class="credential-box">
                <h3>Login Credentials</h3>
                <p>The following users have been set up with new password hashes:</p>
                
                <div class="credential-item">
                    <strong>Admin User:</strong><br>
                    Email: admin@example.com<br>
                    Password: admin123<br>
                    Role: Admin
                </div>
                
                <div class="credential-item">
                    <strong>Nurse User:</strong><br>
                    Email: nurse@example.com<br>
                    Password: nurse123<br>
                    Role: Nurse
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($status === "login_success"): ?>
            <div class="next-steps">
                <h3>🎉 Login Success!</h3>
                <p>You have successfully logged in as <strong><?php echo $_SESSION['user']['username']; ?></strong> with role <strong><?php echo $_SESSION['user']['role']; ?></strong>.</p>
                <p>You should now be able to log in through the normal login page.</p>
                <a href="login.php" class="button success">Go to Login Page</a>
                <a href="dashboard.php" class="button">Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($status !== "login_success"): ?>
    <div class="card">
        <h2>Fix User Accounts</h2>
        <p>Click the button below to create/update the admin and nurse users with correct password hashes:</p>
        
        <form method="post">
            <button type="submit" name="fix_users" class="button">Fix User Accounts</button>
        </form>
    </div>
    
    <div class="card">
        <h2>Test Login</h2>
        <p>Use this form to test if the login works correctly:</p>
        
        <form method="post">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="admin@example.com" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" value="admin123" required>
            </div>
            
            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="Admin">Admin</option>
                    <option value="Nurse">Nurse</option>
                </select>
            </div>
            
            <button type="submit" name="test_login" class="button">Test Login</button>
        </form>
    </div>
    <?php endif; ?>
    
    <?php if (count($users) > 0): ?>
    <div class="card">
        <h2>Current Users in Database</h2>
        <table class="user-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Password Hash (beginning)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><?php echo htmlspecialchars(substr($user['password'], 0, 15)) . '...'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</body>
</html> 