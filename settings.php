<?php
// settings.php - User and System Settings
require_once 'db.php';
requireLogin();

// Check if user can view this page
if (!checkPageAccess('settings.php')) {
    header('Location: access_denied.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($user_query);

// Check if settings table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'user_settings'");
if (mysqli_num_rows($table_check) == 0) {
    $create_table_sql = "CREATE TABLE `user_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `theme_mode` enum('light','dark','auto') DEFAULT 'light',
        `notifications` tinyint(1) DEFAULT 1,
        `email_notifications` tinyint(1) DEFAULT 1,
        `dashboard_refresh_rate` int(11) DEFAULT 30,
        `items_per_page` int(11) DEFAULT 20,
        `timezone` varchar(50) DEFAULT 'UTC',
        `date_format` varchar(20) DEFAULT 'Y-m-d',
        `time_format` enum('12','24') DEFAULT '24',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`),
        CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    mysqli_query($conn, $create_table_sql);
}

// Get user settings
$settings_query = mysqli_query($conn, "SELECT * FROM user_settings WHERE user_id = '$user_id'");
if (mysqli_num_rows($settings_query) > 0) {
    $settings = mysqli_fetch_assoc($settings_query);
} else {
    // Create default settings
    $default_settings = [
        'theme_mode' => 'light',
        'notifications' => 1,
        'email_notifications' => 1,
        'dashboard_refresh_rate' => 30,
        'items_per_page' => 20,
        'timezone' => 'UTC',
        'date_format' => 'Y-m-d',
        'time_format' => '24'
    ];
    
    // Insert default settings
    $insert_sql = "INSERT INTO user_settings (user_id, theme_mode, notifications, email_notifications, 
                    dashboard_refresh_rate, items_per_page, timezone, date_format, time_format) 
                    VALUES ('$user_id', 
                            '{$default_settings['theme_mode']}', 
                            '{$default_settings['notifications']}', 
                            '{$default_settings['email_notifications']}', 
                            '{$default_settings['dashboard_refresh_rate']}', 
                            '{$default_settings['items_per_page']}', 
                            '{$default_settings['timezone']}', 
                            '{$default_settings['date_format']}', 
                            '{$default_settings['time_format']}')";
    mysqli_query($conn, $insert_sql);
    $settings = $default_settings;
}

// Handle form submissions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_profile'])) {
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email is already used by another user
            $check_email_sql = "SELECT id FROM users WHERE email = '$email' AND id != '$user_id'";
            $check_result = mysqli_query($conn, $check_email_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                $error = "Email address already in use by another account.";
            } else {
                $sql = "UPDATE users SET 
                        full_name = '$full_name',
                        email = '$email',
                        phone = '$phone'
                        WHERE id = '$user_id'";
                
                if (mysqli_query($conn, $sql)) {
                    $success = "Profile updated successfully!";
                    $_SESSION['user_name'] = $full_name;
                } else {
                    $error = "Error updating profile: " . mysqli_error($conn);
                }
            }
        }
    } elseif (isset($_POST['save_settings'])) {
        $theme_mode = sanitize($_POST['theme_mode']);
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $dashboard_refresh_rate = intval($_POST['dashboard_refresh_rate']);
        $items_per_page = intval($_POST['items_per_page']);
        $timezone = sanitize($_POST['timezone']);
        $date_format = sanitize($_POST['date_format']);
        $time_format = sanitize($_POST['time_format']);
        
        // Validate refresh rate
        if ($dashboard_refresh_rate < 10 || $dashboard_refresh_rate > 300) {
            $error = "Dashboard refresh rate must be between 10 and 300 seconds.";
        } elseif ($items_per_page < 5 || $items_per_page > 100) {
            $error = "Items per page must be between 5 and 100.";
        } else {
            $sql = "UPDATE user_settings SET 
                    theme_mode = '$theme_mode',
                    notifications = '$notifications',
                    email_notifications = '$email_notifications',
                    dashboard_refresh_rate = '$dashboard_refresh_rate',
                    items_per_page = '$items_per_page',
                    timezone = '$timezone',
                    date_format = '$date_format',
                    time_format = '$time_format'
                    WHERE user_id = '$user_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Settings saved successfully!";
                $settings['theme_mode'] = $theme_mode;
                $settings['notifications'] = $notifications;
                $settings['email_notifications'] = $email_notifications;
                $settings['dashboard_refresh_rate'] = $dashboard_refresh_rate;
                $settings['items_per_page'] = $items_per_page;
                $settings['timezone'] = $timezone;
                $settings['date_format'] = $date_format;
                $settings['time_format'] = $time_format;
            } else {
                $error = "Error saving settings: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($current_password, $user_data['password'])) {
            $error = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters long.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = '$hashed_password' WHERE id = '$user_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Password changed successfully!";
            } else {
                $error = "Error changing password: " . mysqli_error($conn);
            }
        }
    }
}

// Timezones list
$timezones = DateTimeZone::listIdentifiers();

// Date formats
$date_formats = [
    'Y-m-d' => '2023-12-31',
    'd/m/Y' => '31/12/2023',
    'm/d/Y' => '12/31/2023',
    'd-m-Y' => '31-12-2023',
    'F j, Y' => 'December 31, 2023',
    'j F, Y' => '31 December, 2023'
];
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $settings['theme_mode']; ?>">
<head>
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Lighthouse Ministers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Theme Variables */
        :root {
            --primary-color: #000000;
            --secondary-color: #1a1a1a;
            --accent-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --gray-color: #6c757d;
            --border-color: #dee2e6;
            --shadow-color: rgba(0, 0, 0, 0.1);
            
            /* Light Theme */
            --bg-color: #ffffff;
            --bg-secondary: #f8f9fa;
            --text-color: #333333;
            --text-secondary: #666666;
            --card-bg: #ffffff;
            --border-light: #e0e0e0;
            --hover-bg: #f5f5f5;
        }

        [data-theme="dark"] {
            /* Dark Theme */
            --primary-color: #ffffff;
            --secondary-color: #e0e0e0;
            --accent-color: #4dabf7;
            --success-color: #51cf66;
            --warning-color: #ffd43b;
            --danger-color: #ff6b6b;
            --light-color: #343a40;
            --dark-color: #f8f9fa;
            --gray-color: #adb5bd;
            --border-color: #495057;
            --shadow-color: rgba(0, 0, 0, 0.3);
            
            --bg-color: #121212;
            --bg-secondary: #1e1e1e;
            --text-color: #ffffff;
            --text-secondary: #b0b0b0;
            --card-bg: #1e1e1e;
            --border-light: #333333;
            --hover-bg: #2d2d2d;
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        body {
            background: var(--bg-color);
            color: var(--text-color);
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
            border-left: 4px solid transparent;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border-left-color: var(--success-color);
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            border-left-color: var(--danger-color);
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-light);
        }
        
        .page-header h1 {
            font-size: 2rem;
            color: var(--text-color);
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-header p {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        /* Theme Toggle */
        .theme-toggle-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
        }
        
        .theme-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            box-shadow: 0 2px 8px var(--shadow-color);
            cursor: pointer;
            user-select: none;
        }
        
        .theme-toggle i {
            font-size: 1.1rem;
        }
        
        .theme-text {
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
        }
        
        /* Settings Tabs */
        .settings-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: transparent;
            border: none;
            border-radius: 6px;
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn:hover {
            background: var(--hover-bg);
            color: var(--text-color);
        }
        
        .tab-btn.active {
            background: var(--primary-color);
            color: white;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Settings Container */
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .settings-section {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border-light);
            box-shadow: 0 2px 10px var(--shadow-color);
        }
        
        .section-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-light);
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            color: var(--text-color);
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 0.9rem;
        }
        
        .form-group label.required::after {
            content: " *";
            color: var(--danger-color);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            font-size: 0.95rem;
            background: var(--bg-color);
            color: var(--text-color);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .form-help {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 5px;
        }
        
        /* Toggle Switch */
        .toggle-group {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .toggle-label {
            flex: 1;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border-light);
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--success-color);
        }
        
        input:checked + .slider:before {
            transform: translateX(30px);
        }
        
        /* Radio and Checkbox Groups */
        .radio-group, .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }
        
        .radio-option, .checkbox-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        
        .radio-option input, .checkbox-option input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .radio-label, .checkbox-label {
            color: var(--text-color);
            cursor: pointer;
        }
        
        .option-preview {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-left: 28px;
        }
        
        /* Button Styles */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        /* Avatar Section */
        .avatar-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .avatar-container {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 15px;
            border: 4px solid var(--border-light);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .avatar-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-initials {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 2.5rem;
        }
        
        .avatar-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        /* Danger Zone */
        .danger-zone {
            border-color: var(--danger-color) !important;
        }
        
        .danger-zone .section-header {
            border-bottom-color: rgba(220, 53, 69, 0.2);
        }
        
        .danger-zone h2 {
            color: var(--danger-color);
        }
        
        /* Footer */
        .page-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .settings-container {
                padding: 0;
            }
            
            .settings-section {
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .theme-toggle {
                padding: 6px 12px;
            }
            
            .theme-text {
                display: none;
            }
            
            .tab-btn span {
                display: none;
            }
            
            .tab-btn i {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <!-- Theme Toggle -->
    <div class="theme-toggle-container">
        <div class="theme-toggle" id="themeToggle">
            <i class="fas fa-moon"></i>
            <span class="theme-text">Dark Mode</span>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-cog"></i> Settings</h1>
            <p>Manage your account preferences and system settings</p>
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
        
        <!-- Settings Tabs -->
        <div class="settings-tabs">
            <button class="tab-btn active" onclick="switchTab('profile')">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </button>
            <button class="tab-btn" onclick="switchTab('preferences')">
                <i class="fas fa-sliders-h"></i>
                <span>Preferences</span>
            </button>
            <button class="tab-btn" onclick="switchTab('security')">
                <i class="fas fa-shield-alt"></i>
                <span>Security</span>
            </button>
        </div>
        
        <!-- Settings Container -->
        <div class="settings-container">
            <!-- Profile Tab -->
            <div class="tab-content active" id="profileTab">
                <!-- Avatar Section -->
                <div class="settings-section">
                    <div class="avatar-section">
                        <div class="avatar-container">
                            <?php if ($user_data['profile_pic'] && file_exists('assets/uploads/' . $user_data['profile_pic'])): ?>
                                <img src="assets/uploads/<?php echo $user_data['profile_pic']; ?>" alt="Profile">
                            <?php else: ?>
                                <div class="avatar-initials">
                                    <?php 
                                    $initials = '';
                                    $names = explode(' ', $user_data['full_name']);
                                    foreach ($names as $name) {
                                        $initials .= strtoupper(substr($name, 0, 1));
                                    }
                                    echo substr($initials, 0, 2);
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="avatar-actions">
                            <button class="btn btn-primary">
                                <i class="fas fa-camera"></i> Change Photo
                            </button>
                            <button class="btn btn-danger">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <div class="section-header">
                            <h2><i class="fas fa-user-edit"></i> Personal Information</h2>
                            <p>Update your personal details and contact information</p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="full_name" class="required">Full Name</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="required">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                                <div class="form-help">Include country code (e.g., +1 234 567 8900)</div>
                            </div>
                            
                            <div class="form-group">
                                <label>Role</label>
                                <input type="text" class="form-control" value="<?php echo ucfirst($user_data['role']); ?>" disabled>
                                <div class="form-help">Your role cannot be changed here</div>
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-top: 30px;">
                            <button type="submit" name="save_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Preferences Tab -->
            <div class="tab-content" id="preferencesTab">
                <form method="POST">
                    <div class="settings-section">
                        <div class="section-header">
                            <h2><i class="fas fa-palette"></i> Appearance</h2>
                            <p>Customize the look and feel of your dashboard</p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="theme_mode">Theme Mode</label>
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="theme_mode" value="light" <?php echo $settings['theme_mode'] == 'light' ? 'checked' : ''; ?>>
                                        <span class="radio-label">Light Mode</span>
                                    </label>
                                    <div class="option-preview">Bright and clean interface</div>
                                    
                                    <label class="radio-option">
                                        <input type="radio" name="theme_mode" value="dark" <?php echo $settings['theme_mode'] == 'dark' ? 'checked' : ''; ?>>
                                        <span class="radio-label">Dark Mode</span>
                                    </label>
                                    <div class="option-preview">Easier on the eyes in low light</div>
                                    
                                    <label class="radio-option">
                                        <input type="radio" name="theme_mode" value="auto" <?php echo $settings['theme_mode'] == 'auto' ? 'checked' : ''; ?>>
                                        <span class="radio-label">Auto</span>
                                    </label>
                                    <div class="option-preview">Follows your system preference</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select id="timezone" name="timezone" class="form-control">
                                    <?php foreach ($timezones as $tz): ?>
                                    <option value="<?php echo $tz; ?>" <?php echo $settings['timezone'] == $tz ? 'selected' : ''; ?>>
                                        <?php echo $tz; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_format">Date Format</label>
                                <select id="date_format" name="date_format" class="form-control">
                                    <?php foreach ($date_formats as $format => $example): ?>
                                    <option value="<?php echo $format; ?>" <?php echo $settings['date_format'] == $format ? 'selected' : ''; ?>>
                                        <?php echo $example; ?> (<?php echo $format; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Time Format</label>
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="time_format" value="24" <?php echo $settings['time_format'] == '24' ? 'checked' : ''; ?>>
                                        <span class="radio-label">24-hour (14:30)</span>
                                    </label>
                                    
                                    <label class="radio-option">
                                        <input type="radio" name="time_format" value="12" <?php echo $settings['time_format'] == '12' ? 'checked' : ''; ?>>
                                        <span class="radio-label">12-hour (2:30 PM)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <div class="section-header">
                            <h2><i class="fas fa-bell"></i> Notifications</h2>
                            <p>Control how and when you receive notifications</p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="toggle-group">
                                <div class="toggle-label">
                                    <label>Push Notifications</label>
                                    <div class="form-help">Show notifications in your browser</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="notifications" <?php echo $settings['notifications'] ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="toggle-group">
                                <div class="toggle-label">
                                    <label>Email Notifications</label>
                                    <div class="form-help">Receive notifications via email</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="email_notifications" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label for="dashboard_refresh_rate">Dashboard Refresh Rate</label>
                                <input type="number" id="dashboard_refresh_rate" name="dashboard_refresh_rate" 
                                       class="form-control" min="10" max="300" step="10"
                                       value="<?php echo $settings['dashboard_refresh_rate']; ?>">
                                <div class="form-help">Seconds between dashboard updates (10-300)</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="items_per_page">Items Per Page</label>
                                <input type="number" id="items_per_page" name="items_per_page" 
                                       class="form-control" min="5" max="100" step="5"
                                       value="<?php echo $settings['items_per_page']; ?>">
                                <div class="form-help">Number of items to display in lists (5-100)</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Security Tab -->
            <div class="tab-content" id="securityTab">
                <form method="POST">
                    <div class="settings-section">
                        <div class="section-header">
                            <h2><i class="fas fa-key"></i> Change Password</h2>
                            <p>Update your password to keep your account secure</p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="current_password" class="required">Current Password</label>
                                <input type="password" id="current_password" name="current_password" 
                                       class="form-control" placeholder="Enter current password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password" class="required">New Password</label>
                                <input type="password" id="new_password" name="new_password" 
                                       class="form-control" placeholder="Enter new password" required minlength="6">
                                <div class="form-help">Minimum 6 characters</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="required">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="form-control" placeholder="Confirm new password" required minlength="6">
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-top: 30px;">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="settings-section danger-zone">
                    <div class="section-header">
                        <h2><i class="fas fa-exclamation-triangle"></i> Danger Zone</h2>
                        <p>Irreversible actions that affect your account</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Delete Account</label>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div>
                                    <p style="margin-bottom: 5px; color: var(--danger-color); font-weight: 500;">
                                        Permanently delete your account
                                    </p>
                                    <p style="font-size: 0.9rem; color: var(--text-secondary);">
                                        This will remove all your data and cannot be undone.
                                    </p>
                                </div>
                                <button type="button" class="btn btn-danger" onclick="confirmDeleteAccount()">
                                    <i class="fas fa-trash"></i> Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="page-footer">
            <p>Lighthouse Ministers Settings &copy; <?php echo date('Y'); ?></p>
            <p>Settings are saved automatically. Changes take effect immediately.</p>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Theme Toggle
        document.getElementById('themeToggle').addEventListener('click', function() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            // Update theme attribute
            html.setAttribute('data-theme', newTheme);
            
            // Update toggle text and icon
            const icon = this.querySelector('i');
            const text = this.querySelector('.theme-text');
            
            if (newTheme === 'dark') {
                icon.className = 'fas fa-sun';
                text.textContent = 'Light Mode';
            } else {
                icon.className = 'fas fa-moon';
                text.textContent = 'Dark Mode';
            }
            
            // Save theme preference to server via AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'save_theme.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('theme_mode=' + newTheme);
        });
        
        // Tab Switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + 'Tab').classList.add('active');
            
            // Set active button
            event.currentTarget.classList.add('active');
        }
        
        // Form Validation
        document.addEventListener('DOMContentLoaded', function() {
            // Password confirmation validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword) {
                function validatePasswords() {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
                
                newPassword.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
        });
        
        // Confirm Account Deletion
        function confirmDeleteAccount() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone and all your data will be permanently lost.')) {
                alert('Account deletion would be processed here. For safety, this feature is disabled.');
                // In a real implementation, you would make an AJAX call or submit a form here
            }
        }
        
        // Auto-save theme preference when radio button changes
        document.querySelectorAll('input[name="theme_mode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'save_theme.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('theme_mode=' + this.value);
            });
        });
        
        // Update theme toggle based on selected radio button
        document.querySelectorAll('input[name="theme_mode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const html = document.documentElement;
                const newTheme = this.value;
                
                if (newTheme === 'auto') {
                    // For auto mode, use system preference
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    html.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
                } else {
                    html.setAttribute('data-theme', newTheme);
                }
                
                // Update toggle appearance
                const themeToggle = document.getElementById('themeToggle');
                const icon = themeToggle.querySelector('i');
                const text = themeToggle.querySelector('.theme-text');
                
                const currentTheme = html.getAttribute('data-theme');
                if (currentTheme === 'dark') {
                    icon.className = 'fas fa-sun';
                    text.textContent = 'Light Mode';
                } else {
                    icon.className = 'fas fa-moon';
                    text.textContent = 'Dark Mode';
                }
            });
        });
        
        // Listen for system theme changes (for auto mode)
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            const selectedTheme = document.querySelector('input[name="theme_mode"]:checked').value;
            if (selectedTheme === 'auto') {
                const html = document.documentElement;
                html.setAttribute('data-theme', e.matches ? 'dark' : 'light');
            }
        });
        
        // Update main content margin based on sidebar
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
        
        document.addEventListener('DOMContentLoaded', updateMainContentMargin);
        
        const sidebar = document.querySelector('.sidebar-container');
        if (sidebar) {
            const observer = new MutationObserver(updateMainContentMargin);
            observer.observe(sidebar, { 
                attributes: true, 
                attributeFilter: ['class'] 
            });
        }
    </script>
</body>
</html>