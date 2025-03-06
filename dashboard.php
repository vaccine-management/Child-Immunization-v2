<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
include 'backend/db.php';

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

// Fetch upcoming vaccines and missed appointments
$upcomingQuery = "
    SELECT c.full_name, v.vaccine_name, v.scheduled_date, v.status
    FROM vaccinations v
    JOIN children c ON v.child_id = c.child_id
    WHERE v.scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    OR (v.scheduled_date < CURDATE() AND v.status = 'Scheduled')
    ORDER BY v.scheduled_date
    LIMIT 10
";
$upcomingVaccines = $conn->query($upcomingQuery)->fetchAll(PDO::FETCH_ASSOC);

// Fetch gender distribution
$genderQuery = "SELECT gender, COUNT(*) as count FROM children GROUP BY gender";
$genderDistribution = $conn->query($genderQuery)->fetchAll(PDO::FETCH_ASSOC);

// Sample vaccine stock data (since table doesn't exist yet)
$vaccineStock = [
    ['vaccine_name' => 'BCG', 'quantity' => 400],
    ['vaccine_name' => 'OPV', 'quantity' => 32],
    ['vaccine_name' => 'DPT', 'quantity' => 8],
    ['vaccine_name' => 'Measles', 'quantity' => 25],
    ['vaccine_name' => 'Hepatitis B', 'quantity' => 15],
    ['vaccine_name' => 'Rotavirus', 'quantity' => 12]
];

// Fetch registered children for the table
$childrenQuery = "
    SELECT child_id, full_name, date_of_birth, gender, guardian_name, phone 
    FROM children 
    ORDER BY registration_date DESC
";
$registeredChildren = $conn->query($childrenQuery)->fetchAll(PDO::FETCH_ASSOC);

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
<!-- Add custom fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #111827;
    }
    .dashboard-card {
        background-color: #1F2937;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
    }
    .dashboard-card:hover {
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
        transform: translateY(-2px);
    }
    .dashboard-stat-card {
        background-color: #1F2937;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
    }
    .dashboard-stat-card:hover {
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
        transform: translateY(-3px);
    }
    .welcome-card {
        background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
        border-radius: 12px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
    }
    .fc-theme-standard {
        font-family: 'Inter', sans-serif;
    }
    .fc-theme-standard th, .fc-theme-standard td {
        border-color: rgba(55, 65, 81, 0.5);
    }
    .fc-theme-standard .fc-day {
        background-color: #1F2937;
    }
    .fc-theme-standard .fc-day-today {
        background-color: rgba(59, 130, 246, 0.15);
    }
    .fc-theme-standard .fc-daygrid-day-number {
        color: #E5E7EB;
        padding: 4px;
        font-size: 0.75rem;
    }
    .fc .fc-daygrid-day-top {
        justify-content: center;
    }
    .progress-bar {
        height: 8px;
        background-color: #374151;
        border-radius: 4px;
        overflow: hidden;
    }
    .progress-value {
        height: 100%;
        border-radius: 4px;
    }
    /* Make calendar more compact */
    .fc .fc-toolbar {
        margin-bottom: 0.5rem;
    }
    .fc .fc-toolbar-title {
        font-size: 1rem;
        color: #E5E7EB;
    }
    .fc .fc-toolbar-chunk {
        display: flex;
        align-items: center;
    }
    .fc .fc-button {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        background-color: #374151;
        border: 1px solid #4B5563;
        color: #E5E7EB;
    }
    .fc .fc-button-primary {
        background-color: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }
    .fc .fc-scrollgrid-liquid {
        height: auto !important;
    }
    .fc-daygrid-event {
        padding: 1px !important;
        font-size: 0.7rem !important;
    }
    /* Improve responsiveness */
    @media (max-width: 1280px) {
        .card-grid-container {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 768px) {
        .card-grid-container {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
    }
    
    /* Custom Calendar Styles to match Medica design */
    .custom-calendar {
        background-color: #1F2937;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        color: aliceblue;
    }
    .custom-calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        background-color: #111827;
        border-bottom: 1px solid #374151;
        color: #ffffff;
    }
    .custom-calendar-title {
        color: #E5E7EB;
        font-weight: 600;
        font-size: 1rem;
    }
    .custom-calendar-nav {
        display: flex;
        gap: 10px;
        color: #ffffff;
    }
    .custom-calendar-btn {
        background-color: #374151;
        border: 1px solid #4B5563;
        color: #9CA3AF;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .custom-calendar-btn:hover {
        background-color: #4B5563;
        color: #E5E7EB;
    }
    .custom-calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        padding: 10px;
        gap: 8px;
    }
    .custom-calendar-weekday {
        color: #9CA3AF;
        font-size: 0.7rem;
        text-align: center;
        font-weight: 500;
        text-transform: uppercase;
        padding: 5px 0;
    }
    .custom-calendar-day {
        aspect-ratio: 1/1;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        position: relative;
        cursor: pointer;
        font-size: 0.85rem;
        color: #E5E7EB;
        transition: all 0.2s;
    }
    .custom-calendar-day:hover {
        background-color: #374151;
    }
    .custom-calendar-day.other-month {
        color: #6B7280;
    }
    .custom-calendar-day.today {
        background-color: rgba(59, 130, 246, 0.2);
        font-weight: 600;
        color: #3B82F6;
    }
    .custom-calendar-day.has-events {
        font-weight: 600;
    }
    .custom-calendar-day.has-events::after {
        content: '';
    }
</style>

<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
    <div class="p-4 sm:p-6 lg:p-8">
        <?php include 'dashboard/dashboard_main.php'; ?>
    </div>
</main>

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