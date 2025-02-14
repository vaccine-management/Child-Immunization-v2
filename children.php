<?php
session_start();

// Ensure the user is logged in and is a nurse
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Nurse') {
    header('Location: login.php');
    exit();
}

// Include database connection
include 'backend/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name']);
    $age = trim($_POST['age']); // Changed to accept string input like "7months" or "1year"
    $weight = floatval($_POST['weight']);
    $parentName = trim($_POST['parent_name']);
    $parentPhone = trim($_POST['parent_phone']);

    // Validate inputs
    if (empty($fullName) || empty($parentName) || empty($parentPhone)) {
        $error = "All fields are required.";
    } elseif ($weight <= 0) {
        $error = "Weight must be a positive number.";
    } else {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO children (full_name, age, weight, parent_name, parent_phone) VALUES (:full_name, :age, :weight, :parent_name, :parent_phone)");
        $stmt->bindParam(':full_name', $fullName);
        $stmt->bindParam(':age', $age);
        $stmt->bindParam(':weight', $weight);
        $stmt->bindParam(':parent_name', $parentName);
        $stmt->bindParam(':parent_phone', $parentPhone);

        if ($stmt->execute()) {
            $success = "Child registered successfully!";
        } else {
            $error = "Failed to register child. Please try again.";
        }
    }
}

// Fetch all children from the database
$stmt = $conn->query("SELECT * FROM children");
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Children Registration - Immunization System</title>

    <!-- Include Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <!-- Button to Show Registration Form -->
        <button id="showFormButton" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition duration-300 mb-6">
            Register New Child
        </button>

        <!-- Registration Form (Hidden by Default) -->
        <div id="registrationForm" class="bg-gray-800 p-6 rounded-lg shadow-lg mb-6 hidden">
            <h2 class="text-2xl font-bold text-white mb-6">Register New Child</h2>

            <!-- Display error or success messages -->
            <?php if (isset($error)): ?>
                <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-green-500 text-white p-3 rounded-lg mb-4">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mb-4">
                        <label for="full_name" class="block text-white mb-2">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                    </div>

                    <div class="mb-4">
                        <label for="age" class="block text-white mb-2">Age (e.g., 7months, 1year)</label>
                        <input type="text" id="age" name="age" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                    </div>

                    <div class="mb-4">
                        <label for="weight" class="block text-white mb-2">Weight (kg)</label>
                        <input type="number" step="0.1" id="weight" name="weight" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                    </div>

                    <div class="mb-4">
                        <label for="parent_name" class="block text-white mb-2">Parent's Name</label>
                        <input type="text" id="parent_name" name="parent_name" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                    </div>

                    <div class="mb-4">
                        <label for="parent_phone" class="block text-white mb-2">Parent's Phone</label>
                        <input type="tel" id="parent_phone" name="parent_phone" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg" required>
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-300">
                    Register Child
                </button>
            </form>
        </div>

        <!-- Search Bar -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg mb-6">
            <h2 class="text-2xl font-bold text-white mb-6">Search Children</h2>
            <input type="text" id="searchInput" placeholder="Search by name or parent's phone" class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg">
        </div>

        <!-- Children Table -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-bold text-white mb-6">Registered Children</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-gray-700 rounded-lg">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-white">Full Name</th>
                            <th class="px-4 py-2 text-left text-white">Age</th>
                            <th class="px-4 py-2 text-left text-white">Weight</th>
                            <th class="px-4 py-2 text-left text-white">Parent's Name</th>
                            <th class="px-4 py-2 text-left text-white">Parent's Phone</th>
                        </tr>
                    </thead>
                    <tbody id="childrenTable">
                        <?php foreach ($children as $child): ?>
                            <tr class="hover:bg-gray-600 transition duration-300 cursor-pointer" onclick="window.location.href='child_profile.php?id=<?php echo $child['id']; ?>'">
                                <td class="px-4 py-2 text-white"><?php echo htmlspecialchars($child['full_name']); ?></td>
                                <td class="px-4 py-2 text-white"><?php echo htmlspecialchars($child['age']); ?></td>
                                <td class="px-4 py-2 text-white"><?php echo htmlspecialchars($child['weight']); ?> kg</td>
                                <td class="px-4 py-2 text-white"><?php echo htmlspecialchars($child['parent_name']); ?></td>
                                <td class="px-4 py-2 text-white"><?php echo htmlspecialchars($child['parent_phone']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Script to Toggle Registration Form -->
    <script>
        const showFormButton = document.getElementById('showFormButton');
        const registrationForm = document.getElementById('registrationForm');

        showFormButton.addEventListener('click', function() {
            registrationForm.classList.toggle('hidden');
        });
    </script>

    <!-- Search Functionality -->
    <script>
        const searchInput = document.getElementById('searchInput');
        const childrenTable = document.getElementById('childrenTable').getElementsByTagName('tr');

        searchInput.addEventListener('input', function() {
            const searchTerm = searchInput.value.toLowerCase();

            for (let row of childrenTable) {
                const name = row.cells[0].textContent.toLowerCase();
                const phone = row.cells[4].textContent.toLowerCase();

                if (name.includes(searchTerm) || phone.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>