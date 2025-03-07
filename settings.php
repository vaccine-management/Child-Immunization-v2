<?php
// Include the auth check file
include 'includes/auth_check.php';

// Only allow admins to access this page
checkAdminRole();

// Rest of the page code
// ... existing code ... 