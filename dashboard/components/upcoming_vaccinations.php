<?php
// Check if this file is included in the main dashboard file
if (!defined('DASHBOARD_INCLUDE')) {
    header('Location: ../../../dashboard.php');
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
                    $statusText = $vaccination['status'];
                    
                    // Translate status from appointment to more user-friendly terms
                    if ($vaccination['status'] === 'scheduled') {
                        $statusText = 'Scheduled';
                    } elseif ($vaccination['status'] === 'completed') {
                        $statusText = 'Completed';
                        $statusClass = 'text-green-400 bg-green-500/10';
                    } elseif ($vaccination['status'] === 'missed' || 
                              ($vaccination['scheduled_date'] < date('Y-m-d') && $vaccination['status'] === 'scheduled')) {
                        $statusText = 'Missed';
                        $statusClass = 'text-red-400 bg-red-500/10';
                    }

                    // Check if we have a child_id in the data
                    $hasChildId = isset($vaccination['child_id']);
                ?>
                <div class="flex items-center hover:bg-gray-700/20 rounded-lg p-1 transition-colors cursor-pointer" 
                     <?php if ($hasChildId): ?>onclick="window.location.href='child_profile.php?id=<?= htmlspecialchars($vaccination['child_id']) ?>'"<?php endif; ?>>
                    <div class="w-2 h-2 rounded-full bg-blue-500 mr-2"></div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-white">
                            <?php if ($hasChildId): ?>
                                <a href="child_profile.php?id=<?= htmlspecialchars($vaccination['child_id']) ?>" class="hover:text-blue-400 transition-colors">
                                    <?= htmlspecialchars($vaccination['full_name']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($vaccination['full_name']) ?>
                            <?php endif; ?>
                        </p>
                        <div class="flex justify-between items-center mt-1">
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($vaccination['vaccine_name']) ?> - <?= date('M j', strtotime($vaccination['scheduled_date'])) ?></p>
                            <span class="text-xs px-2 py-1 rounded-full <?= $statusClass ?>">
                                <?= $statusText ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="mt-6">
            <a href="appointments.php" class="text-sm text-blue-400 hover:text-blue-300 transition-colors duration-200 flex items-center">
                View all appointments
                <i class="fas fa-arrow-right text-xs ml-1"></i>
            </a>
        </div>
    </div>
</div> 