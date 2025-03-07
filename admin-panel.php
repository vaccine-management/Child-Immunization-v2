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