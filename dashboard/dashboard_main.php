<?php
// Define a constant to check in component files
define('DASHBOARD_INCLUDE', true);

// Include the welcome card component
include 'components/welcome_card.php';

// Include the stats cards component
include 'components/stats_cards.php';

// Main Content Grid
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <!-- Left Column - full width on mobile, 2/3 width on desktop -->
    <div class="lg:col-span-8 space-y-6">
        <?php 
        // Include the stock chart component
        include 'components/stock_chart.php';
        
        // Include the age distribution component
        include 'components/age_distribution.php';
        
        // Include the registered children component
        include 'components/registered_children.php';
        ?>
    </div>

    <!-- Right Column - full width on mobile, 1/3 width on desktop -->
    <div class="lg:col-span-4 space-y-6">
        <?php 
        // Include the vaccine inventory component
        include 'components/vaccine_inventory.php';
        
        // Include the upcoming vaccinations component
        include 'components/upcoming_vaccinations.php';
        ?>
    </div>
</div> 