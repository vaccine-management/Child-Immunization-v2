<?php
// filepath: /C:/xampp/htdocs/Child-Immunization-v2/users.php

session_start();

// Include the auth check file
include 'includes/auth_check.php';

// Only allow admins to access this page
checkAdminRole();

// Include the database connection file
include 'backend/db.php';

// Handle AJAX requests
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// AJAX Handler - Get User
if ($isAjax && isset($_GET['action']) && $_GET['action'] === 'getUser') {
    $userId = $_GET['id'];
    try {
        $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode($user);
        } else {
            echo json_encode(['error' => 'User not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX Handler - Add User
if ($isAjax && isset($_POST['action']) && $_POST['action'] === 'addUser') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['error' => 'All fields are required']);
        exit;
    }
    
    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->execute([$email]);
    if ($checkEmail->rowCount() > 0) {
        echo json_encode(['error' => 'Email already exists']);
        exit;
    }
    
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$username, $email, $hashedPassword, $role]);
        
        if ($result) {
            echo json_encode(['success' => 'User added successfully!']);
        } else {
            echo json_encode(['error' => 'Failed to add user']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX Handler - Edit User
if ($isAjax && isset($_POST['action']) && $_POST['action'] === 'editUser') {
    $userId = $_POST['id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    
    // Basic validation
    if (empty($username) || empty($email)) {
        echo json_encode(['error' => 'Username and email are required']);
        exit;
    }
    
    // Check if email already exists for other users
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkEmail->execute([$email, $userId]);
    if ($checkEmail->rowCount() > 0) {
        echo json_encode(['error' => 'Email already exists for another user']);
        exit;
    }
    
    try {
        if (!empty($password)) {
            // Update with new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?");
            $result = $stmt->execute([$username, $email, $hashedPassword, $role, $userId]);
        } else {
            // Update without changing password
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
            $result = $stmt->execute([$username, $email, $role, $userId]);
        }
        
        if ($result) {
            echo json_encode(['success' => 'User updated successfully!']);
        } else {
            echo json_encode(['error' => 'Failed to update user']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX Handler - Delete User
if ($isAjax && isset($_POST['action']) && $_POST['action'] === 'deleteUser') {
    $userId = $_POST['id'];
    
    // Don't allow deletion of own account
    if ($userId == $_SESSION['user']['id']) {
        echo json_encode(['error' => 'You cannot delete your own account']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([$userId]);
        
        if ($result) {
            echo json_encode(['success' => 'User deleted successfully!']);
        } else {
            echo json_encode(['error' => 'Failed to delete user']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch all users from the database for display
try {
    $query = "SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
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
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
        <div class="p-4 sm:p-6 lg:p-8">
            <!-- Page Header -->
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-white">Users Management</h1>
                    <p class="text-gray-400">Manage and view all registered users</p>
                </div>
                <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add New User
                </button>
            </div>
            
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
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-800/50 transition-colors">
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $user['role'] === 'Admin' ? 'bg-purple-600 text-white' : 'bg-blue-600 text-white'; ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-300"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex space-x-2">
                                            <button onclick="editUser(<?php echo $user['id']; ?>)" class="text-blue-400 hover:text-blue-300">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="text-red-400 hover:text-red-300">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-4 py-3 text-sm text-gray-300 text-center">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add User Modal -->
    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-white mb-4">Add New User</h3>
            <form id="addUserForm">
                <div id="addUserMessage"></div>
                <div class="mb-4">
                    <label for="add_username" class="block text-gray-300 mb-2">Username</label>
                    <input type="text" name="username" id="add_username" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="add_email" class="block text-gray-300 mb-2">Email</label>
                    <input type="email" name="email" id="add_email" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="add_password" class="block text-gray-300 mb-2">Password</label>
                    <input type="password" name="password" id="add_password" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="add_role" class="block text-gray-300 mb-2">Role</label>
                    <select name="role" id="add_role" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                        <option value="Admin">Admin</option>
                        <option value="Nurse">Nurse</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 text-gray-300 hover:text-white">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Add User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-white mb-4">Edit User</h3>
            <div id="editUserFormContent">
                <!-- Content will be loaded dynamically -->
                <div class="animate-pulse">
                    <div class="h-4 bg-gray-700 rounded w-3/4 mb-6"></div>
                    <div class="h-8 bg-gray-700 rounded mb-4"></div>
                    <div class="h-8 bg-gray-700 rounded mb-4"></div>
                    <div class="h-8 bg-gray-700 rounded mb-4"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold text-white mb-4">Confirm Delete</h3>
            <p class="text-gray-300 mb-6">Are you sure you want to delete this user? This action cannot be undone.</p>
            <div id="deleteUserMessage"></div>
            <div class="flex justify-end space-x-4">
                <button onclick="closeModal('deleteModal')" class="px-4 py-2 text-gray-300 hover:text-white">
                    Cancel
                </button>
                <button onclick="confirmDelete()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <script>
        let userIdToDelete = null;

        // Open and close modals
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('flex');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
            
            // Reset form messages
            if (modalId === 'addModal') {
                document.getElementById('addUserMessage').innerHTML = '';
            }
            if (modalId === 'deleteModal') {
                document.getElementById('deleteUserMessage').innerHTML = '';
                userIdToDelete = null;
            }
        }

        // Open Add User Modal
        function openAddModal() {
            document.getElementById('addUserForm').reset();
            openModal('addModal');
        }

        // Edit User
        function editUser(userId) {
            openModal('editModal');
            
            // Fetch user data with AJAX
            fetch(`users.php?action=getUser&id=${userId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('editUserFormContent').innerHTML = `
                        <div class="bg-red-500 text-white p-3 rounded-lg mb-4">${data.error}</div>`;
                    return;
                }
                
                // Populate form with user data
                document.getElementById('editUserFormContent').innerHTML = `
                    <form id="editUserForm">
                        <div id="editUserMessage"></div>
                        <input type="hidden" name="id" value="${data.id}">
                        <div class="mb-4">
                            <label for="edit_username" class="block text-gray-300 mb-2">Username</label>
                            <input type="text" name="username" id="edit_username" value="${data.username}" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                        </div>
                        <div class="mb-4">
                            <label for="edit_email" class="block text-gray-300 mb-2">Email</label>
                            <input type="email" name="email" id="edit_email" value="${data.email}" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                        </div>
                        <div class="mb-4">
                            <label for="edit_password" class="block text-gray-300 mb-2">Password (leave empty to keep current)</label>
                            <input type="password" name="password" id="edit_password" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg">
                        </div>
                        <div class="mb-4">
                            <label for="edit_role" class="block text-gray-300 mb-2">Role</label>
                            <select name="role" id="edit_role" class="w-full px-4 py-2 bg-gray-700 text-gray-300 rounded-lg" required>
                                <option value="Admin" ${data.role === 'Admin' ? 'selected' : ''}>Admin</option>
                                <option value="Nurse" ${data.role === 'Nurse' ? 'selected' : ''}>Nurse</option>
                            </select>
                        </div>
                        <div class="flex justify-end space-x-4">
                            <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 text-gray-300 hover:text-white">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Update User
                            </button>
                        </div>
                    </form>
                `;
                
                // Add submit handler for edit form
                document.getElementById('editUserForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    updateUser();
                });
            })
            .catch(error => {
                document.getElementById('editUserFormContent').innerHTML = `
                    <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                        An error occurred while loading user data.
                    </div>`;
            });
        }

        // Delete User
        function deleteUser(userId) {
            userIdToDelete = userId;
            openModal('deleteModal');
        }

        // Confirm Delete
        function confirmDelete() {
            if (!userIdToDelete) return;
            
            const formData = new FormData();
            formData.append('action', 'deleteUser');
            formData.append('id', userIdToDelete);
            
            fetch('users.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('deleteUserMessage').innerHTML = `
                        <div class="bg-green-500 text-white p-3 rounded-lg mb-4">${data.success}</div>`;
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    document.getElementById('deleteUserMessage').innerHTML = `
                        <div class="bg-red-500 text-white p-3 rounded-lg mb-4">${data.error}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('deleteUserMessage').innerHTML = `
                    <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                        An error occurred while deleting the user.
                    </div>`;
            });
        }

        // Update User
        function updateUser() {
            const form = document.getElementById('editUserForm');
            const formData = new FormData(form);
            formData.append('action', 'editUser');
            
            fetch('users.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('editUserMessage').innerHTML = `
                        <div class="bg-green-500 text-white p-3 rounded-lg mb-4">${data.success}</div>`;
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    document.getElementById('editUserMessage').innerHTML = `
                        <div class="bg-red-500 text-white p-3 rounded-lg mb-4">${data.error}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('editUserMessage').innerHTML = `
                    <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                        An error occurred while updating the user.
                    </div>`;
            });
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Add User form submission
            document.getElementById('addUserForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'addUser');
                
                fetch('users.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('addUserMessage').innerHTML = `
                            <div class="bg-green-500 text-white p-3 rounded-lg mb-4">${data.success}</div>`;
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        document.getElementById('addUserMessage').innerHTML = `
                            <div class="bg-red-500 text-white p-3 rounded-lg mb-4">${data.error}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('addUserMessage').innerHTML = `
                        <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                            An error occurred while adding the user.
                        </div>`;
                });
            });

            // Close modals when clicking outside
            document.querySelectorAll('.fixed.inset-0').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal(this.id);
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn = null;
?>