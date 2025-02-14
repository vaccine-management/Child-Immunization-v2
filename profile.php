<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
include 'backend/db.php';

// Initialize variables
$name = $_SESSION['user']['username'] ?? '';
$email = $_SESSION['user']['email'] ?? '';
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);

    // Validate inputs
    if (empty($newName)) {
        $error = 'Name is required.';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        // Update user details in the database
        $userId = $_SESSION['user']['id'];
        $stmt = $conn->prepare("UPDATE users SET username = :username, email = :email WHERE id = :id");
        $stmt->bindParam(':username', $newName);
        $stmt->bindParam(':email', $newEmail);
        $stmt->bindParam(':id', $userId);

        if ($stmt->execute()) {
            // Update session data
            $_SESSION['user']['username'] = $newName;
            $_SESSION['user']['email'] = $newEmail;

            $success = 'Profile updated successfully!';
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Immunization System</title>

    <!-- Include Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-2xl mx-auto">
            <h2 class="text-2xl font-bold text-white mb-6">Profile</h2>

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

            <!-- Profile Update Form -->
            <form method="POST">
                <div class="mb-4">
                    <label for="name" class="block text-white mb-2">Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" 
                           class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                </div>

                <div class="mb-6">
                    <label for="email" class="block text-white mb-2">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                           class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                </div>

                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">
                    Update Profile
                </button>
            </form>
        </div>
    </div>
</body>
</html>