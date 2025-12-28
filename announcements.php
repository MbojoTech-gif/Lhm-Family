<?php
// announcements.php - Announcements Management
require_once 'db.php';
requireLogin();

// Check if user can view this page
if (!checkPageAccess('announcements.php')) {
    header('Location: access_denied.php');
    exit();
}

// Check if user can edit this page
$can_edit = checkPageAccess('announcements.php', true);

// Get user information
$user_id = $_SESSION['user_id'];

// Check if announcements table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'announcements'");
if (mysqli_num_rows($table_check) == 0) {
    $create_table_sql = "CREATE TABLE `announcements` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(200) NOT NULL,
        `content` text NOT NULL,
        `category` enum('general','meeting','practice','event','important','reminder') DEFAULT 'general',
        `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
        `author_id` int(11) NOT NULL,
        `start_date` date DEFAULT NULL,
        `end_date` date DEFAULT NULL,
        `status` enum('draft','active','expired','archived') DEFAULT 'draft',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `author_id` (`author_id`),
        KEY `status` (`status`),
        KEY `start_date` (`start_date`),
        CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    mysqli_query($conn, $create_table_sql);
}

// Handle form submissions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_announcement'])) {
        if (!$can_edit) {
            $error = "You don't have permission to create announcements.";
        } else {
            $title = sanitize($_POST['title']);
            $content = sanitize($_POST['content']);
            $category = sanitize($_POST['category']);
            $priority = sanitize($_POST['priority']);
            $start_date = sanitize($_POST['start_date']);
            $end_date = sanitize($_POST['end_date']);
            
            if (empty($title) || empty($content)) {
                $error = "Title and content are required.";
            } else {
                $status = 'active';
                
                $sql = "INSERT INTO announcements (title, content, category, priority, author_id, start_date, end_date, status) 
                        VALUES ('$title', '$content', '$category', '$priority', '$user_id', '$start_date', '$end_date', '$status')";
                
                if (mysqli_query($conn, $sql)) {
                    $success = "Announcement published successfully!";
                } else {
                    $error = "Error creating announcement: " . mysqli_error($conn);
                }
            }
        }
    } elseif (isset($_POST['update_announcement'])) {
        if (!$can_edit) {
            $error = "You don't have permission to update announcements.";
        } else {
            $announcement_id = intval($_POST['announcement_id']);
            $title = sanitize($_POST['title']);
            $content = sanitize($_POST['content']);
            $category = sanitize($_POST['category']);
            $priority = sanitize($_POST['priority']);
            $start_date = sanitize($_POST['start_date']);
            $end_date = sanitize($_POST['end_date']);
            $status = sanitize($_POST['status']);
            
            $sql = "UPDATE announcements SET 
                    title = '$title',
                    content = '$content',
                    category = '$category',
                    priority = '$priority',
                    start_date = '$start_date',
                    end_date = '$end_date',
                    status = '$status'
                    WHERE id = '$announcement_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Announcement updated successfully!";
            } else {
                $error = "Error updating announcement: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['delete_announcement'])) {
        if (!isAdmin()) {
            $error = "Only administrators can delete announcements.";
        } else {
            $announcement_id = intval($_POST['announcement_id']);
            
            $sql = "DELETE FROM announcements WHERE id = '$announcement_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Announcement deleted successfully!";
            } else {
                $error = "Error deleting announcement: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['toggle_status'])) {
        if (!$can_edit) {
            $error = "You don't have permission to change announcement status.";
        } else {
            $announcement_id = intval($_POST['announcement_id']);
            
            $check_sql = "SELECT status FROM announcements WHERE id = '$announcement_id'";
            $check_result = mysqli_query($conn, $check_sql);
            $current_data = mysqli_fetch_assoc($check_result);
            $current_status = $current_data['status'];
            
            $new_status = $current_status === 'active' ? 'archived' : 'active';
            
            $sql = "UPDATE announcements SET status = '$new_status' WHERE id = '$announcement_id'";
            
            if (mysqli_query($conn, $sql)) {
                $status_text = $new_status === 'active' ? 'published' : 'archived';
                $success = "Announcement $status_text successfully!";
            } else {
                $error = "Error updating announcement status: " . mysqli_error($conn);
            }
        }
    }
}

// Get filter parameters
$filter_category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$filter_status = isset($_GET['status']) ? sanitize($_GET['status']) : 'active';
$filter_priority = isset($_GET['priority']) ? sanitize($_GET['priority']) : '';
$search_query = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$sql = "SELECT a.*, u.full_name as author_name 
        FROM announcements a 
        LEFT JOIN users u ON a.author_id = u.id 
        WHERE 1=1";

if ($filter_category) {
    $sql .= " AND a.category = '$filter_category'";
}
if ($filter_status && $can_edit) {
    $sql .= " AND a.status = '$filter_status'";
} else {
    $sql .= " AND a.status = 'active'";
}
if ($filter_priority) {
    $sql .= " AND a.priority = '$filter_priority'";
}
if ($search_query) {
    $sql .= " AND (a.title LIKE '%$search_query%' OR a.content LIKE '%$search_query%')";
}

$sql .= " ORDER BY 
    CASE 
        WHEN a.priority = 'urgent' THEN 1
        WHEN a.priority = 'high' THEN 2
        WHEN a.priority = 'medium' THEN 3
        ELSE 4
    END,
    a.created_at DESC";

$announcements_result = mysqli_query($conn, $sql);
$total_announcements = mysqli_num_rows($announcements_result);

// Categories for dropdown
$categories = [
    'general' => 'General',
    'meeting' => 'Meeting',
    'practice' => 'Practice',
    'event' => 'Event',
    'important' => 'Important',
    'reminder' => 'Reminder'
];

// Priorities
$priorities = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'urgent' => 'Urgent'
];

// Statuses
$statuses = [
    'draft' => 'Draft',
    'active' => 'Active',
    'expired' => 'Expired',
    'archived' => 'Archived'
];

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
    FROM announcements";

$stats_result = mysqli_query($conn, $stats_sql);
$stats_data = mysqli_fetch_assoc($stats_result);

$stats = [
    'total' => $stats_data['total'] ?? 0,
    'active' => $stats_data['active'] ?? 0,
    'draft' => $stats_data['draft'] ?? 0,
    'urgent' => $stats_data['urgent'] ?? 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Lighthouse Ministers</title>
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
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 2rem;
            color: #222;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: #666;
            font-size: 1rem;
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
        
        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #222;
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
        }
        
        /* Filters and Actions */
        .filters-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }
        
        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .filters-header h3 {
            font-size: 1.1rem;
            color: #222;
            font-weight: 600;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            margin-bottom: 8px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 0.85rem;
        }
        
        .filter-select, .search-input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            background: white;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.85rem;
            transition: background 0.2s;
            display: inline-flex;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        /* Announcements Container */
        .announcements-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }
        
        .section-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .section-header h2 {
            font-size: 1.2rem;
            color: #222;
            font-weight: 600;
        }
        
        .announcements-list {
            padding: 0;
        }
        
        .announcement-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .announcement-item:last-child {
            border-bottom: none;
        }
        
        .announcement-header {
            margin-bottom: 12px;
        }
        
        .announcement-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #222;
            margin-bottom: 8px;
        }
        
        .announcement-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .announcement-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-category-general { background: #e3f2fd; color: #1976d2; }
        .badge-category-meeting { background: #f3e5f5; color: #7b1fa2; }
        .badge-category-practice { background: #e8f5e9; color: #388e3c; }
        .badge-category-event { background: #fff3e0; color: #f57c00; }
        .badge-category-important { background: #fce4ec; color: #c2185b; }
        .badge-category-reminder { background: #e8eaf6; color: #303f9f; }
        
        .badge-priority-low { background: #e8f5e9; color: #388e3c; }
        .badge-priority-medium { background: #fff3e0; color: #f57c00; }
        .badge-priority-high { background: #fce4ec; color: #c2185b; }
        .badge-priority-urgent { background: #ffebee; color: #d32f2f; }
        
        .badge-status-draft { background: #fff3cd; color: #856404; }
        .badge-status-active { background: #d4edda; color: #155724; }
        .badge-status-expired { background: #f8d7da; color: #721c24; }
        .badge-status-archived { background: #e2e3e5; color: #383d41; }
        
        .announcement-content {
            color: #444;
            line-height: 1.5;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }
        
        .announcement-dates {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 12px;
        }
        
        .date-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .announcement-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .announcement-author {
            font-size: 0.85rem;
            color: #666;
        }
        
        .announcement-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 10px;
            opacity: 0.2;
        }
        
        .empty-state h3 {
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: #444;
        }
        
        .empty-state p {
            font-size: 0.9rem;
            max-width: 400px;
            margin: 0 auto;
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
            padding: 15px;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            width: 100%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
        }
        
        .modal-header h3 {
            font-size: 1.1rem;
            color: #222;
            font-weight: 600;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            color: #666;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            background: white;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .announcement-header {
                flex-direction: column;
            }
            
            .announcement-footer {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .announcement-actions {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }
            
            .form-row {
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
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Announcements</h1>
            <p>Stay updated with the latest news and important notices</p>
        </div>
        
      
        
        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Announcements</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['draft']; ?></div>
                <div class="stat-label">Drafts</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['urgent']; ?></div>
                <div class="stat-label">Urgent</div>
            </div>
        </div>
        
        <!-- Filters and Actions -->
        <div class="filters-container">
            <div class="filters-header">
                <h3>Filter Announcements</h3>
                <?php if ($can_edit): ?>
                <button class="btn btn-success" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> New Announcement
                </button>
                <?php endif; ?>
            </div>
            
            <form method="GET" action="announcements.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Category</label>
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $value => $label) { ?>
                            <option value="<?php echo $value; ?>" <?php echo $filter_category == $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    
                    <?php if ($can_edit): ?>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <?php foreach ($statuses as $value => $label) { ?>
                            <option value="<?php echo $value; ?>" <?php echo $filter_status == $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <label>Priority</label>
                        <select name="priority" class="filter-select">
                            <option value="">All Priorities</option>
                            <?php foreach ($priorities as $value => $label) { ?>
                            <option value="<?php echo $value; ?>" <?php echo $filter_priority == $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" class="search-input" 
                               placeholder="Search..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="announcements.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Announcements List -->
        <div class="announcements-container">
            <div class="section-header">
                <h2>
                    <?php echo $total_announcements; ?> 
                    Announcement<?php echo $total_announcements != 1 ? 's' : ''; ?> Found
                </h2>
            </div>
            
            <div class="announcements-list">
                <?php if ($total_announcements > 0): ?>
                    <?php while ($announcement = mysqli_fetch_assoc($announcements_result)): ?>
                    <div class="announcement-item">
                        <div class="announcement-header">
                            <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <div class="announcement-meta">
                                <span class="announcement-badge badge-category-<?php echo $announcement['category']; ?>">
                                    <?php echo $categories[$announcement['category']]; ?>
                                </span>
                                <span class="announcement-badge badge-priority-<?php echo $announcement['priority']; ?>">
                                    <?php echo $priorities[$announcement['priority']]; ?>
                                </span>
                                <?php if ($can_edit): ?>
                                <span class="announcement-badge badge-status-<?php echo $announcement['status']; ?>">
                                    <?php echo $statuses[$announcement['status']]; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="announcement-content">
                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                        </div>
                        
                        <?php if ($announcement['start_date'] || $announcement['end_date']): ?>
                        <div class="announcement-dates">
                            <?php if ($announcement['start_date']): ?>
                            <div class="date-item">
                                <i class="far fa-calendar-alt"></i>
                                <span>From: <?php echo date('M j, Y', strtotime($announcement['start_date'])); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($announcement['end_date']): ?>
                            <div class="date-item">
                                <i class="far fa-calendar-check"></i>
                                <span>To: <?php echo date('M j, Y', strtotime($announcement['end_date'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="announcement-footer">
                            <div class="announcement-author">
                                <i class="fas fa-user"></i> 
                                By <?php echo htmlspecialchars($announcement['author_name']); ?>
                                • <?php echo date('M j, Y', strtotime($announcement['created_at'])); ?>
                            </div>
                            
                            <?php if ($can_edit): ?>
                            <div class="announcement-actions">
                                <button class="btn btn-primary btn-sm" onclick="editAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                
                                <?php if ($announcement['status'] === 'active'): ?>
                                <button class="btn btn-secondary btn-sm" onclick="toggleAnnouncementStatus(<?php echo $announcement['id']; ?>)">
                                    <i class="fas fa-archive"></i> Archive
                                </button>
                                <?php else: ?>
                                <button class="btn btn-success btn-sm" onclick="toggleAnnouncementStatus(<?php echo $announcement['id']; ?>)">
                                    <i class="fas fa-eye"></i> Publish
                                </button>
                                <?php endif; ?>
                                
                                <?php if (isAdmin()): ?>
                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $announcement['id']; ?>, '<?php echo addslashes($announcement['title']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bullhorn"></i>
                        <h3>No announcements found</h3>
                        <p>
                            <?php if ($search_query || $filter_category || $filter_status || $filter_priority): ?>
                                Try adjusting your filters or search terms
                            <?php else: ?>
                                <?php if ($can_edit): ?>
                                    No announcements have been created yet.
                                <?php else: ?>
                                    There are no active announcements at the moment.
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($can_edit): ?>
                        <button class="btn btn-success" onclick="openAddModal()" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Create First Announcement
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Announcement Modal -->
    <?php if ($can_edit): ?>
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> New Announcement</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addAnnouncementForm">
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               placeholder="Enter title" required maxlength="200">
                    </div>
                    
                    <div class="form-group">
                        <label for="content">Content *</label>
                        <textarea id="content" name="content" class="form-control" 
                                  placeholder="Enter content" required rows="6"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="form-control">
                                <?php foreach ($categories as $value => $label) { ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select id="priority" name="priority" class="form-control">
                                <?php foreach ($priorities as $value => $label) { ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="add_announcement" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> Publish
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeAddModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Edit Announcement Modal -->
    <?php if ($can_edit): ?>
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Announcement</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editAnnouncementForm">
                    <input type="hidden" id="edit_announcement_id" name="announcement_id">
                    
                    <div class="form-group">
                        <label for="edit_title">Title *</label>
                        <input type="text" id="edit_title" name="title" class="form-control" required maxlength="200">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_content">Content *</label>
                        <textarea id="edit_content" name="content" class="form-control" required rows="6"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_category">Category</label>
                            <select id="edit_category" name="category" class="form-control">
                                <?php foreach ($categories as $value => $label) { ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_priority">Priority</label>
                            <select id="edit_priority" name="priority" class="form-control">
                                <?php foreach ($priorities as $value => $label) { ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_start_date">Start Date</label>
                            <input type="date" id="edit_start_date" name="start_date" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_end_date">End Date</label>
                            <input type="date" id="edit_end_date" name="end_date" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" class="form-control">
                            <?php foreach ($statuses as $value => $label) { ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_announcement" class="btn btn-success">
                            <i class="fas fa-save"></i> Update
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
            document.getElementById('addAnnouncementForm').reset();
            document.getElementById('start_date').value = new Date().toISOString().split('T')[0];
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openEditModal() {
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function editAnnouncement(announcement) {
            document.getElementById('edit_announcement_id').value = announcement.id;
            document.getElementById('edit_title').value = announcement.title;
            document.getElementById('edit_content').value = announcement.content;
            document.getElementById('edit_category').value = announcement.category;
            document.getElementById('edit_priority').value = announcement.priority;
            document.getElementById('edit_status').value = announcement.status;
            
            if (announcement.start_date) {
                const startDate = new Date(announcement.start_date);
                document.getElementById('edit_start_date').value = startDate.toISOString().split('T')[0];
            } else {
                document.getElementById('edit_start_date').value = '';
            }
            
            if (announcement.end_date) {
                const endDate = new Date(announcement.end_date);
                document.getElementById('edit_end_date').value = endDate.toISOString().split('T')[0];
            } else {
                document.getElementById('edit_end_date').value = '';
            }
            
            openEditModal();
        }
        
        function toggleAnnouncementStatus(announcementId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'announcement_id';
            idInput.value = announcementId;
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'toggle_status';
            actionInput.value = '1';
            
            form.appendChild(idInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
        
        function confirmDelete(announcementId, announcementTitle) {
            if (confirm(`Delete "${announcementTitle}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'announcement_id';
                idInput.value = announcementId;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'delete_announcement';
                actionInput.value = '1';
                
                form.appendChild(idInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            
            if (addModal && event.target === addModal) {
                closeAddModal();
            }
            if (editModal && event.target === editModal) {
                closeEditModal();
            }
        };
        
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