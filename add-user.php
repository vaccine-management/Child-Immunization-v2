<?php
session_start();
// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

// Database connection
include 'backend/db.php';

// Initialize variables
$error = '';
$success = '';

// Handle form submissions for adding users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userName = trim($_POST['user_name']);
    $userEmail = trim($_POST['user_email']);
    $userRole = trim($_POST['user_role']);
    $userPassword = password_hash(trim($_POST['user_password']), PASSWORD_DEFAULT);

    if (empty($userName) || empty($userEmail) || empty($userRole) || empty($userPassword)) {
        $error = 'All fields are required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, email, role, password) VALUES (?, ?, ?, ?)");
        $stmt->bindParam(1, $userName);
        $stmt->bindParam(2, $userEmail);
        $stmt->bindParam(3, $userRole);
        $stmt->bindParam(4, $userPassword);

        if ($stmt->execute()) {
            $success = 'User added successfully!';
        } else {
            $error = 'Failed to add user. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - Immunization System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-white mb-6">Add Doctor/Nurse</h2>

            <!-- Display error or success messages -->
            <?php if ($error): ?>
                <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-500 text-white p-3 rounded-lg mb-4">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Add User Form -->
            <form method="POST" class="bg-gray-700 p-6 rounded-lg mb-6">
                <div class="mb-4">
                    <label for="user_name" class="block text-white mb-2">Name</label>
                    <input type="text" id="user_name" name="user_name" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="user_email" class="block text-white mb-2">Email</label>
                    <input type="email" id="user_email" name="user_email" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="user_password" class="block text-white mb-2">Password</label>
                    <input type="password" id="user_password" name="user_password" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="user_role" class="block text-white mb-2">Role</label>
                    <select id="user_role" name="user_role" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg" required>
                        <option value="Doctor">Doctor</option>
                        <option value="Nurse">Nurse</option>
                    </select>
                </div>
                <button type="submit" name="add_user" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">Add User</button>
            </form>
        </div>
    </div>
</body>
</html>