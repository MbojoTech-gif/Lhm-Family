<?php
// itenary.php - Itinerary Management
require_once 'db.php';
requireLogin();

// Check if user can view this page
if (!checkPageAccess('itenary.php')) {
    header('Location: access_denied.php');
    exit();
}

// Check if user can edit this page
$can_edit = checkPageAccess('itenary.php', true);

// Get user information
$user_id = $_SESSION['user_id'];

// Get current quarter
$current_month = date('n');
$current_quarter = ceil($current_month / 3);

// Get selected quarter from URL or default to current quarter
$selected_quarter = isset($_GET['quarter']) ? sanitize($_GET['quarter']) : 'Q' . $current_quarter;
$quarters = ['Q1', 'Q2', 'Q3', 'Q4'];

// Handle form submissions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_edit) {
        $error = "You don't have permission to manage events.";
    } else {
        if (isset($_POST['add_event'])) {
            $quarter = sanitize($_POST['quarter']);
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $event_date = sanitize($_POST['event_date']);
            $event_time = sanitize($_POST['event_time']);
            $venue = sanitize($_POST['venue']);
            $type = sanitize($_POST['type']);
            $sponsored = sanitize($_POST['sponsored']);
            $contribution_amount = $sponsored == 'no' ? floatval($_POST['contribution_amount']) : 0.00;
            $contribution_required = $sponsored == 'no' ? floatval($_POST['contribution_required']) : 0.00;
            $status = 'upcoming';
            
            $sql = "INSERT INTO itinerary (quarter, title, description, event_date, event_time, venue, type, sponsored, contribution_amount, contribution_required, status, created_by) 
                    VALUES ('$quarter', '$title', '$description', '$event_date', '$event_time', '$venue', '$type', '$sponsored', '$contribution_amount', '$contribution_required', '$status', '$user_id')";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Event added successfully!";
            } else {
                $error = "Error adding event: " . mysqli_error($conn);
            }
        } elseif (isset($_POST['edit_event'])) {
            $event_id = intval($_POST['event_id']);
            $title = sanitize($_POST['title']);
            $description = sanitize($_POST['description']);
            $event_date = sanitize($_POST['event_date']);
            $event_time = sanitize($_POST['event_time']);
            $venue = sanitize($_POST['venue']);
            $type = sanitize($_POST['type']);
            $sponsored = sanitize($_POST['sponsored']);
            $contribution_amount = $sponsored == 'no' ? floatval($_POST['contribution_amount']) : 0.00;
            $contribution_required = $sponsored == 'no' ? floatval($_POST['contribution_required']) : 0.00;
            $status = sanitize($_POST['status']);
            
            $sql = "UPDATE itinerary SET 
                    title = '$title', 
                    description = '$description', 
                    event_date = '$event_date', 
                    event_time = '$event_time', 
                    venue = '$venue', 
                    type = '$type', 
                    sponsored = '$sponsored', 
                    contribution_amount = '$contribution_amount', 
                    contribution_required = '$contribution_required', 
                    status = '$status' 
                    WHERE id = '$event_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Event updated successfully!";
            } else {
                $error = "Error updating event: " . mysqli_error($conn);
            }
        } elseif (isset($_POST['delete_event'])) {
            if (!isAdmin()) {
                $error = "Only administrators can delete events.";
            } else {
                $event_id = intval($_POST['event_id']);
                
                $sql = "DELETE FROM itinerary WHERE id = '$event_id'";
                if (mysqli_query($conn, $sql)) {
                    $success = "Event deleted successfully!";
                } else {
                    $error = "Error deleting event: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Get events for selected quarter
$sql = "SELECT i.*, u.full_name as created_by_name 
        FROM itinerary i 
        LEFT JOIN users u ON i.created_by = u.id 
        WHERE quarter = '$selected_quarter' 
        ORDER BY event_date, event_time";
$events_result = mysqli_query($conn, $sql);

// Calculate statistics
$total_events = 0;
$sponsored_events = 0;
$total_contributions = 0;
$upcoming_events = 0;
$events = [];

if ($events_result && mysqli_num_rows($events_result) > 0) {
    $total_events = mysqli_num_rows($events_result);
    $events = mysqli_fetch_all($events_result, MYSQLI_ASSOC);
    foreach ($events as $event) {
        if ($event['sponsored'] == 'yes') {
            $sponsored_events++;
        } else {
            $total_contributions += $event['contribution_required'];
        }
        if ($event['status'] == 'upcoming') {
            $upcoming_events++;
        }
    }
    mysqli_data_seek($events_result, 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Itinerary - Lighthouse Ministers</title>
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
            margin-bottom: 25px;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            color: #222;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: #666;
            font-size: 0.95rem;
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
        
        /* Quarter Navigation */
        .quarter-nav {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }
        
        .quarter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .quarter-tab {
            flex: 1;
            min-width: 140px;
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: #333;
        }
        
        .quarter-tab:hover {
            background: #e9ecef;
        }
        
        .quarter-tab.active {
            background: #000;
            color: white;
            border-color: #000;
        }
        
        .quarter-tab h3 {
            font-size: 1.1rem;
            margin-bottom: 3px;
        }
        
        .quarter-tab p {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        /* Statistics Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .stat-card .value {
            font-size: 1.6rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 3px;
        }
        
        .stat-card .description {
            font-size: 0.8rem;
            color: #888;
        }
        
        /* Events Section */
        .events-section {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
            margin-bottom: 25px;
        }
        
        .section-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            font-size: 1.1rem;
            color: #222;
            font-weight: 600;
        }
        
        .add-event-btn {
            background: #000;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .add-event-btn:hover {
            background: #333;
        }
        
        .events-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .events-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
            font-size: 0.85rem;
        }
        
        .events-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top;
            font-size: 0.9rem;
        }
        
        .event-title {
            font-weight: 600;
            color: #222;
            margin-bottom: 5px;
        }
        
        .event-details {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        
        .event-details i {
            width: 14px;
        }
        
        .event-description {
            font-size: 0.85rem;
            color: #777;
            margin-top: 6px;
            line-height: 1.4;
        }
        
        .sponsored-badge {
            display: inline-block;
            padding: 2px 6px;
            background: #28a745;
            color: white;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 4px;
        }
        
        .contribution-badge {
            display: inline-block;
            padding: 2px 6px;
            background: #ffc107;
            color: #333;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 4px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 4px;
        }
        
        .status-upcoming { background: #007bff; color: white; }
        .status-ongoing { background: #17a2b8; color: white; }
        .status-completed { background: #6c757d; color: white; }
        .status-cancelled { background: #dc3546; color: white; }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .edit-btn, .delete-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .edit-btn {
            background: #007bff;
            color: white;
        }
        
        .delete-btn {
            background: #dc3546;
            color: white;
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
        }
        
        /* Footer */
        .page-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.8rem;
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
            max-width: 500px;
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .contribution-fields {
            display: none;
            margin-top: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .btn-submit {
            background: #000;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        @media (max-width: 768px) {
            .quarter-tabs {
                flex-direction: column;
            }
            
            .quarter-tab {
                min-width: 100%;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .events-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Ministry Itinerary</h1>
            <p>View and manage all ministry events and activities</p>
        </div>
        
     
        
        <!-- Quarter Navigation -->
        <div class="quarter-nav">
            <div class="quarter-tabs">
                <?php foreach ($quarters as $quarter): ?>
                <a href="?quarter=<?php echo $quarter; ?>" 
                   class="quarter-tab <?php echo $quarter == $selected_quarter ? 'active' : ''; ?>">
                    <h3><?php echo $quarter; ?></h3>
                    <p>
                        <?php 
                        $quarter_num = substr($quarter, 1);
                        $months = [
                            '1' => 'Jan - Mar',
                            '2' => 'Apr - Jun',
                            '3' => 'Jul - Sep',
                            '4' => 'Oct - Dec'
                        ];
                        echo $months[$quarter_num];
                        ?>
                    </p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3>Total Events</h3>
                <div class="value"><?php echo $total_events; ?></div>
                <div class="description">in <?php echo $selected_quarter; ?></div>
            </div>
            <div class="stat-card">
                <h3>Sponsored Events</h3>
                <div class="value"><?php echo $sponsored_events; ?></div>
                <div class="description">Fully covered</div>
            </div>
            <div class="stat-card">
                <h3>Total Contributions</h3>
                <div class="value">KSh <?php echo number_format($total_contributions, 2); ?></div>
                <div class="description">Required</div>
            </div>
            <div class="stat-card">
                <h3>Upcoming</h3>
                <div class="value"><?php echo $upcoming_events; ?></div>
                <div class="description">Events remaining</div>
            </div>
        </div>
        
        <!-- Events Section -->
        <div class="events-section">
            <div class="section-header">
                <h2><?php echo $selected_quarter; ?> Events</h2>
                <?php if ($can_edit): ?>
                <button class="add-event-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Event
                </button>
                <?php endif; ?>
            </div>
            
            <?php if ($total_events > 0): ?>
            <table class="events-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Event Details</th>
                        <th style="width: 20%;">Date & Time</th>
                        <th style="width: 20%;">Venue & Type</th>
                        <th style="width: 15%;">Financial Info</th>
                        <?php if ($can_edit): ?>
                        <th style="width: 15%;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($events) && count($events) > 0): ?>
                    <?php foreach ($events as $event): ?>
                    <tr>
                        <td>
                            <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                            <div class="event-description">
                                <?php echo nl2br(htmlspecialchars(substr($event['description'], 0, 80) . (strlen($event['description']) > 80 ? '...' : ''))); ?>
                            </div>
                            <span class="status-badge status-<?php echo $event['status']; ?>">
                                <?php echo ucfirst($event['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="event-details">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                            </div>
                            <div class="event-details">
                                <i class="fas fa-clock"></i>
                                <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                            </div>
                        </td>
                        <td>
                            <div class="event-details">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($event['venue']); ?>
                            </div>
                            <div class="event-details">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($event['type']); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($event['sponsored'] == 'yes'): ?>
                            <span class="sponsored-badge">Sponsored</span>
                            <?php else: ?>
                            <span class="contribution-badge">
                                KSh <?php echo number_format($event['contribution_required'], 2); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <?php if ($can_edit): ?>
                        <td>
                            <div class="action-buttons">
                                <button class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($event)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <?php if (isAdmin()): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this event?');">
                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <button type="submit" name="delete_event" class="delete-btn">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $can_edit ? '5' : '4'; ?>" style="text-align: center; padding: 30px; color: #666;">
                            No events found for <?php echo $selected_quarter; ?>.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Events Scheduled</h3>
                <p>There are no events scheduled for <?php echo $selected_quarter; ?> yet.</p>
                <?php if ($can_edit): ?>
                <button class="add-event-btn" onclick="openAddModal()" style="margin-top: 15px;">
                    <i class="fas fa-plus"></i> Add First Event
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="page-footer">
            <p>Lighthouse Ministers Itinerary &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>
    
    <!-- Add Event Modal -->
    <?php if ($can_edit): ?>
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Event</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addEventForm">
                    <div class="form-group">
                        <label for="quarter">Quarter</label>
                        <select id="quarter" name="quarter" class="form-control" required>
                            <?php foreach ($quarters as $quarter): ?>
                            <option value="<?php echo $quarter; ?>" <?php echo $quarter == $selected_quarter ? 'selected' : ''; ?>>
                                <?php echo $quarter; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Event Title</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="event_date">Date</label>
                            <input type="date" id="event_date" name="event_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="event_time">Time</label>
                            <input type="time" id="event_time" name="event_time" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="venue">Venue</label>
                            <input type="text" id="venue" name="venue" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="type">Event Type</label>
                            <select id="type" name="type" class="form-control" required>
                                <option value="Meeting">Meeting</option>
                                <option value="Practice">Practice</option>
                                <option value="Service">Service</option>
                                <option value="Event">Event</option>
                                <option value="Retreat">Retreat</option>
                                <option value="Conference">Conference</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Financial Arrangement</label>
                        <div style="display: flex; gap: 15px; margin-top: 8px;">
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="sponsored" value="yes" onclick="toggleContributionFields(false)">
                                Sponsored
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="sponsored" value="no" onclick="toggleContributionFields(true)" checked>
                                Member Contribution
                            </label>
                        </div>
                    </div>
                    
                    <div id="contributionFields" class="contribution-fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="contribution_required">Required Contribution (KSh)</label>
                                <input type="number" id="contribution_required" name="contribution_required" 
                                       class="form-control" step="0.01" min="0" value="0.00">
                            </div>
                            <div class="form-group">
                                <label for="contribution_amount">Already Collected (KSh)</label>
                                <input type="number" id="contribution_amount" name="contribution_amount" 
                                       class="form-control" step="0.01" min="0" value="0.00">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="add_event" class="btn-submit">
                            <i class="fas fa-plus"></i> Add Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Edit Event Modal -->
    <?php if ($can_edit): ?>
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Event</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editEventForm">
                    <input type="hidden" id="edit_event_id" name="event_id">
                    
                    <div class="form-group">
                        <label for="edit_quarter">Quarter</label>
                        <select id="edit_quarter" name="quarter" class="form-control" required>
                            <?php foreach ($quarters as $quarter): ?>
                            <option value="<?php echo $quarter; ?>"><?php echo $quarter; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_title">Event Title</label>
                        <input type="text" id="edit_title" name="title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_event_date">Date</label>
                            <input type="date" id="edit_event_date" name="event_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_event_time">Time</label>
                            <input type="time" id="edit_event_time" name="event_time" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_venue">Venue</label>
                            <input type="text" id="edit_venue" name="venue" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_type">Event Type</label>
                            <select id="edit_type" name="type" class="form-control" required>
                                <option value="Meeting">Meeting</option>
                                <option value="Practice">Practice</option>
                                <option value="Service">Service</option>
                                <option value="Event">Event</option>
                                <option value="Retreat">Retreat</option>
                                <option value="Conference">Conference</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Financial Arrangement</label>
                        <div style="display: flex; gap: 15px; margin-top: 8px;">
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="radio" id="edit_sponsored_yes" name="sponsored" value="yes" onclick="toggleEditContributionFields(false)">
                                Sponsored
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="radio" id="edit_sponsored_no" name="sponsored" value="no" onclick="toggleEditContributionFields(true)">
                                Member Contribution
                            </label>
                        </div>
                    </div>
                    
                    <div id="editContributionFields" class="contribution-fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_contribution_required">Required Contribution (KSh)</label>
                                <input type="number" id="edit_contribution_required" name="contribution_required" 
                                       class="form-control" step="0.01" min="0">
                            </div>
                            <div class="form-group">
                                <label for="edit_contribution_amount">Already Collected (KSh)</label>
                                <input type="number" id="edit_contribution_amount" name="contribution_amount" 
                                       class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" class="form-control" required>
                            <option value="upcoming">Upcoming</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="edit_event" class="btn-submit">
                            <i class="fas fa-save"></i> Update Event
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
            document.getElementById('event_date').valueAsDate = new Date();
            const now = new Date();
            const minutes = Math.ceil(now.getMinutes() / 15) * 15;
            now.setMinutes(minutes);
            document.getElementById('event_time').value = now.toTimeString().slice(0, 5);
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('addEventForm').reset();
            toggleContributionFields(true);
        }
        
        function openEditModal(event) {
            document.getElementById('editModal').style.display = 'flex';
            
            document.getElementById('edit_event_id').value = event.id;
            document.getElementById('edit_quarter').value = event.quarter;
            document.getElementById('edit_title').value = event.title;
            document.getElementById('edit_description').value = event.description;
            document.getElementById('edit_event_date').value = event.event_date;
            document.getElementById('edit_event_time').value = event.event_time;
            document.getElementById('edit_venue').value = event.venue;
            document.getElementById('edit_type').value = event.type;
            document.getElementById('edit_status').value = event.status;
            
            if (event.sponsored === 'yes') {
                document.getElementById('edit_sponsored_yes').checked = true;
                toggleEditContributionFields(false);
            } else {
                document.getElementById('edit_sponsored_no').checked = true;
                toggleEditContributionFields(true);
                document.getElementById('edit_contribution_required').value = parseFloat(event.contribution_required).toFixed(2);
                document.getElementById('edit_contribution_amount').value = parseFloat(event.contribution_amount).toFixed(2);
            }
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function toggleContributionFields(show) {
            const fields = document.getElementById('contributionFields');
            fields.style.display = show ? 'block' : 'none';
        }
        
        function toggleEditContributionFields(show) {
            const fields = document.getElementById('editContributionFields');
            fields.style.display = show ? 'block' : 'none';
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            toggleContributionFields(true);
            
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