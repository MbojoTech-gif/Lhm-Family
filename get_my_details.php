<?php
// get_my_details.php
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Please login to view your details.</div>';
    exit;
}

$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$current_user_id = $_SESSION['user_id'];

// Security check: Users can only view their own details
if ($member_id <= 0 || $member_id != $current_user_id) {
    echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Access denied. You can only view your own details.</div>';
    exit;
}

// Get member details
$member_sql = "SELECT u.*, a.full_name as added_by_name 
               FROM users u 
               LEFT JOIN users a ON u.added_by = a.id 
               WHERE u.id = '$member_id'";
$member_result = mysqli_query($conn, $member_sql);

if (!$member_result || mysqli_num_rows($member_result) === 0) {
    echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Member not found.</div>';
    exit;
}

$member = mysqli_fetch_assoc($member_result);

// Get attendance statistics
$present_count = 0;
$absent_count = 0;
$total_count = 0;

// Check if attendance table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'attendance'");
if (mysqli_num_rows($table_check) > 0) {
    $stats_sql = "SELECT 
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        COUNT(*) as total_count
        FROM attendance WHERE member_id = '$member_id'";
    $stats_result = mysqli_query($conn, $stats_sql);
    
    if ($stats_result && mysqli_num_rows($stats_result) > 0) {
        $stats = mysqli_fetch_assoc($stats_result);
        $present_count = $stats['present_count'] ?? 0;
        $absent_count = $stats['absent_count'] ?? 0;
        $total_count = $stats['total_count'] ?? 0;
    }
}

$attendance_rate = $total_count > 0 ? round(($present_count / $total_count) * 100) : 0;

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
            <?php if (!empty($member['voice_part']) && isset($voice_parts[$member['voice_part']])): ?>
            <div class="detail-stat">
                <div class="value"><?php echo $voice_parts[$member['voice_part']]; ?></div>
                <div class="label">Voice Part</div>
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
        <?php if (!empty($member['phone'])): ?>
        <div class="detail-item">
            <div class="detail-label">Phone Number</div>
            <div class="detail-value">
                <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>" class="phone-link">
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($member['phone']); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($member['address'])): ?>
        <div class="detail-item">
            <div class="detail-label">Address</div>
            <div class="detail-value"><?php echo htmlspecialchars($member['address']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="detail-card">
        <h4><i class="fas fa-user-circle"></i> Personal Information</h4>
        <?php if (!empty($member['date_of_birth'])): ?>
        <div class="detail-item">
            <div class="detail-label">Date of Birth</div>
            <div class="detail-value"><?php echo date('F j, Y', strtotime($member['date_of_birth'])); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($member['voice_part']) && isset($voice_parts[$member['voice_part']])): ?>
        <div class="detail-item">
            <div class="detail-label">Voice Part</div>
            <div class="detail-value"><?php echo $voice_parts[$member['voice_part']]; ?></div>
        </div>
        <?php endif; ?>
        <div class="detail-item">
            <div class="detail-label">Username</div>
            <div class="detail-value"><?php echo htmlspecialchars($member['username']); ?></div>
        </div>
        <?php if (!empty($member['created_at'])): ?>
        <div class="detail-item">
            <div class="detail-label">Account Created</div>
            <div class="detail-value"><?php echo date('F j, Y', strtotime($member['created_at'])); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($member['added_by_name'])): ?>
        <div class="detail-item">
            <div class="detail-label">Added By</div>
            <div class="detail-value"><?php echo htmlspecialchars($member['added_by_name']); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="detail-card">
        <h4><i class="fas fa-calendar-check"></i> Your Attendance</h4>
        <div class="detail-item">
            <div class="detail-label">Total Practices</div>
            <div class="detail-value"><?php echo $total_count; ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Present</div>
            <?php $present_percent = $total_count > 0 ? round(($present_count / $total_count) * 100) : 0; ?>
            <div class="detail-value"><?php echo $present_count; ?> (<?php echo $present_percent; ?>%)</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Absent</div>
            <?php $absent_percent = $total_count > 0 ? round(($absent_count / $total_count) * 100) : 0; ?>
            <div class="detail-value"><?php echo $absent_count; ?> (<?php echo $absent_percent; ?>%)</div>
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

<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">
    <h4 style="margin-bottom: 10px; color: #222;"><i class="fas fa-info-circle"></i> Account Information</h4>
    <p style="color: #666; font-size: 0.9rem; margin-bottom: 5px;">
        <strong>Member ID:</strong> <?php echo $member['id']; ?>
    </p>
    <p style="color: #666; font-size: 0.9rem; margin-bottom: 5px;">
        <strong>Account Status:</strong> 
        <span class="member-tag tag-status-<?php echo $member['status']; ?>" style="margin-left: 5px;">
            <i class="fas fa-circle"></i> <?php echo ucfirst($member['status']); ?>
        </span>
    </p>
    <?php if (!empty($member['last_login'])): ?>
    <p style="color: #666; font-size: 0.9rem;">
        <strong>Last Login:</strong> <?php echo date('F j, Y g:i A', strtotime($member['last_login'])); ?>
    </p>
    <?php endif; ?>
</div>