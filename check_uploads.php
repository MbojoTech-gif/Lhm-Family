<?php
// check_uploads.php - Debug upload directory
$upload_dir = "assets/uploads/profiles/";

echo "<h2>Upload Directory Check</h2>";
echo "Directory path: " . realpath($upload_dir) . "<br>";

// Check if directory exists
if (file_exists($upload_dir)) {
    echo "✓ Directory exists<br>";
    
    // Check if directory is writable
    if (is_writable($upload_dir)) {
        echo "✓ Directory is writable<br>";
    } else {
        echo "✗ Directory is NOT writable<br>";
        echo "Current permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "<br>";
    }
} else {
    echo "✗ Directory does not exist<br>";
    
    // Try to create it
    if (mkdir($upload_dir, 0777, true)) {
        echo "✓ Directory created successfully<br>";
        echo "New permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "<br>";
    } else {
        echo "✗ Failed to create directory<br>";
    }
}

// Check if we can create a test file
$test_file = $upload_dir . "test.txt";
if (file_put_contents($test_file, "test")) {
    echo "✓ Can write files to directory<br>";
    unlink($test_file);
} else {
    echo "✗ Cannot write files to directory<br>";
}

// Show PHP upload settings
echo "<h2>PHP Upload Settings</h2>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";

// Show current directory structure
echo "<h2>Current Directory Structure</h2>";
function listDirectory($dir, $indent = "") {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != "." && $file != "..") {
            $path = $dir . "/" . $file;
            echo $indent . $file;
            if (is_dir($path)) {
                echo "/<br>";
                listDirectory($path, $indent . "&nbsp;&nbsp;&nbsp;&nbsp;");
            } else {
                echo " (" . filesize($path) . " bytes)<br>";
            }
        }
    }
}

if (file_exists($upload_dir)) {
    listDirectory($upload_dir);
}
?>