<?php
// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Define $userName if not already defined
if (!isset($userName)) {
    $userName = isset($_SESSION['user']['username']) && !empty($_SESSION['user']['username']) 
        ? $_SESSION['user']['username'] 
        : $_SESSION['user']['email'];
}
?>
<!-- Fixed Navbar -->
<div class="fixed top-0 left-0 w-full bg-gray-800 shadow-lg z-50">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <!-- Logo and System Name -->
        <div class="flex items-center space-x-4">
            <div class="text-xl font-bold text-white">Immunization System</div>
        </div>

        <!-- Profile Dropdown -->
        <div class="relative">
            <button id="profileDropdown" class="flex items-center space-x-2 focus:outline-none">
                <!-- User Icon -->
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path>
                </svg>
                <!-- User Name -->
                <span class="text-white"><?php echo htmlspecialchars($userName); ?></span>
            </button>

            <!-- Dropdown Menu -->
            <div id="dropdownMenu" class="hidden absolute right-0 mt-2 w-48 bg-gray-700 rounded-lg shadow-lg">
                <!-- Profile Link -->
                <a href="profile.php" class="flex items-center px-4 py-2 text-white hover:bg-gray-600 transition duration-300">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Profile
                </a>

                <!-- Logout Link -->
                <a href="logout.php" class="flex items-center px-4 py-2 text-white hover:bg-gray-600 transition duration-300">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    Logout
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle dropdown menu
    document.getElementById('profileDropdown').addEventListener('click', function() {
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