<?php
// Start the session to manage user sessions
session_start();

// Ensure session user is set before using it
if (!isset($_SESSION['user'])) {
    // Redirect to login page if user is not logged in
    header('Location: login.php');
    exit();
}

// Include database connection file
include 'backend/db.php';

// Prepare and execute a query to fetch user details from the database
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

// Determine the username to display, fallback to email if username is not available
$userName = !empty($userDetails['username']) ? $userDetails['username'] : $userDetails['email'];

// Fetch dashboard statistics from the database
$stmt = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM children) as total_children,
        (SELECT COUNT(*) FROM vaccinations WHERE scheduled_date >= CURDATE() AND status = 'Scheduled') as upcoming_vaccines,
        (SELECT COUNT(*) FROM vaccines) as total_vaccines
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch upcoming vaccinations with additional details from the database
$stmt = $conn->query("
    SELECT 
        c.full_name,
        c.gender,
        c.age,
        v.vaccine_name,
        v.scheduled_date,
        COALESCE(v.scheduled_time, '00:00:00') as scheduled_time,
        COALESCE(v.notes, '') as notes
    FROM vaccinations v
    JOIN children c ON v.child_id = c.id
    WHERE v.status = 'Scheduled' 
        AND v.scheduled_date >= CURDATE()
    ORDER BY v.scheduled_date ASC, v.scheduled_time ASC
    LIMIT 30
");
$upcomingVaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group vaccinations by date for easier display
$groupedVaccinations = [];
foreach ($upcomingVaccinations as $vaccination) {
    $date = $vaccination['scheduled_date'];
    if (!isset($groupedVaccinations[$date])) {
        $groupedVaccinations[$date] = [];
    }
    $groupedVaccinations[$date][] = $vaccination;
}


// Fetch vaccine distribution data for the chart
$stmt = $conn->query("
    SELECT vaccine_name, COUNT(*) as count 
    FROM vaccinations 
    WHERE status = 'Taken' 
    GROUP BY vaccine_name
");
$vaccineDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch list of registered children with vaccination status
$stmt = $conn->query("
    SELECT DISTINCT 
        c.id, 
        c.full_name, 
        c.age, 
        c.weight, 
        c.gender, 
        c.parent_name, 
        c.parent_phone,
        (SELECT COUNT(*) FROM vaccinations v WHERE v.child_id = c.id AND v.status = 'Taken') as taken_vaccines
    FROM children c
    ORDER BY c.id DESC
");
$registeredChildren = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total required vaccines from the database
$stmt = $conn->query("SELECT COUNT(*) as total FROM vaccines");
$totalRequiredVaccines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate vaccination status for each child
foreach ($registeredChildren as &$child) {
    $takenVaccines = $child['taken_vaccines'];
    if ($takenVaccines == $totalRequiredVaccines) {
        $child['vaccination_status'] = 'Fully Vaccinated';
    } elseif ($takenVaccines > 0) {
        $child['vaccination_status'] = 'Partially Vaccinated';
    } else {
        $child['vaccination_status'] = 'Not Vaccinated';
    }
}
unset($child); // Break the reference with the last element

// Function to format age in months or years
function formatAge($age) {
    if ($age < 12) {
        return $age . " month" . ($age > 1 ? "s" : "");
    }
    $years = floor($age / 12);
    return $years . " year" . ($years > 1 ? "s" : "");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Immunization System</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/animations/scale.css"/>
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
    <style>
        /* Base Styles */
        body {
            font-family: 'Inter', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }
        
        /* Card Styles */
        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        /* Scrollbar Styles */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(31, 41, 55, 0.5);
            border-radius: 4px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.5);
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(59, 130, 246, 0.7);
        }
        
        /* Update the chart container styles */
        .chart-container {
            background: rgba(31, 41, 55, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            min-height: 450px; /* Increased height */
            height: 450px; /* Match calendar height */
            position: relative;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }
        
        .chart-container canvas {
            width: 100% !important;
            height: calc(100% - 50px) !important; /* Adjust for title */
            max-width: 100%; /* Remove max-width limitation */
            max-height: 100%; /* Remove max-height limitation */
        }
        
        /* Add header toolbar style for charts to match calendar */
        .chart-header {
            background: rgba(31, 41, 55, 0.5);
            padding: 0.75rem !important;
            border-radius: 0.5rem;
            margin-bottom: 1rem !important;
            width: 100%;
        }
        
        .chart-title {
            color: white !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* FullCalendar Customization */
        .fc {
            --fc-small-font-size: .85em;
            --fc-page-bg-color: transparent;
            --fc-neutral-bg-color: rgba(255, 255, 255, 0.05);
            --fc-list-event-hover-bg-color: rgba(255, 255, 255, 0.1);
            --fc-today-bg-color: rgba(59, 130, 246, 0.1) !important;
            --fc-border-color: rgba(255, 255, 255, 0.1);
            --fc-button-text-color: #fff;
            --fc-button-bg-color: rgba(59, 130, 246, 0.1);
            --fc-button-border-color: rgba(59, 130, 246, 0.2);
            --fc-button-hover-bg-color: rgba(59, 130, 246, 0.2);
            --fc-button-hover-border-color: rgba(59, 130, 246, 0.3);
            --fc-button-active-bg-color: rgba(59, 130, 246, 0.3);
            font-family: 'Inter', sans-serif;
            height: 100% !important;
        }
        
        .fc-theme-standard th,
        .fc-theme-standard td {
            border-color: var(--fc-border-color);
        }
        
        .fc-header-toolbar {
            background: rgba(31, 41, 55, 0.5);
            padding: 0.75rem !important;
            border-radius: 0.5rem;
            margin-bottom: 1rem !important;
        }
        
        .fc-toolbar-title {
            color: white !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .fc-button {
            padding: 0.3rem 0.6rem !important;
            border-radius: 0.375rem !important;
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            transition: all 0.2s !important;
        }
        
        .fc-button-active {
            background: rgba(59, 130, 246, 0.3) !important;
            border-color: rgba(59, 130, 246, 0.4) !important;
        }
        
        .fc-day-today {
            background: var(--fc-today-bg-color) !important;
        }
        
        .fc-daygrid-day-number,
        .fc-col-header-cell-cushion {
            color: white;
            padding: 0.3rem 0.5rem !important;
            font-size: 0.75rem !important;
        }
        
        .fc-daygrid-day-frame {
            padding: 0.25rem !important;
            min-height: 60px !important;
        }
        
        .fc-day-other .fc-daygrid-day-number {
            color: rgba(255, 255, 255, 0.4) !important;
        }
        
        .vaccination-event {
            margin: 1px 2px !important;
            padding: 2px 4px !important;
            font-size: 0.7rem !important;
            border-radius: 0.25rem !important;
            background: rgba(59, 130, 246, 0.2) !important;
            border: none !important;
            transition: transform 0.2s;
        }
        
        .vaccination-event:hover {
            transform: translateY(-1px);
        }
        
        /* Tippy Theme Customization */
        .tippy-box[data-theme~='dark'] {
            background-color: rgba(17, 24, 39, 0.95);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
        
        .tippy-box[data-theme~='dark'] .tippy-arrow {
            color: rgba(17, 24, 39, 0.95);
        }
        
        .tippy-box[data-theme~='dark'] .tippy-content {
            padding: 0.75rem;
        }
        
        /* Calendar Event Hover Effect */
        .fc-event-main {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .fc-event-main:hover {
            transform: scale(1.05);
        }
        .calendar-wrapper {
    background: rgba(31, 41, 55, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    min-height: 450px;
    height: 450px;
    position: relative;
    display: flex;
    flex-direction: column;
}
        #calendar {
    flex: 1;
    min-height: 0; 
}
        /* Additional Calendar Responsive Adjustments */
        @media (max-width: 640px) {
            .fc-header-toolbar {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .fc-toolbar-title {
                font-size: 0.875rem !important;
            }
            
            .fc-button {
                padding: 0.25rem 0.5rem !important;
                font-size: 0.7rem !important;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 min-h-screen">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    
    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6 space-y-6">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-xl p-8">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-2">Welcome back, <?php echo htmlspecialchars($userName); ?>!</h2>
                    <p class="text-blue-100 text-lg">Here's what's happening with your immunization program today.</p>
                </div>
                <div class="hidden md:block">
                    <svg class="w-32 h-32 text-white/20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 100-12 6 6 0 000 12z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Total Children Card -->
            <div class="stat-card rounded-2xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-400 text-sm font-semibold uppercase tracking-wider">Total Children</p>
                        <h3 class="text-white text-3xl font-bold mt-2"><?php echo $stats['total_children']; ?></h3>
                        <p class="text-gray-400 text-sm mt-2">Registered in the system</p>
                    </div>
                    <div class="bg-blue-500/10 p-3 rounded-xl">
                        <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Vaccines Available Card -->
            <div class="stat-card rounded-2xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-emerald-400 text-sm font-semibold uppercase tracking-wider">Available Vaccines</p>
                        <h3 class="text-white text-3xl font-bold mt-2"><?php echo $stats['total_vaccines']; ?></h3>
                        <p class="text-gray-400 text-sm mt-2">Types of vaccines</p>
                    </div>
                    <div class="bg-emerald-500/10 p-3 rounded-xl">
                        <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Upcoming Vaccinations Card -->
            <div class="stat-card rounded-2xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-amber-400 text-sm font-semibold uppercase tracking-wider">Upcoming</p>
                        <h3 class="text-white text-3xl font-bold mt-2"><?php echo $stats['upcoming_vaccines']; ?></h3>
                        <p class="text-gray-400 text-sm mt-2">Scheduled vaccinations</p>
                    </div>
                    <div class="bg-amber-500/10 p-3 rounded-xl">
                        <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Calendar Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Vaccine Distribution Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                        </svg>
                        Vaccine Distribution
                    </h3>
                </div>
                <div class="flex-1">
                    <canvas id="vaccineChart"></canvas>
                </div>
            </div>
            
            <!-- Gender Distribution Card -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        Gender Distribution
                    </h3>
                </div>
                <div class="flex-1">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>

            <!-- Calendar -->
            <div class="calendar-wrapper p-6">
                <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Vaccination Calendar
                </h3>
                <div id="calendar"></div>
            </div>
        </div>

        <!-- Registered Children Section -->
        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="p-4 border-b border-gray-700">
                <h3 class="text-lg font-bold text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Registered Children
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-800 sticky top-0 z-10">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Child Info
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Gender
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Age & Weight
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Parent Details
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php foreach ($registeredChildren as $child): ?>
                            <tr class="hover:bg-gray-700/50 transition duration-300 cursor-pointer"
                                onclick="window.location.href='child_profile.php?id=<?php echo $child['id']; ?>'">
                                <!-- Child Info -->
                                <td class="px-4 py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full 
                                                <?php echo $child['gender'] === 'Male' 
                                                    ? 'bg-blue-500/10' 
                                                    : 'bg-pink-500/10'; ?> 
                                                flex items-center justify-center">
                                                <svg class="w-6 h-6 <?php echo $child['gender'] === 'Male' 
                                                    ? 'text-blue-500' 
                                                    : 'text-pink-500'; ?>" 
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" 
                                                        stroke-width="2" 
                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-white">
                                                <?php echo htmlspecialchars($child['full_name']); ?>
                                            </div>
                                            <div class="text-xs text-gray-400">
                                                ID: #<?php echo $child['id']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Gender -->
                                <td class="px-4 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full
                                        <?php echo $child['gender'] === 'Male' 
                                            ? 'bg-blue-500/10 text-blue-400' 
                                            : 'bg-pink-500/10 text-pink-400'; ?>">
                                        <?php echo $child['gender']; ?>
                                    </span>
                                </td>

                                <!-- Age & Weight -->
                                <td class="px-4 py-4">
                                    <div class="text-sm text-white">
                                        <?php echo formatAge($child['age']); ?>
                                    </div>
                                    <div class="text-sm text-gray-400">
                                        <?php echo htmlspecialchars($child['weight']); ?> kg
                                    </div>
                                </td>

                                <!-- Parent Details -->
                                <td class="px-4 py-4">
                                    <div class="text-sm text-white">
                                        <?php echo htmlspecialchars($child['parent_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-400">
                                        <?php echo htmlspecialchars($child['parent_phone']); ?>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full
                                        <?php
                                        switch($child['vaccination_status']) {
                                            case 'Fully Vaccinated':
                                                echo 'bg-green-500/10 text-green-400';
                                                break;
                                            case 'Partially Vaccinated':
                                                echo 'bg-yellow-500/10 text-yellow-400';
                                                break;
                                            default:
                                                echo 'bg-red-500/10 text-red-400';
                                        }
                                        ?>">
                                        <?php echo $child['vaccination_status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Initialize Charts -->
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare events for the calendar
    const eventsByDate = <?php echo json_encode(array_map(function($date, $vaccinations) {
        return [
            'count' => count($vaccinations), // Number of vaccines scheduled for this date
            'details' => array_map(function($vaccination) {
                return [
                    'vaccine' => $vaccination['vaccine_name'],
                    'time' => $vaccination['scheduled_time']
                ];
            }, $vaccinations)
        ];
    }, array_keys($groupedVaccinations), array_values($groupedVaccinations))); ?>;

    // Initialize Vaccine Distribution Chart
    const vaccineChart = new Chart(document.getElementById('vaccineChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($vaccineDistribution, 'vaccine_name')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($vaccineDistribution, 'count')); ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(167, 139, 250, 0.8)'
                ],
                borderWidth: 2,
                borderColor: 'rgba(255, 255, 255, 0.1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right', 
                    labels: {
                        color: '#fff',
                        padding: 20,
                        font: {
                            size: 12,
                            family: 'Inter'
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });

    // Initialize Gender Distribution Chart
    const genderData = {
        male: <?php echo array_count_values(array_column($registeredChildren, 'gender'))['Male'] ?? 0; ?>,
        female: <?php echo array_count_values(array_column($registeredChildren, 'gender'))['Female'] ?? 0; ?>
    };

    const genderChart = new Chart(document.getElementById('genderChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Male', 'Female'],
            datasets: [{
                data: [genderData.male, genderData.female],
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(236, 72, 153, 0.8)'
                ],
                borderWidth: 2,
                borderColor: 'rgba(255, 255, 255, 0.1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right', 
                    labels: {
                        color: '#fff',
                        padding: 20,
                        font: {
                            size: 12,
                            family: 'Inter'
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });

    // Initialize Calendar
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 400,
        headerToolbar: {
            left: 'prev,next',
            center: 'title',
            right: 'today'
        },
        titleFormat: { 
            month: 'short',
            year: 'numeric'
        },
        dayHeaderFormat: {
            weekday: 'narrow'
        },
        events: Object.entries(eventsByDate).map(([date, data]) => ({
            title: `${data.count} Vaccines`, // Display the count of vaccines
            start: date,
            extendedProps: {
                details: data.details
            },
            className: 'vaccination-event',
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            borderColor: 'rgba(59, 130, 246, 0.2)'
        })),
        eventContent: function(arg) {
            return {
                html: `<div class="text-xs font-semibold text-center">${arg.event.title}</div>`
            };
        },
        eventDidMount: function(info) {
            tippy(info.el, {
                content: `<div class="p-2">
                    <div class="font-semibold mb-1">${info.event.title}</div>
                    ${info.event.extendedProps.details.map(d => 
                        `<div class="text-xs">${d.vaccine} - ${d.time}</div>`
                    ).join('')}
                </div>`,
                allowHTML: true,
                theme: 'dark',
                placement: 'top',
                interactive: true,
                maxWidth: 300
            });
        }
    });

    calendar.render();
});
</script>
</body>
</html>