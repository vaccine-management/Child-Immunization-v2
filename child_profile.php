<?php

// Ensure user is logged in
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Nurse') {
    header('Location: index.php');
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

// Fetch vaccination records with scheduled time
$stmt = $conn->prepare("
    SELECT *, TIME_FORMAT(scheduled_time, '%h:%i %p') as formatted_time 
    FROM vaccinations 
    WHERE child_id = :child_id 
    ORDER BY scheduled_date DESC, scheduled_time DESC
");
$stmt->bindParam(':child_id', $childId);
$stmt->execute();
$vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate vaccination progress
$stmt = $conn->query("SELECT COUNT(*) FROM vaccines");
$totalVaccines = $stmt->fetchColumn();
$takenVaccines = count(array_filter($vaccinations, function($v) {
    return $v['status'] === 'Taken';
}));
$progress = ($totalVaccines > 0) ? ($takenVaccines / $totalVaccines) * 100 : 0;

// Fetch vaccine options
$stmt = $conn->query("SELECT name FROM vaccines ORDER BY name");
$vaccineOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['record_vaccine'])) {
        $vaccineName = trim($_POST['vaccine_name']);
        $dateTaken = date('Y-m-d');

        if (empty($vaccineName)) {
            $error = "Vaccine name is required.";
        } else {
            // Check if the vaccine is already scheduled
            $stmt = $conn->prepare("SELECT id FROM vaccinations WHERE child_id = :child_id AND vaccine_name = :vaccine_name AND status = 'Scheduled'");
            $stmt->execute([
                ':child_id' => $childId,
                ':vaccine_name' => $vaccineName
            ]);
            $scheduledVaccine = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($scheduledVaccine) {
                // Update the scheduled vaccine to "Taken"
                $stmt = $conn->prepare("UPDATE vaccinations SET date_taken = :date_taken, status = 'Taken' WHERE id = :id");
                $stmt->execute([
                    ':date_taken' => $dateTaken,
                    ':id' => $scheduledVaccine['id']
                ]);
            } else {
                // Insert a new record for the vaccine as "Taken"
                $stmt = $conn->prepare("INSERT INTO vaccinations (child_id, vaccine_name, date_taken, status) 
                                      VALUES (:child_id, :vaccine_name, :date_taken, 'Taken')");
                $stmt->execute([
                    ':child_id' => $childId,
                    ':vaccine_name' => $vaccineName,
                    ':date_taken' => $dateTaken
                ]);
            }

            header("Location: child_profile.php?id=$childId&success=1");
            exit();
        }
    }

    if (isset($_POST['schedule_vaccine'])) {
        $vaccineName = trim($_POST['vaccine_name']);
        $scheduledDate = trim($_POST['scheduled_date']);
        $scheduledTime = trim($_POST['scheduled_time']);

        if (empty($vaccineName) || empty($scheduledDate) || empty($scheduledTime)) {
            $error = "All fields are required.";
        } else {
            // Insert a new record for the vaccine as "Scheduled"
            $stmt = $conn->prepare("
                INSERT INTO vaccinations 
                    (child_id, vaccine_name, scheduled_date, scheduled_time, status) 
                VALUES 
                    (:child_id, :vaccine_name, :scheduled_date, :scheduled_time, 'Scheduled')
            ");
            try {
                $stmt->execute([
                    ':child_id' => $childId,
                    ':vaccine_name' => $vaccineName,
                    ':scheduled_date' => $scheduledDate,
                    ':scheduled_time' => $scheduledTime
                ]);
                header("Location: child_profile.php?id=$childId&success=2");
                exit();
            } catch (PDOException $e) {
                $error = "Failed to schedule vaccine.";
            }
        }
    }

    if (isset($_POST['update_child'])) {
        $fullName = trim($_POST['full_name']);
        $age = trim($_POST['age']);
        $weight = floatval($_POST['weight']);
        $gender = trim($_POST['gender']);
        $parentName = trim($_POST['parent_name']);
        $parentPhone = trim($_POST['parent_phone']);

        if (empty($fullName) || empty($age) || empty($gender) || empty($parentName) || empty($parentPhone)) {
            $error = "All fields are required.";
        } else {
            $stmt = $conn->prepare("UPDATE children SET 
                full_name = :full_name,
                age = :age,
                weight = :weight,
                gender = :gender,
                parent_name = :parent_name,
                parent_phone = :parent_phone
                WHERE id = :id");
            
            try {
                $stmt->execute([
                    ':full_name' => $fullName,
                    ':age' => $age,
                    ':weight' => $weight,
                    ':gender' => $gender,
                    ':parent_name' => $parentName,
                    ':parent_phone' => $parentPhone,
                    ':id' => $childId
                ]);
                header("Location: child_profile.php?id=$childId&success=3");
                exit();
            } catch (PDOException $e) {
                $error = "Failed to update child details.";
            }
        }
    }
}

// Define success messages
if (isset($_GET['success'])) {
    $messages = [
        1 => "Vaccine recorded successfully!",
        2 => "Vaccine scheduled successfully!",
        3 => "Child details updated successfully!"
    ];
    $message = $messages[$_GET['success']] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($child['full_name']); ?> - Child Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <!-- Profile Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg mb-6">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-6">
                        <!-- Gender-specific Avatar -->
                        <div class="relative">
                            <div class="h-20 w-20 rounded-full bg-gradient-to-br 
                                <?php echo $child['gender'] === 'Male' 
                                    ? 'from-blue-400/20 to-blue-600/20 border-blue-500/50' 
                                    : 'from-pink-400/20 to-pink-600/20 border-pink-500/50'; ?> 
                                border-2 flex items-center justify-center">
                                <svg class="w-12 h-12 
                                    <?php echo $child['gender'] === 'Male' ? 'text-blue-400' : 'text-pink-400'; ?>" 
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                        </div>
                        <!-- Child Info -->
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-1">
                                <?php echo htmlspecialchars($child['full_name']); ?>
                            </h1>
                            <div class="flex items-center space-x-4 text-sm">
                                <span class="text-blue-200">ID: #<?php echo $child['id']; ?></span>
                                <span class="px-2 py-1 rounded-full text-xs font-medium 
                                    <?php echo $child['gender'] === 'Male' 
                                        ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' 
                                        : 'bg-pink-500/10 text-pink-400 border border-pink-500/20'; ?>">
                                    <?php echo $child['gender']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <!-- Edit Button -->
                    <button id="showEditModal" 
                            class="px-4 py-2 bg-gray-700/50 hover:bg-gray-700/70 text-white rounded-lg 
                                   transition duration-300 flex items-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                        <span>Edit Profile</span>
                    </button>
                </div>
            </div>
            <!-- Quick Stats -->
            <div class="grid grid-cols-4 divide-x divide-blue-500/20 border-t border-blue-500/20">
                <div class="px-6 py-4">
                    <p class="text-blue-200 text-sm">Age</p>
                    <p class="text-white font-semibold"><?php echo htmlspecialchars($child['age']); ?></p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-blue-200 text-sm">Weight</p>
                    <p class="text-white font-semibold"><?php echo htmlspecialchars($child['weight']); ?> kg</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-blue-200 text-sm">Parent</p>
                    <p class="text-white font-semibold"><?php echo htmlspecialchars($child['parent_name']); ?></p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-blue-200 text-sm">Contact</p>
                    <a href="tel:<?php echo htmlspecialchars($child['parent_phone']); ?>" 
                       class="text-white font-semibold hover:text-blue-300 transition duration-300">
                        <?php echo htmlspecialchars($child['parent_phone']); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Progress -->
            <div class="lg:col-span-1">
                <div class="bg-gray-800 rounded-lg shadow-lg p-6 space-y-6">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Vaccination Progress
                    </h2>
                    
                    <!-- Progress Bar -->
                    <div class="bg-gray-700/50 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-300">Overall Progress</span>
                            <span class="text-blue-400 font-medium"><?php echo round($progress); ?>%</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-500 rounded-full h-2 transition-all duration-500" 
                                 style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <p class="text-gray-400 text-sm mt-2">
                            <?php echo $takenVaccines; ?> of <?php echo $totalVaccines; ?> vaccines completed
                        </p>
                    </div>

                    <!-- Next Scheduled Vaccine -->
                    <?php
                    $nextScheduled = array_filter($vaccinations, function($v) {
                        return $v['status'] === 'Scheduled' && strtotime($v['scheduled_date']) >= strtotime('today');
                    });
                    if (!empty($nextScheduled)) {
                        $next = reset($nextScheduled);
                    ?>
                    <div class="bg-blue-500/10 border border-blue-500/20 rounded-lg p-4">
                        <h3 class="text-blue-400 text-sm font-medium mb-2">Next Scheduled Vaccine</h3>
                        <p class="text-white font-medium"><?php echo htmlspecialchars($next['vaccine_name']); ?></p>
                        <p class="text-gray-400 text-sm">
                            <?php echo date('F j, Y', strtotime($next['scheduled_date'])); ?>
                        </p>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Right Column - Forms and Records -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Action Forms -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Record Vaccine Form -->
                    <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Record Vaccine
                        </h2>
                        <form method="POST" class="space-y-4">
                            <div class="relative">
                                <select name="vaccine_name" required
                                        class="w-full px-4 py-3 bg-gray-700 text-white rounded-lg appearance-none 
                                               focus:ring-2 focus:ring-green-500 transition duration-300">
                                    <option value="" disabled selected>Select Vaccine</option>
                                    <?php foreach ($vaccineOptions as $vaccine): ?>
                                        <option value="<?php echo htmlspecialchars($vaccine); ?>">
                                            <?php echo htmlspecialchars($vaccine); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="record_vaccine"
                                    class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg 
                                           transition duration-300 flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Record Vaccine
                            </button>
                        </form>
                    </div>

                    <!-- Schedule Vaccine Form -->
                    <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Schedule Vaccine
                        </h2>
                        <form method="POST" class="space-y-4">
                            <div class="relative">
                                <select name="vaccine_name" required
                                        class="w-full px-4 py-3 bg-gray-700 text-white rounded-lg appearance-none 
                                               focus:ring-2 focus:ring-blue-500 transition duration-300">
                                    <option value="" disabled selected>Select Vaccine</option>
                                    <?php foreach ($vaccineOptions as $vaccine): ?>
                                        <option value="<?php echo htmlspecialchars($vaccine); ?>">
                                            <?php echo htmlspecialchars($vaccine); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <input type="date" name="scheduled_date" required min="<?php echo date('Y-m-d'); ?>"
                                       class="w-full px-4 py-3 bg-gray-700 text-white rounded-lg 
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                                <input type="time" name="scheduled_time" required
                                       class="w-full px-4 py-3 bg-gray-700 text-white rounded-lg 
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                            </div>
                            <button type="submit" name="schedule_vaccine"
                                    class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-lg 
                                           transition duration-300 flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Schedule Vaccine
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Vaccination History Table -->
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        Vaccination History
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left border-b border-gray-700">
                                    <th class="pb-3 text-gray-400 font-medium">Vaccine</th>
                                    <th class="pb-3 text-gray-400 font-medium">Date Taken</th>
                                    <th class="pb-3 text-gray-400 font-medium">Scheduled Date</th>
                                    <th class="pb-3 text-gray-400 font-medium">Time</th>
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
                                        <?php echo $vaccination['date_taken'] 
                                            ? date('M d, Y', strtotime($vaccination['date_taken'])) 
                                            : '-'; ?>
                                    </td>
                                    <td class="py-4 text-white">
                                        <?php echo $vaccination['scheduled_date'] 
                                            ? date('M d, Y', strtotime($vaccination['scheduled_date'])) 
                                            : '-'; ?>
                                    </td>
                                    <td class="py-4 text-white">
                                        <?php echo $vaccination['formatted_time'] ?? '-'; ?>
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

    <!-- Notification -->
    <?php if (isset($_GET['success'])): ?>
    <div id="notification" 
         class="fixed top-24 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg 
                animate__animated animate__fadeInRight flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span><?php echo $message; ?></span>
    </div>

    <script>
        // Notification functionality
        const notification = document.getElementById('notification');
        if (notification) {
            // Remove the success parameter from the URL
            const url = new URL(window.location.href);
            url.searchParams.delete('success');
            history.replaceState(null, '', url);

            // Hide the notification after 3 seconds
            setTimeout(() => {
                notification.classList.replace('animate__fadeInRight', 'animate__fadeOutRight');
                setTimeout(() => {
                    notification.remove();
                }, 1000);
            }, 3000);
        }
    </script>
    <?php endif; ?>

    <!-- Edit Modal -->
    <div id="editModal" class="hidden fixed inset-0 z-50">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black bg-opacity-50" id="modalBackdrop"></div>
        
        <!-- Modal Content -->
        <div class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl">
            <div class="bg-gray-800 rounded-lg shadow-2xl animate__animated animate__fadeInDown">
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-700">
                    <h3 class="text-xl font-semibold text-white flex items-center">
                        <svg class="w-6 h-6 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                        Edit Child Details
                    </h3>
                    <button id="closeEditModal" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <form method="POST" class="p-6 space-y-6">
                    <!-- Personal Information -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-blue-400 uppercase tracking-wider">Personal Information</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="full_name" class="block text-gray-300 text-sm font-medium mb-2">Full Name</label>
                                <input type="text" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($child['full_name']); ?>" required
                                       class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="age" class="block text-gray-300 text-sm font-medium mb-2">Age</label>
                                    <input type="text" id="age" name="age" 
                                           value="<?php echo htmlspecialchars($child['age']); ?>" required
                                           class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg
                                                  focus:ring-2 focus:ring-blue-500 transition duration-300">
                                </div>
                                <div>
                                    <label for="weight" class="block text-gray-300 text-sm font-medium mb-2">Weight (kg)</label>
                                    <input type="number" step="0.1" id="weight" name="weight" 
                                           value="<?php echo htmlspecialchars($child['weight']); ?>" required
                                           class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg
                                                  focus:ring-2 focus:ring-blue-500 transition duration-300">
                                </div>
                            </div>
                            <div>
                                <label for="gender" class="block text-gray-300 text-sm font-medium mb-2">Gender</label>
                                <select id="gender" name="gender" required
                                        class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg
                                               focus:ring-2 focus:ring-blue-500 transition duration-300">
                                    <option value="Male" <?php echo $child['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $child['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
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
                                <input type="text" id="parent_name" name="parent_name" 
                                       value="<?php echo htmlspecialchars($child['parent_name']); ?>" required
                                       class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                            </div>
                            <div>
                                <label for="parent_phone" class="block text-gray-300 text-sm font-medium mb-2">Parent's Phone</label>
                                <input type="tel" id="parent_phone" name="parent_phone" 
                                       value="<?php echo htmlspecialchars($child['parent_phone']); ?>" required
                                       class="w-full px-4 py-2.5 bg-gray-700 border border-gray-600 text-white rounded-lg
                                              focus:ring-2 focus:ring-blue-500 transition duration-300">
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="update_child"
                            class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-lg
                                   transition duration-300 flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Edit Modal Functionality
        const editModal = document.getElementById('editModal');
        const showEditModal = document.getElementById('showEditModal');
        const closeEditModal = document.getElementById('closeEditModal');
        const modalBackdrop = document.getElementById('modalBackdrop');

        function toggleModal(show) {
            editModal.classList.toggle('hidden', !show);
        }

        showEditModal.addEventListener('click', () => toggleModal(true));
        closeEditModal.addEventListener('click', () => toggleModal(false));
        modalBackdrop.addEventListener('click', () => toggleModal(false));

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') toggleModal(false);
        });
    </script>
</body>
</html>