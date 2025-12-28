<?php
// logout.php - Simple version
require_once 'db.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
?>