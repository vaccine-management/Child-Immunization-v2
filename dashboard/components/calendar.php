<?php
// Check if this file is included in the main dashboard file
if (!defined('DASHBOARD_INCLUDE')) {
    header('Location: ../dashboard.php');
    exit();
}
?>

<!-- Calendar Card -->
<div class="dashboard-card overflow-hidden border border-gray-700 shadow-xl">
    <div class="bg-gray-800 px-5 py-3 border-b border-gray-700">
        <h2 class="text-base font-bold text-white flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Immunization Calendar
        </h2>
    </div>
    <div class="p-3">
        <div id="calendar" class="calendar-container"></div>
    </div>
    <!-- Quick Event Legend -->
    <div class="px-5 py-2 bg-gray-800 border-t border-gray-700">
        <div class="flex items-center space-x-3 text-xs text-gray-300">
            <div class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-blue-500 mr-1"></span>
                <span class="text-xs">Vaccination</span>
            </div>
            <div class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-green-500 mr-1"></span>
                <span class="text-xs">Checkup</span>
            </div>
            <div class="flex items-center">
                <span class="w-2 h-2 rounded-full bg-yellow-500 mr-1"></span>
                <span class="text-xs">Follow-up</span>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional styles for better calendar integration */
.calendar-container {
    min-height: 320px;
    max-height: 320px;
}

/* Custom event colors */
.fc-event.vaccination-event {
    background-color: #2563EB;
}
.fc-event.checkup-event {
    background-color: #10B981;
}
.fc-event.followup-event {
    background-color: #F59E0B;
}
</style> 
