<?php
// get_attendance.php - Get attendance records for a specific member
require_once 'db.php';
requireLogin();

if (!isset($_GET['member_id'])) {
    die('<div class="alert alert-error">Member ID is required.</div>');
}

$member_id = intval($_GET['member_id']);
$member_name = isset($_GET['member_name']) ? urldecode($_GET['member_name']) : 'Member';

// Get member details
$member_sql = "SELECT full_name, email, phone, voice_part FROM users WHERE id = '$member_id'";
$member_result = mysqli_query($conn, $member_sql);
$member = mysqli_fetch_assoc($member_result);

// Get attendance records for the last 30 practices
$attendance_sql = "SELECT a.practice_date, a.status, a.recorded_at, u.full_name as recorded_by 
                   FROM attendance a 
                   LEFT JOIN users u ON a.recorded_by = u.id 
                   WHERE a.member_id = '$member_id' 
                   ORDER BY a.practice_date DESC 
                   LIMIT 30";
$attendance_result = mysqli_query($conn, $attendance_sql);

// Calculate statistics
$stats_sql = "SELECT 
    COUNT(*) as total_practices,
    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance 
    WHERE member_id = '$member_id'";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

$total = $stats['present_count'] + $stats['absent_count'];
$attendance_rate = $total > 0 ? round(($stats['present_count'] / $total) * 100) : 0;

// Display attendance data
?>
<div class="member-detail-header">
    <div class="detail-avatar">
        <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
    </div>
    <div class="detail-info">
        <h2><?php echo htmlspecialchars($member['full_name']); ?></h2>
        <p><i class="fas fa-music"></i> <?php echo htmlspecialchars($member['voice_part'] ?? 'Not specified'); ?></p>
        <?php if (isAdmin()): ?>
        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($member['email']); ?></p>
        <?php endif; ?>
        <?php if (!empty($member['phone'])): ?>
        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($member['phone']); ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="detail-stats">
    <div class="detail-stat">
        <div class="value"><?php echo $stats['present_count'] ?? 0; ?></div>
        <div class="label">Present</div>
    </div>
    <div class="detail-stat">
        <div class="value"><?php echo $stats['absent_count'] ?? 0; ?></div>
        <div class="label">Absent</div>
    </div>
    <div class="detail-stat">
        <div class="value"><?php echo $attendance_rate; ?>%</div>
        <div class="label">Attendance Rate</div>
    </div>
</div>

<?php if (mysqli_num_rows($attendance_result) > 0): ?>
<div class="attendance-table-container" style="overflow-x: auto; margin-top: 20px;">
    <table class="attendance-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th>Status</th>
                <th>Recorded By</th>
                <th>Recorded At</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($record = mysqli_fetch_assoc($attendance_result)): 
                $date_obj = new DateTime($record['practice_date']);
                $day_name = $date_obj->format('l');
                $formatted_date = $date_obj->format('M j, Y');
            ?>
            <tr>
                <td><?php echo $formatted_date; ?></td>
                <td><?php echo $day_name; ?></td>
                <td>
                    <?php if ($record['status'] === 'present'): ?>
                    <span class="status-present">P</span>
                    <?php else: ?>
                    <span class="status-absent">A</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($record['recorded_by'] ?? 'System'); ?></td>
                <td><?php echo date('M j, Y g:i A', strtotime($record['recorded_at'])); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<p style="text-align: center; margin-top: 15px; color: #666; font-size: 0.9rem;">
    <i class="fas fa-info-circle"></i> Showing last 30 attendance records
</p>
<?php else: ?>
<div class="empty-state" style="padding: 30px 20px;">
    <i class="fas fa-calendar-times"></i>
    <h3>No Attendance Records</h3>
    <p>This member has no attendance records yet.</p>
</div>
<?php endif; ?>

<?php if (isAdmin()): ?>
<div class="admin-actions">
    <button class="btn btn-primary" onclick="editMember(<?php echo htmlspecialchars(json_encode([
        'id' => $member_id,
        'full_name' => $member['full_name'],
        'email' => $member['email'],
        'phone' => $member['phone'] ?? '',
        'voice_part' => $member['voice_part'] ?? '',
        'address' => $member['address'] ?? '',
        'date_of_birth' => $member['date_of_birth'] ?? ''
    ])); ?>)">
        <i class="fas fa-edit"></i> Edit Member
    </button>
    
    <button class="btn btn-success" onclick="markAttendance(<?php echo $member_id; ?>, '<?php echo addslashes($member['full_name']); ?>')">
        <i class="fas fa-calendar-check"></i> Mark Attendance
    </button>
</div>
<?php endif; ?>