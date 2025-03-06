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
$user = null;

// Fetch user details if ID is provided
if (isset($_GET['id'])) {
    $userId = $_GET['id'];
    $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = 'User not found.';
    }
}

// Handle form submissions for updating user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'];
    $userName = trim($_POST['user_name']);
    $userEmail = trim($_POST['user_email']);
    $userRole = trim($_POST['user_role']);

    if (empty($userName) || empty($userEmail) || empty($userRole)) {
        $error = 'All fields are required.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
        $stmt->execute([$userName, $userEmail, $userRole, $userId]);

        if ($stmt->rowCount() > 0) {
            $success = 'User updated successfully!';
        } else {
            $error = 'Failed to update user. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Immunization System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
        <div class="p-4 sm:p-6 lg:p-8">
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-4xl mx-auto">
                <h2 class="text-3xl font-bold text-white mb-6">Edit User</h2>

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

                <!-- Edit User Form -->
                <?php if ($user): ?>
                    <form method="POST" class="bg-gray-700 p-6 rounded-lg mb-6">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                        <div class="mb-4">
                            <label for="user_name" class="block text-white mb-2">Name</label>
                            <input type="text" id="user_name" name="user_name" value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="mb-4">
                            <label for="user_email" class="block text-white mb-2">Email</label>
                            <input type="email" id="user_email" name="user_email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="mb-4">
                            <label for="user_role" class="block text-white mb-2">Role</label>
                            <select id="user_role" name="user_role" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <option value="Admin" <?php echo $user['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="Nurse" <?php echo $user['role'] === 'Nurse' ? 'selected' : ''; ?>>Nurse</option>
                            </select>
                        </div>
                        <button type="submit" name="update_user" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">Update User</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>