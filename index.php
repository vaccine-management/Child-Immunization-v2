<?php
session_start();

// Ensure session user is set before using it
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
include 'backend/db.php';

// Get username from session
$userName = isset($_SESSION['user']['username']) && !empty($_SESSION['user']['username']) 
    ? $_SESSION['user']['username'] 
    : $_SESSION['user']['email'];

$userRole = $_SESSION['user']['role'] ?? 'User';

// Fetch dashboard statistics
$stmt = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM children) as total_children,
        (SELECT COUNT(*) FROM vaccinations WHERE scheduled_date >= CURDATE() AND status = 'Scheduled') as upcoming_vaccines,
        (SELECT COUNT(*) FROM vaccines) as total_vaccines
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch upcoming vaccinations with additional details
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

// Group vaccinations by date
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

// Get total required vaccines
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
unset($child);

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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <style>
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

        .calendar-event {
            transition: all 0.3s ease;
        }

        .calendar-event:hover {
            transform: translateX(4px);
        }
    </style>
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <!-- Welcome Message -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-6 rounded-lg shadow-lg mb-6">
            <h2 class="text-xl font-bold text-white">Welcome, <?php echo htmlspecialchars($userName); ?></h2>
            <p class="text-blue-200">You are logged in as a <?php echo htmlspecialchars($userRole); ?>.</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Total Children Card -->
            <div class="bg-gradient-to-br from-purple-600 to-purple-700 p-6 rounded-lg shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-purple-200 text-sm font-medium">Total Children</h3>
                        <p class="text-white text-2xl font-bold mt-1"><?php echo $stats['total_children']; ?></p>
                    </div>
                    <div class="bg-purple-500/20 rounded-lg p-3">
                        <svg class="w-8 h-8 text-purple-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Vaccines Available Card -->
            <div class="bg-gradient-to-br from-green-600 to-green-700 p-6 rounded-lg shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-green-200 text-sm font-medium">Available Vaccines</h3>
                        <p class="text-white text-2xl font-bold mt-1"><?php echo $stats['total_vaccines']; ?></p>
                    </div>
                    <div class="bg-green-500/20 rounded-lg p-3">
                        <svg class="w-8 h-8 text-green-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Upcoming Vaccinations Card -->
            <a href="upcoming_vaccinations.php">
                <div class="bg-gradient-to-br from-yellow-600 to-yellow-700 p-6 rounded-lg shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-yellow-200 text-sm font-medium">Upcoming Vaccinations</h3>
                            <p class="text-white text-2xl font-bold mt-1"><?php echo $stats['upcoming_vaccines']; ?></p>
                        </div>
                        <div class="bg-yellow-500/20 rounded-lg p-3">
                            <svg class="w-8 h-8 text-yellow-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6 w-auto">
            <!-- Vaccine Distribution Chart -->
            <div class="lg:col-span-2 bg-gray-800 rounded-lg shadow-lg p-2">
                <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                    </svg>
                    Vaccine Distribution
                </h3>
                <div class="w-6 h-64">
                    <canvas id="vaccineChart"></canvas>
                </div>
            </div>

            <!-- Calendar View -->
            <div class="bg-gray-100 rounded-lg shadow-lg p-6 mx-auto p-3">
                <h3 class="text-sm font-bold text-white mb-4 flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-1 h-5 mr-1 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" 
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-blue-800 text-sm">Vaccination Calendar</span>
                    </div>
                </h3>
                <div id="calendar"></div>
            </div>
        </div>

        <!-- Children List -->
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

            <div class="max-h-[calc(100vh-12rem)] overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-800 sticky top-0 z-10">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-400 
                                                 uppercase tracking-wider">Child Info</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-400 
                                                 uppercase tracking-wider">Gender</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-400 
                                                 uppercase tracking-wider">Age & Weight</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-400 
                                                 uppercase tracking-wider">Parent Details</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-400 
                                                 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php foreach ($registeredChildren as $child): ?>
                            <tr class="hover:bg-gray-700/50 transition duration-300 cursor-pointer"
                                onclick="window.location.href='child_profile.php?id=<?php echo $child['id']; ?>'">
                                <td class="px-6 py-4">
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
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full
                                        <?php echo $child['gender'] === 'Male' 
                                            ? 'bg-blue-500/10 text-blue-400' 
                                            : 'bg-pink-500/10 text-pink-400'; ?>">
                                        <?php echo $child['gender']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-white">
                                        <?php echo formatAge($child['age']); ?>
                                    </div>
                                    <div class="text-sm text-gray-400">
                                        <?php echo htmlspecialchars($child['weight']); ?> kg
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-white">
                                        <?php echo htmlspecialchars($child['parent_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-400">
                                        <?php echo htmlspecialchars($child['parent_phone']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
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
        // Vaccine Distribution Chart
        new Chart(document.getElementById('vaccineChart').getContext('2d'), {
            type: 'pie',
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
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#fff',
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });

        // Initialize FullCalendar
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [
                    <?php foreach ($upcomingVaccinations as $vaccination): ?>
                    {
                        title: '<?php echo htmlspecialchars($vaccination['full_name'] . " - " . $vaccination['vaccine_name']); ?>',
                        start: '<?php echo $vaccination['scheduled_date']; ?>',
                        description: '<?php echo htmlspecialchars($vaccination['notes'] ?? ''); ?>',
                        color: '<?php echo $vaccination['gender'] === 'Male' ? '#3b82f6' : '#ec4899'; ?>'
                    },
                    <?php endforeach; ?>
                ],
                eventContent: function(arg) {
                    return {
                        html: `
                            <div class="flex items-center p-1">
                                <div class="w-2 h-2 rounded-full mr-2" style="background-color: ${arg.event.backgroundColor};"></div>
                                <div class="text-sm">${arg.event.title}</div>
                            </div>
                        `
                    };
                },
                eventDidMount: function(arg) {
                    if (arg.event.extendedProps.description) {
                        new bootstrap.Tooltip(arg.el, {
                            title: arg.event.extendedProps.description,
                            placement: 'top',
                            trigger: 'hover',
                            container: 'body'
                        });
                    }
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>