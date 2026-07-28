<?php
/**
 * includes/db.php
 * Single, authoritative database configuration for Anugrah Accounting.
 * Environment-aware & Cloud-Ready (reads environment variables automatically).
 */

// ============================================================
// DATABASE CREDENTIALS (Environment Variables with Local Fallback)
// ============================================================
$dbHost = getenv('DB_HOST') ? getenv('DB_HOST') : (defined('DB_HOST') ? DB_HOST : 'localhost');
$dbUser = getenv('DB_USER') ? getenv('DB_USER') : (defined('DB_USER') ? DB_USER : 'root');
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (defined('DB_PASS') ? DB_PASS : '');
$dbName = getenv('DB_NAME') ? getenv('DB_NAME') : (defined('DB_NAME') ? DB_NAME : 'anugrah_accounting');
$dbPort = getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306;

if (!defined('DB_HOST')) define('DB_HOST', $dbHost);
if (!defined('DB_USER')) define('DB_USER', $dbUser);
if (!defined('DB_PASS')) define('DB_PASS', $dbPass);
if (!defined('DB_NAME')) define('DB_NAME', $dbName);
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ============================================================
// ERROR REPORTING — Environment Aware
// ============================================================
$appEnv = getenv('APP_ENV') ? getenv('APP_ENV') : 'production';
if (!defined('APP_ENV')) define('APP_ENV', $appEnv);

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// ============================================================
// DATABASE CONNECTION
// ============================================================
$conn = null;

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

    if ($conn->connect_error) {
        error_log('Database connection failed: ' . $conn->connect_error);
        die('Service temporarily unavailable. Please try again later.');
    }

    $conn->set_charset(DB_CHARSET);

} catch (Exception $e) {
    error_log('Database exception: ' . $e->getMessage());
    die('Service temporarily unavailable. Please try again later.');
}

/**
 * Returns the global database connection.
 */
function getDBConnection() {
    global $conn;
    return $conn;
}

/**
 * Sanitizes user input against XSS.
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Returns an existing user's ID by email, or creates a new user record.
 */
function getUserIdByEmail($conn, $email, $name, $phone = null) {
    $email = trim($email);
    $name  = trim($name);

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row    = $result->fetch_assoc();
        $userId = $row['id'];

        if (!empty($phone)) {
            $phone = trim($phone);
            $upd   = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $upd->bind_param("ssi", $name, $phone, $userId);
            $upd->execute();
            $upd->close();
        }
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO users (email, name, phone) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $name, $phone);
        $stmt->execute();
        $userId = $stmt->insert_id;
    }

    $stmt->close();
    return $userId;
}

/**
 * Logs activity to the activity_log table.
 */
function logActivity($conn, $userId, $action, $description = null) {
    $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;

    $stmt = $conn->prepare(
        "INSERT INTO activity_log (user_id, action, description, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issss", $userId, $action, $description, $ipAddress, $userAgent);
    $stmt->execute();
    $stmt->close();
}
?>
