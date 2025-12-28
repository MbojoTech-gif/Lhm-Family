<?php
require_once 'db.php';
requireLogin();

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$view_sql = "SELECT can_view FROM page_permissions WHERE role = '$user_role' AND page_name = 'attendance.php'";
$view_result = mysqli_query($conn, $view_sql);

if (mysqli_num_rows($view_result) == 0) {
    header('Location: access_denied.php');
    exit();
}

$view_permission = mysqli_fetch_assoc($view_result);
if (!$view_permission['can_view']) {
    header('Location: access_denied.php');
    exit();
}

$edit_sql = "SELECT can_edit FROM page_permissions WHERE role = '$user_role' AND page_name = 'attendance.php'";
$edit_result = mysqli_query($conn, $edit_sql);
$edit_permission = mysqli_fetch_assoc($edit_result);
$can_edit = $edit_permission['can_edit'] == 1;

$current_month = date('n');
$current_year = date('Y');

$selected_month = isset($_GET['month']) ? intval($_GET['month']) : $current_month;
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;

if ($selected_month < 1 || $selected_month > 12) $selected_month = $current_month;
if ($selected_year < 2020 || $selected_year > 2030) $selected_year = $current_year;

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

$sundays = getSundaysInMonth($selected_month, $selected_year);

$members_sql = "SELECT id, full_name, voice_part FROM users ORDER BY full_name ASC";
$members_result = mysqli_query($conn, $members_sql);
$total_members = mysqli_num_rows($members_result);

$attendance_data = [];
if ($total_members > 0 && !empty($sundays)) {
    $first_day = "$selected_year-$selected_month-01";
    $last_day = date('Y-m-t', strtotime($first_day));
    
    $attendance_sql = "SELECT member_id, practice_date, status 
                       FROM attendance 
                       WHERE practice_date BETWEEN '$first_day' AND '$last_day'";
    $attendance_result = mysqli_query($conn, $attendance_sql);
    
    while ($row = mysqli_fetch_assoc($attendance_result)) {
        $attendance_data[$row['member_id']][$row['practice_date']] = $row['status'];
    }
}

if ($can_edit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_member_attendance'])) {
        $member_id = intval($_POST['member_id']);
        $attendance_date = sanitize($_POST['attendance_date']);
        $status = sanitize($_POST['status']);
        
        $date_obj = new DateTime($attendance_date);
        if ($date_obj->format('w') != 0) {
            $error = "Selected date must be a Sunday.";
        } else {
            $check_sql = "SELECT id FROM attendance WHERE member_id = '$member_id' AND practice_date = '$attendance_date'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                $sql = "UPDATE attendance SET status = '$status', recorded_by = '$user_id', recorded_at = NOW() 
                        WHERE member_id = '$member_id' AND practice_date = '$attendance_date'";
            } else {
                $sql = "INSERT INTO attendance (member_id, practice_date, status, recorded_by) 
                        VALUES ('$member_id', '$attendance_date', '$status', '$user_id')";
            }
            
            if (mysqli_query($conn, $sql)) {
                $success = "Attendance marked successfully!";
            } else {
                $error = "Error saving attendance: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['mark_bulk_attendance'])) {
        $attendance_date = sanitize($_POST['bulk_date']);
        $status = sanitize($_POST['bulk_status']);
        
        $date_obj = new DateTime($attendance_date);
        if ($date_obj->format('w') != 0) {
            $error = "Selected date must be a Sunday.";
        } else {
            $success_count = 0;
            $errors = [];
            
            mysqli_data_seek($members_result, 0);
            while ($member = mysqli_fetch_assoc($members_result)) {
                $member_id = $member['id'];
                
                $check_sql = "SELECT id FROM attendance WHERE member_id = '$member_id' AND practice_date = '$attendance_date'";
                $check_result = mysqli_query($conn, $check_sql);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $sql = "UPDATE attendance SET status = '$status', recorded_by = '$user_id', recorded_at = NOW() 
                            WHERE member_id = '$member_id' AND practice_date = '$attendance_date'";
                } else {
                    $sql = "INSERT INTO attendance (member_id, practice_date, status, recorded_by) 
                            VALUES ('$member_id', '$attendance_date', '$status', '$user_id')";
                }
                
                if (!mysqli_query($conn, $sql)) {
                    $errors[] = "Member {$member['full_name']}: " . mysqli_error($conn);
                } else {
                    $success_count++;
                }
            }
            
            if (empty($errors)) {
                $success = "Bulk attendance marked successfully!";
            } else {
                $error = "Saved $success_count records. Errors: " . implode(', ', $errors);
            }
        }
    }
}

$stats = [
    'total_members' => $total_members,
    'total_sundays' => count($sundays),
    'total_present' => 0,
    'total_absent' => 0,
    'attendance_rate' => 0
];

foreach ($attendance_data as $member_attendance) {
    foreach ($member_attendance as $status) {
        if ($status === 'present') $stats['total_present']++;
        if ($status === 'absent') $stats['total_absent']++;
    }
}

$total_marked = $stats['total_present'] + $stats['total_absent'];
if ($total_marked > 0) {
    $stats['attendance_rate'] = round(($stats['total_present'] / $total_marked) * 100, 1);
}

if ($can_edit && isset($_GET['download_report'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    $header = ['Member Name'];
    foreach ($sundays as $sunday) {
        $header[] = date('M j', strtotime($sunday));
    }
    $header[] = 'Present';
    $header[] = 'Absent';
    $header[] = 'Attendance Rate';
    fputcsv($output, $header);
    
    mysqli_data_seek($members_result, 0);
    while ($member = mysqli_fetch_assoc($members_result)) {
        $member_id = $member['id'];
        $present_count = 0;
        $absent_count = 0;
        $row = [$member['full_name']];
        
        foreach ($sundays as $sunday) {
            $status = $attendance_data[$member_id][$sunday] ?? '';
            $row[] = $status === 'present' ? 'P' : ($status === 'absent' ? 'A' : '');
            
            if ($status === 'present') $present_count++;
            if ($status === 'absent') $absent_count++;
        }
        
        $total = $present_count + $absent_count;
        $rate = $total > 0 ? round(($present_count / $total) * 100) . '%' : '0%';
        
        $row[] = $present_count;
        $row[] = $absent_count;
        $row[] = $rate;
        
        fputcsv($output, $row);
    }
    
    $summary = ['TOTAL'];
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
        
        $summary[] = "P:$present A:$absent";
        $total_present_all += $present;
        $total_absent_all += $absent;
    }
    
    $total_all = $total_present_all + $total_absent_all;
    $overall_rate = $total_all > 0 ? round(($total_present_all / $total_all) * 100) . '%' : '0%';
    
    $summary[] = $total_present_all;
    $summary[] = $total_absent_all;
    $summary[] = $overall_rate;
    
    fputcsv($output, $summary);
    
    fclose($output);
    exit;
}

$months = [];
for ($i = 1; $i <= 12; $i++) {
    $months[$i] = date('F', mktime(0, 0, 0, $i, 1));
}

$years = range(date('Y') - 1, date('Y') + 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Lighthouse Ministers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            color: #333;
            min-height: 100vh;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .sidebar-container.collapsed ~ .main-content {
            margin-left: 70px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                padding: 20px;
            }
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 2rem;
            color: #222;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .month-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .month-selectors {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .select-control {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            font-size: 0.95rem;
            min-width: 150px;
        }
        
        .stats-info {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            flex-wrap: wrap;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 200px;
        }
        
        .stat-icon {
            color: #007bff;
            font-size: 1.2rem;
        }
        
        .stat-text {
            font-size: 0.9rem;
            color: #666;
        }
        
        .stat-number {
            font-weight: 600;
            color: #222;
            font-size: 1.1rem;
        }
        
        .attendance-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            color: #222;
            font-weight: 600;
        }
        
        .controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .members-list {
            padding: 0 20px 20px;
        }
        
        .member-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .member-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .member-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #000;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .member-details h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #222;
            margin-bottom: 8px;
        }
        
        .member-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: #000;
            color: white;
        }
        
        .btn-primary:hover {
            background: #333;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: 1.2rem;
            color: #222;
            font-weight: 600;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .attendance-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #222;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .attendance-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        
        .attendance-table tr:hover {
            background: #f8f9fa;
        }
        
        .date-cell {
            min-width: 150px;
        }
        
        .status-cell {
            text-align: center;
            min-width: 120px;
        }
        
        .attendance-toggle {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .toggle-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .present-btn {
            background: #d4edda;
            color: #155724;
        }
        
        .present-btn.active {
            background: #28a745;
            color: white;
        }
        
        .present-btn:hover:not(.active) {
            background: #c3e6cb;
        }
        
        .absent-btn {
            background: #f8d7da;
            color: #721c24;
        }
        
        .absent-btn.active {
            background: #dc3545;
            color: white;
        }
        
        .absent-btn:hover:not(.active) {
            background: #f5c6cb;
        }
        
        .bulk-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #444;
        }
        
        .page-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .member-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .member-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .stats-info {
                flex-direction: column;
            }
            
            .stat-item {
                min-width: 100%;
            }
            
            .attendance-table {
                display: block;
                overflow-x: auto;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .controls {
                width: 100%;
                justify-content: space-between;
            }
            
            .month-controls {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .month-selectors {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> Attendance Management</h1>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="month-controls">
            <div class="month-selectors">
                <select id="monthSelect" class="select-control" onchange="changeMonth()">
                    <?php foreach ($months as $num => $name): ?>
                    <option value="<?php echo $num; ?>" <?php echo $selected_month == $num ? 'selected' : ''; ?>>
                        <?php echo $name; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <select id="yearSelect" class="select-control" onchange="changeMonth()">
                    <?php foreach ($years as $year): ?>
                    <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                        <?php echo $year; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="controls">
                <?php if ($can_edit): ?>
                <a href="attendance.php?download_report=1&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-download"></i> Download Report
                </a>
                
                <button class="btn btn-info" onclick="openBulkModal()">
                    <i class="fas fa-calendar-plus"></i> Bulk Mark
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="stats-info">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-text">
                    <div class="stat-number"><?php echo $stats['total_members']; ?></div>
                    <div>Total Members</div>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-text">
                    <div class="stat-number"><?php echo $stats['total_sundays']; ?></div>
                    <div>Sundays</div>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-text">
                    <div class="stat-number"><?php echo $stats['attendance_rate']; ?>%</div>
                    <div>Attendance Rate</div>
                </div>
            </div>
        </div>
        
        <div class="attendance-container">
            <div class="section-header">
                <h2>Members List</h2>
                <div class="controls">
                    <span><?php echo $total_members; ?> members</span>
                </div>
            </div>
            
            <div class="members-list">
                <?php if ($total_members > 0): ?>
                    <?php mysqli_data_seek($members_result, 0); ?>
                    <?php while ($member = mysqli_fetch_assoc($members_result)): 
                        $member_id = $member['id'];
                        $present_count = 0;
                        $absent_count = 0;
                        
                        foreach ($sundays as $sunday) {
                            $status = $attendance_data[$member_id][$sunday] ?? '';
                            if ($status === 'present') $present_count++;
                            if ($status === 'absent') $absent_count++;
                        }
                        
                        $total_member = $present_count + $absent_count;
                        $rate = $total_member > 0 ? round(($present_count / $total_member) * 100) : 0;
                    ?>
                    <div class="member-item">
                        <div class="member-info">
                            <div class="member-avatar">
                                <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                            </div>
                            <div class="member-details">
                                <h3><?php echo htmlspecialchars($member['full_name']); ?></h3>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <?php if (!empty($member['voice_part'])): ?>
                                    <span style="padding: 2px 8px; background: #e3f2fd; color: #1976d2; border-radius: 10px; font-size: 0.75rem;">
                                        <?php echo ucfirst($member['voice_part']); ?>
                                    </span>
                                    <?php endif; ?>
                                    <span style="font-size: 0.8rem; color: #666;">
                                        P:<strong style="color: #28a745;"><?php echo $present_count; ?></strong> 
                                        A:<strong style="color: #dc3545;"><?php echo $absent_count; ?></strong> 
                                        Rate:<strong style="color: #007bff;"><?php echo $rate; ?>%</strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="member-actions">
                            <button class="btn btn-primary" onclick="viewMemberAttendance(<?php echo htmlspecialchars(json_encode([
                                'id' => $member['id'],
                                'name' => $member['full_name']
                            ])); ?>)">
                                <i class="fas fa-eye"></i> View Attendance
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Members Found</h3>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="memberAttendanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalMemberName"></h3>
                <button class="close-modal" onclick="closeMemberModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="memberAttendanceContent"></div>
            </div>
        </div>
    </div>
    
    <?php if ($can_edit): ?>
    <div id="bulkModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-plus"></i> Bulk Mark Attendance</h3>
                <button class="close-modal" onclick="closeBulkModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="bulkAttendanceForm" class="bulk-form">
                    <div class="form-group">
                        <label for="bulk_date">Select Sunday</label>
                        <select id="bulk_date" name="bulk_date" class="form-control" required>
                            <option value="">-- Select a Sunday --</option>
                            <?php foreach ($sundays as $sunday): ?>
                            <option value="<?php echo $sunday; ?>">
                                <?php echo date('l, F j, Y', strtotime($sunday)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Mark All Members As</label>
                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                            <button type="button" class="btn btn-success" style="flex: 1;" onclick="setBulkStatus('present')">
                                <i class="fas fa-check"></i> All Present
                            </button>
                            <button type="button" class="btn btn-danger" style="flex: 1;" onclick="setBulkStatus('absent')">
                                <i class="fas fa-times"></i> All Absent
                            </button>
                        </div>
                        <input type="hidden" name="bulk_status" id="bulk_status" value="" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="mark_bulk_attendance" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-save"></i> Save Bulk Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        function changeMonth() {
            const month = document.getElementById('monthSelect').value;
            const year = document.getElementById('yearSelect').value;
            window.location.href = `attendance.php?month=${month}&year=${year}`;
        }
        
        function viewMemberAttendance(member) {
            document.getElementById('modalMemberName').textContent = member.name + "'s Attendance";
            
            document.getElementById('memberAttendanceContent').innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div class="loading" style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #007bff; border-radius: 50%; margin: 0 auto 20px; animation: spin 1s linear infinite;"></div>
                    <p>Loading attendance data...</p>
                </div>
                <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
            `;
            
            document.getElementById('memberAttendanceModal').style.display = 'flex';
            
            setTimeout(() => {
                loadMemberAttendance(member.id);
            }, 500);
        }
        
        function loadMemberAttendance(memberId) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `get_member_attendance.php?member_id=${memberId}&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&can_edit=<?php echo $can_edit ? 1 : 0; ?>`, true);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    document.getElementById('memberAttendanceContent').innerHTML = xhr.responseText;
                } else {
                    document.getElementById('memberAttendanceContent').innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> Error loading attendance data.
                        </div>
                    `;
                }
            };
            
            xhr.send();
        }
        
        function closeMemberModal() {
            document.getElementById('memberAttendanceModal').style.display = 'none';
        }
        
        function markMemberAttendance(memberId, date, status) {
            const canEdit = <?php echo $can_edit ? 'true' : 'false'; ?>;
            
            if (!canEdit) {
                alert("You don't have permission to mark attendance.");
                return;
            }
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'save_attendance.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        loadMemberAttendance(memberId);
                        showToast('Attendance updated successfully!', 'success');
                    } else {
                        showToast('Error: ' + response.message, 'error');
                    }
                } else {
                    showToast('Network error occurred', 'error');
                }
            };
            
            xhr.send(`member_id=${memberId}&date=${date}&status=${status}`);
        }
        
        function openBulkModal() {
            const canEdit = <?php echo $can_edit ? 'true' : 'false'; ?>;
            
            if (!canEdit) {
                alert("You don't have permission to mark bulk attendance.");
                return;
            }
            
            document.getElementById('bulkModal').style.display = 'flex';
        }
        
        function closeBulkModal() {
            document.getElementById('bulkModal').style.display = 'none';
            document.getElementById('bulkAttendanceForm').reset();
            document.getElementById('bulk_status').value = '';
        }
        
        function setBulkStatus(status) {
            document.getElementById('bulk_status').value = status;
            
            const buttons = document.querySelectorAll('#bulkAttendanceForm .btn');
            buttons.forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (status === 'present') {
                document.querySelector('#bulkAttendanceForm .btn-success').classList.add('active');
            } else {
                document.querySelector('#bulkAttendanceForm .btn-danger').classList.add('active');
            }
        }
        
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 9999;
                animation: slideIn 0.3s ease;
            `;
            
            if (type === 'success') {
                toast.style.background = '#28a745';
            } else if (type === 'error') {
                toast.style.background = '#dc3545';
            } else {
                toast.style.background = '#17a2b8';
            }
            
            toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${message}`;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
            
            if (!document.getElementById('toast-styles')) {
                const style = document.createElement('style');
                style.id = 'toast-styles';
                style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOut {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        window.onclick = function(event) {
            const memberModal = document.getElementById('memberAttendanceModal');
            const bulkModal = document.getElementById('bulkModal');
            
            if (memberModal && event.target === memberModal) {
                closeMemberModal();
            }
            if (bulkModal && event.target === bulkModal) {
                closeBulkModal();
            }
        }
        
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMemberModal();
                closeBulkModal();
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            function updateMainContentMargin() {
                const sidebar = document.querySelector('.sidebar-container');
                const mainContent = document.getElementById('mainContent');
                
                if (sidebar && mainContent) {
                    if (sidebar.classList.contains('collapsed')) {
                        mainContent.style.marginLeft = '70px';
                    } else {
                        mainContent.style.marginLeft = '250px';
                    }
                }
            }
            
            updateMainContentMargin();
            
            const sidebar = document.querySelector('.sidebar-container');
            if (sidebar) {
                const observer = new MutationObserver(updateMainContentMargin);
                observer.observe(sidebar, { 
                    attributes: true, 
                    attributeFilter: ['class'] 
                });
            }
        });
    </script>
</body>
</html>