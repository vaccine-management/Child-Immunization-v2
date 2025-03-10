<?php
/**
 * Role-based Access Control for protecting pages
 * 
 * Usage:
 * 1. Include this file at the top of any page that requires role-based access
 * 2. Use the checkUserRole() function with the required role(s)
 * 
 * Example:
 * <?php
 * include 'includes/auth_check.php'; // or '../includes/auth_check.php' if in subdirectory
 * checkUserRole(['admin']); // Only allow admins
 * // or
 * checkUserRole(['admin', 'nurse']); // Allow both admins and nurses
 * ?>
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect if the script is in admin_pages directory
$in_admin_dir = strpos($_SERVER['SCRIPT_FILENAME'], 'admin_pages') !== false;
$base_path = $in_admin_dir ? '../' : '';

/**
 * Check if the current user has one of the required roles
 * 
 * @param array $allowedRoles Array of allowed roles
 * @param string $redirectUrl URL to redirect if access denied (default: login.php)
 * @return void
 */
function checkUserRole($allowedRoles, $redirectUrl = 'login.php') {
    global $base_path;
    
    // Adjust redirect URL based on directory
    $adjusted_redirect = strpos($redirectUrl, '/') === false ? $base_path . $redirectUrl : $redirectUrl;
    
    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        header("Location: {$adjusted_redirect}");
        exit();
    }
    
    // Get user's role, default to 'nurse' if not set
    $userRole = strtolower($_SESSION['user']['role'] ?? 'nurse');
    
    // Check if user's role is in the allowed roles array
    if (!in_array($userRole, array_map('strtolower', $allowedRoles))) {
        // Redirect to dashboard with access denied message
        $_SESSION['error_message'] = 'Access denied. You do not have permission to view this page.';
        header("Location: {$base_path}dashboard.php");
        exit();
    }
}

/**
 * Check if the current user is an admin
 * 
 * @param string $redirectUrl URL to redirect if access denied (default: dashboard.php)
 * @return void
 */
function checkAdminRole($redirectUrl = 'dashboard.php') {
    global $base_path;
    
    // Adjust redirect URL based on directory
    $adjusted_redirect = strpos($redirectUrl, '/') === false ? $base_path . $redirectUrl : $redirectUrl;
    
    checkUserRole(['admin'], $adjusted_redirect);
}

/**
 * Check if the current user is a nurse
 * 
 * @param string $redirectUrl URL to redirect if access denied (default: dashboard.php)
 * @return void
 */
function checkNurseRole($redirectUrl = 'dashboard.php') {
    global $base_path;
    
    // Adjust redirect URL based on directory
    $adjusted_redirect = strpos($redirectUrl, '/') === false ? $base_path . $redirectUrl : $redirectUrl;
    
    checkUserRole(['nurse'], $adjusted_redirect);
} 