<?php
require_once 'includes/auth.php';

// Log activity if user was logged in
if (isUserLoggedIn()) {
    require_once 'includes/db.php';
    logActivity($conn, getUserId(), 'LOGOUT', 'User logged out');
}

session_unset();
session_destroy();

header("Location: index.php");
exit();
?>