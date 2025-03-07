<?php
// Check if this file is included in the main dashboard file
if (!defined('DASHBOARD_INCLUDE')) {
    header('Location: ../dashboard.php');
    exit();
}
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Total Children Card -->
    <div class="dashboard-stat-card p-6 flex items-center">
        <div class="w-12 h-12 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-400 mr-4">
            <i class="fas fa-child text-xl"></i>
        </div>
        <div>
            <h3 class="text-gray-400 text-sm font-medium">Total Children</h3>
            <p class="text-2xl font-bold text-white"><?php echo $totalChildren; ?></p>
        </div>
    </div>

    <!-- Upcoming Appointments Card -->
    <div class="dashboard-stat-card p-6 flex items-center">
        <div class="w-12 h-12 rounded-lg bg-green-500/10 flex items-center justify-center text-green-400 mr-4">
            <i class="fas fa-calendar-check text-xl"></i>
        </div>
        <div>
            <h3 class="text-gray-400 text-sm font-medium">Upcoming Vaccinations</h3>
            <p class="text-2xl font-bold text-white"><?php echo $upcomingCount; ?></p>
        </div>
    </div>

    <!-- Missed Appointments Card -->
    <div class="dashboard-stat-card p-6 flex items-center">
        <div class="w-12 h-12 rounded-lg bg-red-500/10 flex items-center justify-center text-red-400 mr-4">
            <i class="fas fa-calendar-times text-xl"></i>
        </div>
        <div>
            <h3 class="text-gray-400 text-sm font-medium">Missed Vaccinations</h3>
            <p class="text-2xl font-bold text-white"><?php echo $missedCount; ?></p>
        </div>
    </div>

    <!-- Low Stock Card -->
    <div class="dashboard-stat-card p-6 flex items-center">
        <div class="w-12 h-12 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400 mr-4">
            <i class="fas fa-syringe text-xl"></i>
        </div>
        <div>
            <h3 class="text-gray-400 text-sm font-medium">Low Stock Vaccines</h3>
            <p class="text-2xl font-bold text-white"><?php echo $lowStockCount; ?></p>
        </div>
    </div>
</div> 