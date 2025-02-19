<?php
// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$userName = isset($_SESSION['user']['username']) && !empty($_SESSION['user']['username']) 
    ? $_SESSION['user']['username'] 
    : $_SESSION['user']['email'];
$profileImage = $_SESSION['user']['profile_image'] ?? null;
$currentPage = basename($_SERVER['PHP_SELF']);
?>


<!-- Sidebar -->
<aside class="fixed left-0 top-16 h-[calc(100vh-4rem)] w-64 bg-gray-800 border-r border-gray-700 overflow-y-auto transition-transform duration-300 ease-in-out z-40">
    <div class="px-4 py-6">

        <!-- Navigation -->
        <nav class="space-y-1">
            <!-- Dashboard -->
            <a href="index.php" 
               class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg <?php echo $currentPage === 'dashboard.php' ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition-all duration-200">
                <i class="fas fa-home w-5 h-5 mr-3"></i>
                Dashboard
            </a>

            <?php if ($_SESSION['user']['role'] === 'Admin'): ?>
            <!-- Admin Section -->
            <div class="pt-4">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Administration
                </p>
                <div class="mt-3 space-y-1">
                    <a href="users.php" 
                       class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg <?php echo $currentPage === 'users.php' ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition-all duration-200">
                        <i class="fas fa-users w-5 h-5 mr-3"></i>
                        Users
                    </a>
                                       <a href="vaccines.php" 
                       class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg <?php echo $currentPage === 'vaccines.php' ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition-all duration-200">
                        <i class="fas fa-syringe w-5 h-5 mr-3"></i>
                        Vaccines
                    </a>
                    <a href="settings.php" 
                       class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg <?php echo $currentPage === 'settings.php' ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition-all duration-200">
                        <i class="fas fa-cog w-5 h-5 mr-3"></i>
                        Settings
                    </a>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($_SESSION['user']['role'] === 'Nurse'): ?>
            <!-- Main Navigation -->
            <div class="pt-4">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Main Menu
                </p>
                <div class="mt-3 space-y-1">
                    <a href="children.php" 
                       class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg <?php echo $currentPage === 'children.php' ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition-all duration-200">
                        <i class="fas fa-child w-5 h-5 mr-3"></i>
                        Children Records
                    </a>
                    <a href="reports.php" 
                       class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg <?php echo $currentPage === 'reports.php' ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition-all duration-200">
                        <i class="fas fa-chart-bar w-5 h-5 mr-3"></i>
                        Reports
                    </a>
                </div>
            </div>
            <?php endif; ?>
            <!-- Help Section -->
            <div class="pt-4">
                <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Support
                </p>
                <div class="mt-3 space-y-1">
                    <a href="help.php" 
                       class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg <?php echo $currentPage === 'help.php' ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> transition-all duration-200">
                        <i class="fas fa-question-circle w-5 h-5 mr-3"></i>
                        Help & Support
                    </a>
                </div>
            </div>
        </nav>
    </div>
</aside>