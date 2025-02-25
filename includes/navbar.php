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
?>
<!-- Fixed Navbar -->
<nav class="fixed top-0 left-0 right-0 bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 border-b border-gray-700 z-50 shadow-lg">
    <!-- Main Navbar -->
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Logo Section -->
            <div class="flex items-center space-x-8">
                <div class="flex items-center">
                    <div class="h-10 w-10 rounded-lg overflow-hidden shadow-lg ring-2 ring-blue-500/30">
                        <img src="assets/login.jpg" alt="Logo" class="h-full w-full object-cover">
                    </div>
                    <div class="ml-3">
                        <h1 class="text-xl font-bold bg-gradient-to-r from-white via-blue-100 to-white bg-clip-text text-transparent tracking-wide">
                            CHILD IMMUNIZATION
                        </h1>
                        <p class="text-xs text-blue-400">Healthcare Management System</p>
                    </div>
                </div>
            </div>

            <!-- Right Side Controls -->
            <div class="flex items-center space-x-6">
                <!-- Notifications -->
                <div class="relative">
                    <button class="relative p-2.5 rounded-lg bg-gray-700/50 hover:bg-blue-500/20 text-gray-300 hover:text-blue-400 
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800
                                   transition-all duration-200 group"
                            aria-label="View notifications">
                        <i class="fas fa-bell text-xl"></i>
                        <!-- Notification Badge -->
                        <span class="absolute top-1.5 right-1.5 flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-blue-500"></span>
                        </span>
                        <!-- Tooltip -->
                        <span class="absolute -bottom-12 left-1/2 -translate-x-1/2 px-3 py-2 bg-gray-800 text-white text-xs rounded-lg 
                                   opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap border border-gray-700">
                            Notifications
                        </span>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div id="notificationsDropdown" class="hidden absolute right-0 mt-3 w-80 rounded-lg bg-gray-800 border border-gray-700 shadow-xl">
                        <div class="px-4 py-3 border-b border-gray-700 bg-gradient-to-r from-gray-800 to-gray-700">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-white">Notifications</h3>
                                <span class="text-xs text-blue-400 hover:text-blue-300 cursor-pointer transition-colors duration-200">
                                    Mark all as read
                                </span>
                            </div>
                        </div>
                        
                        <!-- Notifications List -->
                        <div class="max-h-96 overflow-y-auto">
                            <div class="px-4 py-3 hover:bg-gray-700/50 border-b border-gray-700/50 cursor-pointer transition-all duration-200">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0">
                                        <div class="h-9 w-9 rounded-full bg-blue-500/10 flex items-center justify-center">
                                            <i class="fas fa-syringe text-blue-400"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-white font-medium">Upcoming Vaccination</p>
                                        <p class="text-xs text-gray-400 mt-0.5">Child ID #1234 is due for BCG vaccine tomorrow</p>
                                        <p class="text-xs text-gray-500 mt-1">2 hours ago</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- View All Link -->
                        <a href="notifications.php" class="block px-4 py-3 text-sm text-center text-blue-400 hover:text-blue-300 
                                                        border-t border-gray-700 transition-colors duration-200 bg-gray-800/50">
                            View all notifications
                        </a>
                    </div>
                </div>

                <!-- Profile Dropdown -->
                <div class="relative">
                    <button id="profileDropdown" 
                            class="flex items-center space-x-3 bg-gray-700/50 hover:bg-blue-500/20 py-2 px-4 rounded-lg 
                                   transition-all duration-200 group">
                        <div class="flex items-center space-x-3">
                            <?php if ($profileImage): ?>
                                <img class="h-9 w-9 rounded-lg object-cover ring-2 ring-blue-500/30" 
                                     src="<?php echo htmlspecialchars($profileImage); ?>" 
                                     alt="Profile">
                            <?php else: ?>
                                <div class="h-9 w-9 rounded-lg bg-gradient-to-br from-blue-600 to-blue-700 
                                            flex items-center justify-center ring-2 ring-blue-500/30">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                            <?php endif; ?>
                            <div class="flex flex-col items-start">
                                <span class="text-sm font-medium text-white group-hover:text-blue-400 transition-colors duration-200">
                                    <?php echo htmlspecialchars($userName); ?>
                                </span>
                                <span class="text-xs text-gray-400"><?php echo $_SESSION['user']['role']; ?></span>
                            </div>
                            <i class="fas fa-chevron-down text-gray-400 group-hover:text-blue-400 transition-colors duration-200"></i>
                        </div>
                    </button>

                    <!-- Profile Dropdown Menu -->
                    <div id="dropdownMenu" 
                         class="hidden absolute right-0 mt-3 w-64 rounded-lg bg-gray-800 border border-gray-700 shadow-xl">
                        <div class="px-4 py-3 border-b border-gray-700 bg-gradient-to-r from-gray-800 to-gray-700">
                            <p class="text-sm text-gray-400">Signed in as</p>
                            <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($userName); ?></p>
                        </div>
                        
                        <div class="py-1">
                            <a href="profile.php" class="group flex items-center px-4 py-2.5 text-sm text-gray-300 hover:bg-blue-500/10 
                                                       transition-colors duration-200">
                                <i class="fas fa-user-circle w-5 mr-3 text-gray-400 group-hover:text-blue-400"></i>
                                Your Profile
                            </a>
                        </div>
                        
                        <div class="py-1 border-t border-gray-700">
                            <a href="logout.php" class="group flex items-center px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/10 
                                                      transition-colors duration-200">
                                <i class="fas fa-sign-out-alt w-5 mr-3 group-hover:text-red-500"></i>
                                Sign out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
// DOM Elements
const elements = {
    profileDropdown: document.getElementById('profileDropdown'),
    dropdownMenu: document.getElementById('dropdownMenu'),
    notificationBtn: document.querySelector('[aria-label="View notifications"]'),
    notificationsDropdown: document.getElementById('notificationsDropdown'),
    searchInput: document.querySelector('input[type="text"]')
};

// Animation Classes
const classes = {
    hidden: 'hidden',
    visible: ['transform', 'transition-all', 'duration-200', 'opacity-100', 'scale-100'],
    invisible: ['opacity-0', 'scale-95']
};

// Utility Functions
const utils = {
    toggleDropdownVisibility: function(dropdown, show) {
        if (show) {
            dropdown.classList.remove(classes.hidden);
            dropdown.classList.add(...classes.visible);
            dropdown.classList.remove(...classes.invisible);
        } else {
            dropdown.classList.add(classes.hidden);
            dropdown.classList.remove(...classes.visible);
            dropdown.classList.add(...classes.invisible);
        }
    }
};

// Event Handlers
const handlers = {
    profileDropdownClick: function(event) {
        event.stopPropagation();
        const isHidden = elements.dropdownMenu.classList.contains(classes.hidden);
        utils.toggleDropdownVisibility(elements.dropdownMenu, isHidden);
    },

    notificationClick: function(event) {
        event.stopPropagation();
        elements.notificationsDropdown.classList.toggle(classes.hidden);
    },

    searchShortcut: function(event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            elements.searchInput.focus();
        }
    },

    documentClick: function(event) {
        // Close profile dropdown if clicking outside
        if (!elements.profileDropdown.contains(event.target)) {
            utils.toggleDropdownVisibility(elements.dropdownMenu, false);
        }

        // Close notifications dropdown if clicking outside
        if (!elements.notificationBtn.contains(event.target)) {
            elements.notificationsDropdown.classList.add(classes.hidden);
        }
    }
};

// Initialize Event Listeners
function initializeEventListeners() {
    elements.profileDropdown.addEventListener('click', handlers.profileDropdownClick);
    elements.notificationBtn.addEventListener('click', handlers.notificationClick);
    document.addEventListener('keydown', handlers.searchShortcut);
    document.addEventListener('click', handlers.documentClick);
}

// Initialize when DOM is fully loaded
document.addEventListener('DOMContentLoaded', initializeEventListeners);
</script>