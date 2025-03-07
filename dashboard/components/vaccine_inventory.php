<?php
// Check if this file is included in the main dashboard file
if (!defined('DASHBOARD_INCLUDE')) {
    header('Location: ../dashboard.php');
    exit();
}
?>

<!-- Vaccine Stock Card -->
<div class="dashboard-card">
    <div class="p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Vaccine Inventory</h2>
        <div class="space-y-4">
            <?php foreach($vaccineStock as $vaccine): 
                $percentage = ($vaccine['quantity'] / 50) * 100; // Assuming max capacity is 50
                $colorClass = 'bg-green-500';
                if ($percentage < 20) {
                    $colorClass = 'bg-red-500';
                } elseif ($percentage < 40) {
                    $colorClass = 'bg-amber-500';
                }
            ?>
            <div>
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium text-gray-300"><?= htmlspecialchars($vaccine['vaccine_name']) ?></span>
                    <span class="text-sm font-semibold text-white"><?= $vaccine['quantity'] ?> doses</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-value <?= $colorClass ?>" style="width: <?= $percentage ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-6">
            <a href="vaccines.php" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200">
                Manage Inventory
            </a>
        </div>
    </div>
</div> 