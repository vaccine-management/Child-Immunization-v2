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

// Handle form submission for adding a new vaccine
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vaccineName = trim($_POST['vaccine_name']);
    $vaccineQuantity = trim($_POST['vaccine_quantity']);
    $vaccineDescription = trim($_POST['vaccine_description']);

    if (empty($vaccineName) || empty($vaccineQuantity)) {
        $error = 'Vaccine name and quantity are required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO vaccines (name, quantity, description) VALUES (?, ?, ?)");
        $stmt->bindParam(1, $vaccineName);
        $stmt->bindParam(2, $vaccineQuantity);
        $stmt->bindParam(3, $vaccineDescription);

        if ($stmt->execute()) {
            $success = 'Vaccine added successfully!';
        } else {
            $error = 'Failed to add vaccine. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vaccine - Immunization System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-6 rounded-lg shadow-lg mb-6">
            <h2 class="text-xl font-bold text-white">Add Vaccine</h2>
        </div>
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-white mb-6">Add New Vaccine</h2>

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

            <!-- Add Vaccine Form -->
            <form method="POST" class="bg-gray-700 p-6 rounded-lg mb-6">
                <div class="mb-4">
                    <label for="vaccine_name" class="block text-white mb-2">Name</label>
                    <input type="text" id="vaccine_name" name="vaccine_name" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="vaccine_quantity" class="block text-white mb-2">Quantity</label>
                    <input type="number" id="vaccine_quantity" name="vaccine_quantity" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="vaccine_description" class="block text-white mb-2">Description</label>
                    <textarea id="vaccine_description" name="vaccine_description" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg"></textarea>
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">Add Vaccine</button>
            </form>
        </div>
    </div>
</body>
</html>