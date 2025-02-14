<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
include 'backend/db.php';

// Get child ID from URL
if (!isset($_GET['id'])) {
    header('Location: children.php');
    exit();
}

$childId = intval($_GET['id']);

// Fetch child details
$stmt = $conn->prepare("SELECT * FROM children WHERE id = :id");
$stmt->bindParam(':id', $childId);
$stmt->execute();
$child = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$child) {
    header('Location: children.php');
    exit();
}

// Fetch vaccination records
$stmt = $conn->prepare("SELECT * FROM vaccinations WHERE child_id = :child_id");
$stmt->bindParam(':child_id', $childId);
$stmt->execute();
$vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch vaccine names from the vaccines table
$vaccineOptions = [];
try {
    $stmt = $conn->query("SELECT name FROM vaccines");
    $vaccineOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // If the vaccines table doesn't exist, use a static list
    $vaccineOptions = ["BCG", "Polio", "Hepatitis B", "DPT", "Measles", "Rotavirus", "Pneumococcal"];
}

// Handle vaccine taken form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['record_vaccine'])) {
        $vaccineName = trim($_POST['vaccine_name']);
        $dateTaken = date('Y-m-d'); // Current date

        // Validate inputs
        if (empty($vaccineName)) {
            $error = "Vaccine name is required.";
        } else {
            // Insert the vaccine into the database
            $stmt = $conn->prepare("INSERT INTO vaccinations (child_id, vaccine_name, date_taken, status) VALUES (:child_id, :vaccine_name, :date_taken, 'Taken')");
            $stmt->bindParam(':child_id', $childId);
            $stmt->bindParam(':vaccine_name', $vaccineName);
            $stmt->bindParam(':date_taken', $dateTaken);

            if ($stmt->execute()) {
                $success = "Vaccine recorded successfully!";
                // Refresh the page to show updated data
                header("Location: child_profile.php?id=$childId");
                exit();
            } else {
                $error = "Failed to record vaccine. Please try again.";
            }
        }
    }

    // Handle scheduling a vaccine
    if (isset($_POST['schedule_vaccine'])) {
        $vaccineName = trim($_POST['vaccine_name']);
        $scheduledDate = trim($_POST['scheduled_date']);

        // Validate inputs
        if (empty($vaccineName) || empty($scheduledDate)) {
            $error = "Vaccine name and scheduled date are required.";
        } else {
            // Insert the scheduled vaccine into the database
            $stmt = $conn->prepare("INSERT INTO vaccinations (child_id, vaccine_name, scheduled_date, status) VALUES (:child_id, :vaccine_name, :scheduled_date, 'Scheduled')");
            $stmt->bindParam(':child_id', $childId);
            $stmt->bindParam(':vaccine_name', $vaccineName);
            $stmt->bindParam(':scheduled_date', $scheduledDate);

            if ($stmt->execute()) {
                $success = "Vaccine scheduled successfully!";
                // Refresh the page to show updated data
                header("Location: child_profile.php?id=$childId");
                exit();
            } else {
                $error = "Failed to schedule vaccine. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Profile - Immunization System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <!-- Profile Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg mb-6 p-6">
            <div class="flex items-center space-x-4">
                <div class="bg-white p-3 rounded-full">
                    <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-white mb-1"><?php echo htmlspecialchars($child['full_name']); ?></h1>
                    <p class="text-blue-200">ID: #<?php echo htmlspecialchars($child['id']); ?></p>
                </div>
            </div>
        </div>

        <!-- Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Child Details -->
            <div class="lg:col-span-1">
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Personal Information
                    </h2>
                    <div class="space-y-4">
                        <div class="bg-gray-700 p-4 rounded-lg">
                            <p class="text-gray-400 text-sm">Age</p>
                            <p class="text-white text-lg"><?php echo htmlspecialchars($child['age']); ?> years</p>
                        </div>
                        <div class="bg-gray-700 p-4 rounded-lg">
                            <p class="text-gray-400 text-sm">Weight</p>
                            <p class="text-white text-lg"><?php echo htmlspecialchars($child['weight']); ?> kg</p>
                        </div>
                        <div class="bg-gray-700 p-4 rounded-lg">
                            <p class="text-gray-400 text-sm">Parent's Name</p>
                            <p class="text-white text-lg"><?php echo htmlspecialchars($child['parent_name']); ?></p>
                        </div>
                        <div class="bg-gray-700 p-4 rounded-lg">
                            <p class="text-gray-400 text-sm">Contact Number</p>
                            <a href="tel:<?php echo htmlspecialchars($child['parent_phone']); ?>" 
                               class="text-blue-400 text-lg hover:text-blue-300 transition duration-300 flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                <?php echo htmlspecialchars($child['parent_phone']); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Forms and Records -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Record & Schedule Vaccine Forms -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Record Vaccine Form -->
                    <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Record Vaccine
                        </h2>
                        <?php if (isset($success)): ?>
                            <div class="bg-green-500 bg-opacity-10 border border-green-500 text-green-500 px-4 py-3 rounded-lg mb-4">
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" class="space-y-4">
                            <div class="relative">
                                <select name="vaccine_name" class="w-full px-4 py-3 bg-gray-700 text-white rounded-lg appearance-none focus:ring-2 focus:ring-blue-500 transition duration-300">
                                    <option value="" disabled selected>Select Vaccine</option>
                                    <?php foreach ($vaccineOptions as $vaccine): ?>
                                        <option value="<?php echo htmlspecialchars($vaccine); ?>">
                                            <?php echo htmlspecialchars($vaccine); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>
                            <button type="submit" name="record_vaccine" class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg transition duration-300 flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Record Vaccine
                            </button>
                        </form>
                    </div>

                    <!-- Schedule Vaccine Form -->
                    <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Schedule Vaccine
                        </h2>
                        <form method="POST" class="space-y-4">
                            <div class="relative">
                                <select name="vaccine_name" class="w-full px-4 py-3 bg-gray-700 text-white rounded-lg appearance-none focus:ring-2 focus:ring-blue-500 transition duration-300">
                                    <option value="" disabled selected>Select Vaccine</option>
                                    <?php foreach ($vaccineOptions as $vaccine): ?>
                                        <option value="<?php echo htmlspecialchars($vaccine); ?>">
                                            <?php echo htmlspecialchars($vaccine); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="date" name="scheduled_date" class="w-full px-4 py-3 bg-gray-700 text-white rounded-lg focus:ring-2 focus:ring-blue-500 transition duration-300" required>
                            <button type="submit" name="schedule_vaccine" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-lg transition duration-300 flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Schedule Vaccine
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Vaccination Records -->
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Vaccination History
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left border-b border-gray-700">
                                    <th class="pb-3 text-gray-400 font-medium">Vaccine</th>
                                    <th class="pb-3 text-gray-400 font-medium">Date Taken</th>
                                    <th class="pb-3 text-gray-400 font-medium">Scheduled</th>
                                    <th class="pb-3 text-gray-400 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach ($vaccinations as $vaccination): ?>
                                    <tr class="hover:bg-gray-700/50 transition duration-300">
                                        <td class="py-4 text-white">
                                            <?php echo htmlspecialchars($vaccination['vaccine_name']); ?>
                                        </td>
                                        <td class="py-4 text-white">
                                            <?php echo $vaccination['date_taken'] ? date('M d, Y', strtotime($vaccination['date_taken'])) : '-'; ?>
                                        </td>
                                        <td class="py-4 text-white">
                                            <?php echo $vaccination['scheduled_date'] ? date('M d, Y', strtotime($vaccination['scheduled_date'])) : '-'; ?>
                                        </td>
                                        <td class="py-4">
                                            <span class="px-3 py-1 rounded-full text-sm font-medium
                                                <?php echo $vaccination['status'] === 'Taken' 
                                                    ? 'bg-green-500/10 text-green-500' 
                                                    : 'bg-yellow-500/10 text-yellow-500'; ?>">
                                                <?php echo htmlspecialchars($vaccination['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>