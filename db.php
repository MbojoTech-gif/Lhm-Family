<?php
// db.php - Database Connection File
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lhm-family');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, 'utf8');

// Function to sanitize input data
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// ============================
// AUTHENTICATION FUNCTIONS
// ============================

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is member
function isMember() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'member';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: access_denied.php');
        exit();
    }
}

// ============================
// ROLE MANAGEMENT FUNCTIONS
// ============================

// Get user's role from database
function getUserRole($user_id = null) {
    global $conn;
    
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
    }
    
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $query = mysqli_query($conn, "SELECT role FROM users WHERE id = '$user_id'");
    
    if (mysqli_num_rows($query) > 0) {
        $user = mysqli_fetch_assoc($query);
        return $user['role'];
    }
    
    return false;
}

// Check if user is a leader (admin or any leadership role)
function isLeader($user_id = null) {
    $role = getUserRole($user_id);
    $leader_roles = ['admin', 'chairman', 'secretary', 'treasurer', 
                     'music_director', 'spiritual_leader', 'publicity_leader'];
    return in_array($role, $leader_roles);
}

// Specific role check functions
function isChairman() {
    return getUserRole() == 'chairman';
}

function isSecretary() {
    return getUserRole() == 'secretary';
}

function isTreasurer() {
    return getUserRole() == 'treasurer';
}

function isMusicDirector() {
    return getUserRole() == 'music_director';
}

function isSpiritualLeader() {
    return getUserRole() == 'spiritual_leader';
}

function isPublicityLeader() {
    return getUserRole() == 'publicity_leader';
}

// ============================
// PERMISSION CHECK FUNCTIONS
// ============================

// Check if role can view a page
function canViewPage($role, $page_name) {
    global $conn;
    $page_name = mysqli_real_escape_string($conn, basename($page_name));
    $role = mysqli_real_escape_string($conn, $role);
    
    $query = mysqli_query($conn, 
        "SELECT can_view FROM page_permissions 
         WHERE role = '$role' AND page_name = '$page_name' 
         LIMIT 1"
    );
    
    if (mysqli_num_rows($query) > 0) {
        $permission = mysqli_fetch_assoc($query);
        return $permission['can_view'] == 1;
    }
    
    return false;
}

// Check if role can edit a page
function canEditPage($role, $page_name) {
    global $conn;
    $page_name = mysqli_real_escape_string($conn, basename($page_name));
    $role = mysqli_real_escape_string($conn, $role);
    
    $query = mysqli_query($conn, 
        "SELECT can_edit FROM page_permissions 
         WHERE role = '$role' AND page_name = '$page_name' 
         LIMIT 1"
    );
    
    if (mysqli_num_rows($query) > 0) {
        $permission = mysqli_fetch_assoc($query);
        return $permission['can_edit'] == 1;
    }
    
    return false;
}

// Main function to check page access (view or edit)
function checkPageAccess($page_name, $check_edit = false) {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get user role
    $role = getUserRole($user_id);
    if (!$role) {
        return false;
    }
    
    // Get page permissions
    $page_name = mysqli_real_escape_string($conn, basename($page_name));
    $query = mysqli_query($conn, 
        "SELECT can_view, can_edit FROM page_permissions 
         WHERE role = '$role' AND page_name = '$page_name' 
         LIMIT 1"
    );
    
    if (mysqli_num_rows($query) > 0) {
        $permission = mysqli_fetch_assoc($query);
        
        if ($check_edit) {
            return $permission['can_edit'] == 1;
        } else {
            return $permission['can_view'] == 1;
        }
    }
    
    return false;
}

// Function to check if current user can edit current page
function canEditCurrentPage() {
    $current_page = basename($_SERVER['PHP_SELF']);
    return checkPageAccess($current_page, true);
}

// Function to check if current user can view current page
function canViewCurrentPage() {
    $current_page = basename($_SERVER['PHP_SELF']);
    return checkPageAccess($current_page, false);
}

// Function to require specific role access
function requireRoleAccess($page_name, $require_edit = false) {
    requireLogin();
    
    if (!checkPageAccess($page_name, $require_edit)) {
        if ($require_edit) {
            $_SESSION['error'] = "You don't have permission to edit this page.";
        } else {
            $_SESSION['error'] = "You don't have permission to view this page.";
        }
        header('Location: access_denied.php');
        exit();
    }
}

// ============================
// HELPER FUNCTIONS
// ============================

// Get user's full name
function getUserFullName($user_id = null) {
    global $conn;
    
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return 'Guest';
        }
        $user_id = $_SESSION['user_id'];
    }
    
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $query = mysqli_query($conn, "SELECT full_name FROM users WHERE id = '$user_id'");
    
    if (mysqli_num_rows($query) > 0) {
        $user = mysqli_fetch_assoc($query);
        return htmlspecialchars($user['full_name']);
    }
    
    return 'Unknown User';
}

// Get user profile picture
function getUserProfilePic($user_id = null) {
    global $conn;
    
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return 'default.png';
        }
        $user_id = $_SESSION['user_id'];
    }
    
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $query = mysqli_query($conn, "SELECT profile_pic FROM users WHERE id = '$user_id'");
    
    if (mysqli_num_rows($query) > 0) {
        $user = mysqli_fetch_assoc($query);
        return $user['profile_pic'] ?: 'default.png';
    }
    
    return 'default.png';
}

// Get all pages user can access
function getUserAccessiblePages($user_id = null) {
    global $conn;
    
    $role = getUserRole($user_id);
    if (!$role) {
        return [];
    }
    
    $role = mysqli_real_escape_string($conn, $role);
    $query = mysqli_query($conn, 
        "SELECT page_name, can_view, can_edit 
         FROM page_permissions 
         WHERE role = '$role' AND can_view = 1
         ORDER BY page_name"
    );
    
    $pages = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $pages[] = $row;
    }
    
    return $pages;
}

// ============================
// ERROR HANDLING FUNCTIONS
// ============================

// Display error message
function displayError($message = 'An error occurred') {
    echo "<div class='alert alert-danger'>" . htmlspecialchars($message) . "</div>";
}

// Display success message
function displaySuccess($message = 'Operation successful') {
    echo "<div class='alert alert-success'>" . htmlspecialchars($message) . "</div>";
}

// Log activity
function logActivity($user_id, $action, $details = '') {
    global $conn;
    
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $action = mysqli_real_escape_string($conn, $action);
    $details = mysqli_real_escape_string($conn, $details);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $query = "INSERT INTO activity_logs (user_id, action, details, ip_address) 
              VALUES ('$user_id', '$action', '$details', '$ip_address')";
    
    return mysqli_query($conn, $query);
}

// ============================
// SESSION MANAGEMENT
// ============================

// Initialize session variables after login
function initSession($user_id) {
    global $conn;
    
    $user_id = mysqli_real_escape_string($conn, $user_id);
    $query = mysqli_query($conn, "SELECT id, username, full_name, role FROM users WHERE id = '$user_id'");
    
    if (mysqli_num_rows($query) > 0) {
        $user = mysqli_fetch_assoc($query);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        
        // Update last login
        mysqli_query($conn, "UPDATE users SET last_login = NOW() WHERE id = '$user_id'");
        
        return true;
    }
    
    return false;
}

// ============================
// DATABASE CLEANUP
// ============================

// Close database connection
function closeConnection() {
    global $conn;
    if ($conn) {
        mysqli_close($conn);
    }
}

// Register shutdown function to close connection
register_shutdown_function('closeConnection');
?>