<?php
// Check if this file is included in the main dashboard file
if (!defined('DASHBOARD_INCLUDE')) {
    header('Location: ../dashboard.php');
    exit();
}
?>

<!-- Upcoming Appointments Card -->
<div class="dashboard-card">
    <div class="p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Upcoming Vaccinations</h2>
        <div class="space-y-4">
            <?php if (empty($upcomingVaccines)): ?>
                <p class="text-gray-400 text-sm">No upcoming vaccinations scheduled.</p>
            <?php else: ?>
                <?php foreach(array_slice($upcomingVaccines, 0, 4) as $vaccination): 
                    $statusClass = 'text-blue-400 bg-blue-500/10';
                    if ($vaccination['status'] === 'Completed') {
                        $statusClass = 'text-green-400 bg-green-500/10';
                    } elseif ($vaccination['scheduled_date'] < date('Y-m-d')) {
                        $statusClass = 'text-red-400 bg-red-500/10';
                    }
                ?>
                <div class="flex items-center">
                    <div class="w-2 h-2 rounded-full bg-blue-500 mr-2"></div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-white"><?= htmlspecialchars($vaccination['full_name']) ?></p>
                        <div class="flex justify-between items-center mt-1">
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($vaccination['vaccine_name']) ?> - <?= date('M j', strtotime($vaccination['scheduled_date'])) ?></p>
                            <span class="text-xs px-2 py-1 rounded-full <?= $statusClass ?>">
                                <?= $vaccination['scheduled_date'] < date('Y-m-d') && $vaccination['status'] === 'Scheduled' ? 'Missed' : $vaccination['status'] ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="mt-6">
            <a href="reports.php" class="text-sm text-blue-400 hover:text-blue-300 transition-colors duration-200 flex items-center">
                View all appointments
                <i class="fas fa-arrow-right text-xs ml-1"></i>
            </a>
        </div>
    </div>
</div> 