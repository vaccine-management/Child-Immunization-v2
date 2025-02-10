<!-- Fixed Navbar -->
<div class="fixed top-0 left-0 w-full bg-gray-800 shadow-lg z-50">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
        <div class="text-xl font-bold text-white">Immunization System</div>
        <div class="relative">
            <button id="profileDropdown" class="flex items-center space-x-2 focus:outline-none">
                <img src="https://via.placeholder.com/40" alt="User" class="rounded-full">
                <span class="text-white"><?php echo $_SESSION['user']['name']; ?></span>
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <!-- Dropdown Menu -->
            <div id="dropdownMenu" class="hidden absolute right-0 mt-2 w-48 bg-gray-700 rounded-lg shadow-lg">
                <a href="#" class="block px-4 py-2 text-white hover:bg-gray-600">Profile</a>
                <a href="logout.php" class="block px-4 py-2 text-white hover:bg-gray-600">Logout</a>
            </div>
        </div>
    </div>
</div>