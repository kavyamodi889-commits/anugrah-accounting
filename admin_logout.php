<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Log the logout activity before destroying session
if (isAdminLoggedIn()) {
    logActivity($conn, getAdminId(), 'LOGOUT', 'Admin logged out');
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect to admin login
header('Location: admin_login.php');
exit();
?>