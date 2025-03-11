<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
include 'backend/db.php';
// Include the vaccine helper functions
include 'backend/vaccine_helpers.php';

// Prepare and execute a query to fetch user details from the database
try {
    // Use username from the database as confirmed by the user
    $stmt = $conn->prepare("SELECT id, email, username FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Use username as the display name, fallback to email if username is not available
    if (!empty($userDetails['username'])) {
        $displayName = $userDetails['username'];
    } else {
        $displayName = $userDetails['email'];
    }
    
    // Store in session for future use
    $_SESSION['user']['display_name'] = $displayName;
    
} catch (PDOException $e) {
    // In case of database error, fall back to email
    $displayName = $_SESSION['user']['email'];
}
// Try to get the user's full name from the database if available
$userName = "User"; // Default value
if (isset($_SESSION['user']['id'])) {
    try {
        $userId = $_SESSION['user']['id'];
        $userStmt = $conn->prepare("SELECT username, email, full_name FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userData) {
            $userName = !empty($userData['full_name']) ? $userData['full_name'] : 
                        (!empty($userData['username']) ? $userData['username'] : 
                        (!empty($userData['email']) ? $userData['email'] : "User"));
        }
    } catch (Exception $e) {
        // If there's an error, use what's in the session
        if (isset($_SESSION['user']['username'])) {
            $userName = $_SESSION['user']['username'];
        } elseif (isset($_SESSION['user']['email'])) {
            $userName = $_SESSION['user']['email'];
        }
    }
} else {
    // Fallback to session values if no ID
    if (isset($_SESSION['user']['username'])) {
        $userName = $_SESSION['user']['username'];
    } elseif (isset($_SESSION['user']['email'])) {
        $userName = $_SESSION['user']['email'];
    }
}

// Get current date and time for welcome message
$currentHour = date('G');
$greeting = "Good morning";
if ($currentHour >= 12 && $currentHour < 18) {
    $greeting = "Good afternoon";
} elseif ($currentHour >= 18) {
    $greeting = "Good evening";
}

// Add success message if user just logged in
$showLoginSuccess = isset($_SESSION['login_success']) && $_SESSION['login_success'] === true;
if ($showLoginSuccess) {
    unset($_SESSION['login_success']); 
}

// Fetch total registered children grouped by age
$ageGroupQuery = "
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) < 12 THEN '0-1 year'
            WHEN TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) < 24 THEN '1-2 years'
            WHEN TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) < 36 THEN '2-3 years'
            ELSE '3+ years'
        END as age_group,
        COUNT(*) as count
    FROM children
    GROUP BY age_group
";
$ageGroupResult = $conn->query($ageGroupQuery)->fetchAll(PDO::FETCH_ASSOC);

// Use the helper function to fetch upcoming appointments
$upcomingAppointments = getUpcomingAppointments(date('Y-m-d'), date('Y-m-d', strtotime('+7 days')));

// Convert appointments to the format expected by the component
$upcomingVaccines = [];
foreach ($upcomingAppointments as $appointment) {
    // Get vaccines for this appointment
    $appointmentVaccines = getAppointmentVaccines($appointment['id']);
    
    foreach ($appointmentVaccines as $vaccine) {
        $upcomingVaccines[] = [
            'child_id' => $appointment['child_id'],
            'full_name' => $appointment['child_name'],
            'vaccine_name' => $vaccine['vaccine_name'],
            'scheduled_date' => $appointment['scheduled_date'],
            'status' => $appointment['status']
        ];
    }
}

// Fetch gender distribution
$genderQuery = "SELECT gender, COUNT(*) as count FROM children GROUP BY gender";
$genderDistribution = $conn->query($genderQuery)->fetchAll(PDO::FETCH_ASSOC);

// Fetch registered children for the table
$childrenQuery = "
    SELECT child_id, full_name, date_of_birth, gender, guardian_name, phone 
    FROM children 
    ORDER BY registration_date DESC
";
$registeredChildren = $conn->query($childrenQuery)->fetchAll(PDO::FETCH_ASSOC);

// Get vaccine inventory using the helper function
$inventoryItems = getAllInventory();

// Format inventory data for the dashboard
$vaccineStock = [];
foreach ($inventoryItems as $item) {
    // If we already have this vaccine in the array, add to its quantity
    $found = false;
    foreach ($vaccineStock as &$stock) {
        if ($stock['vaccine_name'] === $item['vaccine_name']) {
            $stock['quantity'] += $item['quantity'];
            $found = true;
            break;
        }
    }
    
    // If not found, add a new entry
    if (!$found) {
        $vaccineStock[] = [
            'vaccine_name' => $item['vaccine_name'],
            'quantity' => $item['quantity']
        ];
    }
}

// If no inventory data yet, use sample data
if (empty($vaccineStock)) {
    $vaccineStock = [
        ['vaccine_name' => 'BCG', 'quantity' => 40],
        ['vaccine_name' => 'OPV', 'quantity' => 32],
        ['vaccine_name' => 'DPT', 'quantity' => 8],
        ['vaccine_name' => 'Measles', 'quantity' => 25],
        ['vaccine_name' => 'Hepatitis B', 'quantity' => 15],
        ['vaccine_name' => 'Rotavirus', 'quantity' => 12]
    ];
}

// Calculate total children count
$totalChildren = array_sum(array_column($ageGroupResult, 'count'));

// Calculate upcoming appointments count
$upcomingCount = count(array_filter($upcomingVaccines, function($v) {
    return $v['scheduled_date'] >= date('Y-m-d');
}));

// Calculate missed appointments count
$missedCount = count(array_filter($upcomingVaccines, function($v) {
    return $v['scheduled_date'] < date('Y-m-d');
}));

// Low stock count
$lowStockCount = count(array_filter($vaccineStock, function($v) {
    return $v['quantity'] < 10;
}));

// Prepare data for JavaScript
$vaccineNames = array_column($vaccineStock, 'vaccine_name');
$stockLevels = array_column($vaccineStock, 'quantity');
$ageGroups = array_column($ageGroupResult, 'age_group');
$ageCounts = array_column($ageGroupResult, 'count');

// Prepare calendar events
$calendarEvents = [];
foreach ($upcomingVaccines as $vaccination) {
    $calendarEvents[] = [
        'title' => $vaccination['full_name'] . ' - ' . $vaccination['vaccine_name'],
        'start' => $vaccination['scheduled_date'],
        'color' => $vaccination['status'] === 'Completed' ? '#10B981' : 
                  ($vaccination['scheduled_date'] < date('Y-m-d') ? '#EF4444' : '#3B82F6')
    ];
}
?>

<?php include 'includes/header.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<!-- Add Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Add animate.css for animations -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
<!-- Add custom fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="dashboard/css/dashboard.css">
<!-- Navbar and Sidebar -->

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
    <div class="p-4 sm:p-6 lg:p-8">
        <?php include 'dashboard/dashboard_main.php'; ?>
    </div>
</main>

<!-- Success Notification -->
<?php if ($showLoginSuccess): ?>
<div id="loginSuccessAlert" 
     class="fixed top-24 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg 
            animate__animated animate__fadeInRight flex items-center space-x-2">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span>Login successful! Welcome to the Child Immunization System.</span>
</div>

<script>
    // Auto-hide the success message after 5 seconds
    setTimeout(() => {
        const alert = document.getElementById('loginSuccessAlert');
        if (alert) {
            alert.classList.replace('animate__fadeInRight', 'animate__fadeOutRight');
            setTimeout(() => alert.remove(), 500);
        }
    }, 5000);
</script>
<?php endif; ?>

<!-- Pass data to JavaScript -->
<script>
    // Data for charts and calendar
    const vaccineNames = <?php echo json_encode($vaccineNames); ?>;
    const stockLevels = <?php echo json_encode($stockLevels); ?>;
    const ageGroups = <?php echo json_encode($ageGroups); ?>;
    const ageCounts = <?php echo json_encode($ageCounts); ?>;
    const calendarEvents = <?php echo json_encode($calendarEvents); ?>;
</script>

<!-- Include JavaScript files -->
<script src="dashboard/js/stock_chart.js"></script>
<script src="dashboard/js/age_chart.js"></script>
<script src="dashboard/js/calendar.js"></script>
<script src="dashboard/js/sidebar_responsive.js"></script>
<script src="dashboard/dashboard_init.js"></script> 