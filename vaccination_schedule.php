<?php
// Include the auth check file
include 'includes/auth_check.php';

// Only allow nurses and admins to access this page
checkUserRole(['nurse', 'admin']);

// Include database connection
include 'backend/db.php';

// Page title
$pageTitle = "Vaccination Schedule";
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
    <div class="p-4 sm:p-6 lg:p-8">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">Vaccination Schedule</h1>
            <p class="text-gray-400">Manage and view upcoming and missed vaccinations</p>
        </div>
        
        <!-- Calendar View -->
        <div class="dashboard-card p-5 mb-6">
            <div id="vaccination-calendar" class="min-h-[400px]">
                <p class="text-white text-center py-10">Vaccination calendar will be displayed here.</p>
            </div>
        </div>
        
        <!-- Upcoming Vaccinations -->
        <div class="dashboard-card p-5">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-white">Upcoming Vaccinations</h2>
            </div>
            <div class="overflow-x-auto -mx-5 px-5">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Child Name
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Vaccine
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Scheduled Date
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-gray-800/30 divide-y divide-gray-700">
                        <tr>
                            <td class="px-4 py-4 text-sm text-gray-400 text-center" colspan="5">
                                Loading vaccination data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?> 