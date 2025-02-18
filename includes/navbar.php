<?php
// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Define $userName and $profileImage
$userName = isset($_SESSION['user']['username']) && !empty($_SESSION['user']['username']) 
    ? $_SESSION['user']['username'] 
    : $_SESSION['user']['email'];
$profileImage = $_SESSION['user']['profile_image'] ?? null;
?>

<!-- Fixed Navbar -->
<nav class="fixed top-0 left-0 w-full bg-gray-800 shadow-lg z-50">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo and System Name -->
            <div class="flex-shrink-0 flex items-center">
                <svg class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                </svg>
                <span class="ml-2 text-xl font-bold text-white">CHILD IMMUNIZATION SYSTEM</span>
            </div>

            <!-- Profile Dropdown -->
            <div class="ml-3 relative">
                <div>
                    <button id="profileDropdown" class="max-w-xs bg-gray-800 rounded-full flex items-center text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-white" aria-expanded="false">
                        <span class="sr-only">Open user menu</span>
                        <?php if ($profileImage): ?>
                            <img class="h-8 w-8 rounded-full object-cover" 
                                 src="<?php echo htmlspecialchars($profileImage); ?>" 
                                 alt="Profile">
                        <?php else: ?>
                            <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- Dropdown Menu -->
                <div id="dropdownMenu" class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-gray-800 ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                    <div class="px-4 py-2 text-xs text-gray-400">
                        Signed in as <br><strong class="text-white"><?php echo htmlspecialchars($userName); ?></strong>
                    </div>
                    <a href="profile.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700" role="menuitem" tabindex="-1">Your Profile</a>
                    <a href="logout.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700" role="menuitem" tabindex="-1">Sign out</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    // Toggle dropdown menu
    document.getElementById('profileDropdown').addEventListener('click', function(event) {
        event.stopPropagation();
        var dropdownMenu = document.getElementById('dropdownMenu');
        dropdownMenu.classList.toggle('hidden');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        var dropdownMenu = document.getElementById('dropdownMenu');
        var profileDropdown = document.getElementById('profileDropdown');
        if (!profileDropdown.contains(event.target)) {
            dropdownMenu.classList.add('hidden');
        }
    });
</script>