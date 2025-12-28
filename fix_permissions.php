<?php
// fix_permissions.php - Fix upload directory permissions
session_start();

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admin only.");
}

$upload_dir = "assets/uploads/";
$profiles_dir = $upload_dir . "profiles/";

echo "<h2>Fixing Directory Permissions</h2>";

// Create main uploads directory if it doesn't exist
if (!file_exists($upload_dir)) {
    if (mkdir($upload_dir, 0755, true)) {
        echo "✓ Created uploads directory: $upload_dir<br>";
    } else {
        echo "✗ Failed to create uploads directory<br>";
    }
}

// Create profiles directory if it doesn't exist
if (!file_exists($profiles_dir)) {
    if (mkdir($profiles_dir, 0755, true)) {
        echo "✓ Created profiles directory: $profiles_dir<br>";
    } else {
        echo "✗ Failed to create profiles directory<br>";
    }
}

// Set permissions
if (file_exists($upload_dir)) {
    if (chmod($upload_dir, 0755)) {
        echo "✓ Set uploads directory permissions to 755<br>";
    } else {
        echo "✗ Failed to set uploads directory permissions<br>";
    }
}

if (file_exists($profiles_dir)) {
    if (chmod($profiles_dir, 0755)) {
        echo "✓ Set profiles directory permissions to 755<br>";
    } else {
        echo "✗ Failed to set profiles directory permissions<br>";
    }
    
    // Create .htaccess to protect the directory
    $htaccess = $profiles_dir . ".htaccess";
    $htcontent = "Options -Indexes\n";
    $htcontent .= "Order Deny,Allow\n";
    $htcontent .= "Allow from all\n";
    $htcontent .= "<FilesMatch \"\.(php|php5|php7|phtml)$\">\n";
    $htcontent .= "    Deny from all\n";
    $htcontent .= "</FilesMatch>\n";
    
    if (file_put_contents($htaccess, $htcontent)) {
        echo "✓ Created .htaccess protection file<br>";
    } else {
        echo "✗ Failed to create .htaccess file<br>";
    }
}

// Test file writing
$test_file = $profiles_dir . "test.txt";
if (file_put_contents($test_file, "test")) {
    echo "✓ Can write to profiles directory<br>";
    unlink($test_file);
} else {
    echo "✗ Cannot write to profiles directory<br>";
}

echo "<h2>Current Status</h2>";
echo "Uploads directory exists: " . (file_exists($upload_dir) ? "Yes" : "No") . "<br>";
echo "Profiles directory exists: " . (file_exists($profiles_dir) ? "Yes" : "No") . "<br>";
echo "Uploads directory writable: " . (is_writable($upload_dir) ? "Yes" : "No") . "<br>";
echo "Profiles directory writable: " . (is_writable($profiles_dir) ? "Yes" : "No") . "<br>";

// List existing files
if (file_exists($profiles_dir)) {
    echo "<h3>Existing Profile Pictures</h3>";
    $files = scandir($profiles_dir);
    foreach ($files as $file) {
        if ($file != "." && $file != ".." && $file != ".htaccess") {
            $filepath = $profiles_dir . $file;
            echo "- $file (" . filesize($filepath) . " bytes, " . 
                 date("Y-m-d H:i:s", filemtime($filepath)) . ")<br>";
        }
    }
}
?>