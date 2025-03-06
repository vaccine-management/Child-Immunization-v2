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

// Get user role - default to 'nurse' if not specified
$userRole = strtolower($_SESSION['user']['role'] ?? 'nurse');
?>

<!-- Sidebar -->
<aside id="sidebar" class="fixed left-0 top-16 h-[calc(100vh-4rem)] lg:w-64 w-0 lg:translate-x-0 -translate-x-full bg-gradient-to-b from-gray-900 via-gray-800 to-gray-900 
          border-r border-gray-700/50 overflow-y-auto transition-all duration-300 ease-in-out z-40
          scrollbar-thin scrollbar-thumb-gray-600 scrollbar-track-gray-800">
    <div class="py-5 px-4">
        <!-- Logo and App Name -->
        <div class="flex items-center mb-6">
            <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-400 mr-3">
                <i class="fas fa-heartbeat text-xl"></i>
            </div>
        </div>


        <!-- Navigation Menu -->
        <nav>
            <!-- Menu Header -->
            <p class="uppercase text-xs text-gray-500 font-semibold tracking-wider mb-4">Main Menu</p>
            
            <!-- Menu Items -->
            <ul class="space-y-2">
                <!-- Dashboard - Both admin and nurse -->
                <li>
                    <a href="dashboard.php" class="group flex items-center text-gray-300 hover:text-white py-2 px-3 rounded-lg hover:bg-gray-800/80
                          <?php echo ($currentPage === 'dashboard.php') ? 'text-white bg-blue-500/10 hover:bg-blue-500/20' : ''; ?>">
                        <div class="w-8 h-8 flex items-center justify-center mr-3
                            <?php echo ($currentPage === 'dashboard.php') ? 'text-blue-400' : 'text-gray-400 group-hover:text-blue-400'; ?>">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <span class="text-sm">Dashboard</span>
                        <?php if ($currentPage === 'dashboard.php'): ?>
                            <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Nurse-specific menu items -->
                <?php if ($userRole === 'nurse'): ?>
                    <!-- Children - Nurse only -->
                <li>
                    <a href="children.php" class="group flex items-center text-gray-300 hover:text-white py-2 px-3 rounded-lg hover:bg-gray-800/80
                          <?php echo ($currentPage === 'children.php') ? 'text-white bg-blue-500/10 hover:bg-blue-500/20' : ''; ?>">
                        <div class="w-8 h-8 flex items-center justify-center mr-3
                            <?php echo ($currentPage === 'children.php') ? 'text-blue-400' : 'text-gray-400 group-hover:text-blue-400'; ?>">
                            <i class="fas fa-child"></i>
                        </div>
                        <span class="text-sm">Children</span>
                        <?php if ($currentPage === 'children.php'): ?>
                            <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                        <?php endif; ?>
                    </a>
                </li>

                    <!-- Vaccination Schedule - Nurse only -->
                <li>
                        <a href="vaccination_schedule.php" class="group flex items-center text-gray-300 hover:text-white py-2 px-3 rounded-lg hover:bg-gray-800/80
                              <?php echo ($currentPage === 'vaccination_schedule.php') ? 'text-white bg-blue-500/10 hover:bg-blue-500/20' : ''; ?>">
                        <div class="w-8 h-8 flex items-center justify-center mr-3
                                <?php echo ($currentPage === 'vaccination_schedule.php') ? 'text-blue-400' : 'text-gray-400 group-hover:text-blue-400'; ?>">
                                <i class="fas fa-calendar-alt"></i>
                        </div>
                            <span class="text-sm">Vaccine Schedule</span>
                            <?php if ($currentPage === 'vaccination_schedule.php'): ?>
                            <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                        <?php endif; ?>
                    </a>
                </li>
                        <?php endif; ?>

                <!-- Admin-specific menu items -->
                <?php if ($userRole === 'admin'): ?>
                    <!-- Inventory - Admin only -->
                <li>
                    <a href="inventory.php" class="group flex items-center text-gray-300 hover:text-white py-2 px-3 rounded-lg hover:bg-gray-800/80
                          <?php echo ($currentPage === 'inventory.php') ? 'text-white bg-blue-500/10 hover:bg-blue-500/20' : ''; ?>">
                        <div class="w-8 h-8 flex items-center justify-center mr-3
                            <?php echo ($currentPage === 'inventory.php') ? 'text-blue-400' : 'text-gray-400 group-hover:text-blue-400'; ?>">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <span class="text-sm">Inventory</span>
                        <?php if ($currentPage === 'inventory.php'): ?>
                            <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Reports - Admin only -->
                <li>
                    <a href="reports.php" class="group flex items-center text-gray-300 hover:text-white py-2 px-3 rounded-lg hover:bg-gray-800/80
                          <?php echo ($currentPage === 'reports.php') ? 'text-white bg-blue-500/10 hover:bg-blue-500/20' : ''; ?>">
                        <div class="w-8 h-8 flex items-center justify-center mr-3
                            <?php echo ($currentPage === 'reports.php') ? 'text-blue-400' : 'text-gray-400 group-hover:text-blue-400'; ?>">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <span class="text-sm">Reports</span>
                        <?php if ($currentPage === 'reports.php'): ?>
                            <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Users Management - Admin only -->
                <li>
                    <a href="users.php" class="group flex items-center text-gray-300 hover:text-white py-2 px-3 rounded-lg hover:bg-gray-800/80
                          <?php echo ($currentPage === 'users.php') ? 'text-white bg-blue-500/10 hover:bg-blue-500/20' : ''; ?>">
                        <div class="w-8 h-8 flex items-center justify-center mr-3
                            <?php echo ($currentPage === 'users.php') ? 'text-blue-400' : 'text-gray-400 group-hover:text-blue-400'; ?>">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="text-sm">Users</span>
                        <?php if ($currentPage === 'users.php'): ?>
                            <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Settings - Admin only -->
                <li>
                    <a href="settings.php" class="group flex items-center text-gray-300 hover:text-white py-2 px-3 rounded-lg hover:bg-gray-800/80
                          <?php echo ($currentPage === 'settings.php') ? 'text-white bg-blue-500/10 hover:bg-blue-500/20' : ''; ?>">
                        <div class="w-8 h-8 flex items-center justify-center mr-3
                            <?php echo ($currentPage === 'settings.php') ? 'text-blue-400' : 'text-gray-400 group-hover:text-blue-400'; ?>">
                            <i class="fas fa-cog"></i>
                        </div>
                        <span class="text-sm">Settings</span>
                        <?php if ($currentPage === 'settings.php'): ?>
                            <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                        <?php endif; ?>
                    </a>
                </li>
                        <?php endif; ?>
            </ul>
        </nav>
    </div>
</aside>

<!-- Mobile Toggle Button for Sidebar -->
<button id="sidebar-mobile-toggle" class="fixed bottom-6 right-6 lg:hidden z-50 w-14 h-14 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-lg focus:outline-none">
    <i class="fas fa-bars text-lg"></i>
</button>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        
        if (sidebar && sidebarToggle) {
            let sidebarPinned = false;
            
            sidebarToggle.addEventListener('click', function() {
                sidebarPinned = !sidebarPinned;
                
                if (sidebarPinned) {
                    // Pin sidebar in expanded state
                    sidebar.classList.add('sidebar-expanded', 'w-64');
                    sidebar.classList.remove('sidebar-collapsed', 'w-[70px]');
                    sidebar.classList.add('hover:w-64');
                    
                    // Change icon to indicate pinned state
                    sidebarToggle.querySelector('i').classList.remove('fa-thumbtack');
                    sidebarToggle.querySelector('i').classList.add('fa-thumbtack', 'text-blue-400');
                    sidebarToggle.classList.add('rotate-45');
                    
                    // Adjust opacity for toggle button to always show
                    sidebarToggle.classList.remove('opacity-0', 'group-hover:opacity-100');
                    sidebarToggle.classList.add('opacity-100');
                    
                    // Adjust main content
                    if (mainContent) {
                        mainContent.classList.add('ml-64');
                        mainContent.classList.remove('ml-[70px]');
                    }
                    
                    // Show all labels
                    const labels = sidebar.querySelectorAll('span, p.uppercase');
                    labels.forEach(function(label) {
                        label.classList.remove('opacity-0', 'group-hover:opacity-100');
                        label.classList.add('opacity-100');
                    });
                } else {
                    // Unpin sidebar
                    sidebar.classList.remove('sidebar-expanded', 'w-64');
                    sidebar.classList.add('sidebar-collapsed', 'w-[70px]');
                    
                    // Revert icon
                    sidebarToggle.querySelector('i').classList.add('fa-thumbtack');
                    sidebarToggle.querySelector('i').classList.remove('text-blue-400');
                    sidebarToggle.classList.remove('rotate-45');
                    
                    // Revert opacity for hover behavior
                    sidebarToggle.classList.add('opacity-0', 'group-hover:opacity-100');
                    sidebarToggle.classList.remove('opacity-100');
                    
                    // Adjust main content
                    if (mainContent) {
                        mainContent.classList.remove('ml-64');
                        mainContent.classList.add('ml-[70px]');
                    }
                    
                    // Revert labels to hover behavior
                    const labels = sidebar.querySelectorAll('span, p.uppercase');
                    labels.forEach(function(label) {
                        label.classList.add('opacity-0', 'group-hover:opacity-100');
                        label.classList.remove('opacity-100');
                    });
                }
            });
        }
    });
</script>

<style>
    /* Custom scrollbar styles */
    .scrollbar-thin::-webkit-scrollbar {
        width: 4px;
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
    
    /* Rotate icon for pinned state */
    .rotate-45 {
        transform: rotate(45deg);
    }
</style>