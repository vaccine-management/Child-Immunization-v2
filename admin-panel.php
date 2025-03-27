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
// Get username from session
$userName = isset($_SESSION['user']['username']) && !empty($_SESSION['user']['username']) 
    ? $_SESSION['user']['username'] 
    : $_SESSION['user']['email'];

$userRole = $_SESSION['user']['role'] ?? 'User';

// Handle form submissions for updating vaccines
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_vaccine'])) {
        $vaccineId = $_POST['vaccine_id'];
        $vaccineName = trim($_POST['vaccine_name']);
        $vaccineQuantity = trim($_POST['vaccine_quantity']);

        if (empty($vaccineName) || empty($vaccineQuantity)) {
            $error = 'Vaccine name and quantity are required.';
        } else {
            $stmt = $conn->prepare("UPDATE vaccines SET name = ?, quantity = ? WHERE id = ?");
            if ($stmt->execute([$vaccineName, $vaccineQuantity, $vaccineId])) {
                $success = 'Vaccine updated successfully!';
            } else {
                $error = 'Failed to update vaccine. Please try again.';
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $userId = $_POST['user_id'];
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$userId])) {
            $success = 'User deleted successfully!';
        } else {
            $error = 'Failed to delete user. Please try again.';
        }
    } elseif (isset($_POST['delete_vaccine'])) {
        $vaccineId = $_POST['vaccine_id'];
        $stmt = $conn->prepare("DELETE FROM vaccines WHERE id = ?");
        if ($stmt->execute([$vaccineId])) {
            $success = 'Vaccine deleted successfully!';
        } else {
            $error = 'Failed to delete vaccine. Please try again.';
        }
    }
}

// Fix for vaccines query - only select records with the expected columns
$stmt = $conn->query("SELECT id, vaccine_name, batch_number, stock_quantity FROM vaccines");
$vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch doctors and nurses
$doctors = $conn->query("SELECT * FROM users WHERE role = 'Admin'")->fetchAll(PDO::FETCH_ASSOC);
$nurses = $conn->query("SELECT * FROM users WHERE role = 'Nurse'")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Immunization System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-6 rounded-lg shadow-lg mb-6">
            <h2 class="text-xl font-bold text-white">Welcome, <?php echo htmlspecialchars($userName); ?></h2>
            <p class="text-blue-200">You are logged in as a <?php echo htmlspecialchars($userRole); ?>.</p>
        </div>
        
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-white mb-6">Admin Panel</h2>

            <!-- Display error or success messages -->
            <?php if ($error): ?>
                <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-500 text-white p-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Display SMS notification message if available -->
            <?php if (isset($_SESSION['sms_notification'])): ?>
                <div class="bg-<?php echo $_SESSION['sms_notification']['status'] === 'success' ? 'green' : 'red'; ?>-500 text-white p-3 rounded-lg mb-4">
                    <?php echo htmlspecialchars($_SESSION['sms_notification']['message']); ?>
                </div>
                <?php unset($_SESSION['sms_notification']); ?>
            <?php endif; ?>
            
            <!-- Admin Quick Links -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <a href="admin_pages/users.php" class="bg-indigo-700 hover:bg-indigo-600 transition-colors p-4 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <span>Manage Users</span>
                </a>
                <a href="admin_pages/inventory.php" class="bg-green-700 hover:bg-green-600 transition-colors p-4 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <span>Inventory</span>
                </a>
                <a href="admin_pages/sms-management.php" class="bg-purple-700 hover:bg-purple-600 transition-colors p-4 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                    </svg>
                    <span>SMS Management</span>
                </a>
            </div>
        </div>
            </div>

    <script>
    function editVaccine(id, name, quantity) {
        document.getElementById('vaccine_id').value = id;
        document.getElementById('vaccine_name').value = name;
        document.getElementById('vaccine_quantity').value = quantity;
        document.getElementById('editVaccineModal').classList.remove('hidden');
    }
    
    function editUser(id, name, email) {
        document.getElementById('user_id').value = id;
        document.getElementById('user_name').value = name;
        document.getElementById('user_email').value = email;
        document.getElementById('editUserModal').classList.remove('hidden');
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }
    </script>
</body>
</html>