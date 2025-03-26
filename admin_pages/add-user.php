<?php
// Define root path for includes
define('ROOT_PATH', dirname(__FILE__) . '/../');

session_start();
// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once '../backend/db.php';

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
            $success = "User '" . $userName . "' added successfully!";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        /* Custom styles for notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
            animation: fadeInOut 4s forwards;
        }
        
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); }
        }
    </style>
</head>
<body class="bg-gray-900">
    <?php require_once ROOT_PATH . 'includes/header.php'; ?>
    <?php require_once ROOT_PATH . 'includes/navbar.php'; ?>
    <?php require_once ROOT_PATH . 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
        <div class="p-4 sm:p-6 lg:p-8">
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-4xl mx-auto">
                <h2 class="text-2xl font-bold text-white mb-6">Add Admin/Nurse</h2>

                <!-- Display error or success messages -->
                <?php if ($error): ?>
                    <div class="notification bg-red-500">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="notification bg-green-500">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <!-- Add User Form -->
                <form method="POST" action="process_user.php" id="addUserForm" autocomplete="off" class="bg-gray-700 p-6 rounded-lg mb-6">
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
                        <div class="relative group">
                            <input type="password" id="user_password" name="user_password" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg pr-10" required>
                            <button type="button" id="togglePassword" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-blue-400 focus:outline-none transition-colors duration-200">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="user_role" class="block text-white mb-2">Role</label>
                        <select id="user_role" name="user_role" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg" required>
                            <option value="Admin">Admin</option>
                            <option value="Nurse">Nurse</option>
                        </select>
                    </div>
                    <button type="submit" name="add_user" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">Add User</button>
                </form>
                
                <div class="text-center">
                    <a href="users.php" class="text-blue-400 hover:text-blue-300">← Back to Users List</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Function to handle password toggle
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('user_password');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function(e) {
                    // Prevent form submission
                    e.preventDefault();
                    
                    // Toggle between password and text
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        this.querySelector('i').classList.remove('fa-eye');
                        this.querySelector('i').classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        this.querySelector('i').classList.remove('fa-eye-slash');
                        this.querySelector('i').classList.add('fa-eye');
                    }
                });
            }
        });
    </script>
</body>
</html>