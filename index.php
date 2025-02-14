<?php
session_start();

// Ensure session user is set before using it
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Debug session data
error_log("Session data in index: " . print_r($_SESSION['user'], true));

// Get username from session, prioritize username over email
$userName = isset($_SESSION['user']['username']) && !empty($_SESSION['user']['username']) 
    ? $_SESSION['user']['username'] 
    : $_SESSION['user']['email'];

$userRole = $_SESSION['user']['role'] ?? 'User';

// Debug final values
error_log("Username to display: " . $userName);
error_log("User role to display: " . $userRole);

// Sample data for registered children (replace with data from your database)
$registeredChildren = [
    ['name' => 'John Doe', 'age' => 5, 'vaccination_status' => 'Partially Vaccinated'],
    ['name' => 'Jane Smith', 'age' => 3, 'vaccination_status' => 'Fully Vaccinated'],
    ['name' => 'Alice Johnson', 'age' => 4, 'vaccination_status' => 'Not Vaccinated'],
    ['name' => 'Bob Brown', 'age' => 6, 'vaccination_status' => 'Fully Vaccinated'],
    ['name' => 'Charlie Davis', 'age' => 2, 'vaccination_status' => 'Partially Vaccinated'],
    ['name' => 'Eva Green', 'age' => 7, 'vaccination_status' => 'Fully Vaccinated'],
    ['name' => 'Frank White', 'age' => 5, 'vaccination_status' => 'Not Vaccinated'],
    ['name' => 'Grace Black', 'age' => 4, 'vaccination_status' => 'Fully Vaccinated'],
    ['name' => 'Henry Wilson', 'age' => 3, 'vaccination_status' => 'Partially Vaccinated'],
    ['name' => 'Ivy Lee', 'age' => 6, 'vaccination_status' => 'Fully Vaccinated'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Immunization System</title>

    <!-- Include Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <!-- Welcome Message -->
        <div class="bg-blue-800 p-6 rounded-lg shadow-lg mb-6">
            <h2 class="text-xl font-bold text-white">Welcome, <?php echo htmlspecialchars($userName); ?></h2>
            <p class="text-gray-200">You are logged in as a <?php echo htmlspecialchars($userRole); ?>.</p>
        </div>

        <!-- Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Total Children Card -->
            <div class="bg-purple-600 p-6 rounded-lg shadow-lg hover:bg-purple-700 transition duration-300">
                <h3 class="text-lg font-bold text-white">Total Children</h3>
                <p id="totalChildren" class="text-2xl text-white">1,234</p>
            </div>

            <!-- Vaccines Available Card -->
            <div class="bg-green-600 p-6 rounded-lg shadow-lg hover:bg-green-700 transition duration-300">
                <h3 class="text-lg font-bold text-white">Vaccines Available</h3>
                <p id="totalVaccines" class="text-2xl text-white">300</p>
            </div>

            <!-- Upcoming Vaccinations Card -->
            <div class="bg-yellow-600 p-6 rounded-lg shadow-lg hover:bg-yellow-700 transition duration-300">
                <h3 class="text-lg font-bold text-white">Upcoming Vaccinations</h3>
                <p id="upcomingVaccinations" class="text-2xl text-white">15</p>
            </div>
        </div>

        <!-- Chart and Vaccine Usage -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Chart -->
            <div class="lg:col-span-2 bg-gray-800 p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-bold text-white mb-4">Vaccine Distribution</h3>
                <div class="w-full h-64"> <!-- Smaller chart container -->
                    <canvas id="vaccineChart" class="w-full h-full"></canvas>
                </div>
            </div>

            <!-- Vaccine Usage -->
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-bold text-white mb-4">Vaccine Usage</h3>
                <div class="w-full h-64"> <!-- Smaller histogram container -->
                    <canvas id="vaccineHistogram" class="w-full h-full"></canvas>
                </div>
            </div>
        </div>

        <!-- Registered Children List -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h3 class="text-lg font-bold text-white mb-4">Registered Children</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-gray-700 rounded-lg">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-white">Name</th>
                            <th class="px-4 py-2 text-left text-white">Age</th>
                            <th class="px-4 py-2 text-left text-white">Vaccination Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registeredChildren as $child): ?>
                            <tr class="hover:bg-gray-600 transition duration-300">
                                <td class="px-4 py-2 text-white"><?php echo htmlspecialchars($child['name']); ?></td>
                                <td class="px-4 py-2 text-white"><?php echo htmlspecialchars($child['age']); ?></td>
                                <td class="px-4 py-2 text-white">
                                    <span class="px-2 py-1 rounded-full 
                                        <?php echo $child['vaccination_status'] === 'Fully Vaccinated' ? 'bg-green-500' : 
                                              ($child['vaccination_status'] === 'Partially Vaccinated' ? 'bg-yellow-500' : 'bg-red-500'); ?>">
                                        <?php echo htmlspecialchars($child['vaccination_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Link external JavaScript -->
    <script src="assets/js/script.js"></script>

    <!-- Initialize Charts -->
    <script>
        // Vaccine Distribution Chart (Pie Chart)
        const vaccineChartCtx = document.getElementById('vaccineChart').getContext('2d');
        const vaccineChart = new Chart(vaccineChartCtx, {
            type: 'pie',
            data: {
                labels: ['BCG', 'Polio', 'Measles', 'Hepatitis B'],
                datasets: [{
                    label: 'Vaccine Distribution',
                    data: [300, 150, 200, 100],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Disable aspect ratio to control size
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#fff'
                        }
                    }
                }
            }
        });

        // Vaccine Usage Histogram (Bar Chart)
        const vaccineHistogramCtx = document.getElementById('vaccineHistogram').getContext('2d');
        const vaccineHistogram = new Chart(vaccineHistogramCtx, {
            type: 'bar',
            data: {
                labels: ['January', 'February', 'March', 'April', 'May', 'June'],
                datasets: [{
                    label: 'Vaccine Usage',
                    data: [65, 59, 80, 81, 56, 55],
                    backgroundColor: 'rgba(75, 192, 192, 0.8)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Disable aspect ratio to control size
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>