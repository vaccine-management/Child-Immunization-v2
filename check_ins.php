<?php
// Include the auth check file
include 'includes/auth_check.php';

// Only allow nurses and admins to access this page
checkUserRole(['nurse', 'admin']);

// Include database connection
include 'backend/db.php';

// Page title
$pageTitle = "Daily Check-ins";
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main id="main-content" class="lg:ml-64 ml-0 pt-16 min-h-screen bg-gray-900 transition-all duration-300 ease-in-out">
    <div class="p-4 sm:p-6 lg:p-8">
        <!-- Page Header -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-white">Daily Check-ins</h1>
                <p class="text-gray-400">Record and manage daily child check-ins for vaccinations</p>
            </div>
            <div>
                <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center">
                    <i class="fas fa-plus mr-2"></i> New Check-in
                </button>
            </div>
        </div>
        
        <!-- Today's Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="dashboard-stat-card p-5">
                <div class="flex items-center">
                    <div class="rounded-lg bg-blue-500/10 p-3 mr-4">
                        <i class="fas fa-clipboard-check text-blue-500 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Today's Check-ins</p>
                        <h3 class="text-white text-2xl font-bold">12</h3>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-stat-card p-5">
                <div class="flex items-center">
                    <div class="rounded-lg bg-green-500/10 p-3 mr-4">
                        <i class="fas fa-syringe text-green-500 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Vaccinations Given</p>
                        <h3 class="text-white text-2xl font-bold">8</h3>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-stat-card p-5">
                <div class="flex items-center">
                    <div class="rounded-lg bg-purple-500/10 p-3 mr-4">
                        <i class="fas fa-calendar-check text-purple-500 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Appointments Remaining</p>
                        <h3 class="text-white text-2xl font-bold">5</h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Check-ins Table -->
        <div class="dashboard-card p-5">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-white">Recent Check-ins</h2>
                <div class="flex space-x-2">
                    <div class="relative">
                        <input type="text" placeholder="Search..." class="bg-gray-800 text-white text-sm rounded-lg block w-full p-2 pl-10 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <i class="fas fa-search text-gray-400 absolute left-3 top-2.5"></i>
                    </div>
                    <button class="px-3 py-1 bg-gray-700 text-gray-300 rounded-md text-sm hover:bg-gray-600 focus:outline-none">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto -mx-5 px-5">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Check-in Time
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Child Name
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Guardian
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Vaccine
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
                        <!-- Sample data -->
                        <tr class="hover:bg-gray-700/50 transition duration-150">
                            <td class="px-4 py-3 text-sm text-gray-300">
                                <?php echo date('h:i A'); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-white">
                                <a href="#" class="hover:text-blue-400">John Smith</a>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                Mary Smith
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                Hepatitis B
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Completed
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                <div class="flex space-x-2">
                                    <button class="text-blue-400 hover:text-blue-300" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="text-gray-400 hover:text-gray-300" title="Print Record">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        
                        <tr class="hover:bg-gray-700/50 transition duration-150">
                            <td class="px-4 py-3 text-sm text-gray-300">
                                <?php echo date('h:i A', strtotime('-1 hour')); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-white">
                                <a href="#" class="hover:text-blue-400">Sarah Johnson</a>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                James Johnson
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                Polio (OPV)
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    In Progress
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                <div class="flex space-x-2">
                                    <button class="text-blue-400 hover:text-blue-300" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="text-green-400 hover:text-green-300" title="Complete">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        
                        <tr class="hover:bg-gray-700/50 transition duration-150">
                            <td class="px-4 py-3 text-sm text-gray-300">
                                <?php echo date('h:i A', strtotime('-2 hours')); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-white">
                                <a href="#" class="hover:text-blue-400">Emily Davis</a>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                Robert Davis
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                MMR
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Completed
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                <div class="flex space-x-2">
                                    <button class="text-blue-400 hover:text-blue-300" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="text-gray-400 hover:text-gray-300" title="Print Record">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-between items-center">
                <div class="text-sm text-gray-400">
                    Showing 3 of 12 records
                </div>
                <div class="flex space-x-1">
                    <button class="px-3 py-1 bg-gray-800 text-gray-300 rounded-md text-sm hover:bg-gray-700">
                        Previous
                    </button>
                    <button class="px-3 py-1 bg-blue-600 text-white rounded-md text-sm">1</button>
                    <button class="px-3 py-1 bg-gray-800 text-gray-300 rounded-md text-sm hover:bg-gray-700">2</button>
                    <button class="px-3 py-1 bg-gray-800 text-gray-300 rounded-md text-sm hover:bg-gray-700">3</button>
                    <button class="px-3 py-1 bg-gray-800 text-gray-300 rounded-md text-sm hover:bg-gray-700">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?> 