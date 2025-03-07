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
$profileImage = $_SESSION['user']['profile_image'] ?? '';
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    $imageUpdated = false;

    // Handle image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array(strtolower($filetype), $allowed)) {
            $tempname = $_FILES['profile_image']['tmp_name'];
            $newFilename = 'user_' . $_SESSION['user']['id'] . '_' . time() . '.' . $filetype;
            $upload_path = 'uploads/profile_images/' . $newFilename;

            // Create directory if it doesn't exist
            if (!file_exists('uploads/profile_images/')) {
                mkdir('uploads/profile_images/', 0777, true);
            }

            if (move_uploaded_file($tempname, $upload_path)) {
                $profileImage = $upload_path;
                $imageUpdated = true;
            }
        }
    }

    // Validate inputs
    if (empty($newName)) {
        $error = 'Name is required.';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        // Update user details in the database
        $query = "UPDATE users SET username = :username, email = :email";
        if ($imageUpdated) {
            $query .= ", profile_image = :profile_image";
        }
        $query .= " WHERE id = :id";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $newName);
        $stmt->bindParam(':email', $newEmail);
        $stmt->bindParam(':id', $_SESSION['user']['id']);
        if ($imageUpdated) {
            $stmt->bindParam(':profile_image', $profileImage);
        }

        if ($stmt->execute()) {
            // Update session data
            $_SESSION['user']['username'] = $newName;
            $_SESSION['user']['email'] = $newEmail;
            if ($imageUpdated) {
                $_SESSION['user']['profile_image'] = $profileImage;
            }
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <div class="max-w-3xl mx-auto">
            <div class="bg-gray-800 shadow-xl rounded-xl overflow-hidden">
                <!-- Profile Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-8">
                    <div class="flex items-center space-x-4">
                        <div class="relative group">
                            <div class="bg-gray-700 w-20 h-20 rounded-full shadow-lg overflow-hidden">
                                <?php if ($profileImage): ?>
                                    <img src="<?php echo htmlspecialchars($profileImage); ?>" 
                                         alt="Profile" 
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user-circle text-4xl text-blue-400 w-full h-full flex items-center justify-center"></i>
                                <?php endif; ?>
                            </div>
                            <label for="profile_image" 
                                   class="absolute bottom-0 right-0 bg-blue-500 rounded-full p-2 cursor-pointer hover:bg-blue-600 transition-colors">
                                <i class="fas fa-camera text-white text-sm"></i>
                            </label>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-100">Your Profile</h2>
                            <p class="text-blue-200"><?php echo htmlspecialchars($email); ?></p>
                        </div>
                    </div>
                </div>

                <div class="p-8">
                    <!-- Error Message -->
                    <?php if ($error): ?>
                        <div class="bg-red-900/50 border-l-4 border-red-500 text-red-300 p-4 mb-6 rounded-lg" role="alert">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <p class="font-semibold"><?php echo $error; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Success Message -->
                    <?php if ($success): ?>
                        <div class="bg-blue-900/50 border-l-4 border-blue-500 text-blue-300 p-4 mb-6 rounded-lg" role="alert">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>
                                <p class="font-semibold"><?php echo $success; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Update Form -->
                    <form method="POST" class="space-y-6" enctype="multipart/form-data">
                        <input type="file" 
                               id="profile_image" 
                               name="profile_image" 
                               accept="image/*" 
                               class="hidden" 
                               onchange="this.form.submit()">

                        <div class="bg-gray-700 p-6 rounded-lg">
                            <div class="mb-6">
                                <label for="name" class="block text-sm font-semibold text-gray-200 mb-2">Full Name</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-blue-400"></i>
                                    </div>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" 
                                           class="pl-10 w-full px-4 py-2 bg-gray-600 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                           required>
                                </div>
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-semibold text-gray-200 mb-2">Email Address</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-envelope text-blue-400"></i>
                                    </div>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" 
                                           class="pl-10 w-full px-4 py-2 bg-gray-600 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="flex items-center px-6 py-3 bg-blue-600 text-gray-100 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Additional Profile Section -->
            <div class="mt-6 bg-gray-800 shadow-xl rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-200">Account Security</h3>
                </div>
                <p class="text-gray-400 text-sm">
                    Last login: <?php echo date('F j, Y, g:i a'); ?>
                </p>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('profile_image').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.querySelector('.rounded-full img');
                if (img) {
                    img.src = e.target.result;
                }
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });
    </script>
</body>
</html>