<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
include 'backend/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name']);
    $age = trim($_POST['age']);
    $weight = floatval($_POST['weight']);
    $gender = trim($_POST['gender']);
    $parentName = trim($_POST['parent_name']);
    $parentPhone = trim($_POST['parent_phone']);

    // Validate inputs
    if (empty($fullName) || empty($parentName) || empty($parentPhone) || empty($gender)) {
        $error = "All fields are required.";
    } elseif ($weight <= 0) {
        $error = "Weight must be a positive number.";
    } else {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO children (full_name, age, weight, gender, parent_name, parent_phone) 
                               VALUES (:full_name, :age, :weight, :gender, :parent_name, :parent_phone)");
        
        try {
            $stmt->execute([
                ':full_name' => $fullName,
                ':age' => $age,
                ':weight' => $weight,
                ':gender' => $gender,
                ':parent_name' => $parentName,
                ':parent_phone' => $parentPhone
            ]);
            // Redirect to prevent form resubmission
            header("Location: children.php?success=1");
            exit();
        } catch (PDOException $e) {
            $error = "Failed to register child. Please try again.";
        }
    }
}

// Fetch all children from the database
$stmt = $conn->query("SELECT * FROM children ORDER BY id DESC");
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define success message
if (isset($_GET['success'])) {
    $success = "Child registered successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Children Management - Immunization System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Modal Backdrop -->
    <div id="modalBackdrop" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40"></div>

    <!-- Registration Modal -->
    <div id="registrationModal" class="hidden fixed inset-0 flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg shadow-2xl w-full max-w-2xl animate__animated">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-700">
                <h3 class="text-xl font-semibold text-white flex items-center">
                    <svg class="w-6 h-6 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                        </path>
                    </svg>
                    Register New Child
                </h3>
                <button id="closeModal" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6">
                <form method="POST" class="space-y-6">
                    <!-- Personal Information -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-blue-400 uppercase tracking-wider">Personal Information</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="full_name" class="block text-gray-300 text-sm font-medium mb-2">Full Name</label>
                                <input type="text" id="full_name" name="full_name" required
                                       class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="age" class="block text-gray-300 text-sm font-medium mb-2">Age</label>
                                    <input type="text" id="age" name="age" placeholder="e.g., 7months" required
                                           class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                  focus:ring-2 focus:ring-blue-500 transition duration-300">
                                </div>
                                <div>
                                    <label for="weight" class="block text-gray-300 text-sm font-medium mb-2">Weight (kg)</label>
                                    <input type="number" step="0.1" id="weight" name="weight" required
                                           class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                                  focus:ring-2 focus:ring-blue-500 transition duration-300">
                                </div>
                            </div>
                            <div>
                                <label for="gender" class="block text-gray-300 text-sm font-medium mb-2">Gender</label>
                                <select id="gender" name="gender" required
                                        class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                               focus:ring-2 focus:ring-blue-500 transition duration-300">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Parent Information -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-green-400 uppercase tracking-wider">Parent Information</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="parent_name" class="block text-gray-300 text-sm font-medium mb-2">Parent's Name</label>
                                <input type="text" id="parent_name" name="parent_name" required
                                       class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                            </div>
                            <div>
                                <label for="parent_phone" class="block text-gray-300 text-sm font-medium mb-2">Parent's Phone</label>
                                <input type="tel" id="parent_phone" name="parent_phone" required
                                       class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg 
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                            </div>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 
                                   text-white py-3 rounded-lg transition duration-300 flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Register Child
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notificationContainer" class="fixed top-4 right-4 z-50 space-y-4">
        <?php if (isset($error)): ?>
            <div class="notification bg-red-500 text-white p-4 rounded-lg shadow-lg animate__animated animate__fadeInRight">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo $error; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="notification bg-green-500 text-white p-4 rounded-lg shadow-lg animate__animated animate__fadeInRight">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo $success; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <!-- Page Header with Search -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg p-6 mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-white">Children Management</h1>
                <p class="text-blue-200">Register and manage children's immunization records</p>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Search Bar -->
                <div class="w-64">
                    <div class="relative">
                        <input type="text" id="searchInput" placeholder="Search children..." 
                               class="w-full pl-10 pr-4 py-2 bg-gray-700/50 border border-gray-600 text-white rounded-lg 
                                      focus:ring-2 focus:ring-blue-500 transition duration-300">
                        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
                <!-- Register Button -->
                <button id="showFormButton" 
                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-300 
                               flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <span>Register Child</span>
                </button>
            </div>
        </div>

        <!-- Children List -->
        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="max-h-[calc(100vh-12rem)] overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-800 sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Child Info</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Gender</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Age & Weight</th>
                            <th class="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Parent Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700" id="childrenTable">
                        <?php foreach ($children as $child): ?>
                            <tr class="hover:bg-gray-700/50 transition duration-300 cursor-pointer" 
                                onclick="window.location.href='child_profile.php?id=<?php echo $child['id']; ?>'">
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full bg-blue-500/10 flex items-center justify-center">
                                                <?php if ($child['gender'] === 'Male'): ?>
                                                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="w-6 h-6 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-white">
                                                <?php echo htmlspecialchars($child['full_name']); ?>
                                            </div>
                                            <div class="text-xs text-gray-400">ID: #<?php echo $child['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full
                                        <?php echo $child['gender'] === 'Male' ? 'bg-blue-500/10 text-blue-400' : 'bg-pink-500/10 text-pink-400'; ?>">
                                        <?php echo $child['gender']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-white"><?php echo htmlspecialchars($child['age']); ?></div>
                                    <div class="text-sm text-gray-400"><?php echo htmlspecialchars($child['weight']); ?> kg</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-white"><?php echo htmlspecialchars($child['parent_name']); ?></div>
                                    <div class="text-sm text-gray-400"><?php echo htmlspecialchars($child['parent_phone']); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const modalBackdrop = document.getElementById('modalBackdrop');
        const registrationModal = document.getElementById('registrationModal');
        const showFormButton = document.getElementById('showFormButton');
        const closeModal = document.getElementById('closeModal');

        function openModal() {
            modalBackdrop.classList.remove('hidden');
            registrationModal.classList.remove('hidden');
            registrationModal.classList.add('animate__fadeInDown');
        }

        function closeModalFunc() {
            registrationModal.classList.remove('animate__fadeInDown');
            registrationModal.classList.add('animate__fadeOutUp');
            setTimeout(() => {
                modalBackdrop.classList.add('hidden');
                registrationModal.classList.add('hidden');
                registrationModal.classList.remove('animate__fadeOutUp');
            }, 300);
        }

        showFormButton.addEventListener('click', openModal);
        closeModal.addEventListener('click', closeModalFunc);
        modalBackdrop.addEventListener('click', closeModalFunc);

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModalFunc();
        });

        // Prevent modal close when clicking inside
        registrationModal.addEventListener('click', (e) => e.stopPropagation());

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const childrenTable = document.getElementById('childrenTable').getElementsByTagName('tr');

        searchInput.addEventListener('input', function() {
            const searchTerm = searchInput.value.toLowerCase();
            Array.from(childrenTable).forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Auto-hide notifications and clear URL
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(notification => {
            setTimeout(() => {
                notification.classList.remove('animate__fadeInRight');
                notification.classList.add('animate__fadeOutRight');
                setTimeout(() => {
                    notification.remove();
                }, 1000);
            }, 5000);
        });

        // Clear success parameter from URL
        if (window.location.search.includes('success')) {
            const url = new URL(window.location.href);
            url.searchParams.delete('success');
            history.replaceState(null, '', url);
        }
    </script>
</body>
</html>