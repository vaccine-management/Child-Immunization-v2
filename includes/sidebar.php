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
<aside class="fixed left-0 top-16 h-[calc(100vh-4rem)] w-64 bg-gradient-to-b from-gray-900 via-gray-800 to-gray-900 
              border-r border-gray-700/50 overflow-y-auto transition-transform duration-300 ease-in-out z-40
              scrollbar-thin scrollbar-thumb-gray-600 scrollbar-track-gray-800">
    <div class="px-4 py-6">
        <!-- Navigation -->
        <nav class="space-y-2">
            <!-- Dashboard -->
            <a href="index.php" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg 
                      <?php echo $currentPage === 'dashboard.php' 
                          ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' 
                          : 'text-gray-300 hover:bg-blue-500/10 hover:text-blue-400 hover:border border-blue-500/20'; ?> 
                      transition-all duration-200">
                <i class="fas fa-home w-5 h-5 mr-3"></i>
                Dashboard
            </a>

            <?php if ($_SESSION['user']['role'] === 'Admin'): ?>
            <!-- Admin Section -->
            <div class="pt-6">
                <p class="px-4 text-xs font-semibold text-blue-400 uppercase tracking-wider mb-3">
                    Administration
                </p>
                <div class="space-y-2">
                    <a href="users.php" 
                       class="flex items-center px-4 py-3 text-sm font-medium rounded-lg 
                              <?php echo $currentPage === 'users.php' 
                                  ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' 
                                  : 'text-gray-300 hover:bg-blue-500/10 hover:text-blue-400 hover:border border-blue-500/20'; ?> 
                              transition-all duration-200">
                        <i class="fas fa-users w-5 h-5 mr-3"></i>
                        Users
                    </a>
                    <a href="vaccines.php" 
                       class="flex items-center px-4 py-3 text-sm font-medium rounded-lg 
                              <?php echo $currentPage === 'vaccines.php' 
                                  ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' 
                                  : 'text-gray-300 hover:bg-blue-500/10 hover:text-blue-400 hover:border border-blue-500/20'; ?> 
                              transition-all duration-200">
                        <i class="fas fa-syringe w-5 h-5 mr-3"></i>
                        Vaccines
                    </a>
                    <a href="settings.php" 
                       class="flex items-center px-4 py-3 text-sm font-medium rounded-lg 
                              <?php echo $currentPage === 'settings.php' 
                                  ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' 
                                  : 'text-gray-300 hover:bg-blue-500/10 hover:text-blue-400 hover:border border-blue-500/20'; ?> 
                              transition-all duration-200">
                        <i class="fas fa-cog w-5 h-5 mr-3"></i>
                        Settings
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($_SESSION['user']['role'] === 'Nurse'): ?>
            <!-- Main Navigation -->
            <div class="pt-6">
                <p class="px-4 text-xs font-semibold text-blue-400 uppercase tracking-wider mb-3">
                    Main Menu
                </p>
                <div class="space-y-2">
                    <a href="children.php" 
                       class="flex items-center px-4 py-3 text-sm font-medium rounded-lg 
                              <?php echo $currentPage === 'children.php' 
                                  ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' 
                                  : 'text-gray-300 hover:bg-blue-500/10 hover:text-blue-400 hover:border border-blue-500/20'; ?> 
                              transition-all duration-200 group">
                        <i class="fas fa-child w-5 h-5 mr-3 group-hover:transform group-hover:scale-110 transition-transform duration-200"></i>
                        Children Records
                    </a>
                    <a href="reports.php" 
                       class="flex items-center px-4 py-3 text-sm font-medium rounded-lg 
                              <?php echo $currentPage === 'reports.php' 
                                  ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' 
                                  : 'text-gray-300 hover:bg-blue-500/10 hover:text-blue-400 hover:border border-blue-500/20'; ?> 
                              transition-all duration-200 group">
                        <i class="fas fa-chart-bar w-5 h-5 mr-3 group-hover:transform group-hover:scale-110 transition-transform duration-200"></i>
                        Reports
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Help Section -->
            <div class="pt-6">
                <p class="px-4 text-xs font-semibold text-blue-400 uppercase tracking-wider mb-3">
                    Support
                </p>
                <div class="space-y-2">
                    <a href="help.php" 
                       class="flex items-center px-4 py-3 text-sm font-medium rounded-lg 
                              <?php echo $currentPage === 'help.php' 
                                  ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' 
                                  : 'text-gray-300 hover:bg-blue-500/10 hover:text-blue-400 hover:border border-blue-500/20'; ?> 
                              transition-all duration-200 group">
                        <i class="fas fa-question-circle w-5 h-5 mr-3 group-hover:transform group-hover:scale-110 transition-transform duration-200"></i>
                        Help & Support
                    </a>
                </div>
            </div>
        </nav>
    </div>
</aside>

<style>
    /* Custom scrollbar styles */
    .scrollbar-thin::-webkit-scrollbar {
        width: 6px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-track {
        background: #1f2937;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: #4b5563;
        border-radius: 3px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: #6b7280;
    }
</style>