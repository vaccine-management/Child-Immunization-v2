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
            $stmt->bindParam(1, $vaccineName);
            $stmt->bindParam(2, $vaccineQuantity);
            $stmt->bindParam(3, $vaccineId);

            if ($stmt->execute()) {
                $success = 'Vaccine updated successfully!';
            } else {
                $error = 'Failed to update vaccine. Please try again.';
            }
        }
    } elseif (isset($_POST['add_user'])) {
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
                $success = 'User added successfully!';
            } else {
                $error = 'Failed to add user. Please try again.';
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $userId = $_POST['user_id'];

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bindParam(1, $userId);

        if ($stmt->execute()) {
            $success = 'User deleted successfully!';
        } else {
            $error = 'Failed to delete user. Please try again.';
        }
    } elseif (isset($_POST['add_vaccine'])) {
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
    } elseif (isset($_POST['delete_vaccine'])) {
        $vaccineId = $_POST['vaccine_id'];

        $stmt = $conn->prepare("DELETE FROM vaccines WHERE id = ?");
        $stmt->bindParam(1, $vaccineId);

        if ($stmt->execute()) {
            $success = 'Vaccine deleted successfully!';
        } else {
            $error = 'Failed to delete vaccine. Please try again.';
        }
    }
}

// Fetch vaccines
$vaccines = $conn->query("SELECT * FROM vaccines")->fetchAll(PDO::FETCH_ASSOC);

// Fetch doctors and nurses
$doctors = $conn->query("SELECT * FROM users WHERE role = 'Doctor'")->fetchAll(PDO::FETCH_ASSOC);
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
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Vaccines List -->
            <h3 class="text-xl font-bold text-white mb-4">Vaccines</h3>
            <table class="w-full bg-gray-700 text-white rounded-lg mb-6">
                <thead>
                    <tr>
                        <th class="p-3">ID</th>
                        <th class="p-3">Name</th>
                        <th class="p-3">Quantity</th>
                        <th class="p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vaccines as $vaccine): ?>
                        <tr>
                            <td class="p-3"><?php echo $vaccine['id']; ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($vaccine['name']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($vaccine['quantity']); ?></td>
                            <td class="p-3">
                                <button class="bg-blue-500 text-white px-3 py-1 rounded-lg" onclick="editVaccine(<?php echo $vaccine['id']; ?>, '<?php echo htmlspecialchars($vaccine['name']); ?>', '<?php echo htmlspecialchars($vaccine['quantity']); ?>')">Edit</button>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="vaccine_id" value="<?php echo $vaccine['id']; ?>">
                                    <button type="submit" name="delete_vaccine" class="bg-red-500 text-white px-3 py-1 rounded-lg">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Add Vaccine Form -->
            <h3 class="text-xl font-bold text-white mb-4">Add Vaccine</h3>
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
                <button type="submit" name="add_vaccine" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">Add Vaccine</button>
            </form>

            <!-- Doctors List -->
            <h3 class="text-xl font-bold text-white mb-4">Doctors</h3>
            <table class="w-full bg-gray-700 text-white rounded-lg mb-6">
                <thead>
                    <tr>
                        <th class="p-3">ID</th>
                        <th class="p-3">Name</th>
                        <th class="p-3">Email</th>
                        <th class="p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $doctor): ?>
                        <tr>
                            <td class="p-3"><?php echo $doctor['id']; ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($doctor['username']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($doctor['email']); ?></td>
                            <td class="p-3">
                                <button class="bg-blue-500 text-white px-3 py-1 rounded-lg" onclick="editUser(<?php echo $doctor['id']; ?>, '<?php echo htmlspecialchars($doctor['username']); ?>', '<?php echo htmlspecialchars($doctor['email']); ?>')">Edit</button>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="user_id" value="<?php echo $doctor['id']; ?>">
                                    <button type="submit" name="delete_user" class="bg-red-500 text-white px-3 py-1 rounded-lg">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Nurses List -->
            <h3 class="text-xl font-bold text-white mb-4">Nurses</h3>
            <table class="w-full bg-gray-700 text-white rounded-lg mb-6">
                <thead>
                    <tr>
                        <th class="p-3">ID</th>
                        <th class="p-3">Name</th>
                        <th class="p-3">Email</th>
                        <th class="p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nurses as $nurse): ?>
                        <tr>
                            <td class="p-3"><?php echo $nurse['id']; ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($nurse['username']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($nurse['email']); ?></td>
                            <td class="p-3">
                                <button class="bg-blue-500 text-white px-3 py-1 rounded-lg" onclick="editUser(<?php echo $nurse['id']; ?>, '<?php echo htmlspecialchars($nurse['username']); ?>', '<?php echo htmlspecialchars($nurse['email']); ?>')">Edit</button>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="user_id" value="<?php echo $nurse['id']; ?>">
                                    <button type="submit" name="delete_user" class="bg-red-500 text-white px-3 py-1 rounded-lg">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Add User Form -->
            <h3 class="text-xl font-bold text-white mb-4">Add Doctor/Nurse</h3>
            <form method="POST" class="bg-gray-700 p-6 rounded-lg mb-6">
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
                    <input type="password" id="user_password" name="user_password" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="user_role" class="block text-white mb-2">Role</label>
                    <select id="user_role" name="user_role" class="w-full px-3 py-2 bg-gray-800 text-white rounded-lg" required>
                        <option value="Doctor">Doctor</option>
                        <option value="Nurse">Nurse</option>
                    </select>
                </div>
                <button type="submit" name="add_user" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">Add User</button>
            </form>
        </div>
    </div>

    <!-- Edit Vaccine Modal -->
    <div id="editVaccineModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-md w-full">
            <h3 class="text-xl font-bold text-white mb-4">Edit Vaccine</h3>
            <form method="POST">
                <input type="hidden" id="vaccine_id" name="vaccine_id">
                <div class="mb-4">
                    <label for="vaccine_name" class="block text-white mb-2">Name</label>
                    <input type="text" id="vaccine_name" name="vaccine_name" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="vaccine_quantity" class="block text-white mb-2">Quantity</label>
                    <input type="number" id="vaccine_quantity" name="vaccine_quantity" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                </div>
                <button type="submit" name="update_vaccine" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">Update Vaccine</button>
                <button type="button" class="w-full bg-red-500 text-white py-2 rounded-lg hover:bg-red-600 transition duration-300 mt-2" onclick="closeModal('editVaccineModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-md w-full">
            <h3 class="text-xl font-bold text-white mb-4">Edit User</h3>
            <form method="POST">
                <input type="hidden" id="user_id" name="user_id">
                <div class="mb-4">
                    <label for="user_name" class="block text-white mb-2">Name</label>
                    <input type="text" id="user_name" name="user_name" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label for="user_email" class="block text-white mb-2">Email</label>
                    <input type="email" id="user_email" name="user_email" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                </div>
                <button type="submit" name="update_user" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">Update User</button>
                <button type="button" class="w-full bg-red-500 text-white py-2 rounded-lg hover:bg-red-600 transition duration-300 mt-2" onclick="closeModal('editUserModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script src="js/admin-panel.js"></script>
</body>
</html>