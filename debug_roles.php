<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$user_id = $_SESSION['user_id'];

// Get user data directly
$query = mysqli_query($conn, "SELECT role, full_name FROM users WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($query);

echo "<h2>Debug Information for: {$user['full_name']}</h2>";
echo "<pre>";

// 1. Check session data
echo "SESSION DATA:\n";
print_r($_SESSION);

// 2. Check database data
echo "\nDATABASE DATA:\n";
echo "Role from DB: " . $user['role'] . "\n";

// 3. Check permissions in database
echo "\nPERMISSIONS IN DATABASE for role '{$user['role']}':\n";
$perm_query = mysqli_query($conn, 
    "SELECT page_name, can_view, can_edit FROM page_permissions 
     WHERE role = '{$user['role']}' 
     ORDER BY page_name"
);

if (mysqli_num_rows($perm_query) > 0) {
    while ($perm = mysqli_fetch_assoc($perm_query)) {
        echo "{$perm['page_name']}: View={$perm['can_view']}, Edit={$perm['can_edit']}\n";
    }
} else {
    echo "NO PERMISSIONS FOUND!\n";
}

// 4. Test checkPageAccess function
echo "\nFUNCTION TEST:\n";
$pages_to_test = ['announcements.php', 'itenary.php', 'suggestions.php'];
foreach ($pages_to_test as $page) {
    $can_view = checkPageAccess($page) ? 'YES' : 'NO';
    $can_edit = checkPageAccess($page, true) ? 'YES' : 'NO';
    echo "$page: View=$can_view, Edit=$can_edit\n";
}

// 5. Check all roles in database
echo "\nALL ROLES IN DATABASE:\n";
$roles_query = mysqli_query($conn, "SELECT DISTINCT role FROM users");
while ($role_row = mysqli_fetch_assoc($roles_query)) {
    echo "Role found: '{$role_row['role']}'\n";
}

echo "</pre>";
?>