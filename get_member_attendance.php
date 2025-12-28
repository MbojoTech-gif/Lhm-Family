<?php
// get_member_attendance.php - Get attendance records for a specific member
require_once 'db.php';
requireLogin();

if (!isset($_GET['member_id'])) {
    die('<div class="alert alert-error">Member ID is required.</div>');
}

$member_id = intval($_GET['member_id']);
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get current user's role and permissions
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Check if current user can edit attendance
$can_edit_sql = "SELECT can_edit FROM page_permissions WHERE role = '$user_role' AND page_name = 'attendance.php'";
$edit_result = mysqli_query($conn, $can_edit_sql);
$edit_permission = mysqli_fetch_assoc($edit_result);
$can_edit = $edit_permission['can_edit'] == 1;

// Alternative: Direct role check for admin, chairman, and secretary
$allowed_edit_roles = ['admin', 'chairman', 'secretary'];
$can_edit = in_array($user_role, $allowed_edit_roles);

// Get member details
$member_sql = "SELECT full_name, email, phone, voice_part FROM users WHERE id = '$member_id'";
$member_result = mysqli_query($conn, $member_sql);
$member = mysqli_fetch_assoc($member_result);

if (!$member) {
    die('<div class="alert alert-error">Member not found.</div>');
}

// Get Sundays in the selected month
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

// Get attendance data for selected month
$attendance_data = [];
$first_day = "$year-$month-01";
$last_day = date('Y-m-t', strtotime($first_day));

$attendance_sql = "SELECT practice_date, status, recorded_at 
                   FROM attendance 
                   WHERE member_id = '$member_id' 
                   AND practice_date BETWEEN '$first_day' AND '$last_day'
                   ORDER BY practice_date DESC";
$attendance_result = mysqli_query($conn, $attendance_sql);

while ($row = mysqli_fetch_assoc($attendance_result)) {
    $attendance_data[$row['practice_date']] = $row;
}

// Calculate statistics for the month
$present_count = 0;
$absent_count = 0;

foreach ($sundays as $sunday) {
    $status = $attendance_data[$sunday]['status'] ?? '';
    if ($status === 'present') $present_count++;
    if ($status === 'absent') $absent_count++;
}

$total = $present_count + $absent_count;
$attendance_rate = $total > 0 ? round(($present_count / $total) * 100) : 0;

// Display attendance data
?>
<div class="member-detail-header" style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
    <div class="member-avatar" style="width: 80px; height: 80px; border-radius: 50%; background: #000; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 2rem;">
        <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
    </div>
    <div class="detail-info">
        <h2 style="margin-bottom: 10px; font-size: 1.5rem;"><?php echo htmlspecialchars($member['full_name']); ?></h2>
        <p style="margin-bottom: 5px; color: #666;"><i class="fas fa-music"></i> <?php echo htmlspecialchars($member['voice_part'] ?? 'Not specified'); ?></p>
        <?php if (!empty($member['email'])): ?>
        <p style="margin-bottom: 5px; color: #666;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($member['email']); ?></p>
        <?php endif; ?>
        <?php if (!empty($member['phone'])): ?>
        <p style="margin-bottom: 5px; color: #666;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($member['phone']); ?></p>
        <?php endif; ?>
    </div>
</div>

<div style="display: flex; gap: 20px; margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
    <div style="text-align: center; flex: 1;">
        <div style="font-size: 1.5rem; font-weight: 600; color: #28a745;"><?php echo $present_count; ?></div>
        <div style="font-size: 0.85rem; color: #666;">Present</div>
    </div>
    <div style="text-align: center; flex: 1;">
        <div style="font-size: 1.5rem; font-weight: 600; color: #dc3545;"><?php echo $absent_count; ?></div>
        <div style="font-size: 0.85rem; color: #666;">Absent</div>
    </div>
    <div style="text-align: center; flex: 1;">
        <div style="font-size: 1.5rem; font-weight: 600; color: #007bff;"><?php echo $attendance_rate; ?>%</div>
        <div style="font-size: 0.85rem; color: #666;">Attendance Rate</div>
    </div>
</div>

<?php if (!empty($sundays)): ?>
<div class="attendance-table-container" style="overflow-x: auto; margin-top: 20px;">
    <table class="attendance-table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: #222; border-bottom: 2px solid #e0e0e0;">Date</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: #222; border-bottom: 2px solid #e0e0e0;">Day</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: #222; border-bottom: 2px solid #e0e0e0;">Status</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: #222; border-bottom: 2px solid #e0e0e0;">Recorded At</th>
                <?php if ($can_edit): ?>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: #222; border-bottom: 2px solid #e0e0e0;">Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sundays as $sunday): 
                $date_obj = new DateTime($sunday);
                $day_name = $date_obj->format('l');
                $formatted_date = $date_obj->format('M j, Y');
                $attendance = $attendance_data[$sunday] ?? null;
                $status = $attendance['status'] ?? '';
                $recorded_at = $attendance['recorded_at'] ?? '';
            ?>
            <tr style="border-bottom: 1px solid #e0e0e0;">
                <td style="padding: 12px; vertical-align: middle;"><?php echo $formatted_date; ?></td>
                <td style="padding: 12px; vertical-align: middle;"><?php echo $day_name; ?></td>
                <td style="padding: 12px; vertical-align: middle;">
                    <?php if ($status === 'present'): ?>
                    <span style="color: #28a745; font-weight: 600;">P</span>
                    <?php elseif ($status === 'absent'): ?>
                    <span style="color: #dc3545; font-weight: 600;">A</span>
                    <?php else: ?>
                    <span style="color: #666;">-</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 12px; vertical-align: middle;">
                    <?php if ($recorded_at): ?>
                    <?php echo date('M j, Y g:i A', strtotime($recorded_at)); ?>
                    <?php else: ?>
                    <span style="color: #666;">-</span>
                    <?php endif; ?>
                </td>
                <?php if ($can_edit): ?>
                <td style="padding: 12px; vertical-align: middle;">
                    <div class="attendance-toggle" style="display: flex; gap: 10px; justify-content: center;">
                        <button class="toggle-btn present-btn <?php echo $status === 'present' ? 'active' : ''; ?>" 
                                onclick="markMemberAttendance(<?php echo $member_id; ?>, '<?php echo $sunday; ?>', 'present')"
                                style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; transition: all 0.3s; background: #d4edda; color: #155724; <?php echo $status === 'present' ? 'background: #28a745; color: white;' : ''; ?>">
                            P
                        </button>
                        <button class="toggle-btn absent-btn <?php echo $status === 'absent' ? 'active' : ''; ?>" 
                                onclick="markMemberAttendance(<?php echo $member_id; ?>, '<?php echo $sunday; ?>', 'absent')"
                                style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; transition: all 0.3s; background: #f8d7da; color: #721c24; <?php echo $status === 'absent' ? 'background: #dc3545; color: white;' : ''; ?>">
                            A
                        </button>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p style="text-align: center; margin-top: 15px; color: #666; font-size: 0.9rem;">
    <i class="fas fa-info-circle"></i> P = Present | A = Absent | - = Not Marked
</p>
<?php else: ?>
<div class="empty-state" style="padding: 30px 20px; text-align: center; color: #666;">
    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
    <h3 style="font-size: 1.2rem; margin-bottom: 10px; color: #444;">No Sundays in Selected Month</h3>
    <p>There are no Sundays in <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>.</p>
</div>
<?php endif; ?>