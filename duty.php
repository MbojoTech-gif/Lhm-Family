<?php
// duty.php - Duty Roster Management
require_once 'db.php';
requireLogin();

// Check if user can view this page
if (!checkPageAccess('duty.php')) {
    header('Location: access_denied.php');
    exit();
}

// Check if user can edit this page
$can_edit = checkPageAccess('duty.php', true);

$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

// Get selected week from URL or default to current week
$selected_week = isset($_GET['week']) ? sanitize($_GET['week']) : $current_week_start;

// Calculate week range
$week_start = date('Y-m-d', strtotime($selected_week));
$week_end = date('Y-m-d', strtotime($selected_week . ' +6 days'));

// Format for display
$week_display = date('jS', strtotime($week_start)) . ' - ' . date('jS F Y', strtotime($week_end));

// Handle form submissions (Only users with edit permission)
if ($can_edit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_duty'])) {
        // Add new duty roster
        $week_start_input = sanitize($_POST['week_start']);
        $week_end_input = date('Y-m-d', strtotime($week_start_input . ' +6 days'));
        
        // Get member IDs (limit 2)
        $member1 = !empty($_POST['member1']) ? intval($_POST['member1']) : NULL;
        $member2 = !empty($_POST['member2']) ? intval($_POST['member2']) : NULL;
        
        // Check if members are the same
        if ($member1 && $member2 && $member1 == $member2) {
            $error = "Cannot assign the same member twice!";
        } else {
            $notes = sanitize($_POST['notes']);
            
            // Check if duty already exists for this week
            $check_sql = "SELECT id FROM duty_roster WHERE week_start = '$week_start_input'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                $error = "Duty roster already exists for this week!";
            } else {
                $sql = "INSERT INTO duty_roster (week_start, week_end, member1_id, member2_id, notes, created_by) 
                        VALUES ('$week_start_input', '$week_end_input', '$member1', '$member2', '$notes', '$user_id')";
                
                if (mysqli_query($conn, $sql)) {
                    $success = "Duty roster added successfully!";
                    // Add to log
                    $duty_id = mysqli_insert_id($conn);
                    $log_sql = "INSERT INTO duty_log (duty_id, user_id, action, details) 
                               VALUES ('$duty_id', '$user_id', 'create', 'Created duty roster for week $week_start_input to $week_end_input')";
                    mysqli_query($conn, $log_sql);
                } else {
                    $error = "Error adding duty roster: " . mysqli_error($conn);
                }
            }
        }
    } elseif (isset($_POST['edit_duty'])) {
        // Edit duty roster
        $duty_id = intval($_POST['duty_id']);
        
        // Get member IDs (limit 2)
        $member1 = !empty($_POST['member1']) ? intval($_POST['member1']) : NULL;
        $member2 = !empty($_POST['member2']) ? intval($_POST['member2']) : NULL;
        
        // Check if members are the same
        if ($member1 && $member2 && $member1 == $member2) {
            $error = "Cannot assign the same member twice!";
        } else {
            $notes = sanitize($_POST['notes']);
            $status = sanitize($_POST['status']);
            
            // Get old data for log
            $old_sql = "SELECT * FROM duty_roster WHERE id = '$duty_id'";
            $old_result = mysqli_query($conn, $old_sql);
            $old_data = mysqli_fetch_assoc($old_result);
            
            $sql = "UPDATE duty_roster SET 
                    member1_id = '$member1', 
                    member2_id = '$member2', 
                    notes = '$notes', 
                    status = '$status' 
                    WHERE id = '$duty_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Duty roster updated successfully!";
                // Add to log
                $details = "Updated duty roster. Changes: ";
                $changes = [];
                if ($old_data['member1_id'] != $member1) $changes[] = "Member 1";
                if ($old_data['member2_id'] != $member2) $changes[] = "Member 2";
                if ($old_data['notes'] != $notes) $changes[] = "Notes";
                if ($old_data['status'] != $status) $changes[] = "Status";
                
                if (!empty($changes)) {
                    $log_details = "Updated: " . implode(", ", $changes);
                    $log_sql = "INSERT INTO duty_log (duty_id, user_id, action, details) 
                               VALUES ('$duty_id', '$user_id', 'update', '$log_details')";
                    mysqli_query($conn, $log_sql);
                }
            } else {
                $error = "Error updating duty roster: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['delete_duty'])) {
        // Delete duty roster - Only admin can delete
        if (!isAdmin()) {
            $error = "Only administrators can delete duty rosters.";
        } else {
            $duty_id = intval($_POST['duty_id']);
            
            // Get data for log before deleting
            $old_sql = "SELECT * FROM duty_roster WHERE id = '$duty_id'";
            $old_result = mysqli_query($conn, $old_sql);
            $old_data = mysqli_fetch_assoc($old_result);
            
            $sql = "DELETE FROM duty_roster WHERE id = '$duty_id'";
            if (mysqli_query($conn, $sql)) {
                $success = "Duty roster deleted successfully!";
                // Add to log
                $log_sql = "INSERT INTO duty_log (duty_id, user_id, action, details) 
                           VALUES ('$duty_id', '$user_id', 'delete', 'Deleted duty roster for week " . $old_data['week_start'] . " to " . $old_data['week_end'] . "')";
                mysqli_query($conn, $log_sql);
            } else {
                $error = "Error deleting duty roster: " . mysqli_error($conn);
            }
        }
    }
}

// Get all active members for dropdowns
$members_sql = "SELECT id, full_name, username FROM users WHERE status = 'active' ORDER BY full_name";
$members_result = mysqli_query($conn, $members_sql);

// Get current duty roster
$duty_sql = "SELECT dr.*, 
             m1.full_name as member1_name,
             m2.full_name as member2_name,
             u.full_name as created_by_name
             FROM duty_roster dr
             LEFT JOIN users m1 ON dr.member1_id = m1.id
             LEFT JOIN users m2 ON dr.member2_id = m2.id
             LEFT JOIN users u ON dr.created_by = u.id
             WHERE dr.week_start = '$week_start'
             LIMIT 1";
$duty_result = mysqli_query($conn, $duty_sql);
$duty_data = mysqli_fetch_assoc($duty_result);

// Get upcoming duties (next 4 weeks)
$upcoming_sql = "SELECT dr.*, 
                m1.full_name as member1_name,
                m2.full_name as member2_name
                FROM duty_roster dr
                LEFT JOIN users m1 ON dr.member1_id = m1.id
                LEFT JOIN users m2 ON dr.member2_id = m2.id
                WHERE dr.week_start >= '$current_week_start'
                ORDER BY dr.week_start
                LIMIT 4";
$upcoming_result = mysqli_query($conn, $upcoming_sql);

// Get user's upcoming duties (for members)
$user_upcoming_sql = "SELECT dr.*,
                     CASE 
                         WHEN dr.member1_id = '$user_id' THEN 'Member 1'
                         WHEN dr.member2_id = '$user_id' THEN 'Member 2'
                     END as duty_position
                     FROM duty_roster dr
                     WHERE (dr.member1_id = '$user_id' OR dr.member2_id = '$user_id')
                     AND dr.week_start >= '$current_week_start'
                     ORDER BY dr.week_start
                     LIMIT 5";
$user_upcoming_result = mysqli_query($conn, $user_upcoming_sql);

// Calculate weeks for navigation
$prev_week = date('Y-m-d', strtotime($week_start . ' -7 days'));
$next_week = date('Y-m-d', strtotime($week_start . ' +7 days'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Include favicon -->
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duty Roster - Lighthouse Ministers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Main Content Styles */
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
        
        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 2rem;
            color: #222;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #666;
            font-size: 1rem;
        }
        
        /* Week Navigation */
        .week-navigation {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .week-display {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .week-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #222;
        }
        
        .week-dates {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .week-arrows {
            display: flex;
            gap: 10px;
        }
        
        .week-btn {
            background: #000;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .week-btn:hover {
            background: #333;
        }
        
        .week-btn.today {
            background: #007bff;
        }
        
        .week-btn.today:hover {
            background: #0056b3;
        }
        
        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Current Duty Section */
        .current-duty {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            color: #222;
            font-weight: 600;
        }
        
        .add-duty-btn, .edit-duty-btn {
            background: #000;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-duty-btn:hover, .edit-duty-btn:hover {
            background: #333;
        }
        
        /* Duty Members Section */
        .duty-members {
            padding: 25px;
        }
        
        .members-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 576px) {
            .members-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .member-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            border: 2px solid transparent;
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        
        .member-card:hover {
            transform: translateY(-3px);
        }
        
        .member-card.member1 {
            border-color: #007bff;
        }
        
        .member-card.member2 {
            border-color: #28a745;
        }
        
        .member-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #000, #333);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.5rem;
            margin: 0 auto 15px;
        }
        
        .member-card.member1 .member-avatar {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        
        .member-card.member2 .member-avatar {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        
        .member-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #222;
            margin-bottom: 5px;
        }
        
        .member-position {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 15px;
        }
        
        .member-status {
            display: inline-block;
            padding: 5px 12px;
            background: #000;
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .member-card.member1 .member-status {
            background: #007bff;
        }
        
        .member-card.member2 .member-status {
            background: #28a745;
        }
        
        /* Schedule Info */
        .schedule-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .schedule-info h3 {
            font-size: 1.1rem;
            color: #222;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .schedule-days {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .schedule-day {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #000;
        }
        
        .schedule-day.wednesday { border-left-color: #007bff; }
        .schedule-day.friday { border-left-color: #28a745; }
        .schedule-day.sunday { border-left-color: #ffc107; }
        
        .day-title {
            font-weight: 600;
            color: #222;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .day-time {
            font-size: 0.9rem;
            color: #666;
        }
        
        /* Status Badge */
        .duty-status {
            padding: 0 25px 25px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-completed { background: #e2e3e5; color: #383d41; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        /* Notes Section */
        .notes-section {
            padding: 20px;
            border-top: 1px solid #e0e0e0;
            background: #f8f9fa;
        }
        
        .notes-section h4 {
            font-size: 1rem;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .notes-content {
            font-size: 0.95rem;
            color: #555;
            line-height: 1.5;
        }
        
        /* Sidebar Sections */
        .sidebar-section {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .sidebar-section:last-child {
            margin-bottom: 0;
        }
        
        .sidebar-section .section-header {
            padding: 15px;
        }
        
        .sidebar-section .section-header h3 {
            font-size: 1.1rem;
            color: #222;
            font-weight: 600;
        }
        
        /* My Upcoming Duties */
        .my-duties-list {
            padding: 15px;
        }
        
        .my-duty-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .my-duty-item:last-child {
            border-bottom: none;
        }
        
        .my-duty-week {
            font-weight: 600;
            color: #222;
            margin-bottom: 5px;
        }
        
        .my-duty-position {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 5px;
        }
        
        .my-duty-position.member1 { background: rgba(0, 123, 255, 0.1); color: #007bff; }
        .my-duty-position.member2 { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        
        /* Upcoming Weeks */
        .upcoming-list {
            padding: 15px;
        }
        
        .upcoming-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .upcoming-item:last-child {
            border-bottom: none;
        }
        
        .upcoming-week {
            font-weight: 600;
            color: #222;
            margin-bottom: 5px;
        }
        
        .upcoming-members {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }
        
        /* Footer */
        .page-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.85rem;
        }
        
        /* Modal Styles */
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
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
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
        
        .form-control:focus {
            outline: none;
            border-color: #000;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .member-select-group {
            margin-bottom: 15px;
        }
        
        .member-select-group label {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .btn-submit {
            background: #000;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-submit:hover {
            background: #333;
        }
        
        /* Delete Button */
        .delete-form {
            display: inline;
        }
        
        .delete-btn {
            background: #dc3546;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: opacity 0.3s;
        }
        
        .delete-btn:hover {
            opacity: 0.9;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
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
        
        /* Warning Message */
        .warning-message {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Duty Roster</h1>
            <p>Weekly duty assignments for Vespers (Wednesday & Friday) and Sunday Practice.</p>
        </div>
        
     
        
        <!-- Week Navigation -->
        <div class="week-navigation">
            <div class="week-display">
                <div class="week-title">Duty Week</div>
                <div class="week-dates"><?php echo $week_display; ?></div>
            </div>
            <div class="week-arrows">
                <a href="?week=<?php echo $prev_week; ?>" class="week-btn" title="Previous Week">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <a href="?week=<?php echo $current_week_start; ?>" class="week-btn today" title="Current Week">
                    <i class="fas fa-calendar-alt"></i>
                </a>
                <a href="?week=<?php echo $next_week; ?>" class="week-btn" title="Next Week">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
        
        <!-- Warning Message -->
        <div class="warning-message">
            <i class="fas fa-info-circle"></i> 
            <strong>Note:</strong> Two members are assigned for the entire week. They will cover all sessions.
        </div>
        
        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Current Duty Section -->
            <div class="current-duty">
                <div class="section-header">
                    <h2>Weekly Duty Assignments</h2>
                    <?php if ($can_edit): ?>
                        <?php if ($duty_data): ?>
                        <button class="edit-duty-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($duty_data)); ?>)">
                            <i class="fas fa-edit"></i> Edit Duty
                        </button>
                        <?php else: ?>
                        <button class="add-duty-btn" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Duty Roster
                        </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($duty_data): ?>
                <!-- Duty Members -->
                <div class="duty-members">
                    <div class="members-grid">
                        <!-- Member 1 -->
                        <div class="member-card member1">
                            <?php if ($duty_data['member1_name']): ?>
                            <div class="member-avatar">
                                <?php echo strtoupper(substr($duty_data['member1_name'], 0, 1)); ?>
                            </div>
                            <div class="member-name"><?php echo htmlspecialchars($duty_data['member1_name']); ?></div>
                            <div class="member-position">Primary Duty Member</div>
                            <div class="member-status">On Duty</div>
                            <?php else: ?>
                            <div class="member-avatar">
                                <i class="fas fa-user-slash"></i>
                            </div>
                            <div class="member-name">Not Assigned</div>
                            <div class="member-position">Primary Duty Member</div>
                            <div class="member-status" style="background: #6c757d;">Vacant</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Member 2 -->
                        <div class="member-card member2">
                            <?php if ($duty_data['member2_name']): ?>
                            <div class="member-avatar">
                                <?php echo strtoupper(substr($duty_data['member2_name'], 0, 1)); ?>
                            </div>
                            <div class="member-name"><?php echo htmlspecialchars($duty_data['member2_name']); ?></div>
                            <div class="member-position">Secondary Duty Member</div>
                            <div class="member-status">On Duty</div>
                            <?php else: ?>
                            <div class="member-avatar">
                                <i class="fas fa-user-slash"></i>
                            </div>
                            <div class="member-name">Not Assigned</div>
                            <div class="member-position">Secondary Duty Member</div>
                            <div class="member-status" style="background: #6c757d;">Vacant</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Schedule Info -->
                    <div class="schedule-info">
                        <h3><i class="fas fa-calendar-week"></i> Weekly Schedule</h3>
                        <div class="schedule-days">
                            <div class="schedule-day wednesday">
                                <div class="day-title">
                                    <i class="fas fa-pray"></i> Wednesday Vespers
                                </div>
                                <div class="day-time">8:00 PM - 9:00 PM</div>
                            </div>
                            <div class="schedule-day friday">
                                <div class="day-title">
                                    <i class="fas fa-pray"></i> Friday Vespers
                                </div>
                                <div class="day-time">8:00 PM - 9:00 PM</div>
                            </div>
                            <div class="schedule-day sunday">
                                <div class="day-title">
                                    <i class="fas fa-church"></i> Sunday Practice
                                </div>
                                <div class="day-time">10:00 AM - 1:00 PM</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Badge -->
                    <div class="duty-status">
                        <span class="status-badge status-<?php echo $duty_data['status']; ?>">
                            <?php echo ucfirst($duty_data['status']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Notes Section -->
                <?php if ($duty_data['notes']): ?>
                <div class="notes-section">
                    <h4><i class="fas fa-sticky-note"></i> Notes & Instructions</h4>
                    <div class="notes-content">
                        <?php echo nl2br(htmlspecialchars($duty_data['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Created Info -->
                <div class="notes-section" style="background: white; font-size: 0.9rem; color: #666;">
                    <div>Created by: <?php echo htmlspecialchars($duty_data['created_by_name']); ?></div>
                    <div>Last updated: <?php echo date('M j, Y g:i A', strtotime($duty_data['updated_at'])); ?></div>
                </div>
                
                <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Duty Roster Found</h3>
                    <p>No duty assignments have been created for this week yet.</p>
                    <?php if ($can_edit): ?>
                    <button class="add-duty-btn" onclick="openAddModal()" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i> Create Duty Roster
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar Sections -->
            <div class="sidebar-sections">
                <!-- My Upcoming Duties -->
                <div class="sidebar-section">
                    <div class="section-header">
                        <h3><i class="fas fa-user-clock"></i> My Upcoming Duties</h3>
                    </div>
                    <div class="my-duties-list">
                        <?php if ($user_upcoming_result && mysqli_num_rows($user_upcoming_result) > 0): ?>
                            <?php while ($my_duty = mysqli_fetch_assoc($user_upcoming_result)): ?>
                            <div class="my-duty-item">
                                <div class="my-duty-week">
                                    <?php echo date('M j', strtotime($my_duty['week_start'])) . ' - ' . date('M j', strtotime($my_duty['week_end'])); ?>
                                </div>
                                <div class="my-duty-position <?php echo strtolower(str_replace(' ', '', $my_duty['duty_position'])); ?>">
                                    <?php echo $my_duty['duty_position']; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state" style="padding: 20px;">
                                <i class="fas fa-user-check"></i>
                                <p>No upcoming duties assigned to you.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Upcoming Weeks -->
                <div class="sidebar-section">
                    <div class="section-header">
                        <h3><i class="fas fa-calendar-alt"></i> Upcoming Duty Weeks</h3>
                    </div>
                    <div class="upcoming-list">
                        <?php if ($upcoming_result && mysqli_num_rows($upcoming_result) > 0): ?>
                            <?php while ($upcoming = mysqli_fetch_assoc($upcoming_result)): ?>
                            <div class="upcoming-item">
                                <div class="upcoming-week">
                                    <?php echo date('M j', strtotime($upcoming['week_start'])) . ' - ' . date('M j', strtotime($upcoming['week_end'])); ?>
                                </div>
                                <div class="upcoming-members">
                                    <?php 
                                    $members = [];
                                    if ($upcoming['member1_name']) $members[] = $upcoming['member1_name'];
                                    if ($upcoming['member2_name']) $members[] = $upcoming['member2_name'];
                                    
                                    if (!empty($members)) {
                                        echo htmlspecialchars(implode(' & ', $members));
                                    } else {
                                        echo 'No members assigned';
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state" style="padding: 20px;">
                                <i class="fas fa-calendar-times"></i>
                                <p>No upcoming duty rosters scheduled.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="page-footer">
            <p>Lighthouse Ministers Duty Roster &copy; <?php echo date('Y'); ?> | Week of <?php echo $week_display; ?></p>
            <p>Vespers: Wed & Fri (8-9 PM) | Practice: Sun (10-1 PM)</p>
        </div>
    </div>
    
    <!-- Add Duty Modal (Only users with edit permission) -->
    <?php if ($can_edit): ?>
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Duty Roster</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addDutyForm" onsubmit="return validateMembers()">
                    <div class="form-group">
                        <label for="week_start">Week Starting (Monday) *</label>
                        <input type="date" id="week_start" name="week_start" class="form-control" 
                               value="<?php echo $week_start; ?>" required>
                    </div>
                    
                    <div class="member-select-group">
                        <label for="member1">Primary Duty Member *</label>
                        <select id="member1" name="member1" class="form-control" required>
                            <option value="">-- Select Primary Member --</option>
                            <?php if ($members_result): ?>
                                <?php mysqli_data_seek($members_result, 0); ?>
                                <?php while ($member = mysqli_fetch_assoc($members_result)): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['full_name']); ?> (<?php echo htmlspecialchars($member['username']); ?>)
                                </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="member-select-group">
                        <label for="member2">Secondary Duty Member (Optional)</label>
                        <select id="member2" name="member2" class="form-control">
                            <option value="">-- Select Secondary Member --</option>
                            <?php if ($members_result): ?>
                                <?php mysqli_data_seek($members_result, 0); ?>
                                <?php while ($member = mysqli_fetch_assoc($members_result)): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['full_name']); ?> (<?php echo htmlspecialchars($member['username']); ?>)
                                </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <small style="color: #666; font-size: 0.85rem;">Optional - You can assign one or two members</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes & Instructions</label>
                        <textarea id="notes" name="notes" class="form-control" rows="4" 
                                  placeholder="Any special instructions or notes for this week's duties..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="add_duty" class="btn-submit">
                            <i class="fas fa-plus"></i> Create Duty Roster
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Duty Modal (Only users with edit permission) -->
    <?php if ($duty_data): ?>
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Duty Roster</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editDutyForm" onsubmit="return validateEditMembers()">
                    <input type="hidden" name="duty_id" value="<?php echo $duty_data['id']; ?>">
                    
                    <div class="form-group">
                        <label>Week</label>
                        <div class="form-control" style="background: #f8f9fa;">
                            <?php echo $week_display; ?>
                        </div>
                        <small style="color: #666; font-size: 0.85rem;">Week cannot be changed after creation</small>
                    </div>
                    
                    <div class="member-select-group">
                        <label for="edit_member1">Primary Duty Member *</label>
                        <select id="edit_member1" name="member1" class="form-control" required>
                            <option value="">-- Select Primary Member --</option>
                            <?php if ($members_result): ?>
                                <?php mysqli_data_seek($members_result, 0); ?>
                                <?php while ($member = mysqli_fetch_assoc($members_result)): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo $duty_data['member1_id'] == $member['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['full_name']); ?> (<?php echo htmlspecialchars($member['username']); ?>)
                                </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="member-select-group">
                        <label for="edit_member2">Secondary Duty Member (Optional)</label>
                        <select id="edit_member2" name="member2" class="form-control">
                            <option value="">-- Select Secondary Member --</option>
                            <?php if ($members_result): ?>
                                <?php mysqli_data_seek($members_result, 0); ?>
                                <?php while ($member = mysqli_fetch_assoc($members_result)): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo $duty_data['member2_id'] == $member['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['full_name']); ?> (<?php echo htmlspecialchars($member['username']); ?>)
                                </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_notes">Notes & Instructions</label>
                        <textarea id="edit_notes" name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($duty_data['notes']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">Status *</label>
                        <select id="edit_status" name="status" class="form-control" required>
                            <option value="active" <?php echo $duty_data['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $duty_data['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $duty_data['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="display: flex; gap: 15px;">
                        <button type="submit" name="edit_duty" class="btn-submit">
                            <i class="fas fa-save"></i> Update Duty Roster
                        </button>
                        <?php if (isAdmin()): ?>
                        <button type="button" class="btn-submit" style="background: #dc3546;" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Hidden Delete Form (Only for admin) -->
                <?php if (isAdmin()): ?>
                <form method="POST" id="deleteForm" style="display: none;">
                    <input type="hidden" name="duty_id" value="<?php echo $duty_data['id']; ?>">
                    <input type="hidden" name="delete_duty" value="1">
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- JavaScript -->
    <script>
        // Modal Functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openEditModal(dutyData) {
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Delete Confirmation
        function confirmDelete() {
            if (confirm('Are you sure you want to delete this duty roster? This action cannot be undone.')) {
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Validate members are not the same
        function validateMembers() {
            const member1 = document.getElementById('member1').value;
            const member2 = document.getElementById('member2').value;
            
            if (member1 && member2 && member1 === member2) {
                alert('Cannot assign the same member twice! Please select different members.');
                return false;
            }
            return true;
        }
        
        function validateEditMembers() {
            const member1 = document.getElementById('edit_member1').value;
            const member2 = document.getElementById('edit_member2').value;
            
            if (member1 && member2 && member1 === member2) {
                alert('Cannot assign the same member twice! Please select different members.');
                return false;
            }
            return true;
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            
            if (addModal && event.target === addModal) {
                closeAddModal();
            }
            if (editModal && event.target === editModal) {
                closeEditModal();
            }
        }
        
        // Update main content margin based on sidebar
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
            
            // Watch for sidebar changes
            const sidebar = document.querySelector('.sidebar-container');
            if (sidebar) {
                const observer = new MutationObserver(updateMainContentMargin);
                observer.observe(sidebar, { 
                    attributes: true, 
                    attributeFilter: ['class'] 
                });
            }
            
            // Set minimum date for week start (today)
            const weekStartInput = document.getElementById('week_start');
            if (weekStartInput) {
                const today = new Date().toISOString().split('T')[0];
                weekStartInput.min = today;
            }
            
            // Update member2 dropdown options to exclude selected member1
            const member1Select = document.getElementById('member1');
            const member2Select = document.getElementById('member2');
            const editMember1Select = document.getElementById('edit_member1');
            const editMember2Select = document.getElementById('edit_member2');
            
            function updateMember2Options(select1, select2) {
                if (!select1 || !select2) return;
                
                const selectedValue = select1.value;
                const options = select2.options;
                
                for (let i = 0; i < options.length; i++) {
                    options[i].style.display = 'block';
                    if (options[i].value === selectedValue && selectedValue !== '') {
                        options[i].style.display = 'none';
                    }
                }
            }
            
            if (member1Select && member2Select) {
                member1Select.addEventListener('change', function() {
                    updateMember2Options(member1Select, member2Select);
                });
            }
            
            if (editMember1Select && editMember2Select) {
                editMember1Select.addEventListener('change', function() {
                    updateMember2Options(editMember1Select, editMember2Select);
                });
                // Initial update for edit modal
                updateMember2Options(editMember1Select, editMember2Select);
            }
        });
    </script>
</body>
</html>