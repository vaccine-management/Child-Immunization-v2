<?php
// Check if this file is included in the main dashboard file
if (!defined('DASHBOARD_INCLUDE')) {
    header('Location: ../dashboard.php');
    exit();
}
?>

<!-- Age Distribution Card -->
<div class="dashboard-card">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-semibold text-white">Children by Age Group</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <canvas id="ageDistributionChart" height="260"></canvas>
            </div>
            <div class="space-y-4 my-auto">
                <?php
                $colors = ['bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500'];
                $i = 0;
                foreach ($ageGroupResult as $group): 
                    $percentage = $totalChildren > 0 ? round(($group['count'] / $totalChildren) * 100) : 0;
                ?>
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-medium text-gray-300"><?= htmlspecialchars($group['age_group']) ?></span>
                        <span class="text-sm font-semibold text-white"><?= $group['count'] ?> (<?= $percentage ?>%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-value <?= $colors[$i % count($colors)] ?>" style="width: <?= $percentage ?>%"></div>
                    </div>
                </div>
                <?php $i++; endforeach; ?>
            </div>
        </div>
    </div>
</div> 