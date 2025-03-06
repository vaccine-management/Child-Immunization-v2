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
    } elseif (isset($_POST['delete_user'])) {
        $userId = $_POST['user_id'];

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bindParam(1, $userId);

        if ($stmt->execute()) {
            $success = 'User deleted successfully!';
        } else {
            $error = 'Failed to delete user. Please try again.';
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
    <?php include  'includes/navbar.php'; ?>

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

            <!-- Vaccines List -->
            <h3 class="text-xl font-bold text-white mb-4">Vaccines</h3>
            <a href="add-vaccine.php" class="bg-blue-500 text-white px-3 py-2 rounded-lg mb-4 inline-block">Add Vaccine</a>
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
                            <td class="p-3 flex space-x-2">
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

            <!-- Doctors List -->
            <h3 class="text-xl font-bold text-white mb-4">Admin</h3>
            <a href="add-user.php" class="bg-blue-500 text-white px-3 py-2 rounded-lg mb-4 inline-block">Add Doctor/Nurse</a>
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
                            <td class="p-3 flex space-x-2">
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
                            <td class="p-3 flex space-x-2">
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
        </div>
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-4xl mx-auto mt-6">
            <h2 class="text-2xl font-bold text-white mb-6">SMS Notifications Management</h2>

            <!-- SMS Notifications Tab -->
            <div class="mb-6">
                <div class="bg-gray-700 p-4 rounded-lg">
                    <h3 class="text-xl font-semibold text-white mb-4">Send Notifications</h3>
                    
                    <!-- Notification Options -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-800 p-4 rounded-lg">
                            <h4 class="text-lg font-medium text-white mb-2">Upcoming Vaccination Reminders</h4>
                            <p class="text-gray-400 mb-4">Send reminders to parents about upcoming vaccinations.</p>
                            <form action="backend/send_sms_reminders.php" method="post">
                                <div class="mb-4">
                                    <label class="block text-gray-400 text-sm font-medium mb-2">Days Before Vaccination</label>
                                    <select name="days_before" class="w-full bg-gray-700 text-white rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="1">1 Day Before</option>
                                        <option value="3" selected>3 Days Before</option>
                                        <option value="7">7 Days Before</option>
                                    </select>
                                </div>
                                <button type="submit" name="send_upcoming_reminders" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Send Reminders
                                </button>
                            </form>
                        </div>
                        
                        <div class="bg-gray-800 p-4 rounded-lg">
                            <h4 class="text-lg font-medium text-white mb-2">Missed Vaccination Notifications</h4>
                            <p class="text-gray-400 mb-4">Notify parents about missed vaccinations.</p>
                            <form action="backend/send_sms_reminders.php" method="post">
                                <div class="mb-4">
                                    <p class="text-gray-400 text-sm">This will send notifications to parents whose children have missed their scheduled vaccinations.</p>
                                </div>
                                <button type="submit" name="send_missed_notifications" class="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded focus:outline-none focus:ring-2 focus:ring-red-500">
                                    Send Missed Notifications
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- SMS Logs -->
                    <h3 class="text-xl font-semibold text-white mb-4">Recent SMS Logs</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-700">
                            <thead class="bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Recipient</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Message</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Sent At</th>
                                </tr>
                            </thead>
                            <tbody class="bg-gray-800 divide-y divide-gray-700">
                                <?php
                                // Fetch recent SMS logs
                                $stmt = $conn->query("
                                    SELECT recipient, LEFT(message, 50) AS short_message, status, sent_at
                                    FROM sms_logs
                                    ORDER BY sent_at DESC
                                    LIMIT 10
                                ");
                                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($logs) > 0) {
                                    foreach ($logs as $log) {
                                        $statusClass = $log['status'] === 'success' ? 'text-green-500' : 'text-red-500';
                                        echo "<tr>";
                                        echo "<td class='px-4 py-3 text-sm text-gray-300'>" . htmlspecialchars($log['recipient']) . "</td>";
                                        echo "<td class='px-4 py-3 text-sm text-gray-300'>" . htmlspecialchars($log['short_message']) . "...</td>";
                                        echo "<td class='px-4 py-3 text-sm {$statusClass}'>" . htmlspecialchars($log['status']) . "</td>";
                                        echo "<td class='px-4 py-3 text-sm text-gray-300'>" . htmlspecialchars($log['sent_at']) . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='px-4 py-3 text-sm text-gray-400 text-center'>No SMS logs found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
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

    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="js/admin.js"></script>
</body>
</html>