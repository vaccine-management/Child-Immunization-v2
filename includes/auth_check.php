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
 * include 'includes/auth_check.php';
 * checkUserRole(['admin']); // Only allow admins
 * // or
 * checkUserRole(['admin', 'nurse']); // Allow both admins and nurses
 * ?>
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if the current user has one of the required roles
 * 
 * @param array $allowedRoles Array of allowed roles
 * @param string $redirectUrl URL to redirect if access denied (default: login.php)
 * @return void
 */
function checkUserRole($allowedRoles, $redirectUrl = 'login.php') {
    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        header('Location: ' . $redirectUrl);
        exit();
    }
    
    // Get user's role, default to 'nurse' if not set
    $userRole = strtolower($_SESSION['user']['role'] ?? 'nurse');
    
    // Check if user's role is in the allowed roles array
    if (!in_array($userRole, array_map('strtolower', $allowedRoles))) {
        // Redirect to dashboard with access denied message
        $_SESSION['error_message'] = 'Access denied. You do not have permission to view this page.';
        header('Location: dashboard.php');
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
    checkUserRole(['admin'], $redirectUrl);
}

/**
 * Check if the current user is a nurse
 * 
 * @param string $redirectUrl URL to redirect if access denied (default: dashboard.php)
 * @return void
 */
function checkNurseRole($redirectUrl = 'dashboard.php') {
    checkUserRole(['nurse'], $redirectUrl);
} 