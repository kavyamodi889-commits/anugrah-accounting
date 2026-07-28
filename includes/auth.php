<?php
/**
 * includes/auth.php
 * Unified authentication helpers for users and admins.
 * Include this file on any page that needs login protection.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// USER AUTH
// ============================================================

/**
 * Returns true if a regular user is currently logged in.
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Redirects to user login if not logged in.
 */
function requireUserLogin($redirect_to = '') {
    if (!isUserLoggedIn()) {
        $url = 'user_login.php';
        if (!empty($redirect_to)) {
            $url .= '?redirect=' . urlencode($redirect_to);
        }
        header('Location: ' . $url);
        exit();
    }
}

/**
 * Alias kept for backward compatibility with existing files.
 */
function requireLogin($redirect_to = '') {
    requireUserLogin($redirect_to);
}

function getUserName() {
    return isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') : 'Guest';
}

function getUserEmail() {
    return isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
}

function getUserId() {
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

// ============================================================
// ADMIN AUTH
// ============================================================

/**
 * Returns true if an admin is currently logged in.
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Redirects to admin login if not authenticated.
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: admin_login.php');
        exit();
    }
}

function getAdminName() {
    return isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name'], ENT_QUOTES, 'UTF-8') : 'Admin';
}

function getAdminRole() {
    return isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : '';
}

function getAdminId() {
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
}

// ============================================================
// PASSWORD UTILITIES
// ============================================================

/**
 * Hashes a plain-text password using bcrypt.
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verifies a plain-text password against a bcrypt hash.
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Checks if a hash needs to be rehashed (e.g. cost upgrade).
 */
function needsRehash($hash) {
    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
}
?>
