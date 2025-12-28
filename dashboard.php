<?php
// dashboard.php - Main Dashboard
require_once 'db.php';
requireLogin();

// Get user information
$user_id = $_SESSION['user_id'];
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($user_query);

// Safe function to get array value with fallback
function getValue($array, $key, $default = 'Not set') {
    return isset($array[$key]) && !empty($array[$key]) ? $array[$key] : $default;
}

// Get statistics - Fetch real data from database
$total_members = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE status = 'active'"));

// Get suggestions count
$suggestions_result = mysqli_query($conn, "SHOW TABLES LIKE 'suggestions'");
$total_suggestions = 0;
if (mysqli_num_rows($suggestions_result) > 0) {
    $total_suggestions = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM suggestions WHERE status = 'active'"));
}

// Get songs count
$songs_result = mysqli_query($conn, "SHOW TABLES LIKE 'songs'");
$total_songs = 0;
if (mysqli_num_rows($songs_result) > 0) {
    $total_songs = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM songs"));
}

// Get upcoming events count from itinerary table
$total_events = 0;
$itinerary_result = mysqli_query($conn, "SHOW TABLES LIKE 'itinerary'");
if (mysqli_num_rows($itinerary_result) > 0) {
    $total_events = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM itinerary WHERE status IN ('upcoming', 'ongoing') AND event_date >= CURDATE()"));
}

// Get user's attendance statistics
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

// Get latest announcements (if table exists)
$latest_announcements = [];
$announcements_result = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
if (mysqli_num_rows($announcements_result) > 0) {
    $announcements_query = mysqli_query($conn, "SELECT title, content, created_at FROM announcements WHERE status = 'active' ORDER BY created_at DESC LIMIT 3");
    while ($row = mysqli_fetch_assoc($announcements_query)) {
        $latest_announcements[] = $row;
    }
}

// Get upcoming events details from itinerary table
$upcoming_events = [];
$itinerary_query_result = mysqli_query($conn, "SHOW TABLES LIKE 'itinerary'");
if (mysqli_num_rows($itinerary_query_result) > 0) {
    $events_query = mysqli_query($conn, "SELECT id, title, description, event_date, event_time, venue, type, status FROM itinerary WHERE status IN ('upcoming', 'ongoing') AND event_date >= CURDATE() ORDER BY event_date ASC, event_time ASC LIMIT 3");
    while ($row = mysqli_fetch_assoc($events_query)) {
        $upcoming_events[] = $row;
    }
}

// Get latest suggestions
$latest_suggestions = [];
$suggestions_table_result = mysqli_query($conn, "SHOW TABLES LIKE 'suggestions'");
if (mysqli_num_rows($suggestions_table_result) > 0) {
    $suggestions_query = mysqli_query($conn, "SELECT s.id, s.content, s.type, s.status, u.full_name, s.created_at FROM suggestions s LEFT JOIN users u ON s.user_id = u.id WHERE s.status = 'active' ORDER BY s.created_at DESC LIMIT 3");
    while ($row = mysqli_fetch_assoc($suggestions_query)) {
        $latest_suggestions[] = $row;
    }
}

// Get user data safely
$full_name = getValue($user_data, 'full_name', 'User');
$username = getValue($user_data, 'username', 'user');
$role = getValue($user_data, 'role', 'member');
$department = getValue($user_data, 'department', 'Not assigned');
$join_date = getValue($user_data, 'join_date', date('Y-m-d'));
$last_login = getValue($user_data, 'last_login', null);
$email = getValue($user_data, 'email', '');
$phone = getValue($user_data, 'phone', '');
$voice_part = getValue($user_data, 'voice_part', '');

// Format dates
$formatted_join_date = date('F j, Y', strtotime($join_date));
$formatted_last_login = $last_login ? date('M j, g:i A', strtotime($last_login)) : 'First login';

// Voice parts mapping
$voice_parts = [
    'soprano' => 'Soprano',
    'alto' => 'Alto', 
    'tenor' => 'Tenor',
    'bass' => 'Bass'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Include favicon -->
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Lighthouse Ministers Family Portal</title>
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
        
        .date-display {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            font-weight: 500;
        }
        
        /* Welcome Section - Updated to match profile style */
        .welcome-section {
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
            border-radius: 15px;
            padding: 40px;
            color: white;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: url('assets/images/logo1.png') center/contain no-repeat;
            opacity: 0.05;
        }
        
        .welcome-content h2 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .welcome-content p {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.85);
            max-width: 600px;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .welcome-verse {
            font-style: italic;
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            border-left: 3px solid rgba(255, 255, 255, 0.3);
            padding-left: 15px;
        }
        
        /* Statistics Cards - Updated to match profile style */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #f0f0f0;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            opacity: 0.8;
        }
        
        .stat-icon.members { background: rgba(0, 123, 255, 0.1); color: #007bff; }
        .stat-icon.suggestions { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .stat-icon.songs { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .stat-icon.events { background: rgba(220, 53, 69, 0.1); color: #dc3546; }
        
        .stat-content {
            position: relative;
            z-index: 1;
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-description {
            font-size: 0.85rem;
            color: #888;
            margin-top: 8px;
        }
        
        .stat-link {
            display: inline-block;
            margin-top: 15px;
            font-size: 0.85rem;
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .stat-link:hover {
            text-decoration: underline;
        }
        
        /* User Stats - Matching profile stats */
        .user-stats-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            border: 1px solid #f0f0f0;
        }
        
        .user-stats-container h3 {
            font-size: 1.3rem;
            color: #222;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .stats-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid #000;
        }
        
        .stat-icon-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        
        .stat-item:nth-child(1) .stat-icon-circle { background: #007bff; }
        .stat-item:nth-child(2) .stat-icon-circle { background: #28a745; }
        .stat-item:nth-child(3) .stat-icon-circle { background: #ffc107; }
        .stat-item:nth-child(4) .stat-icon-circle { background: #dc3546; }
        
        .stat-text {
            flex: 1;
        }
        
        .stat-number {
            font-weight: 600;
            color: #222;
            font-size: 1.5rem;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        /* Profile Info Section - Matching profile style */
        .profile-info-section {
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
        
        .profile-grid {
            padding: 30px;
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
        
        /* Dashboard Content Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #f0f0f0;
        }
        
        .dashboard-card h3 {
            font-size: 1.2rem;
            color: #222;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .list-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-title {
            font-weight: 500;
            color: #222;
            margin-bottom: 5px;
        }
        
        .list-meta {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .list-actions {
            margin-top: 10px;
        }
        
        .btn-view-all {
            display: inline-block;
            margin-top: 20px;
            padding: 8px 16px;
            background: #000;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .btn-view-all:hover {
            background: #333;
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
        
        .empty-state h4 {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #444;
        }
        
        /* Footer */
        .dashboard-footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.85rem;
        }
        
        .user-info {
            margin-top: 10px;
            color: #888;
        }
        
        .admin-link {
            color: #007bff;
            text-decoration: none;
        }
        
        .admin-link:hover {
            text-decoration: underline;
        }
        
        /* Profile Tags - Matching profile style */
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
        
        /* Event Type Badge */
        .event-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-right: 8px;
        }
        
        .event-type-Meeting { background: #e3f2fd; color: #1976d2; }
        .event-type-Practice { background: #f3e5f5; color: #7b1fa2; }
        .event-type-Service { background: #e8f5e9; color: #388e3c; }
        .event-type-Event { background: #fff3e0; color: #f57c00; }
        .event-type-Retreat { background: #fce4ec; color: #c2185b; }
        .event-type-Workshop { background: #e8eaf6; color: #303f9f; }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .stats-info {
                grid-template-columns: 1fr;
            }
            
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
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
            <h1>Dashboard Overview</h1>
            <p>Welcome back, <?php echo htmlspecialchars($full_name); ?>. Here's an overview of the ministry activities.</p>
            <div class="date-display">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>
        
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <h2>Welcome to Lighthouse Ministers Family Portal</h2>
                <p>This is your central hub for all ministry activities and information. Manage your profile, view announcements, check schedules, and stay connected with the ministry family.</p>
                <div class="profile-tags">
                    <span class="profile-tag">
                        <i class="fas fa-user-tag"></i> <?php echo ucfirst($role); ?>
                    </span>
                    <?php if ($voice_part && isset($voice_parts[$voice_part])): ?>
                    <span class="profile-tag">
                        <i class="fas fa-music"></i> <?php echo $voice_parts[$voice_part]; ?>
                    </span>
                    <?php endif; ?>
                    <span class="profile-tag">
                        <i class="fas fa-circle" style="color: #28a745;"></i> Active
                    </span>
                </div>
                <p class="welcome-verse">"Let your light so shine before men, that they may see your good works and glorify your Father in heaven." - Matthew 5:16</p>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-container">
            <!-- Total Members Card -->
            <div class="stat-card">
                <div class="stat-icon members">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-title">Total Members</div>
                    <div class="stat-value"><?php echo $total_members; ?></div>
                    <div class="stat-description">Active members in the ministry</div>
                    <a href="members.php" class="stat-link">View Members →</a>
                </div>
            </div>
            
            <!-- Total Suggestions Card -->
            <div class="stat-card">
                <div class="stat-icon suggestions">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-title">Total Suggestions</div>
                    <div class="stat-value"><?php echo $total_suggestions; ?></div>
                    <div class="stat-description">Suggestions from members</div>
                    <a href="suggestions.php" class="stat-link">View Suggestions →</a>
                </div>
            </div>
            
            <!-- Total Songs Card -->
            <div class="stat-card">
                <div class="stat-icon songs">
                    <i class="fas fa-music"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-title">Total Songs</div>
                    <div class="stat-value"><?php echo $total_songs; ?></div>
                    <div class="stat-description">Songs in the library</div>
                    <a href="songs.php" class="stat-link">View Songs →</a>
                </div>
            </div>
            
            <!-- Total Events Card -->
            <div class="stat-card">
                <div class="stat-icon events">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-title">Upcoming Events</div>
                    <div class="stat-value"><?php echo $total_events; ?></div>
                    <div class="stat-description">Events scheduled</div>
                    <a href="itenary.php" class="stat-link">View Itinerary →</a>
                </div>
            </div>
        </div>
        
        <!-- User Statistics -->
        <div class="user-stats-container">
            <h3><i class="fas fa-chart-line"></i> Your Ministry Statistics</h3>
            <div class="stats-info">
                <div class="stat-item">
                    <div class="stat-icon-circle">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-text">
                        <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-label">Attendance Rate</div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon-circle">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-text">
                        <div class="stat-number"><?php echo $present_count; ?></div>
                        <div class="stat-label">Present Days</div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon-circle">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-text">
                        <div class="stat-number"><?php echo $absent_count; ?></div>
                        <div class="stat-label">Absent Days</div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon-circle">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-text">
                        <div class="stat-number"><?php echo $total_attendance; ?></div>
                        <div class="stat-label">Total Practices</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Profile Information -->
        <div class="profile-info-section">
            <div class="section-header">
                <h2><i class="fas fa-user-circle"></i> Your Profile Information</h2>
                <a href="profile.php" class="btn-view-all">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
            </div>
            <div class="profile-grid">
                <div class="profile-card">
                    <h3><i class="fas fa-user"></i> Personal Info</h3>
                    <div class="detail-item">
                        <div class="detail-label">Full Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($full_name); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Username</div>
                        <div class="detail-value">
                            <code style="background: #fff; padding: 3px 8px; border-radius: 4px;">
                                <?php echo htmlspecialchars($username); ?>
                            </code>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Role</div>
                        <div class="detail-value"><?php echo ucfirst($role); ?></div>
                    </div>
                </div>
                
                <div class="profile-card">
                    <h3><i class="fas fa-address-book"></i> Contact Info</h3>
                    <div class="detail-item">
                        <div class="detail-label">Email</div>
                        <div class="detail-value">
                            <?php if ($email): ?>
                                <a href="mailto:<?php echo htmlspecialchars($email); ?>" style="color: #007bff; text-decoration: none;">
                                    <?php echo htmlspecialchars($email); ?>
                                </a>
                            <?php else: ?>
                                Not set
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Phone</div>
                        <div class="detail-value">
                            <?php if ($phone): ?>
                                <a href="tel:<?php echo htmlspecialchars($phone); ?>" style="color: #007bff; text-decoration: none;">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($phone); ?>
                                </a>
                            <?php else: ?>
                                Not set
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Department</div>
                        <div class="detail-value"><?php echo htmlspecialchars($department); ?></div>
                    </div>
                </div>
                
                <div class="profile-card">
                    <h3><i class="fas fa-calendar-alt"></i> Account Info</h3>
                    <div class="detail-item">
                        <div class="detail-label">Join Date</div>
                        <div class="detail-value"><?php echo $formatted_join_date; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Last Login</div>
                        <div class="detail-value"><?php echo $formatted_last_login; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Account Status</div>
                        <div class="detail-value">
                            <span style="color: #28a745; font-weight: 500;">
                                <i class="fas fa-circle"></i> Active
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content Grid -->
        <div class="dashboard-grid">
            <!-- Latest Announcements -->
            <div class="dashboard-card">
                <h3><i class="fas fa-bullhorn"></i> Latest Announcements</h3>
                <?php if (!empty($latest_announcements)): ?>
                    <?php foreach ($latest_announcements as $announcement): ?>
                    <div class="list-item">
                        <div class="list-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                        <div class="list-meta">
                            <i class="far fa-clock"></i> <?php echo date('M j, g:i A', strtotime($announcement['created_at'])); ?>
                        </div>
                        <div class="list-actions">
                            <a href="announcements.php" class="stat-link" style="font-size: 0.85rem;">Read more →</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bullhorn"></i>
                        <h4>No announcements yet</h4>
                        <p>Check back later for updates</p>
                    </div>
                <?php endif; ?>
                <a href="announcements.php" class="btn-view-all">View All Announcements</a>
            </div>
            
            <!-- Upcoming Events from Itinerary -->
            <div class="dashboard-card">
                <h3><i class="fas fa-calendar-check"></i> Upcoming Events</h3>
                <?php if (!empty($upcoming_events)): ?>
                    <?php foreach ($upcoming_events as $event): ?>
                    <div class="list-item">
                        <div class="list-title">
                            <span class="event-type-badge event-type-<?php echo htmlspecialchars($event['type']); ?>">
                                <?php echo htmlspecialchars($event['type']); ?>
                            </span>
                            <?php echo htmlspecialchars($event['title']); ?>
                        </div>
                        <div class="list-meta">
                            <i class="far fa-calendar"></i> <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                            <?php if ($event['event_time']): ?>
                                <i class="far fa-clock"></i> <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($event['venue']): ?>
                        <div class="list-meta">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['venue']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($event['description']): ?>
                        <div class="list-meta" style="margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> 
                            <?php 
                            $desc = strip_tags($event['description']);
                            echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4>No upcoming events</h4>
                        <p>Check back for scheduled events</p>
                    </div>
                <?php endif; ?>
                <a href="itenary.php" class="btn-view-all">View Full Itinerary</a>
            </div>
            
            <!-- Recent Suggestions -->
            <div class="dashboard-card">
                <h3><i class="fas fa-lightbulb"></i> Recent Suggestions</h3>
                <?php if (!empty($latest_suggestions)): ?>
                    <?php foreach ($latest_suggestions as $suggestion): ?>
                    <div class="list-item">
                        <div class="list-title">
                            <?php 
                            // Get first few words of content as title
                            $content = $suggestion['content'] ?? 'No content';
                            $words = explode(' ', strip_tags($content));
                            $title = implode(' ', array_slice($words, 0, 8)) . (count($words) > 8 ? '...' : '');
                            echo htmlspecialchars($title);
                            ?>
                        </div>
                        <div class="list-meta">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($suggestion['full_name']); ?>
                            <i class="far fa-clock"></i> <?php echo date('M j', strtotime($suggestion['created_at'])); ?>
                            <span class="event-type-badge" style="background: #f0f2f5; color: #666;">
                                <?php echo ucfirst($suggestion['type'] ?? 'suggestion'); ?>
                            </span>
                        </div>
                        <div class="list-actions">
                            <a href="suggestions.php" class="stat-link" style="font-size: 0.85rem;">View details →</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-lightbulb"></i>
                        <h4>No suggestions yet</h4>
                        <p>Be the first to share your ideas</p>
                    </div>
                <?php endif; ?>
                <a href="suggestions.php" class="btn-view-all">View All Suggestions</a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="dashboard-footer">
            <p>Lighthouse Ministers Family Portal &copy; <?php echo date('Y'); ?> | Version 1.0</p>
            <div class="user-info">
                Logged in as: <?php echo htmlspecialchars($username); ?> 
                (<?php echo ucfirst($role); ?>)
                <?php if ($role === 'admin' || $role === 'superadmin'): ?>
                | <a href="admin.php" class="admin-link">Admin Panel</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript for Dashboard -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update main content margin based on sidebar state
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
            
            // Initial update
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
            
            // Add hover effects to cards
            const cards = document.querySelectorAll('.stat-card, .dashboard-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Mobile responsive
            function handleResize() {
                if (window.innerWidth <= 768) {
                    document.getElementById('mainContent').style.marginLeft = '0';
                } else {
                    updateMainContentMargin();
                }
            }
            
            window.addEventListener('resize', handleResize);
            handleResize(); // Initial check
            
            // Animate stats counter (optional)
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(statValue => {
                const finalValue = parseInt(statValue.textContent);
                if (!isNaN(finalValue) && finalValue > 0) {
                    let current = 0;
                    const increment = finalValue / 50;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= finalValue) {
                            statValue.textContent = finalValue;
                            clearInterval(timer);
                        } else {
                            statValue.textContent = Math.floor(current);
                        }
                    }, 30);
                }
            });
        });
    </script>
</body>
</html>