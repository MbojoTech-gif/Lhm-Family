<?php
// members.php - Choir Members Management (Mobile-Friendly List View)
require_once 'db.php';
requireLogin();

// Check if user can view this page
if (!checkPageAccess('members.php')) {
    header('Location: access_denied.php');
    exit();
}

// Check if user can edit this page
$can_edit = checkPageAccess('members.php', true);

$user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

// Voice parts options
$voice_parts = [
    'soprano' => 'Soprano',
    'alto' => 'Alto', 
    'tenor' => 'Tenor',
    'bass' => 'Bass'
];

// Handle form submissions (Only for users with edit permission)
if ($can_edit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_member'])) {
        // Add new member
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $voice_part = sanitize($_POST['voice_part']);
        $address = sanitize($_POST['address']);
        $date_of_birth = sanitize($_POST['date_of_birth']);
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate required fields
        if (empty($full_name) || empty($email) || empty($username) || empty($password)) {
            $error = "Please fill in all required fields.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if username already exists
            $check_username_sql = "SELECT id FROM users WHERE username = '$username'";
            $check_username_result = mysqli_query($conn, $check_username_sql);
            
            if (mysqli_num_rows($check_username_result) > 0) {
                $error = "Username already exists. Please choose another.";
            } else {
                // Check if email already exists
                $check_email_sql = "SELECT id FROM users WHERE email = '$email'";
                $check_email_result = mysqli_query($conn, $check_email_sql);
                
                if (mysqli_num_rows($check_email_result) > 0) {
                    $error = "Email address already registered.";
                } else {
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Prepare SQL query with correct column names
                    $sql = "INSERT INTO users (full_name, email, phone, voice_part, address, date_of_birth, username, password, role, status, added_by) 
                            VALUES ('$full_name', '$email', '$phone', '$voice_part', '$address', '$date_of_birth', '$username', '$hashed_password', 'member', 'active', '$user_id')";
                    
                    if (mysqli_query($conn, $sql)) {
                        $member_id = mysqli_insert_id($conn);
                        $success = "Member added successfully! Login credentials have been created.";
                    } else {
                        $error = "Error adding member: " . mysqli_error($conn);
                    }
                }
            }
        }
    } elseif (isset($_POST['edit_member'])) {
        // Edit member
        $member_id = intval($_POST['member_id']);
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $voice_part = sanitize($_POST['voice_part']);
        $address = sanitize($_POST['address']);
        $date_of_birth = sanitize($_POST['date_of_birth']);
        
        // Check if email is changed and unique
        $current_email_sql = "SELECT email FROM users WHERE id = '$member_id'";
        $current_email_result = mysqli_query($conn, $current_email_sql);
        $current_email = mysqli_fetch_assoc($current_email_result)['email'];
        
        if ($email !== $current_email) {
            $check_email_sql = "SELECT id FROM users WHERE email = '$email' AND id != '$member_id'";
            $check_email_result = mysqli_query($conn, $check_email_sql);
            
            if (mysqli_num_rows($check_email_result) > 0) {
                $error = "Email address already in use by another member.";
            }
        }
        
        if (!isset($error)) {
            $sql = "UPDATE users SET 
                    full_name = '$full_name',
                    email = '$email',
                    phone = '$phone',
                    voice_part = '$voice_part',
                    address = '$address',
                    date_of_birth = '$date_of_birth'
                    WHERE id = '$member_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Member updated successfully!";
            } else {
                $error = "Error updating member: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['update_password'])) {
        // Update member password
        $member_id = intval($_POST['member_id']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password)) {
            $error = "Please enter a new password.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = '$hashed_password' WHERE id = '$member_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Password updated successfully!";
            } else {
                $error = "Error updating password: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['toggle_status'])) {
        // Toggle member active/inactive status
        $member_id = intval($_POST['member_id']);
        
        // Get current status
        $current_sql = "SELECT status FROM users WHERE id = '$member_id'";
        $current_result = mysqli_query($conn, $current_sql);
        $current_data = mysqli_fetch_assoc($current_result);
        $current_status = $current_data['status'];
        $new_status = $current_status === 'active' ? 'inactive' : 'active';
        
        $sql = "UPDATE users SET status = '$new_status' WHERE id = '$member_id'";
        
        if (mysqli_query($conn, $sql)) {
            $status_text = $new_status === 'active' ? 'activated' : 'deactivated';
            $success = "Member {$status_text} successfully!";
        } else {
            $error = "Error updating member status: " . mysqli_error($conn);
        }
    } elseif (isset($_POST['delete_member'])) {
        // Delete member
        $member_id = intval($_POST['member_id']);
        
        // Check if member has attendance records
        $check_attendance_sql = "SELECT COUNT(*) as count FROM attendance WHERE member_id = '$member_id'";
        $check_attendance_result = mysqli_query($conn, $check_attendance_sql);
        $attendance_count = mysqli_fetch_assoc($check_attendance_result)['count'];
        
        if ($attendance_count > 0) {
            $error = "Cannot delete member. Member has attendance records. Deactivate instead.";
        } else {
            $sql = "DELETE FROM users WHERE id = '$member_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Member deleted successfully!";
            } else {
                $error = "Error deleting member: " . mysqli_error($conn);
            }
        }
    }
}

// Get ALL users (both members and admins) with correct column names
$members_sql = "SELECT u.*, a.full_name as added_by_name 
                FROM users u 
                LEFT JOIN users a ON u.added_by = a.id 
                ORDER BY 
                    CASE WHEN u.status = 'active' THEN 1 ELSE 2 END,
                    u.full_name ASC";
$members_result = mysqli_query($conn, $members_sql);
$total_members = mysqli_num_rows($members_result);

// Count active/inactive members
$active_members = 0;
$inactive_members = 0;
$voice_stats = [];

if ($total_members > 0) {
    mysqli_data_seek($members_result, 0);
    while ($member = mysqli_fetch_assoc($members_result)) {
        if ($member['status'] === 'active') {
            $active_members++;
        } else {
            $inactive_members++;
        }
        
        if ($member['voice_part']) {
            $voice_stats[$member['voice_part']] = ($voice_stats[$member['voice_part']] ?? 0) + 1;
        }
    }
    mysqli_data_seek($members_result, 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Include favicon -->
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choir Members - Lighthouse Ministers</title>
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
        
        /* Stats Container */
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
        
        /* Voice Stats */
        .voice-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .voice-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .voice-soprano {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
        
        .voice-alto {
            background: #f3e5f5;
            color: #7b1fa2;
            border: 1px solid #e1bee7;
        }
        
        .voice-tenor {
            background: #e8f5e9;
            color: #388e3c;
            border: 1px solid #c8e6c9;
        }
        
        .voice-bass {
            background: #fff3e0;
            color: #f57c00;
            border: 1px solid #ffe0b2;
        }
        
        /* Members Container */
        .members-container {
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
        
        .add-member-btn {
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
        
        .add-member-btn:hover {
            background: #333;
        }
        
        /* Members List (Card View) */
        .members-list {
            padding: 20px;
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #000;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .member-details h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #222;
            margin-bottom: 5px;
        }
        
        .member-details p {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .member-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        
        .member-tag {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .tag-voice {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .tag-status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .tag-status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .member-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-sm {
            padding: 6px 12px;
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
        
        /* Attendance Modal */
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .attendance-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #333;
        }
        
        .attendance-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .attendance-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-present {
            color: #28a745;
            font-weight: 600;
        }
        
        .status-absent {
            color: #dc3545;
            font-weight: 600;
        }
        
        /* Member Details View */
        .member-detail-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .detail-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #000;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 2rem;
            flex-shrink: 0;
        }
        
        .detail-info h2 {
            font-size: 1.5rem;
            color: #222;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .detail-info p {
            color: #666;
            margin-bottom: 5px;
        }
        
        .detail-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        
        .detail-stat {
            text-align: center;
        }
        
        .detail-stat .value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #222;
        }
        
        .detail-stat .label {
            font-size: 0.85rem;
            color: #666;
        }
        
        .member-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .detail-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .detail-card h4 {
            font-size: 1rem;
            color: #222;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 500;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 3px;
        }
        
        .detail-value {
            color: #222;
            font-size: 1rem;
        }
        
        .phone-link {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .phone-link:hover {
            text-decoration: underline;
        }
        
        /* Admin Actions */
        .admin-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            flex-wrap: wrap;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group label.required::after {
            content: " *";
            color: #dc3545;
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
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
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
        
        /* Empty State */
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
        
        /* Footer */
        .page-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.85rem;
        }
        
        /* Restricted View Styles */
        .restricted-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .restricted-info i {
            color: #f39c12;
        }
        
        /* Call Button */
        .call-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        
        .call-btn:hover {
            background: #218838;
            color: white;
            text-decoration: none;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .member-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .member-actions {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }
            
            .member-detail-header {
                flex-direction: column;
                text-align: center;
            }
            
            .detail-stats {
                justify-content: center;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .stats-info {
                flex-direction: column;
            }
            
            .stat-item {
                min-width: 100%;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .admin-actions {
                flex-direction: column;
            }
            
            .admin-actions .btn {
                width: 100%;
                justify-content: center;
            }
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
            <h1>Choir Members</h1>
            <?php if ($current_user_role === 'member' && $user_id): ?>
                <p>View choir members.</p>
            <?php elseif ($can_edit): ?>
                <p>Manage choir members, voice parts, and contact information.</p>
            <?php else: ?>
                <p>View choir members information.</p>
            <?php endif; ?>
        </div>
        
        <!-- Alert Messages -->
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
        
        <!-- Stats (Only show stats to users with edit permission) -->
        <?php if ($can_edit): ?>
        <div class="stats-info">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-text">
                    <div class="stat-number"><?php echo $total_members; ?></div>
                    <div>Total Members</div>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-text">
                    <div class="stat-number"><?php echo $active_members; ?></div>
                    <div>Active Members</div>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-text">
                    <div class="stat-number"><?php echo $inactive_members; ?></div>
                    <div>Inactive Members</div>
                </div>
            </div>
        </div>
        
        <!-- Voice Statistics -->
        <?php if (!empty($voice_stats)): ?>
        <div class="voice-stats">
            <?php foreach ($voice_parts as $key => $label): ?>
                <?php if (isset($voice_stats[$key])): ?>
                <div class="voice-badge voice-<?php echo $key; ?>">
                    <i class="fas fa-music"></i>
                    <?php echo $label; ?>: <?php echo $voice_stats[$key]; ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <!-- Members Container -->
        <div class="members-container">
            <div class="section-header">
                <h2>
                    <?php if ($current_user_role === 'member' && $user_id): ?>
                        Choir Members Directory
                    <?php else: ?>
                        All Members
                    <?php endif; ?>
                </h2>
                <?php if ($can_edit): ?>
                <button class="add-member-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Member
                </button>
                <?php endif; ?>
            </div>
            
            <div class="members-list">
                <?php if ($total_members > 0): ?>
                    <?php while ($member = mysqli_fetch_assoc($members_result)): 
                        // Check if current user is viewing their own profile
                        $is_own_profile = ($current_user_role === 'member' && $user_id == $member['id']);
                        
                        // Get first name for call button
                        $first_name = explode(' ', $member['full_name'])[0];
                        
                        // Get attendance statistics for this member - CORRECTED FOR YOUR DATABASE
                        $attendance_sql = "SELECT 
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
                            FROM attendance WHERE member_id = '{$member['id']}'";
                        $attendance_result = mysqli_query($conn, $attendance_sql);
                        $attendance_data = mysqli_fetch_assoc($attendance_result);
                        $present_count = $attendance_data['present_count'] ?? 0;
                        $absent_count = $attendance_data['absent_count'] ?? 0;
                        $total_attendance = $present_count + $absent_count;
                        $attendance_rate = $total_attendance > 0 ? round(($present_count / $total_attendance) * 100) : 0;
                    ?>
                    <div class="member-item">
                        <div class="member-info">
                            <div class="member-avatar">
                                <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                            </div>
                            <div class="member-details">
                                <h3>
                                    <?php echo htmlspecialchars($member['full_name']); ?>
                                    <?php if ($is_own_profile): ?>
                                    <span style="color: #007bff; font-size: 0.8rem; margin-left: 5px;">(You)</span>
                                    <?php endif; ?>
                                </h3>
                                
                                <div class="member-meta">
                                    <?php if ($member['voice_part']): ?>
                                    <span class="member-tag tag-voice">
                                        <i class="fas fa-music"></i>
                                        <?php echo $voice_parts[$member['voice_part']]; ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <span class="member-tag tag-status-<?php echo $member['status']; ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo ucfirst($member['status']); ?>
                                    </span>
                                    
                                    <?php if ($can_edit): ?>
                                    <span class="member-tag" style="background: #f0f2f5; color: #666;">
                                        <i class="fas fa-chart-line"></i>
                                        Attendance: <?php echo $attendance_rate; ?>%
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="member-actions">
                            <?php if ($is_own_profile): ?>
                            <button class="btn btn-primary" onclick="viewMyDetails(<?php echo $member['id']; ?>)">
                                <i class="fas fa-user"></i> My Details
                            </button>
                            <?php else: ?>
                            <!-- Call button - show to everyone except the member themselves -->
                            <?php if (!empty($member['phone'])): ?>
                            <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>" class="call-btn">
                                <i class="fas fa-phone"></i> Call <?php echo htmlspecialchars($first_name); ?>
                            </a>
                            <?php endif; ?>
                            
                            <!-- View Attendance button - ONLY FOR USERS WITH EDIT PERMISSION -->
                            <?php if ($can_edit): ?>
                            <button class="btn btn-info" onclick="viewAttendance(<?php echo $member['id']; ?>, '<?php echo addslashes($member['full_name']); ?>')">
                                <i class="fas fa-calendar-check"></i> View Attendance
                            </button>
                            <?php endif; ?>
                            
                            <!-- Edit actions - ONLY FOR USERS WITH EDIT PERMISSION -->
                            <?php if ($can_edit): ?>
                            <button class="btn btn-primary" onclick="editMember(<?php echo htmlspecialchars(json_encode($member)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger" onclick="confirmToggleStatus(<?php echo $member['id']; ?>, '<?php echo addslashes($member['full_name']); ?>', '<?php echo $member['status']; ?>')">
                                <i class="fas fa-toggle-<?php echo $member['status'] === 'active' ? 'on' : 'off'; ?>"></i>
                                <?php echo $member['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Members Found</h3>
                    <p>There are no choir members in the system yet.</p>
                    <?php if ($can_edit): ?>
                    <button class="add-member-btn" onclick="openAddModal()" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i> Add First Member
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="page-footer">
            <p>Lighthouse Ministers Choir Management &copy; <?php echo date('Y'); ?></p>
            <?php if ($current_user_role === 'member'): ?>
            <p>Click "Call" buttons to contact members directly. View attendance for performance tracking.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- My Details Modal (For Members viewing their own profile) -->
    <div id="myDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> My Account Details</h3>
                <button class="close-modal" onclick="closeMyDetailsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="myDetailsContent">
                    <!-- Member's own details will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Attendance Modal -->
    <?php if ($can_edit): ?>
    <div id="attendanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-check"></i> Attendance Record</h3>
                <button class="close-modal" onclick="closeAttendanceModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="attendanceContent">
                    <!-- Attendance records will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Add Member Modal (Only for users with edit permission) -->
    <?php if ($can_edit): ?>
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Member</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addMemberForm" onsubmit="return validateAddForm()">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name" class="required">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" 
                                   placeholder="Enter full name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="required">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   placeholder="Enter email address" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   placeholder="Enter phone number">
                        </div>
                        
                        <div class="form-group">
                            <label for="voice_part">Voice Part</label>
                            <select id="voice_part" name="voice_part" class="form-control">
                                <option value="">Select Voice Part</option>
                                <?php foreach ($voice_parts as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" 
                                  placeholder="Enter address" rows="2"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="username" class="required">Username *</label>
                            <input type="text" id="username" name="username" class="form-control" 
                                   placeholder="Choose username" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="required">Password *</label>
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="Enter password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="required">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm password" required minlength="6">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="add_member" class="btn-submit">
                            <i class="fas fa-user-plus"></i> Add Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Edit Member Modal (Only for users with edit permission) -->
    <?php if ($can_edit): ?>
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit Member</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editMemberForm">
                    <input type="hidden" id="edit_member_id" name="member_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_full_name" class="required">Full Name *</label>
                            <input type="text" id="edit_full_name" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_email" class="required">Email Address *</label>
                            <input type="email" id="edit_email" name="email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_phone">Phone Number</label>
                            <input type="tel" id="edit_phone" name="phone" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_voice_part">Voice Part</label>
                            <select id="edit_voice_part" name="voice_part" class="form-control">
                                <option value="">Select Voice Part</option>
                                <?php foreach ($voice_parts as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_address">Address</label>
                        <textarea id="edit_address" name="address" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_date_of_birth">Date of Birth</label>
                        <input type="date" id="edit_date_of_birth" name="date_of_birth" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="edit_member" class="btn-submit">
                            <i class="fas fa-save"></i> Update Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Change Password Modal (Only for users with edit permission) -->
    <?php if ($can_edit): ?>
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Change Password</h3>
                <button class="close-modal" onclick="closePasswordModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="passwordForm">
                    <input type="hidden" id="password_member_id" name="member_id">
                    
                    <div class="form-group">
                        <label>Member</label>
                        <div id="password_member_name" style="padding: 10px; background: #f8f9fa; border-radius: 6px; font-weight: 500;"></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password" class="required">New Password *</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" 
                                   placeholder="Enter new password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_new_password" class="required">Confirm Password *</label>
                            <input type="password" id="confirm_new_password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm new password" required minlength="6">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="update_password" class="btn-submit">
                            <i class="fas fa-key"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- JavaScript -->
    <script>
        // Modal Functions
        function openAddModal() {
            <?php if (!$can_edit): ?>
            alert("You don't have permission to add members.");
            return;
            <?php endif; ?>
            
            console.log('Opening add modal');
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('addMemberForm').reset();
        }
        
        function openEditModal() {
            <?php if (!$can_edit): ?>
            alert("You don't have permission to edit members.");
            return;
            <?php endif; ?>
            
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editMemberForm').reset();
        }
        
        function openPasswordModal(memberId, memberName) {
            <?php if (!$can_edit): ?>
            alert("You don't have permission to change passwords.");
            return;
            <?php endif; ?>
            
            document.getElementById('passwordModal').style.display = 'flex';
            
            // Set member info
            document.getElementById('password_member_id').value = memberId;
            document.getElementById('password_member_name').textContent = memberName;
            document.getElementById('passwordForm').reset();
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
            document.getElementById('passwordForm').reset();
        }
        
        function openAttendanceModal() {
            document.getElementById('attendanceModal').style.display = 'flex';
        }
        
        function closeAttendanceModal() {
            document.getElementById('attendanceModal').style.display = 'none';
        }
        
        // Form validation
        function validateAddForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        }
        
        // View Attendance
        function viewAttendance(memberId, memberName) {
            <?php if (!$can_edit): ?>
            alert("You don't have permission to view attendance records.");
            return;
            <?php endif; ?>
            
            // Show loading
            document.getElementById('attendanceContent').innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div class="loading" style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #007bff; border-radius: 50%; margin: 0 auto 20px; animation: spin 1s linear infinite;"></div>
                    <p>Loading attendance records...</p>
                </div>
                <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
            `;
            
            // Show modal
            document.getElementById('attendanceModal').style.display = 'flex';
            
            // Load attendance via AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `get_attendance.php?member_id=${memberId}&member_name=${encodeURIComponent(memberName)}`, true);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    document.getElementById('attendanceContent').innerHTML = xhr.responseText;
                } else {
                    document.getElementById('attendanceContent').innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> Error loading attendance records.
                        </div>
                    `;
                }
            };
            
            xhr.onerror = function() {
                document.getElementById('attendanceContent').innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> Network error. Please check your connection.
                    </div>
                `;
            };
            
            xhr.send();
        }
        
        // View My Details
        function viewMyDetails(memberId) {
            // Show loading
            document.getElementById('myDetailsContent').innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div class="loading" style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #007bff; border-radius: 50%; margin: 0 auto 20px; animation: spin 1s linear infinite;"></div>
                    <p>Loading your details...</p>
                </div>
                <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
            `;
            
            // Show modal
            document.getElementById('myDetailsModal').style.display = 'flex';
            
            // Load details via AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `get_my_details.php?member_id=${memberId}`, true);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    document.getElementById('myDetailsContent').innerHTML = xhr.responseText;
                } else {
                    document.getElementById('myDetailsContent').innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> Error loading your details.
                        </div>
                    `;
                }
            };
            
            xhr.onerror = function() {
                document.getElementById('myDetailsContent').innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> Network error. Please check your connection.
                    </div>
                `;
            };
            
            xhr.send();
        }
        
        function closeMyDetailsModal() {
            document.getElementById('myDetailsModal').style.display = 'none';
        }
        
        // Edit Member
        function editMember(memberData) {
            <?php if (!$can_edit): ?>
            alert("You don't have permission to edit members.");
            return;
            <?php endif; ?>
            
            // Fill form with member data
            document.getElementById('edit_member_id').value = memberData.id;
            document.getElementById('edit_full_name').value = memberData.full_name;
            document.getElementById('edit_email').value = memberData.email;
            document.getElementById('edit_phone').value = memberData.phone || '';
            document.getElementById('edit_voice_part').value = memberData.voice_part || '';
            document.getElementById('edit_address').value = memberData.address || '';
            document.getElementById('edit_date_of_birth').value = memberData.date_of_birth || '';
            
            // Show modal
            document.getElementById('editModal').style.display = 'flex';
        }
        
        // Toggle Status with confirmation
        function confirmToggleStatus(memberId, memberName, currentStatus) {
            <?php if (!$can_edit): ?>
            alert("You don't have permission to change member status.");
            return;
            <?php endif; ?>
            
            const action = currentStatus === 'active' ? 'deactivate' : 'activate';
            if (confirm(`Are you sure you want to ${action} ${memberName}?`)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const memberIdInput = document.createElement('input');
                memberIdInput.type = 'hidden';
                memberIdInput.name = 'member_id';
                memberIdInput.value = memberId;
                
                const toggleStatusInput = document.createElement('input');
                toggleStatusInput.type = 'hidden';
                toggleStatusInput.name = 'toggle_status';
                toggleStatusInput.value = '1';
                
                form.appendChild(memberIdInput);
                form.appendChild(toggleStatusInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Change Password
        function changePassword(memberId, memberName) {
            <?php if (!$can_edit): ?>
            alert("You don't have permission to change passwords.");
            return;
            <?php endif; ?>
            
            openPasswordModal(memberId, memberName);
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = [
                'addModal', 'editModal', 'passwordModal', 
                'attendanceModal', 'myDetailsModal'
            ];
            
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && event.target === modal) {
                    if (modalId === 'addModal') closeAddModal();
                    if (modalId === 'editModal') closeEditModal();
                    if (modalId === 'passwordModal') closePasswordModal();
                    if (modalId === 'attendanceModal') closeAttendanceModal();
                    if (modalId === 'myDetailsModal') closeMyDetailsModal();
                }
            });
        };
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddModal();
                closeEditModal();
                closePasswordModal();
                closeAttendanceModal();
                closeMyDetailsModal();
            }
        });
        
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
        });
        
        // Phone number formatting
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                // Format as (XXX) XXX-XXXX
                if (value.length > 3 && value.length <= 6) {
                    value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
                } else if (value.length > 6) {
                    value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6, 10);
                }
                
                e.target.value = value;
            });
        });
    </script>
</body>
</html>