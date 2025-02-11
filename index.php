<?php
session_start();

// Ensure session user is set before using it
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Immunization System</title>

    <!-- Include FullCalendar & Chart.js -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-64 mt-16 p-6">
        <!-- Welcome Message -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg mb-6">
        <h2 class="text-xl font-bold text-white">Welcome, <?php echo htmlspecialchars($_SESSION['user']['username']); ?></h2>
            <p class="text-gray-400">You are logged in as a <?php echo htmlspecialchars($_SESSION['user']['role']); ?>.</p>
        </div>

        <!-- Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-bold text-white">Total Children</h3>
                <p id="totalChildren" class="text-2xl text-blue-400">1,234</p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-bold text-white">Vaccines Available</h3>
                <p id="totalVaccines" class="text-2xl text-green-400">300</p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-bold text-white">Upcoming Vaccinations</h3>
                <p id="upcomingVaccinations" class="text-2xl text-yellow-400">15</p>
            </div>
        </div>

        <!-- Chart and Calendar -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Chart -->
            <div class="lg:col-span-2 bg-gray-800 p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-bold text-white mb-4">Vaccine Distribution</h3>
                <canvas id="vaccineChart" class="bg-gray-700 rounded-lg p-4"></canvas>
            </div>

            <!-- Calendar -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Vaccination Schedule</h3>
                <div id="calendar" class="bg-white rounded-lg p-4"></div>
            </div>
        </div>

        <!-- Histogram -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg mt-6">
            <h3 class="text-lg font-bold text-white mb-4">Vaccine Usage</h3>
            <canvas id="vaccineHistogram" class="bg-gray-700 rounded-lg p-4"></canvas>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Link external JavaScript -->
    <script src="assets/js/script.js"></script>
</body>
</html>
