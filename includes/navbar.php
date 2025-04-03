<?php
// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Determine if we're in admin_pages directory or root
$isInAdminDir = strpos($_SERVER['SCRIPT_FILENAME'], 'admin_pages') !== false;
$baseUrl = $isInAdminDir ? '../' : '';

// Prepare and execute a query to fetch user details from the database
try {
    // Use username from the database as confirmed by the user
    $stmt = $conn->prepare("SELECT id, email, username FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Use username as the display name, fallback to email if username is not available
    if (!empty($userDetails['username'])) {
        $displayName = $userDetails['username'];
    } else {
        $displayName = $userDetails['email'];
    }
    
    // Store in session for future use
    $_SESSION['user']['display_name'] = $displayName;
    
} catch (PDOException $e) {
    // In case of database error, fall back to email
    $displayName = $_SESSION['user']['email'];
}

// Fix the boolean offset error by ensuring user array exists and profile_image is accessible
$profileImage = isset($_SESSION['user']) && isset($_SESSION['user']['profile_image']) ? $_SESSION['user']['profile_image'] : null;
?>
<!-- Fixed Navbar -->
<nav class="fixed top-0 left-0 right-0 bg-gradient-to-r from-blue-900 via-blue-800 to-indigo-900 border-b border-blue-700/30 z-50 shadow-xl">
    <!-- Main Navbar -->
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Logo Section -->
            <div class="flex items-center space-x-8">
                <!-- Mobile menu toggle button -->
                <button id="mobile-menu-button" class="block lg:hidden text-white focus:outline-none hover:text-blue-300 transition-colors duration-200">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                
                <div class="flex items-center">
                    <div class="h-10 w-10 rounded-full overflow-hidden shadow-lg bg-white/10 backdrop-blur-sm flex items-center justify-center ring-2 ring-blue-400/30">
                        <i class="fas fa-heartbeat text-blue-300 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h1 class="text-xl font-bold bg-gradient-to-r from-white via-blue-200 to-white bg-clip-text text-transparent tracking-wide">
                            IMMUNIZATION SYSTEM
                        </h1>
                        <p class="text-xs text-blue-300">Child Immunization System</p>
                    </div>
                </div>
            </div>

            <!-- Right Side Controls -->
            <div class="flex items-center space-x-6">
                <!-- Profile Dropdown -->
                <div class="relative">
                    <button id="profileDropdown" 
                            class="flex items-center space-x-3 bg-white/5 hover:bg-blue-500/20 py-2 px-4 rounded-full 
                                   transition-all duration-200 group border border-blue-500/20">
                        <div class="flex items-center space-x-3">
                            <?php if ($profileImage): ?>
                                <img class="h-8 w-8 rounded-full object-cover ring-2 ring-blue-400/30" 
                                     src="<?php echo $baseUrl . 'uploads/profiles/' . htmlspecialchars($profileImage); ?>" 
                                     alt="Profile">
                            <?php else: ?>
                                <div class="h-8 w-8 rounded-full bg-gradient-to-br from-blue-400 to-indigo-500 
                                            flex items-center justify-center ring-2 ring-blue-400/30">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                            <?php endif; ?>
                            <div class="flex flex-col items-start">
                                <span class="text-sm font-medium text-white group-hover:text-blue-200 transition-colors duration-200">
                                <?php echo htmlspecialchars($displayName); ?>
                                </span>
                                <span class="text-xs text-blue-300"><?php echo $_SESSION['user']['role']; ?></span>
                            </div>
                            <i class="fas fa-chevron-down text-blue-300 group-hover:text-blue-200 transition-colors duration-200"></i>
                        </div>
                    </button>

                    <!-- Profile Dropdown Menu -->
                    <div id="dropdownMenu" 
                         class="hidden absolute right-0 mt-3 w-64 rounded-xl bg-white shadow-2xl border border-blue-100 overflow-hidden transform transition-all duration-300 ease-in-out origin-top-right">
                        <div class="px-4 py-3 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-blue-100">
                            <p class="text-sm text-blue-600">Signed in as</p>
                            <p class="text-sm font-medium text-blue-900 truncate"><?php echo htmlspecialchars($displayName); ?></p>
                        </div>
                        
                        <div class="py-1">
                            <a href="<?php echo $baseUrl; ?>profile.php" class="group flex items-center px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 
                                                   transition-colors duration-200">
                                <i class="fas fa-user-circle w-5 mr-3 text-blue-500 group-hover:text-blue-600"></i>
                                Your Profile
                            </a>
                        </div>
                        
                        <div class="py-1 border-t border-blue-100">
                            <a href="<?php echo $baseUrl; ?>logout.php" class="group flex items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 
                                                  transition-colors duration-200">
                                <i class="fas fa-sign-out-alt w-5 mr-3 group-hover:text-red-700"></i>
                                Sign out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile Menu Overlay -->
<div id="mobile-menu-overlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

<!-- Mobile Menu -->
<div id="mobile-menu" class="fixed top-16 left-0 bottom-0 w-64 bg-gradient-to-b from-blue-900 to-indigo-900 transform -translate-x-full transition-transform duration-300 ease-in-out z-40 lg:hidden overflow-y-auto">
    <div class="px-4 py-6">
        
        <!-- Mobile Navigation Links -->
        <nav>
            <ul class="space-y-2">
                <li>
                    <a href="<?php echo $baseUrl; ?>dashboard.php" class="flex items-center px-3 py-2 text-blue-100 hover:bg-blue-800/50 rounded-lg transition-colors duration-200">
                        <i class="fas fa-chart-pie w-5 mr-3 text-blue-300"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $baseUrl; ?>children.php" class="flex items-center px-3 py-2 text-blue-100 hover:bg-blue-800/50 rounded-lg transition-colors duration-200">
                        <i class="fas fa-child w-5 mr-3 text-blue-300"></i>
                        <span>Children</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $baseUrl; ?>appointments.php" class="flex items-center px-3 py-2 text-blue-100 hover:bg-blue-800/50 rounded-lg transition-colors duration-200">
                        <i class="fas fa-calendar-check w-5 mr-3 text-blue-300"></i>
                        <span>Appointments</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $baseUrl; ?>admin_pages/inventory.php" class="flex items-center px-3 py-2 text-blue-100 hover:bg-blue-800/50 rounded-lg transition-colors duration-200">
                        <i class="fas fa-boxes w-5 mr-3 text-blue-300"></i>
                        <span>Inventory</span>
                    </a>
                </li>
                
                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                <li class="pt-4 pb-2">
                    <div class="px-3">
                        <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider">Administration</p>
                    </div>
                </li>
                <li>
                    <a href="<?php echo $baseUrl; ?>admin_pages/users.php" class="flex items-center px-3 py-2 text-blue-100 hover:bg-blue-800/50 rounded-lg transition-colors duration-200">
                        <i class="fas fa-users w-5 mr-3 text-blue-300"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo $baseUrl; ?>admin_pages/send_sms.php" class="flex items-center px-3 py-2 text-blue-100 hover:bg-blue-800/50 rounded-lg transition-colors duration-200">
                        <i class="fas fa-sms w-5 mr-3 text-blue-300"></i>
                        <span>Send SMS</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>

<script>
// DOM Elements
const elements = {
    profileDropdown: document.getElementById('profileDropdown'),
    dropdownMenu: document.getElementById('dropdownMenu'),
    notificationBtn: document.querySelector('[aria-label="View notifications"]'),
    notificationsDropdown: document.getElementById('notificationsDropdown'),
    searchInput: document.querySelector('input[aria-label="Search"]'),
    mobileMenuButton: document.getElementById('mobile-menu-button'),
    mobileMenu: document.getElementById('mobile-menu'),
    mobileMenuOverlay: document.getElementById('mobile-menu-overlay')
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
            setTimeout(() => {
                dropdown.classList.add(...classes.visible);
                dropdown.classList.remove(...classes.invisible);
            }, 10);
        } else {
            dropdown.classList.remove(...classes.visible);
            dropdown.classList.add(...classes.invisible);
            setTimeout(() => {
                dropdown.classList.add(classes.hidden);
            }, 200);
        }
    },
    toggleMobileMenu: function(show) {
        if (show) {
            elements.mobileMenu.classList.remove('-translate-x-full');
            elements.mobileMenu.classList.add('translate-x-0');
            elements.mobileMenuOverlay.classList.remove('hidden');
        } else {
            elements.mobileMenu.classList.remove('translate-x-0');
            elements.mobileMenu.classList.add('-translate-x-full');
            elements.mobileMenuOverlay.classList.add('hidden');
        }
    }
};

// Event Handlers
const handlers = {
    profileDropdownClick: function(event) {
        event.stopPropagation();
        const isHidden = elements.dropdownMenu.classList.contains(classes.hidden);
        utils.toggleDropdownVisibility(elements.dropdownMenu, isHidden);
        
        // Close notifications dropdown if open
        if (!elements.notificationsDropdown.classList.contains(classes.hidden)) {
            utils.toggleDropdownVisibility(elements.notificationsDropdown, false);
        }
    },

    notificationClick: function(event) {
        event.stopPropagation();
        const isHidden = elements.notificationsDropdown.classList.contains(classes.hidden);
        utils.toggleDropdownVisibility(elements.notificationsDropdown, isHidden);
        
        // Close profile dropdown if open
        if (!elements.dropdownMenu.classList.contains(classes.hidden)) {
            utils.toggleDropdownVisibility(elements.dropdownMenu, false);
        }
    },

    searchShortcut: function(event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            elements.searchInput?.focus();
        }
    },

    documentClick: function(event) {
        // Close dropdowns when clicking outside
        if (!elements.profileDropdown.contains(event.target)) {
            utils.toggleDropdownVisibility(elements.dropdownMenu, false);
        }
        
        if (!elements.notificationBtn.contains(event.target)) {
            utils.toggleDropdownVisibility(elements.notificationsDropdown, false);
        }
    },
    
    mobileMenuToggle: function(event) {
        event.stopPropagation();
        const isClosed = elements.mobileMenu.classList.contains('-translate-x-full');
        utils.toggleMobileMenu(isClosed);
    },
    
    mobileMenuOverlayClick: function(event) {
        utils.toggleMobileMenu(false);
    }
};

// Event Listeners
if (elements.profileDropdown) {
    elements.profileDropdown.addEventListener('click', handlers.profileDropdownClick);
}

if (elements.notificationBtn) {
    elements.notificationBtn.addEventListener('click', handlers.notificationClick);
}

if (elements.mobileMenuButton) {
    elements.mobileMenuButton.addEventListener('click', handlers.mobileMenuToggle);
}

if (elements.mobileMenuOverlay) {
    elements.mobileMenuOverlay.addEventListener('click', handlers.mobileMenuOverlayClick);
}

document.addEventListener('keydown', handlers.searchShortcut);
document.addEventListener('click', handlers.documentClick);

// Add smooth hover effects
const hoverElements = document.querySelectorAll('.hover\\:bg-blue-500\\/20, .hover\\:bg-blue-800\\/50');
hoverElements.forEach(element => {
    element.addEventListener('mouseenter', function() {
        this.style.transition = 'all 0.2s ease-in-out';
    });
});
</script>
