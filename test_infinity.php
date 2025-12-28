<?php
require_once 'infinityfreedb.php';

echo "<h2>InfinityFree Database Connection Test</h2>";
echo "<hr>";

// Test connection
echo "<h3>Connection Test:</h3>";
echo testDatabaseConnection();
echo "<br><br>";

// Show database info
echo "<h3>Database Info:</h3>";
echo "Host: " . DB_HOST . "<br>";
echo "Database: " . DB_NAME . "<br>";
echo "Username: " . DB_USER . "<br><br>";

// Show tables
echo "<h3>Tables in Database:</h3>";
$result = mysqli_query($conn, "SHOW TABLES");
if ($result) {
    while ($row = mysqli_fetch_array($result)) {
        echo "- " . $row[0] . "<br>";
        
        // Count rows in each table
        $count_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM `{$row[0]}`");
        if ($count_result) {
            $count_row = mysqli_fetch_assoc($count_result);
            echo "&nbsp;&nbsp;Records: " . $count_row['count'] . "<br>";
        }
    }
} else {
    echo "No tables found or error: " . mysqli_error($conn);
}

echo "<hr>";

// Test users table
echo "<h3>Users Table Test:</h3>";
$sql = "SHOW CREATE TABLE users";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    echo "✅ Users table exists<br>";
    
    // Show user count
    $count_sql = "SELECT COUNT(*) as count FROM users";
    $count_result = mysqli_query($conn, $count_sql);
    $count_row = mysqli_fetch_assoc($count_result);
    echo "Total users: " . $count_row['count'] . "<br>";
    
    // Show sample users
    $sample_sql = "SELECT id, username, email, role FROM users LIMIT 5";
    $sample_result = mysqli_query($conn, $sample_sql);
    if (mysqli_num_rows($sample_result) > 0) {
        echo "<br>Sample users:<br>";
        while ($user = mysqli_fetch_assoc($sample_result)) {
            echo "- {$user['username']} ({$user['email']}) - {$user['role']}<br>";
        }
    }
} else {
    echo "❌ Users table doesn't exist or error: " . mysqli_error($conn);
}

echo "<hr>";

// Quick login test link
echo "<h3>Quick Test:</h3>";
echo '<a href="test_infinity.php?test_login=admin">Test Admin Login</a> (will redirect to dashboard)';
?>