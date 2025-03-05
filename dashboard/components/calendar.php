<?php
// Check if this file is included in the main dashboard file
if (!defined('DASHBOARD_INCLUDE')) {
    header('Location: ../dashboard.php');
    exit();
}
?>

<!-- Calendar Card -->
<div class="dashboard-card overflow-hidden">
    <div class="p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Calendar</h2>
        <div id="calendar"></div>
    </div>
</div> 