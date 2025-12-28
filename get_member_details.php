<?php
// get_my_details.php
require_once 'db.php';
requireLogin();

$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$current_user_id = $_SESSION['user_id'];

if ($member_id <= 0 || $member_id != $current_user_id) {
    echo '<div class="alert alert-error">Access denied. You can only view your own details.</div>';
    exit;
}

// Get member details
$member_sql = "SELECT u.*, a.full_name as added_by_name 
               FROM users u 
               LEFT JOIN users a ON u.added_by = a.id 
               WHERE u.id = '$member_id'";
$member_result = mysqli_query($conn, $member_sql);

if (mysqli_num_rows($member_result) === 0) {
    echo '<div class="alert alert-error">Member not found.</div>';
    exit;
}

$member = mysqli_fetch_assoc($member_result);

// Get attendance statistics
$attendance_sql = "SELECT 
    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
    SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_count
    FROM attendance WHERE member_id = '$member_id'";
$attendance_result = mysqli_query($conn, $attendance_sql);
$attendance_data = mysqli_fetch_assoc($attendance_result);

$present_count = $attendance_data['present_count'] ?? 0;
$absent_count = $attendance_data['absent_count'] ?? 0;
$excused_count = $attendance_data['excused_count'] ?? 0;
$total_attendance = $present_count + $absent_count + $excused_count;
$attendance_rate = $total_attendance > 0 ? round(($present_count / $total_attendance) * 100) : 0;

// Voice parts options
$voice_parts = [
    'soprano' => 'Soprano',
    'alto' => 'Alto', 
    'tenor' => 'Tenor',
    'bass' => 'Bass'
];
?>

<div class="member-detail-header">
    <div class="detail-avatar">
        <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
    </div>
    <div class="detail-info">
        <h2>My Account Details</h2>
        <p>
            <i class="fas fa-user"></i>
            Welcome, <?php echo htmlspecialchars($member['full_name']); ?>
        </p>
        
        <div class="detail-stats">
            <div class="detail-stat">
                <div class="value"><?php echo $attendance_rate; ?>%</div>
                <div class="label">Your Attendance</div>
            </div>
            <?php if ($member['voice_part']): ?>
            <div class="detail-stat">
                <div class="value"><?php echo isset($voice_parts[$member['voice_part']]) ? $voice_parts[$member['voice_part']] : 'N/A'; ?></div>
                <div class="label">Your Voice Part</div>
            </div>
            <?php endif; ?>
            <div class="detail-stat">
                <div class="value"><?php echo ucfirst($member['status']); ?></div>
                <div class="label">Account Status</div>
            </div>
        </div>
    </div>
</div>

<div class="member-details-grid">
    <div class="detail-card">
        <h4><i class="fas fa-address-book"></i> Contact Information</h4>
        <div class="detail-item">
            <div class="detail-label">Full Name</div>
            <div class="detail-value"><?php echo htmlspecialchars($member['full_name']); ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Email Address</div>
            <div class="detail-value">
                <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>">
                    <?php echo htmlspecialchars($member['email']); ?>
                </a>
            </div>
        </div>
        <?php if ($member['phone']): ?>
        <div class="detail-item">
            <div class="detail-label">Phone Number</div>
            <div class="detail-value">
                <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>" class="phone-link">
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($member['phone']); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($member['address']): ?>
        <div class="detail-item">
            <div class="detail-label">Address</div>
            <div class="detail-value"><?php echo htmlspecialchars($member['address']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="detail-card">
        <h4><i class="fas fa-user-circle"></i> Personal Information</h4>
        <?php if ($member['date_of_birth']): ?>
        <div class="detail-item">
            <div class="detail-label">Date of Birth</div>
            <div class="detail-value"><?php echo date('F j, Y', strtotime($member['date_of_birth'])); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($member['voice_part']): ?>
        <div class="detail-item">
            <div class="detail-label">Voice Part</div>
            <div class="detail-value"><?php echo isset($voice_parts[$member['voice_part']]) ? $voice_parts[$member['voice_part']] : 'N/A'; ?></div>
        </div>
        <?php endif; ?>
        <div class="detail-item">
            <div class="detail-label">Username</div>
            <div class="detail-value"><?php echo htmlspecialchars($member['username']); ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Account Created</div>
            <div class="detail-value"><?php echo date('F j, Y', strtotime($member['created_at'])); ?></div>
        </div>
        <?php if ($member['added_by_name']): ?>
        <div class="detail-item">
            <div class="detail-label">Added By</div>
            <div class="detail-value"><?php echo htmlspecialchars($member['added_by_name']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="detail-card">
        <h4><i class="fas fa-chart-line"></i> Your Attendance Statistics</h4>
        <div class="detail-item">
            <div class="detail-label">Total Sessions</div>
            <div class="detail-value"><?php echo $total_attendance; ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Present</div>
            <div class="detail-value"><?php echo $present_count; ?> (<?php echo $total_attendance > 0 ? round(($present_count / $total_attendance) * 100) : 0; ?>%)</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Absent</div>
            <div class="detail-value"><?php echo $absent_count; ?> (<?php echo $total_attendance > 0 ? round(($absent_count / $total_attendance) * 100) : 0; ?>%)</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Excused</div>
            <div class="detail-value"><?php echo $excused_count; ?> (<?php echo $total_attendance > 0 ? round(($excused_count / $total_attendance) * 100) : 0; ?>%)</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Your Attendance Rate</div>
            <div class="detail-value">
                <div style="background: #e9ecef; height: 10px; border-radius: 5px; margin-top: 5px; overflow: hidden;">
                    <div style="background: #28a745; height: 100%; width: <?php echo $attendance_rate; ?>%;"></div>
                </div>
                <div style="text-align: center; margin-top: 5px; font-weight: 600;"><?php echo $attendance_rate; ?>%</div>
            </div>
        </div>
    </div>
</div>