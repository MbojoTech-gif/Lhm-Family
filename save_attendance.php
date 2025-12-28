<?php
// save_attendance.php - Handle attendance saving via AJAX
require_once 'db.php';
requireLogin();

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = intval($_POST['member_id']);
    $practice_date = isset($_POST['date']) ? sanitize($_POST['date']) : sanitize($_POST['practice_date']);
    $status = sanitize($_POST['status']);
    $user_id = $_SESSION['user_id'];
    
    // Check if exists
    $check_sql = "SELECT id FROM attendance WHERE member_id = '$member_id' AND practice_date = '$practice_date'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $sql = "UPDATE attendance SET status = '$status', recorded_by = '$user_id', recorded_at = NOW() 
                WHERE member_id = '$member_id' AND practice_date = '$practice_date'";
    } else {
        $sql = "INSERT INTO attendance (member_id, practice_date, status, recorded_by) 
                VALUES ('$member_id', '$practice_date', '$status', '$user_id')";
    }
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Attendance saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
    }
}
?>