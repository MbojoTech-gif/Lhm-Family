<?php
// export_attendance.php - Export attendance to CSV
require_once 'db.php';
requireLogin();

$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get all Sundays in the selected month
function getSundaysInMonth($month, $year) {
    $sundays = [];
    $date = new DateTime("first Sunday of $year-$month");
    $monthEnd = new DateTime("$year-$month-01");
    $monthEnd->modify('last day of this month');
    
    while ($date <= $monthEnd) {
        if ($date->format('n') == $month) {
            $sundays[] = $date->format('Y-m-d');
        }
        $date->modify('+7 days');
    }
    
    return $sundays;
}

$sundays = getSundaysInMonth($month, $year);

// Get all active members
$members_sql = "SELECT id, full_name, email, voice_part FROM users WHERE role != 'admin' AND status = 'active' ORDER BY full_name ASC";
$members_result = mysqli_query($conn, $members_sql);

// Get attendance data
$attendance_data = [];
if (!empty($sundays)) {
    $first_day = "$year-$month-01";
    $last_day = date('Y-m-t', strtotime($first_day));
    
    $attendance_sql = "SELECT member_id, practice_date, status FROM attendance 
                       WHERE practice_date BETWEEN '$first_day' AND '$last_day'";
    $attendance_result = mysqli_query($conn, $attendance_sql);
    
    while ($row = mysqli_fetch_assoc($attendance_result)) {
        $attendance_data[$row['member_id']][$row['practice_date']] = $row['status'];
    }
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendance_' . date('F_Y', mktime(0, 0, 0, $month, 1, $year)) . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Write header row
$header = ['Member Name', 'Voice Part', 'Email'];
foreach ($sundays as $sunday) {
    $header[] = date('M j', strtotime($sunday));
}
$header[] = 'Present';
$header[] = 'Absent';
$header[] = 'Attendance Rate';
fputcsv($output, $header);

// Write member rows
mysqli_data_seek($members_result, 0);
while ($member = mysqli_fetch_assoc($members_result)) {
    $member_id = $member['id'];
    $present_count = 0;
    $absent_count = 0;
    $row = [
        $member['full_name'],
        ucfirst($member['voice_part'] ?? ''),
        $member['email']
    ];
    
    foreach ($sundays as $sunday) {
        $status = $attendance_data[$member_id][$sunday] ?? '';
        $row[] = $status === 'present' ? 'P' : ($status === 'absent' ? 'A' : '');
        
        if ($status === 'present') $present_count++;
        if ($status === 'absent') $absent_count++;
    }
    
    $total = $present_count + $absent_count;
    $rate = $total > 0 ? round(($present_count / $total) * 100) : 0;
    
    $row[] = $present_count;
    $row[] = $absent_count;
    $row[] = $rate . '%';
    
    fputcsv($output, $row);
}

// Write summary row
$summary = ['TOTAL', '', ''];
$total_present_all = 0;
$total_absent_all = 0;

foreach ($sundays as $sunday) {
    $present = 0;
    $absent = 0;
    
    mysqli_data_seek($members_result, 0);
    while ($member = mysqli_fetch_assoc($members_result)) {
        $member_id = $member['id'];
        $status = $attendance_data[$member_id][$sunday] ?? '';
        if ($status === 'present') $present++;
        if ($status === 'absent') $absent++;
    }
    mysqli_data_seek($members_result, 0);
    
    $summary[] = "P:$present/A:$absent";
    $total_present_all += $present;
    $total_absent_all += $absent;
}

$total_all = $total_present_all + $total_absent_all;
$overall_rate = $total_all > 0 ? round(($total_present_all / $total_all) * 100) : 0;

$summary[] = $total_present_all;
$summary[] = $total_absent_all;
$summary[] = $overall_rate . '%';

fputcsv($output, $summary);

// Close output
fclose($output);
exit;
?>