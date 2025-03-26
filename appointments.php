<?php
// Start session
session_start();

// Include the auth check file
require_once 'includes/auth_check.php';

// Include the database connection
require_once 'backend/db.php';

// Include the vaccine helper functions
require_once 'backend/vaccine_helpers.php';

// Fetch all appointments using the helper function
$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days')); // Show last 30 days by default
$toDate = $_GET['to_date'] ?? date('Y-m-d', strtotime('+60 days'));     // Show next 60 days by default

// Try-catch block to catch any errors
try {
    // Get appointments from the database - modified to show all statuses
    $sql = "
        SELECT 
            a.*,
            c.full_name as child_name,
            c.date_of_birth,
            c.guardian_name,
            c.phone,
            COUNT(av.id) as vaccine_count
        FROM appointments a
        JOIN children c ON a.child_id = c.child_id
        LEFT JOIN appointment_vaccines av ON a.id = av.appointment_id
        WHERE 
            a.scheduled_date BETWEEN :from_date AND :to_date
        GROUP BY a.id
        ORDER BY a.scheduled_date
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':from_date', $fromDate, PDO::PARAM_STR);
    $stmt->bindParam(':to_date', $toDate, PDO::PARAM_STR);
    $stmt->execute();
    
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $_SESSION['error'] = "Error loading appointments: " . $e->getMessage();
    $appointments = [];
}

// Check for filter form submission
if (isset($_GET['filter'])) {
    $fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days')); // Show last 30 days by default
    $toDate = $_GET['to_date'] ?? date('Y-m-d', strtotime('+60 days'));     // Show next 60 days by default
    
    // Validate dates
    if (strtotime($fromDate) && strtotime($toDate)) {
        try {
            // Get appointments from the database - modified to show all statuses
            $sql = "
                SELECT 
                    a.*,
                    c.full_name as child_name,
                    c.date_of_birth,
                    c.guardian_name,
                    c.phone,
                    COUNT(av.id) as vaccine_count
                FROM appointments a
                JOIN children c ON a.child_id = c.child_id
                LEFT JOIN appointment_vaccines av ON a.id = av.appointment_id
                WHERE 
                    a.scheduled_date BETWEEN :from_date AND :to_date
                GROUP BY a.id
                ORDER BY a.scheduled_date
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':from_date', $fromDate, PDO::PARAM_STR);
            $stmt->bindParam(':to_date', $toDate, PDO::PARAM_STR);
            $stmt->execute();
            
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching appointments: " . $e->getMessage());
            $_SESSION['error'] = "Error loading appointments: " . $e->getMessage();
            $appointments = [];
        }
    } else {
        $_SESSION['error'] = "Invalid date format";
    }
}

// Get current date for highlighting today's appointments
$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Appointments - Child Immunization System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-gray-900 text-white">
    
    <?php include 'includes/navbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
        <div class="p-4 sm:p-6 lg:p-8">
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-white">All Appointments</h1>
                <p class="text-gray-400">View and manage all scheduled vaccination appointments</p>
            </div>
            
            <!-- Filter Form -->
            <div class="dashboard-card p-5 mb-6">
                <h2 class="text-lg font-semibold text-white mb-4">Filter Appointments</h2>
                <form action="" method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">From Date</label>
                        <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" 
                               class="bg-gray-800 text-white px-3 py-2 rounded-lg border border-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">To Date</label>
                        <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>" 
                               class="bg-gray-800 text-white px-3 py-2 rounded-lg border border-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <button type="submit" name="filter" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                            <i class="fas fa-filter mr-2"></i>Apply Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Appointments Table -->
            <div class="dashboard-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-gray-800">
                                <th class="px-4 py-3 text-gray-400 text-sm font-medium">Date</th>
                                <th class="px-4 py-3 text-gray-400 text-sm font-medium">Time</th>
                                <th class="px-4 py-3 text-gray-400 text-sm font-medium">Child</th>
                                <th class="px-4 py-3 text-gray-400 text-sm font-medium">Vaccines</th>
                                <th class="px-4 py-3 text-gray-400 text-sm font-medium">Status</th>
                                <th class="px-4 py-3 text-gray-400 text-sm font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-5 text-gray-400 text-center">No appointments found within the selected date range.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $appointment): 
                                    // Get the appointment vaccines
                                    try {
                                        $stmt = $conn->prepare("
                                            SELECT 
                                                av.*,
                                                v.name as vaccine_name
                                            FROM appointment_vaccines av
                                            JOIN vaccines v ON av.vaccine_id = v.id
                                            WHERE av.appointment_id = ?
                                        ");
                                        $stmt->execute([$appointment['id']]);
                                        $appointmentVaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (Exception $e) {
                                        error_log("Error fetching appointment vaccines: " . $e->getMessage());
                                        $appointmentVaccines = [];
                                    }
                                    
                                    // Format vaccines for display
                                    $vaccineNames = array_map(function($v) { 
                                        return $v['vaccine_name'] . (isset($v['dose_number']) ? ' (Dose ' . $v['dose_number'] . ')' : ''); 
                                    }, $appointmentVaccines);
                                    
                                    // Get status class
                                    $statusClass = 'text-blue-400 bg-blue-500/10';
                                    $statusText = ucfirst($appointment['status'] ?? 'Scheduled');
                                    
                                    if (isset($appointment['status']) && $appointment['status'] === 'completed') {
                                        $statusClass = 'text-green-400 bg-green-500/10';
                                    } elseif (isset($appointment['status']) && $appointment['status'] === 'missed' || 
                                              (isset($appointment['scheduled_date']) && $appointment['scheduled_date'] < $today && 
                                              (!isset($appointment['status']) || $appointment['status'] === 'scheduled'))) {
                                        $statusClass = 'text-red-400 bg-red-500/10';
                                        if (!isset($appointment['status']) || $appointment['status'] === 'scheduled') {
                                            $statusText = 'Missed';
                                        }
                                    }
                                    
                                    // Highlight today's appointments
                                    $rowClass = isset($appointment['scheduled_date']) && $appointment['scheduled_date'] === $today ? 'bg-blue-500/5' : '';
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td class="px-4 py-4">
                                        <?= isset($appointment['scheduled_date']) ? date('M j, Y', strtotime($appointment['scheduled_date'])) : 'N/A' ?>
                                        <?php if (isset($appointment['scheduled_date']) && $appointment['scheduled_date'] === $today): ?>
                                            <span class="ml-2 text-xs py-1 px-2 bg-blue-500/20 text-blue-400 rounded-full">Today</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?= isset($appointment['scheduled_time']) && !empty($appointment['scheduled_time']) ? 
                                            date('h:i A', strtotime($appointment['scheduled_time'])) : 'Not specified' ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php if (isset($appointment['child_id'])): ?>
                                        <a href="child_profile.php?id=<?= htmlspecialchars($appointment['child_id']) ?>" 
                                           class="text-blue-400 hover:text-blue-300 transition-colors">
                                            <?= htmlspecialchars($appointment['child_name'] ?? 'Unknown Child') ?>
                                        </a>
                                        <div class="text-xs text-gray-400 mt-1">
                                            <?= htmlspecialchars($appointment['guardian_name'] ?? 'N/A') ?> • 
                                            <?= htmlspecialchars($appointment['phone'] ?? 'No phone') ?>
                                        </div>
                                        <?php else: ?>
                                            <span class="text-gray-400">Unknown Child</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php if (empty($vaccineNames)): ?>
                                            <span class="text-gray-400">No vaccines scheduled</span>
                                        <?php else: ?>
                                            <div class="space-y-1">
                                                <?php foreach ($vaccineNames as $vaccine): ?>
                                                    <div class="text-sm"><?= htmlspecialchars($vaccine) ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="text-xs px-2 py-1 rounded-full <?= $statusClass ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex space-x-2">
                                            <?php if (isset($appointment['child_id'])): ?>
                                            <a href="child_profile.php?id=<?= htmlspecialchars($appointment['child_id']) ?>" 
                                               class="text-gray-400 hover:text-white transition-colors" 
                                               title="View Child Profile">
                                                <i class="fas fa-user-circle"></i>
                                            </a>
                                            <?php endif; ?>
                                            <?php if (isset($appointment['status']) && $appointment['status'] === 'scheduled'): ?>
                                                <a href="record_vaccination.php?appointment_id=<?= $appointment['id'] ?>" 
                                                   class="text-gray-400 hover:text-white transition-colors"
                                                   title="Record Vaccination">
                                                    <i class="fas fa-syringe"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Success Alert -->
    <?php if (isset($_SESSION['success'])): ?>
    <div id="successAlert" 
         class="fixed top-24 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg 
                animate__animated animate__fadeInRight flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span><?= $_SESSION['success'] ?></span>
    </div>
    <script>
        // Auto-hide the success message after 5 seconds
        setTimeout(() => {
            const alert = document.getElementById('successAlert');
            if (alert) {
                alert.classList.replace('animate__fadeInRight', 'animate__fadeOutRight');
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
    </script>
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Error Alert -->
    <?php if (isset($_SESSION['error'])): ?>
    <div id="errorAlert" 
         class="fixed top-24 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg 
                animate__animated animate__fadeInRight flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span><?= $_SESSION['error'] ?></span>
    </div>
    <script>
        // Auto-hide the error message after 7 seconds
        setTimeout(() => {
            const alert = document.getElementById('errorAlert');
            if (alert) {
                alert.classList.replace('animate__fadeInRight', 'animate__fadeOutRight');
                setTimeout(() => alert.remove(), 500);
            }
        }, 7000);
    </script>
    <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Mobile Toggle Button for Sidebar -->
    <button id="sidebar-mobile-toggle" class="fixed bottom-6 right-6 lg:hidden z-50 w-14 h-14 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-lg focus:outline-none">
        <i class="fas fa-bars text-lg"></i>
    </button>

    <script>
        // Make sidebar toggle work
        document.getElementById('sidebar-mobile-toggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('translate-x-0');
        });
    </script>
</body>
</html>
