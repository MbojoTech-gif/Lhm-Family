<?php
// profile.php - User Profile Management (Black/White Theme)
require_once 'db.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Voice parts options
$voice_parts = [
    'soprano' => 'Soprano',
    'alto' => 'Alto', 
    'tenor' => 'Tenor',
    'bass' => 'Bass'
];

// Get current user data
$user_sql = "SELECT * FROM users WHERE id = '$user_id'";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    header('Location: logout.php');
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $voice_part = sanitize($_POST['voice_part']);
        $address = sanitize($_POST['address']);
        $date_of_birth = sanitize($_POST['date_of_birth']);
        
        // Check if email is changed and unique
        if ($email !== $user['email']) {
            $check_email_sql = "SELECT id FROM users WHERE email = '$email' AND id != '$user_id'";
            $check_email_result = mysqli_query($conn, $check_email_sql);
            
            if (mysqli_num_rows($check_email_result) > 0) {
                $error = "Email address already in use by another user.";
            }
        }
        
        if (!isset($error)) {
            $sql = "UPDATE users SET 
                    full_name = '$full_name',
                    email = '$email',
                    phone = '$phone',
                    voice_part = '$voice_part',
                    address = '$address',
                    date_of_birth = '$date_of_birth',
                    updated_at = NOW()
                    WHERE id = '$user_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Profile updated successfully!";
                // Refresh user data
                $user_result = mysqli_query($conn, $user_sql);
                $user = mysqli_fetch_assoc($user_result);
            } else {
                $error = "Error updating profile: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $error = "Current password is incorrect.";
        } elseif (empty($new_password)) {
            $error = "Please enter a new password.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = '$hashed_password', updated_at = NOW() WHERE id = '$user_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Password changed successfully!";
            } else {
                $error = "Error changing password: " . mysqli_error($conn);
            }
        }
    }
}

// Get attendance statistics for the user
$attendance_sql = "SELECT 
    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
    COUNT(*) as total_records
    FROM attendance WHERE member_id = '$user_id'";
$attendance_result = mysqli_query($conn, $attendance_sql);
$attendance_data = mysqli_fetch_assoc($attendance_result);

$present_count = $attendance_data['present_count'] ?? 0;
$absent_count = $attendance_data['absent_count'] ?? 0;
$total_attendance = $present_count + $absent_count;
$attendance_rate = $total_attendance > 0 ? round(($present_count / $total_attendance) * 100) : 0;

// Calculate age from date of birth
$age = '';
if ($user['date_of_birth']) {
    $birth_date = new DateTime($user['date_of_birth']);
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Include favicon -->
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Lighthouse Ministers</title>
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
        
        /* Profile Container */
        .profile-container {
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
        
        /* Profile Info */
        .profile-info-section {
            padding: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
        }
        
        .profile-avatar {
            position: relative;
            flex-shrink: 0;
        }
        
        .avatar-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #000;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 600;
            border: 4px solid #f0f0f0;
        }
        
        .profile-details h2 {
            font-size: 1.8rem;
            color: #222;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .profile-details p {
            color: #666;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .profile-tags {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .profile-tag {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            background: #f8f9fa;
            color: #333;
            border: 1px solid #e0e0e0;
        }
        
        /* Stats */
        .stats-info {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
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
        
        /* Profile Sections */
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .profile-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #000;
        }
        
        .profile-card h3 {
            font-size: 1.1rem;
            color: #222;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-item {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-weight: 500;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
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
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #000;
            color: white;
        }
        
        .btn-primary:hover {
            background: #333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
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
        
        /* Footer */
        .page-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.85rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
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
            
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
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
            <h1>My Profile</h1>
            <p>View and manage your personal information and settings.</p>
        </div>
        
      
        
        <!-- Profile Container -->
        <div class="profile-container">
            <div class="section-header">
                <h2>Profile Information</h2>
                <div class="controls">
                    <button class="btn btn-primary" onclick="openEditProfileModal()">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </div>
            </div>
            
            <div class="profile-info-section">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <div class="avatar-image">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                    </div>
                    
                    <div class="profile-details">
                        <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                        <?php if ($user['phone']): ?>
                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></p>
                        <?php endif; ?>
                        
                        <div class="profile-tags">
                            <span class="profile-tag">
                                <i class="fas fa-user-tag"></i> <?php echo ucfirst($user['role']); ?>
                            </span>
                            <?php if ($user['voice_part']): ?>
                            <span class="profile-tag">
                                <i class="fas fa-music"></i> <?php echo $voice_parts[$user['voice_part']]; ?>
                            </span>
                            <?php endif; ?>
                            <span class="profile-tag">
                                <i class="fas fa-circle" style="color: <?php echo $user['status'] === 'active' ? '#28a745' : '#dc3545'; ?>"></i>
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="stats-info">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-text">
                            <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                            <div>Attendance Rate</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-text">
                            <div class="stat-number"><?php echo $present_count; ?></div>
                            <div>Present Days</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-text">
                            <div class="stat-number"><?php echo $absent_count; ?></div>
                            <div>Absent Days</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-text">
                            <div class="stat-number"><?php echo $total_attendance; ?></div>
                            <div>Total Practices</div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Details Grid -->
                <div class="profile-grid">
                    <!-- Personal Information -->
                    <div class="profile-card">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                        
                        <div class="detail-item">
                            <div class="detail-label">Full Name</div>
                            <div class="detail-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Username</div>
                            <div class="detail-value">
                                <code style="background: #fff; padding: 3px 8px; border-radius: 4px;">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </code>
                            </div>
                        </div>
                        
                        <?php if ($user['date_of_birth']): ?>
                        <div class="detail-item">
                            <div class="detail-label">Date of Birth</div>
                            <div class="detail-value">
                                <?php echo date('F j, Y', strtotime($user['date_of_birth'])); ?>
                                <?php if ($age): ?> (<?php echo $age; ?> years)<?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($user['voice_part']): ?>
                        <div class="detail-item">
                            <div class="detail-label">Voice Part</div>
                            <div class="detail-value"><?php echo $voice_parts[$user['voice_part']]; ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="profile-card">
                        <h3><i class="fas fa-address-book"></i> Contact Information</h3>
                        
                        <div class="detail-item">
                            <div class="detail-label">Email Address</div>
                            <div class="detail-value">
                                <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" style="color: #007bff; text-decoration: none;">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </a>
                            </div>
                        </div>
                        
                        <?php if ($user['phone']): ?>
                        <div class="detail-item">
                            <div class="detail-label">Phone Number</div>
                            <div class="detail-value">
                                <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>" class="phone-link">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($user['address']): ?>
                        <div class="detail-item">
                            <div class="detail-label">Address</div>
                            <div class="detail-value"><?php echo nl2br(htmlspecialchars($user['address'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Account Information -->
                    <div class="profile-card">
                        <h3><i class="fas fa-user-cog"></i> Account Information</h3>
                        
                        <div class="detail-item">
                            <div class="detail-label">Account Status</div>
                            <div class="detail-value">
                                <span style="color: <?php echo $user['status'] === 'active' ? '#28a745' : '#dc3545'; ?>; font-weight: 500;">
                                    <i class="fas fa-circle"></i> <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Role</div>
                            <div class="detail-value"><?php echo ucfirst($user['role']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Join Date</div>
                            <div class="detail-value">
                                <?php echo date('F j, Y', strtotime($user['join_date'])); ?>
                                <span style="color: #666; font-size: 0.9rem;">
                                    (<?php echo date_diff(new DateTime($user['join_date']), new DateTime())->days; ?> days ago)
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">Last Login</div>
                            <div class="detail-value">
                                <?php if ($user['last_login']): ?>
                                    <?php echo date('F j, Y g:i A', strtotime($user['last_login'])); ?>
                                <?php else: ?>
                                    Never logged in
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openEditProfileModal()">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                    
                    <button class="btn btn-secondary" onclick="openChangePasswordModal()">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                    
                    <?php if (isAdmin()): ?>
                    <a href="members.php" class="btn" style="background: #17a2b8; color: white;">
                        <i class="fas fa-users"></i> Manage Members
                    </a>
                    <a href="attendance.php" class="btn" style="background: #28a745; color: white;">
                        <i class="fas fa-calendar-check"></i> View Attendance
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="page-footer">
            <p>Lighthouse Ministers Profile Management &copy; <?php echo date('Y'); ?></p>
            <p>Keep your profile information up to date.</p>
        </div>
    </div>
    
    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit Profile</h3>
                <button class="close-modal" onclick="closeEditProfileModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editProfileForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name" class="required">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="required">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="voice_part">Voice Part</label>
                            <select id="voice_part" name="voice_part" class="form-control">
                                <option value="">Select Voice Part</option>
                                <?php foreach ($voice_parts as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $user['voice_part'] == $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                               value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="update_profile" class="btn-submit">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Change Password</h3>
                <button class="close-modal" onclick="closeChangePasswordModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="changePasswordForm">
                    <div class="form-group">
                        <label for="current_password" class="required">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" 
                               placeholder="Enter current password" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password" class="required">New Password *</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" 
                                   placeholder="Enter new password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="required">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm new password" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div style="font-size: 0.85rem; color: #666; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> Password must be at least 6 characters long.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="change_password" class="btn-submit">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Modal Functions
        function openEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'flex';
        }
        
        function closeEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'none';
        }
        
        function openChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'flex';
            document.getElementById('changePasswordForm').reset();
        }
        
        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const editProfileModal = document.getElementById('editProfileModal');
            const changePasswordModal = document.getElementById('changePasswordModal');
            
            if (editProfileModal && event.target === editProfileModal) {
                closeEditProfileModal();
            }
            if (changePasswordModal && event.target === changePasswordModal) {
                closeChangePasswordModal();
            }
        }
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditProfileModal();
                closeChangePasswordModal();
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
        document.getElementById('phone')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // Format as (XXX) XXX-XXXX
            if (value.length > 3 && value.length <= 6) {
                value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
            } else if (value.length > 6) {
                value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6, 10);
            }
            
            e.target.value = value;
        });
    </script>
</body>
</html>