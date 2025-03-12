<?php
// Define root path for includes
define('ROOT_PATH', dirname(__FILE__) . '/../');

session_start();

// Include the auth check file
require_once ROOT_PATH . 'includes/auth_check.php';

// Only allow admins to access this page
checkAdminRole();

// Include the database connection file
require_once ROOT_PATH . 'backend/db.php';

// Initialize variables
$error = '';
$success = '';
$edit_user = null;

// Add this at the top of your users.php file to check DB connection
function checkDatabaseConnection($conn) {
    try {
        $conn->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        return false;
    }
}

// Use it before performing database operations
if (!checkDatabaseConnection($conn)) {
    $_SESSION['error_message'] = "Database connection error. Please try again later.";
}

// Add at the top of your users.php file
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Process user form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($role)) {
        $errors[] = "Role is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email already exists";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error occurred";
    }
    
    // If no errors, add user
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$username, $email, $hashed_password, $role]);
            
            if ($result) {
                $_SESSION['success_message'] = "User added successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to add user";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
    
    // Redirect to prevent form resubmission
    header('Location: users.php');
    exit();
}

// Fetch all users from the database
try {
    $query = "SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Fetch specific user for editing if ID is provided via GET
if (isset($_GET['edit_id'])) {
    $userId = $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// For testing purposes, always set messages
if (!isset($_GET['test'])) {
    $_SESSION['success_message'] = "User added successfully! " . time();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Child Immunization System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .modal {
            transition: opacity 0.25s ease;
        }
        .modal-active {
            overflow-y: auto;
        }
        .modal-container {
            animation: fadeIn 0.3s;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
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
            animation: fadeInOut 5s forwards;
        }
        
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); }
        }
        /* Styles for alert messages */
        .alert-message {
            transition: opacity 0.5s ease-in-out;
            opacity: 1;
        }
        
        .alert-message.fade-out {
            opacity: 0;
        }
        /* Alert animation */
        .auto-dismiss-alert {
            animation: fadeInOut 3s forwards;
            opacity: 1;
            position: relative;
        }
        
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
        /* Base alert styles */
        .auto-dismiss-alert {
            opacity: 1;
            position: relative;
        }
        
        /* Success alert - quick 2 second animation */
        .success-alert {
            animation: quickFadeOut 2s forwards;
        }
        
        /* Error alert - 5 second animation */
        .error-alert {
            animation: slowFadeOut 5s forwards;
        }
        
        /* Quick fade animation for success messages */
        @keyframes quickFadeOut {
            0% { opacity: 0; transform: translateY(-20px); }
            20% { opacity: 1; transform: translateY(0); }
            80% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
        
        /* Slow fade animation for error messages */
        @keyframes slowFadeOut {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
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
            <!-- Page Header -->
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-white">Users Management</h1>
                    <p class="text-gray-400">Manage and view all registered users</p>
                </div>
                <button onclick="openModal('addUserModal')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add New User
                </button>
            </div>
            
            <!-- Display error or success messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div id="successAlert" class="auto-dismiss-alert success-alert bg-green-600 text-white px-4 py-3 rounded mb-4 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <p><?php echo $_SESSION['success_message']; ?></p>
                        </div>
                        <button onclick="dismissAlert('successAlert')" class="text-white hover:text-gray-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div id="errorAlert" class="auto-dismiss-alert error-alert bg-red-600 text-white px-4 py-3 rounded mb-4 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p><?php echo $_SESSION['error_message']; ?></p>
                        </div>
                        <button onclick="dismissAlert('errorAlert')" class="text-white hover:text-gray-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <!-- Users Table -->
            <div class="overflow-x-auto bg-gray-800 rounded-lg shadow-lg">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Username</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Role</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Created At</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-gray-900 divide-y divide-gray-700">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-3 text-sm text-gray-400 text-center">No users found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo $user['id']; ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php echo $user['role'] === 'Admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex space-x-2">
                                            <button onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo addslashes(htmlspecialchars($user['username'])); ?>', '<?php echo addslashes(htmlspecialchars($user['email'])); ?>', '<?php echo addslashes(htmlspecialchars($user['role'])); ?>')" class="text-blue-400 hover:text-blue-300">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="text-red-400 hover:text-red-300">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-white mb-4">Add New User</h3>
            
            <!-- Debug info - remove in production -->
            <div id="formDebug" class="text-xs text-gray-400 mb-4 hidden">Form not submitted yet</div>
            
            <form method="POST" action="" id="addUserForm" autocomplete="off">
                <div class="mb-4">
                    <label for="username" class="block text-gray-300 mb-2">Username</label>
                    <input type="text" name="username" id="username" placeholder="Enter username" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-gray-300 mb-2">Email</label>
                    <input type="email" name="email" id="email" placeholder="Enter email address" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="role" class="block text-gray-300 mb-2">Role</label>
                    <select name="role" id="role" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                        <option value="">Select Role</option>
                        <option value="Admin">Admin</option>
                        <option value="Nurse">Nurse</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-gray-300 mb-2">Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" placeholder="Enter password" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg pr-10" required>
                        <button type="button" class="toggle-password absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="confirm_password" class="block text-gray-300 mb-2">Confirm Password</label>
                    <div class="relative">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg pr-10" required>
                        <button type="button" class="toggle-password absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeModal('addUserModal')" class="px-4 py-2 text-gray-300 hover:text-white">
                        Cancel
                    </button>
                    <button type="submit" name="add_user" id="submitUserBtn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Add User
                    </button>
                </div>
            </form>
        </div>
    </div>

           
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="modal-container bg-gray-800 w-full max-w-md mx-auto rounded-lg shadow-lg overflow-hidden">
            <div class="py-4 px-6 bg-gray-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Edit User</h3>
                <button class="text-gray-400 hover:text-white" onclick="closeModal('editUserModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_user_id" name="user_id" value="">
                <div class="mb-4">
                    <label for="edit_user_name" class="block text-white mb-2">Name</label>
                    <input type="text" id="edit_user_name" name="user_name" class="w-full px-3 py-2 bg-gray-900 text-white rounded-lg border border-gray-700" required>
                </div>
                <div class="mb-4">
                    <label for="edit_user_email" class="block text-white mb-2">Email</label>
                    <input type="email" id="edit_user_email" name="user_email" class="w-full px-3 py-2 bg-gray-900 text-white rounded-lg border border-gray-700" required>
                </div>
                <div class="mb-4">
                    <label for="edit_user_password" class="block text-white mb-2">Password (leave empty to keep current)</label>
                    <input type="password" id="edit_user_password" name="user_password" class="w-full px-3 py-2 bg-gray-900 text-white rounded-lg border border-gray-700">
                </div>
                <div class="mb-6">
                    <label for="edit_user_role" class="block text-white mb-2">Role</label>
                    <select id="edit_user_role" name="user_role" class="w-full px-3 py-2 bg-gray-900 text-white rounded-lg border border-gray-700" required>
                        <option value="Admin">Admin</option>
                        <option value="Nurse">Nurse</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700" onclick="closeModal('editUserModal')">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div id="deleteModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="modal-container bg-gray-800 w-full max-w-md mx-auto rounded-lg shadow-lg overflow-hidden">
            <div class="py-4 px-6 bg-gray-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Confirm Delete</h3>
                <button class="text-gray-400 hover:text-white" onclick="closeModal('deleteModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <p class="text-gray-300 mb-6">Are you sure you want to delete this user? This action cannot be undone.</p>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="button" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700" onclick="confirmDelete()">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Notification -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div id="successNotification" class="notification bg-green-500">
            <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div id="errorNotification" class="notification bg-red-500">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <script>
        let userIdToDelete = null;

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('flex');
            console.log('Modal opened:', modalId);
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('flex');
            document.getElementById(modalId).classList.add('hidden');
            console.log('Form reset for:', modalId);
        }

        // Edit user function
        function openEditModal(id, username, email, role) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_user_name').value = username;
            document.getElementById('edit_user_email').value = email;
            document.getElementById('edit_user_password').value = '';

            // Set the role
            const roleSelect = document.getElementById('edit_user_role');
            for (let i = 0; i < roleSelect.options.length; i++) {
                if (roleSelect.options[i].value === role) {
                    roleSelect.selectedIndex = i;
                    break;
                }
            }

            openModal('editUserModal');
        }

        // Delete user functions
        function deleteUser(userId) {
            userIdToDelete = userId;
            openModal('deleteModal');
        }

        function confirmDelete() {
            if (userIdToDelete) {
                window.location.href = `delete_user.php?id=${userIdToDelete}`;
            }
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('modal-active');
                    document.body.classList.remove('overflow-hidden');
                }
            });
        });

        // Auto-show edit modal if edit_id parameter is in URL
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($edit_user): ?>
                openEditModal(
                    <?php echo $edit_user['id']; ?>,
                    '<?php echo addslashes(htmlspecialchars($edit_user['username'])); ?>',
                    '<?php echo addslashes(htmlspecialchars($edit_user['email'])); ?>',
                    '<?php echo addslashes(htmlspecialchars($edit_user['role'])); ?>'
                );
            <?php endif; ?>
        });

        // Replace your existing password toggle code with this more reliable version
        document.addEventListener('DOMContentLoaded', function() {
            // Function to handle password toggle
            function setupPasswordToggles() {
                document.querySelectorAll('.password-field').forEach(function(field) {
                    const input = field.querySelector('input');
                    const toggleBtn = field.querySelector('.toggle-password');
                    
                    if (input && toggleBtn) {
                        toggleBtn.addEventListener('click', function() {
                            // Toggle between password and text
                            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                            input.setAttribute('type', type);
                            
                            // Update icon based on password visibility
                            if (type === 'text') {
                                this.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>`;
                            } else {
                                this.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>`;
                            }
                        });
                    }
                });
            }
            
            // Initial setup
            setupPasswordToggles();
            
            // Setup again when modals open (for dynamic content)
            document.querySelectorAll('[data-target]').forEach(function(trigger) {
                trigger.addEventListener('click', function() {
                    setTimeout(setupPasswordToggles, 100);
                });
            });
        });

        // Add this to your JavaScript to ensure form resets properly
        function resetModalForm(modalId) {
            const form = document.querySelector(`#${modalId} form`);
            if (form) {
                form.reset();
                
                // Reset any validation messages
                const errorMessages = form.querySelectorAll('.error-message');
                errorMessages.forEach(message => {
                    message.textContent = '';
                    message.classList.add('hidden');
                });
                
                // Reset any highlighted fields
                const inputFields = form.querySelectorAll('input, select');
                inputFields.forEach(field => {
                    field.classList.remove('border-red-500');
                });
            }
        }

        // Use this when opening modals
        document.querySelectorAll('[data-target]').forEach(button => {
            button.addEventListener('click', function() {
                const modalId = this.getAttribute('data-target');
                resetModalForm(modalId);
                openModal(modalId);
            });
        });

        // Add this to your JavaScript for debugging
        console.log('Modal opened:', modalId);
        console.log('Form reset for:', modalId);
        console.log('Toggle password clicked');

        // Auto-dismiss alerts with different timings
        document.addEventListener('DOMContentLoaded', function() {
            // Get all alerts
            const successAlerts = document.querySelectorAll('.success-alert');
            const errorAlerts = document.querySelectorAll('.error-alert');
            
            // Handle success alerts (2 seconds)
            successAlerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 2000); // 2 seconds for success alerts
            });
            
            // Handle error alerts (5 seconds)
            errorAlerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 5000); // 5 seconds for error alerts
            });
        });

        // Function to dismiss an alert manually
        function dismissAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.style.opacity = '0';
                alert.style.visibility = 'hidden';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }
        }
    </script>
</body>
</html>

<?php
// Close the database connection
$conn = null;
?>