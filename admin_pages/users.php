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

// Handle form submissions for adding and editing users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            // Add user logic
            $userName = trim($_POST['user_name']);
            $userEmail = trim($_POST['user_email']);
            $userRole = trim($_POST['user_role']);
            $userPassword = password_hash(trim($_POST['user_password']), PASSWORD_DEFAULT);

            if (empty($userName) || empty($userEmail) || empty($userRole) || empty($_POST['user_password'])) {
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
        } elseif ($_POST['action'] === 'edit') {
            // Edit user logic
            $userName = trim($_POST['user_name']);
            $userEmail = trim($_POST['user_email']);
            $userRole = trim($_POST['user_role']);
            $userId = $_POST['user_id'];

            // Check if password is being updated
            $passwordUpdate = !empty($_POST['user_password']);
            
            if (empty($userName) || empty($userEmail) || empty($userRole) || empty($userId)) {
                $error = 'Required fields are missing.';
            } else {
                if ($passwordUpdate) {
                    // Update user with password
                    $userPassword = password_hash(trim($_POST['user_password']), PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->bindParam(1, $userName);
                    $stmt->bindParam(2, $userEmail);
                    $stmt->bindParam(3, $userRole);
                    $stmt->bindParam(4, $userPassword);
                    $stmt->bindParam(5, $userId);
                } else {
                    // Update user without changing password
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->bindParam(1, $userName);
                    $stmt->bindParam(2, $userEmail);
                    $stmt->bindParam(3, $userRole);
                    $stmt->bindParam(4, $userId);
                }

                if ($stmt->execute()) {
                    $success = 'User updated successfully!';
                } else {
                    $error = 'Failed to update user. Please try again.';
                }
            }
        }
    }
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
            <?php if ($error): ?>
                <div class="bg-red-500/20 border-l-4 border-red-500 p-4 mb-4 animate__animated animate__fadeIn">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                        <p class="text-red-300"><?php echo $error; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-500/20 border-l-4 border-green-500 p-4 mb-4 animate__animated animate__fadeIn">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-400 mr-2"></i>
                        <p class="text-green-300"><?php echo $success; ?></p>
                    </div>
                </div>
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
    <div id="addUserModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="modal-container bg-gray-800 w-full max-w-md mx-auto rounded-lg shadow-lg overflow-hidden">
            <div class="py-4 px-6 bg-gray-700 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">Add New User</h3>
                <button class="text-gray-400 hover:text-white" onclick="closeModal('addUserModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="add">
                <div class="mb-4">
                    <label for="user_name" class="block text-white mb-2">Name</label>
                    <input type="text" id="user_name" name="user_name" class="w-full px-3 py-2 bg-gray-900 text-white rounded-lg border border-gray-700" required>
                </div>
                <div class="mb-4">
                    <label for="user_email" class="block text-white mb-2">Email</label>
                    <input type="email" id="user_email" name="user_email" class="w-full px-3 py-2 bg-gray-900 text-white rounded-lg border border-gray-700" required>
                </div>
                <div class="mb-4">
                    <label for="user_password" class="block text-white mb-2">Password</label>
                    <input type="password" id="user_password" name="user_password" class="w-full px-3 py-2 bg-gray-900 text-white rounded-lg border border-gray-700" required>
                </div>
                <div class="mb-6">
                    <label for="user_role" class="block text-white mb-2">Role</label>
                    <select id="user_role" name="user_role" class="w-full px-3 py-2 bg-gray-900 text-white rounded-lg border border-gray-700" required>
                        <option value="Admin">Admin</option>
                        <option value="Nurse">Nurse</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700" onclick="closeModal('addUserModal')">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Add User</button>
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

    <script>
        let userIdToDelete = null;

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('modal-active');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('modal-active');
            document.body.classList.remove('overflow-hidden');
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
    </script>
</body>
</html>

<?php
// Close the database connection
$conn = null;
?>