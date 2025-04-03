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

// Determine if we're in admin_pages directory or root
$isInAdminDir = strpos($_SERVER['SCRIPT_FILENAME'], 'admin_pages') !== false;
$baseUrl = $isInAdminDir ? '../' : '';

// Get user role - default to 'nurse' if not specified
$userRole = strtolower($_SESSION['user']['role'] ?? 'nurse');
?>

<!-- Sidebar -->
<aside id="sidebar" class="fixed left-0 top-16 h-[calc(100vh-4rem)] lg:w-64 w-0 lg:translate-x-0 -translate-x-full 
          bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900
          border-r border-blue-700/30 overflow-y-auto transition-all duration-300 ease-in-out z-40
          scrollbar-thin scrollbar-thumb-blue-500/50 scrollbar-track-blue-900/30">
    <div class="py-6 px-4">

        <!-- User Profile Section -->
        <div class="mb-8 p-3 rounded-xl bg-white/5 backdrop-blur-sm border border-white/10 flex items-center">
            <?php if ($profileImage): ?>
                <img src="<?php echo $baseUrl . $profileImage; ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-blue-400/30">
            <?php else: ?>
                <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-200">
                    <i class="fas fa-user"></i>
                </div>
            <?php endif; ?>
            <div class="ml-3">
                <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($userName); ?></p>
                <p class="text-xs text-blue-200/70 capitalize"><?php echo htmlspecialchars($userRole); ?></p>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav>
            <!-- Menu Header -->
            <p class="uppercase text-xs text-blue-300/70 font-semibold tracking-wider mb-4 ml-2">Main Menu</p>
            
            <!-- Menu Items -->
            <ul class="space-y-1.5">
                <!-- Dashboard - Both admin and nurse -->
                <li>
                    <a href="<?php echo $baseUrl; ?>dashboard.php" class="group flex items-center text-blue-100 py-2.5 px-4 rounded-lg transition-all duration-200 ease-in-out
                          <?php echo ($currentPage === 'dashboard.php') ? 'bg-blue-500/20 shadow-md shadow-blue-900/30' : 'hover:bg-blue-800/50'; ?>">
                        <div class="w-9 h-9 flex items-center justify-center mr-3 rounded-md
                            <?php echo ($currentPage === 'dashboard.php') ? 'bg-blue-500/30 text-blue-100' : 'text-blue-300 group-hover:text-blue-100 bg-blue-800/30 group-hover:bg-blue-700/30'; ?>">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <span class="text-sm font-medium"><?php echo ($currentPage === 'dashboard.php') ? '<span class="text-white">Dashboard</span>' : 'Dashboard'; ?></span>
                        <?php if ($currentPage === 'dashboard.php'): ?>
                            <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <!-- Children - Both admin and nurse -->
                <li>
                    <a href="<?php echo $baseUrl; ?>children.php" class="group flex items-center text-blue-100 py-2.5 px-4 rounded-lg transition-all duration-200 ease-in-out
                          <?php echo ($currentPage === 'children.php') ? 'bg-blue-500/20 shadow-md shadow-blue-900/30' : 'hover:bg-blue-800/50'; ?>">
                        <div class="w-9 h-9 flex items-center justify-center mr-3 rounded-md
                            <?php echo ($currentPage === 'children.php') ? 'bg-blue-500/30 text-blue-100' : 'text-blue-300 group-hover:text-blue-100 bg-blue-800/30 group-hover:bg-blue-700/30'; ?>">
                            <i class="fas fa-child"></i>
                        </div>
                        <span class="text-sm font-medium"><?php echo ($currentPage === 'children.php') ? '<span class="text-white">Children</span>' : 'Children'; ?></span>
                        <?php if ($currentPage === 'children.php'): ?>
                            <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Appointments - Both admin and nurse -->
                <li>
                    <a href="<?php echo $baseUrl; ?>appointments.php" class="group flex items-center text-blue-100 py-2.5 px-4 rounded-lg transition-all duration-200 ease-in-out
                          <?php echo ($currentPage === 'appointments.php') ? 'bg-blue-500/20 shadow-md shadow-blue-900/30' : 'hover:bg-blue-800/50'; ?>">
                        <div class="w-9 h-9 flex items-center justify-center mr-3 rounded-md
                            <?php echo ($currentPage === 'appointments.php') ? 'bg-blue-500/30 text-blue-100' : 'text-blue-300 group-hover:text-blue-100 bg-blue-800/30 group-hover:bg-blue-700/30'; ?>">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <span class="text-sm font-medium"><?php echo ($currentPage === 'appointments.php') ? '<span class="text-white">Appointments</span>' : 'Appointments'; ?></span>
                        <?php if ($currentPage === 'appointments.php'): ?>
                            <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Inventory - Both admin and nurse -->
                <li>
                    <a href="<?php echo $baseUrl; ?>admin_pages/inventory.php" class="group flex items-center text-blue-100 py-2.5 px-4 rounded-lg transition-all duration-200 ease-in-out
                          <?php echo ($currentPage === 'inventory.php') ? 'bg-blue-500/20 shadow-md shadow-blue-900/30' : 'hover:bg-blue-800/50'; ?>">
                        <div class="w-9 h-9 flex items-center justify-center mr-3 rounded-md
                            <?php echo ($currentPage === 'inventory.php') ? 'bg-blue-500/30 text-blue-100' : 'text-blue-300 group-hover:text-blue-100 bg-blue-800/30 group-hover:bg-blue-700/30'; ?>">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <span class="text-sm font-medium"><?php echo ($currentPage === 'inventory.php') ? '<span class="text-white">Inventory</span>' : 'Inventory'; ?></span>
                        <?php if ($currentPage === 'inventory.php'): ?>
                            <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Admin-specific menu items -->
                <?php if ($userRole === 'admin'): ?>
                    <!-- Section Divider -->
                    <li class="pt-3">
                        <p class="uppercase text-xs text-blue-300/70 font-semibold tracking-wider mb-3 ml-2">Administration</p>
                    </li>
                    
                    <!-- Users Management - Admin only -->
                    <li>
                        <a href="<?php echo $baseUrl; ?>admin_pages/users.php" class="group flex items-center text-blue-100 py-2.5 px-4 rounded-lg transition-all duration-200 ease-in-out
                              <?php echo ($currentPage === 'users.php') ? 'bg-blue-500/20 shadow-md shadow-blue-900/30' : 'hover:bg-blue-800/50'; ?>">
                            <div class="w-9 h-9 flex items-center justify-center mr-3 rounded-md
                                <?php echo ($currentPage === 'users.php') ? 'bg-blue-500/30 text-blue-100' : 'text-blue-300 group-hover:text-blue-100 bg-blue-800/30 group-hover:bg-blue-700/30'; ?>">
                                <i class="fas fa-users"></i>
                            </div>
                            <span class="text-sm font-medium"><?php echo ($currentPage === 'users.php') ? '<span class="text-white">Users</span>' : 'Users'; ?></span>
                            <?php if ($currentPage === 'users.php'): ?>
                                <span class="ml-auto w-1.5 h-6 rounded-sm bg-blue-400"></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $baseUrl; ?>admin_pages/send_sms.php" class="group flex items-center text-blue-100 py-2.5 px-4 rounded-lg transition-all duration-200 ease-in-out
                              <?php echo ($currentPage === 'send_sms.php') ? 'bg-blue-500/20 shadow-md shadow-blue-900/30' : 'hover:bg-blue-800/50'; ?>">
                            <div class="w-9 h-9 flex items-center justify-center mr-3 rounded-md
                                <?php echo ($currentPage === 'send_sms.php') ? 'bg-blue-500/30 text-blue-100' : 'text-blue-300 group-hover:text-blue-100 bg-blue-800/30 group-hover:bg-blue-700/30'; ?>">
                                <i class="fas fa-sms"></i>
                            </div>
                            <span class="text-sm font-medium"><?php echo ($currentPage === 'send_sms.php') ? '<span class="text-white">Send SMS</span>' : 'Send SMS'; ?></span>
                            <?php if ($currentPage === 'send_sms.php'): ?>
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
<button id="sidebar-mobile-toggle" class="fixed bottom-6 right-6 lg:hidden z-50 w-14 h-14 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white flex items-center justify-center shadow-lg shadow-blue-900/30 focus:outline-none hover:from-blue-500 hover:to-indigo-500 transition-all duration-300">
    <i class="fas fa-bars text-lg"></i>
</button>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarMobileToggle = document.getElementById('sidebar-mobile-toggle');
        
        // Mobile sidebar toggle
        if (sidebar && sidebarMobileToggle) {
            sidebarMobileToggle.addEventListener('click', function() {
                if (sidebar.classList.contains('-translate-x-full')) {
                    // Open sidebar
                    sidebar.classList.remove('-translate-x-full');
                    sidebar.classList.add('translate-x-0', 'w-64');
                    // Add overlay
                    const overlay = document.createElement('div');
                    overlay.id = 'sidebar-overlay';
                    overlay.className = 'fixed inset-0 bg-black/50 z-30 lg:hidden';
                    document.body.appendChild(overlay);
                    
                    // Close sidebar when clicking overlay
                    overlay.addEventListener('click', function() {
                        sidebar.classList.remove('translate-x-0', 'w-64');
                        sidebar.classList.add('-translate-x-full');
                        document.body.removeChild(overlay);
                    });
                    
                    // Change icon to X
                    sidebarMobileToggle.innerHTML = '<i class="fas fa-times text-lg"></i>';
                } else {
                    // Close sidebar
                    sidebar.classList.remove('translate-x-0', 'w-64');
                    sidebar.classList.add('-translate-x-full');
                    // Remove overlay if exists
                    const overlay = document.getElementById('sidebar-overlay');
                    if (overlay) {
                        document.body.removeChild(overlay);
                    }
                    // Restore icon
                    sidebarMobileToggle.innerHTML = '<i class="fas fa-bars text-lg"></i>';
                }
            });
        }
        
        // Desktop sidebar toggle (collapsible)
        if (sidebar && sidebarToggle) {
            let sidebarPinned = true;
            
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
        
        // Add hover effects for menu items
        const menuItems = document.querySelectorAll('#sidebar nav ul li a');
        menuItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                if (!this.classList.contains('bg-blue-500/20')) {
                    this.querySelector('div').classList.add('scale-110');
                }
            });
            
            item.addEventListener('mouseleave', function() {
                this.querySelector('div').classList.remove('scale-110');
            });
        });
    });
</script>

<style>
    /* Custom scrollbar styles */
    .scrollbar-thin::-webkit-scrollbar {
        width: 4px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-track {
        background: rgba(30, 58, 138, 0.1);
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: rgba(59, 130, 246, 0.5);
        border-radius: 3px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: rgba(59, 130, 246, 0.7);
    }
    
    /* Rotate icon for pinned state */
    .rotate-45 {
        transform: rotate(45deg);
    }
    
    /* Smooth transitions for all interactive elements */
    #sidebar nav ul li a div {
        transition: all 0.2s ease-in-out;
    }
    
    /* Glassmorphism effects */
    .backdrop-blur-sm {
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
</style>
