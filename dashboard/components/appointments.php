<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to the main appointments.php file in the root directory
header('Location: ../../appointments.php');
exit();
?>
