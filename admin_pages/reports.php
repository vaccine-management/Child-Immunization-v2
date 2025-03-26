<?php
// Define root path for includes
define('ROOT_PATH', dirname(__FILE__) . '/../');

// Include the auth check file
require_once ROOT_PATH . 'includes/auth_check.php';

// Only allow admins to access this page
checkAdminRole();

// Include database connection
require_once ROOT_PATH . 'backend/db.php';

try {
    // Fetch statistics for charts
    // 1. Vaccination Status Count
    $vaccineStatusQuery = "SELECT 
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled,
        COUNT(CASE WHEN status = 'missed' THEN 1 END) as missed
    FROM vaccinations";
    $vaccineStatusStmt = $conn->query($vaccineStatusQuery);
    $vaccineStatus = $vaccineStatusStmt->fetch(PDO::FETCH_ASSOC);

    // Add Appointment Status Count
    $appointmentStatusQuery = "SELECT 
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled,
        COUNT(CASE WHEN status = 'missed' THEN 1 END) as missed,
        COUNT(CASE WHEN status = 'partially_completed' THEN 1 END) as partial,
        COUNT(CASE WHEN status = 'rescheduled' THEN 1 END) as rescheduled
    FROM appointments";
    $appointmentStatusStmt = $conn->query($appointmentStatusQuery);
    $appointmentStatus = $appointmentStatusStmt->fetch(PDO::FETCH_ASSOC);

    // 2. Age Distribution
    $ageDistQuery = "SELECT 
        COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 1 THEN 1 END) as under_one,
        COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 1 AND 2 THEN 1 END) as one_to_two,
        COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 2 AND 3 THEN 1 END) as two_to_three,
        COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) > 3 THEN 1 END) as above_three
    FROM children";
    $ageDistStmt = $conn->query($ageDistQuery);
    $ageDist = $ageDistStmt->fetch(PDO::FETCH_ASSOC);

    // 3. Monthly Appointments and Vaccinations (Last 6 months)
    $monthlyStatsQuery = "SELECT 
        DATE_FORMAT(a.scheduled_date, '%b') as month,
        COUNT(DISTINCT a.id) as appointment_count,
        COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as vaccination_count
    FROM appointments a
    WHERE a.scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY MONTH(a.scheduled_date), DATE_FORMAT(a.scheduled_date, '%b')
    ORDER BY a.scheduled_date DESC
    LIMIT 6";
    $monthlyStatsStmt = $conn->query($monthlyStatsQuery);
    $monthlyStats = $monthlyStatsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent reports
    $reportsQuery = "SELECT 
        r.*,
        u.username as generated_by_name
    FROM generated_reports r
    LEFT JOIN users u ON r.generated_by = u.id
    ORDER BY r.generated_date DESC 
    LIMIT 10";
    $reportsStmt = $conn->query($reportsQuery);
    $reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Handle query errors
    die("Error: " . $e->getMessage());
}

// Page title
$pageTitle = "Reports & Analytics";
?>

<?php require_once ROOT_PATH . 'includes/header.php'; ?>

<!-- Add necessary stylesheets and scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php require_once ROOT_PATH . 'includes/navbar.php'; ?>
<?php require_once ROOT_PATH . 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
    <div class="p-4 sm:p-6 lg:p-8">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">Reports & Analytics</h1>
            <p class="text-gray-400">View and generate reports for the immunization system</p>
        </div>

        <!-- Quick Actions -->
        <div class="mb-8 grid grid-cols-1 md:grid-cols-3 gap-4">
            <button onclick="generateReport('children')" class="dashboard-card p-4 flex items-center justify-between hover:bg-gray-800/80">
                <div class="flex items-center">
                    <i class="fas fa-child text-blue-400 text-xl mr-3"></i>
                    <span class="text-white">Export Children Data</span>
                </div>
                <i class="fas fa-download text-gray-400"></i>
            </button>

            <button onclick="generateReport('staff')" class="dashboard-card p-4 flex items-center justify-between hover:bg-gray-800/80">
                <div class="flex items-center">
                    <i class="fas fa-user-nurse text-green-400 text-xl mr-3"></i>
                    <span class="text-white">Export Staff List</span>
                </div>
                <i class="fas fa-download text-gray-400"></i>
            </button>

            <button onclick="generateReport('vaccines')" class="dashboard-card p-4 flex items-center justify-between hover:bg-gray-800/80">
                <div class="flex items-center">
                    <i class="fas fa-syringe text-purple-400 text-xl mr-3"></i>
                    <span class="text-white">Export Vaccine Records</span>
                </div>
                <i class="fas fa-download text-gray-400"></i>
            </button>
        </div>
        
        <!-- Reports Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <!-- Vaccination Status Report -->
            <div class="dashboard-card p-5">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-white flex items-center">
                        <i class="fas fa-syringe text-blue-500 mr-2"></i>
                        Vaccination Status
                    </h2>
                </div>
                <div class="h-56">
                    <canvas id="vaccinationStatusChart"></canvas>
                </div>
            </div>
            
            <!-- Appointment Status Report -->
            <div class="dashboard-card p-5">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-white flex items-center">
                        <i class="fas fa-calendar-check text-green-500 mr-2"></i>
                        Appointment Status
                    </h2>
                </div>
                <div class="h-56">
                    <canvas id="appointmentStatusChart"></canvas>
                </div>
            </div>
            
            <!-- Monthly Statistics -->
            <div class="dashboard-card p-5">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-white flex items-center">
                        <i class="fas fa-chart-line text-purple-500 mr-2"></i>
                        Monthly Statistics
                    </h2>
                </div>
                <div class="h-56">
                    <canvas id="monthlyStatsChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Generated Reports Table -->
        <div class="mt-8">
            <h2 class="text-xl font-semibold text-white mb-4">Recent Reports</h2>
            
            <div class="dashboard-card p-5">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-800/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Report Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Generated By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date Generated</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-800/30 divide-y divide-gray-700">
                            <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-sm text-gray-400 text-center">No reports generated yet</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($reports as $report): ?>
                            <tr class="hover:bg-gray-700/50 transition duration-150">
                                <td class="px-4 py-3 text-sm text-white">
                                        <?php echo htmlspecialchars($report['report_name']); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                        <?php echo htmlspecialchars($report['generated_by_name']); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                        <?php echo date('M d, Y', strtotime($report['generated_date'])); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php echo $report['type'] === 'PDF' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo htmlspecialchars($report['type']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-300">
                                    <div class="flex space-x-2">
                                        <a href="../download_report.php?id=<?php echo $report['id']; ?>" class="text-green-600 hover:text-green-900 mr-3">
                                            <i class="fas fa-download"></i> Download
                                        </a>
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
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Vaccination Status Chart
        const vaccinationCtx = document.getElementById('vaccinationStatusChart').getContext('2d');
        new Chart(vaccinationCtx, {
            type: 'pie',
            data: {
                labels: ['Completed', 'Scheduled', 'Missed'],
                datasets: [{
                data: [
                    <?php echo $vaccineStatus['completed'] ?? 0; ?>,
                    <?php echo $vaccineStatus['scheduled'] ?? 0; ?>,
                    <?php echo $vaccineStatus['missed'] ?? 0; ?>
                ],
                    backgroundColor: [
                        'rgba(16, 185, 129, 0.7)',  // Green
                        'rgba(59, 130, 246, 0.7)',  // Blue
                        'rgba(239, 68, 68, 0.7)'    // Red
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#E5E7EB',
                        font: { family: 'Inter', size: 12 },
                            padding: 20
                    }
                    }
                }
            }
        });
        
    // Appointment Status Chart
    const appointmentCtx = document.getElementById('appointmentStatusChart').getContext('2d');
    new Chart(appointmentCtx, {
        type: 'pie',
            data: {
            labels: ['Completed', 'Scheduled', 'Partially Completed', 'Missed', 'Rescheduled'],
                datasets: [{
                data: [
                    <?php echo $appointmentStatus['completed'] ?? 0; ?>,
                    <?php echo $appointmentStatus['scheduled'] ?? 0; ?>,
                    <?php echo $appointmentStatus['partial'] ?? 0; ?>,
                    <?php echo $appointmentStatus['missed'] ?? 0; ?>,
                    <?php echo $appointmentStatus['rescheduled'] ?? 0; ?>
                ],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.7)',  // Green
                    'rgba(59, 130, 246, 0.7)',  // Blue
                    'rgba(245, 158, 11, 0.7)',  // Orange
                    'rgba(239, 68, 68, 0.7)',   // Red
                    'rgba(139, 92, 246, 0.7)'   // Purple
                ],
                borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                    position: 'bottom',
                    labels: {
                        color: '#E5E7EB',
                        font: { family: 'Inter', size: 12 },
                        padding: 20
                    }
                    }
                }
            }
        });
        
    // Monthly Statistics Chart
    const statsCtx = document.getElementById('monthlyStatsChart').getContext('2d');
    new Chart(statsCtx, {
            type: 'line',
            data: {
            labels: <?php echo !empty($monthlyStats) ? json_encode(array_column($monthlyStats, 'month')) : '[]'; ?>,
                datasets: [{
                label: 'Completed Appointments',
                data: <?php echo !empty($monthlyStats) ? json_encode(array_column($monthlyStats, 'appointment_count')) : '[]'; ?>,
                borderColor: 'rgba(124, 58, 237, 1)',
                    backgroundColor: 'rgba(124, 58, 237, 0.2)',
                    tension: 0.4,
                fill: true
            },
            {
                label: 'Completed Vaccinations',
                data: <?php echo !empty($monthlyStats) ? json_encode(array_column($monthlyStats, 'vaccination_count')) : '[]'; ?>,
                borderColor: 'rgba(16, 185, 129, 1)',
                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                tension: 0.4,
                fill: true
            }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                    grid: { color: 'rgba(55, 65, 81, 0.3)' },
                    ticks: { color: '#9CA3AF' }
                    },
                    x: {
                    grid: { color: 'rgba(55, 65, 81, 0.2)' },
                    ticks: { color: '#9CA3AF' }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#E5E7EB',
                        font: { family: 'Inter' }
                    }
                }
            }
        }
    });
});

// Function to generate and download reports
function generateReport(type) {
    window.location.href = `../generate_report.php?type=${type}`;
}
</script>
</body>
</html>