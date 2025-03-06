<?php
// Check if this file is included in the main dashboard file
if (!defined('DASHBOARD_INCLUDE')) {
    header('Location: ../dashboard.php');
    exit();
}
?>

<!-- Welcome Card -->
<div class="welcome-card p-6 mb-6">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-white">
            <?php echo htmlspecialchars($displayName); ?>
            </h1>
            <p class="text-blue-100 mt-1">
                Welcome to Child Immunization System. Here's what's happening today.
            </p>
        </div>
        <div class="mt-4 lg:mt-0 flex items-center space-x-2">
            <span class="text-blue-100 text-sm"><?php echo date('l, F j, Y'); ?></span>
        </div>
    </div>
</div> 