<?php
// infinityfreedb.php - Database configuration for InfinityFree hosting
// ============================================
// YOUR INFINITYFREE DATABASE CREDENTIALS
// ============================================
define('DB_HOST', 'sql100.infinityfree.com');     // Your hostname
define('DB_USER', 'if0_40780205');                // Your MySQL username
define('DB_PASS', 'Mbojo2021');          // Your MySQL password
define('DB_NAME', 'if0_40780205_family');         // Your database name
// ============================================

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8mb4");

/**
 * Check if user is logged in
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Sanitize input to prevent SQL injection
 */
function sanitize($input) {
    global $conn;
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($input)));
}

/**
 * Check if user has permission to access a page
 */
function checkPageAccess($page, $requireEdit = false) {
    global $conn;
    
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    $role = $_SESSION['role'];
    $sql = "SELECT can_view, can_edit FROM page_permissions 
            WHERE role = ? AND page_name = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $role, $page);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if ($requireEdit) {
            return $row['can_edit'] == 1;
        }
        return $row['can_view'] == 1;
    }
    return false;
}

/**
 * Hash password using bcrypt
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password against hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Get user information by ID
 */
function getUserById($user_id) {
    global $conn;
    
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return mysqli_fetch_assoc($result);
}

/**
 * Get user information by email
 */
function getUserByEmail($email) {
    global $conn;
    
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return mysqli_fetch_assoc($result);
}

/**
 * Update user last login time
 */
function updateLastLogin($user_id) {
    global $conn;
    
    $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Check if email already exists
 */
function emailExists($email, $exclude_user_id = null) {
    global $conn;
    
    if ($exclude_user_id) {
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $email, $exclude_user_id);
    } else {
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($result) > 0;
}

/**
 * Get all users (for attendance, members list, etc.)
 */
function getAllUsers($order_by = 'full_name', $order_dir = 'ASC') {
    global $conn;
    
    $allowed_columns = ['id', 'full_name', 'email', 'role', 'voice_part', 'join_date'];
    $allowed_directions = ['ASC', 'DESC'];
    
    $order_by = in_array($order_by, $allowed_columns) ? $order_by : 'full_name';
    $order_dir = in_array($order_dir, $allowed_directions) ? $order_dir : 'ASC';
    
    $sql = "SELECT * FROM users WHERE status = 'active' ORDER BY $order_by $order_dir";
    $result = mysqli_query($conn, $sql);
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    return $users;
}

/**
 * Log an action to database
 */
function logAction($user_id, $action, $details = '') {
    global $conn;
    
    $sql = "INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $action, $details);
    return mysqli_stmt_execute($stmt);
}

/**
 * Create activity_log table if it doesn't exist
 */
function createActivityLogTable() {
    global $conn;
    
    $sql = "CREATE TABLE IF NOT EXISTS `activity_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `action` VARCHAR(100) NOT NULL,
        `details` TEXT,
        `ip_address` VARCHAR(45),
        `user_agent` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    )";
    
    return mysqli_query($conn, $sql);
}

/**
 * Close database connection
 */
function closeConnection() {
    global $conn;
    if ($conn) {
        mysqli_close($conn);
    }
}

// Create activity log table if it doesn't exist
createActivityLogTable();

// Register shutdown function to close connection
register_shutdown_function('closeConnection');

// Test function to verify connection
function testDatabaseConnection() {
    global $conn;
    
    if ($conn) {
        $result = mysqli_query($conn, "SELECT 1");
        if ($result) {
            return "✅ Database connection successful!";
        } else {
            return "❌ Database query failed: " . mysqli_error($conn);
        }
    } else {
        return "❌ Database connection not established";
    }
}

// Auto-login for testing (remove in production)
if (isset($_GET['test_login']) && $_GET['test_login'] == 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'System Administrator';
    $_SESSION['role'] = 'admin';
    $_SESSION['email'] = 'admin@lhm.com';
    header('Location: dashboard.php');
    exit();
}
?>