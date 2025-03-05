<?php
// Start session
session_start();

// Check if user is logged in
if (isset($_SESSION['user'])) {
    // Redirect to the dashboard
    header('Location: dashboard.php');
    exit();
} else {
    // Redirect to login page
    header('Location: login.php');
    exit();
}
?> 