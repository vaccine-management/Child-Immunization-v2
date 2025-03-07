<?php
// Check if this file is included in the main dashboard file
if (!defined('DASHBOARD_INCLUDE')) {
    header('Location: ../dashboard.php');
    exit();
}
?>

<!-- Stock Level Chart Card -->
<div class="dashboard-card">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-white">Vaccine Stock Levels</h2>
            <div class="flex items-center space-x-2">
                <select id="chartTimeFilter" class="text-sm bg-gray-700 border-gray-600 rounded-lg pr-8 py-2 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="week">This Week</option>
                    <option value="month" selected>This Month</option>
                    <option value="quarter">This Quarter</option>
                </select>
            </div>
        </div>
        <div id="vaccineStockChart" class="h-80 w-full"></div>
    </div>
</div> 