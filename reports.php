<?php
require_once 'db.php';
requireLogin();

if (!checkPageAccess('reports.php')) {
    header('Location: access_denied.php');
    exit();
}

$can_edit = checkPageAccess('reports.php', true);
$user_id = $_SESSION['user_id'];

$upload_dir = "assets/uploads/reports/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$departments = [
    'Publicity' => 'Publicity',
    'Music' => 'Music',
    'Spiritual' => 'Spiritual',
    'Welfare' => 'Welfare',
    'Treasurer' => 'Treasurer',
    'Chairperson' => 'Chairperson',
    'Secretary' => 'Secretary',
    'Logistics' => 'Logistics',
];

if ($can_edit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_report'])) {
        $department = sanitize($_POST['department']);
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        
        $pdf_uploaded = false;
        $pdf_filename = '';
        $original_filename = '';
        $file_size = 0;
        
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
            $file = $_FILES['pdf_file'];
            $original_filename = basename($file['name']);
            $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
            
            if ($file_extension == 'pdf') {
                $pdf_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $title) . '.pdf';
                $target_file = $upload_dir . $pdf_filename;
                
                if ($file['size'] <= 10485760) {
                    if (move_uploaded_file($file['tmp_name'], $target_file)) {
                        $pdf_uploaded = true;
                        $file_size = $file['size'];
                    } else {
                        $error = "Error uploading PDF file.";
                    }
                } else {
                    $error = "PDF file is too large. Maximum size is 10MB.";
                }
            } else {
                $error = "Only PDF files are allowed.";
            }
        } else {
            $error = "Please select a PDF file.";
        }
        
        if ($pdf_uploaded) {
            $sql = "INSERT INTO reports (department, title, description, pdf_filename, original_filename, file_size, added_by) 
                    VALUES ('$department', '$title', '$description', '$pdf_filename', '$original_filename', '$file_size', '$user_id')";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Report added successfully!";
            } else {
                $error = "Error adding report: " . mysqli_error($conn);
                if (file_exists($target_file)) {
                    unlink($target_file);
                }
            }
        }
    } elseif (isset($_POST['edit_report'])) {
        $report_id = intval($_POST['report_id']);
        $department = sanitize($_POST['department']);
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        
        $pdf_update_sql = '';
        $old_pdf = '';
        
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
            $file = $_FILES['pdf_file'];
            $original_filename = basename($file['name']);
            $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
            
            if ($file_extension == 'pdf') {
                $old_sql = "SELECT pdf_filename FROM reports WHERE id = '$report_id'";
                $old_result = mysqli_query($conn, $old_sql);
                $old_data = mysqli_fetch_assoc($old_result);
                $old_pdf = $old_data['pdf_filename'];
                
                $pdf_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $title) . '.pdf';
                $target_file = $upload_dir . $pdf_filename;
                
                if ($file['size'] <= 10485760) {
                    if (move_uploaded_file($file['tmp_name'], $target_file)) {
                        $pdf_update_sql = ", pdf_filename = '$pdf_filename', original_filename = '$original_filename', file_size = '{$file['size']}'";
                        
                        if ($old_pdf && file_exists($upload_dir . $old_pdf)) {
                            unlink($upload_dir . $old_pdf);
                        }
                    } else {
                        $error = "Error uploading PDF file.";
                    }
                } else {
                    $error = "PDF file is too large. Maximum size is 10MB.";
                }
            } else {
                $error = "Only PDF files are allowed.";
            }
        }
        
        if (!isset($error)) {
            $sql = "UPDATE reports SET department = '$department', title = '$title', description = '$description' $pdf_update_sql WHERE id = '$report_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Report updated successfully!";
            } else {
                $error = "Error updating report: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['delete_report'])) {
        if (!isAdmin()) {
            $error = "Only administrators can delete reports.";
        } else {
            $report_id = intval($_POST['report_id']);
            
            $report_sql = "SELECT pdf_filename FROM reports WHERE id = '$report_id'";
            $report_result = mysqli_query($conn, $report_sql);
            $report_data = mysqli_fetch_assoc($report_result);
            
            if ($report_data['pdf_filename'] && file_exists($upload_dir . $report_data['pdf_filename'])) {
                unlink($upload_dir . $report_data['pdf_filename']);
            }
            
            $sql = "DELETE FROM reports WHERE id = '$report_id'";
            if (mysqli_query($conn, $sql)) {
                $success = "Report deleted successfully!";
            } else {
                $error = "Error deleting report: " . mysqli_error($conn);
            }
        }
    }
}

$reports_sql = "SELECT r.*, u.full_name as added_by_name 
                FROM reports r 
                LEFT JOIN users u ON r.added_by = u.id 
                ORDER BY r.department ASC, r.created_at DESC";
$reports_result = mysqli_query($conn, $reports_sql);
$total_reports = mysqli_num_rows($reports_result);

$reports_by_department = [];
$department_counts = [];

if ($total_reports > 0) {
    mysqli_data_seek($reports_result, 0);
    
    while ($report = mysqli_fetch_assoc($reports_result)) {
        $department = $report['department'];
        if (!isset($reports_by_department[$department])) {
            $reports_by_department[$department] = [];
            $department_counts[$department] = 0;
        }
        $reports_by_department[$department][] = $report;
        $department_counts[$department]++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Reports - Lighthouse Ministers</title>
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
        
        .reports-container {
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
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            color: #222;
            font-weight: 600;
        }
        
        .add-report-btn {
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
        
        .add-report-btn:hover {
            background: #333;
        }
        
        .department-section {
            margin: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .department-header {
            background: #f8f9fa;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .department-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .department-icon {
            color: #007bff;
            font-size: 1.2rem;
        }
        
        .department-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #222;
        }
        
        .report-count {
            background: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .reports-grid {
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .report-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .report-header {
            margin-bottom: 15px;
        }
        
        .report-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #222;
            margin-bottom: 5px;
        }
        
        .report-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 10px;
        }
        
        .report-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 15px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
        }
        
        .report-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .download-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            flex: 1;
            justify-content: center;
        }
        
        .download-btn:hover {
            background: #218838;
        }
        
        .edit-btn, .delete-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        
        .edit-btn {
            background: #007bff;
            color: white;
        }
        
        .delete-btn {
            background: #dc3546;
            color: white;
        }
        
        .edit-btn:hover, .delete-btn:hover {
            opacity: 0.9;
        }
        
        .page-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.85rem;
        }
        
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
            max-width: 600px;
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
            color: #dc3546;
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
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        
        .file-upload:hover {
            border-color: #007bff;
        }
        
        .file-upload i {
            font-size: 2rem;
            color: #007bff;
            margin-bottom: 10px;
        }
        
        .file-input {
            display: none;
        }
        
        .file-name {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .file-requirements {
            font-size: 0.8rem;
            color: #888;
            margin-top: 5px;
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
        
        .stats-info {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
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
        }
        
        .file-size {
            font-size: 0.8rem;
            color: #666;
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            margin-top: 5px;
        }
        
        .department-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .dept-filter-btn {
            padding: 8px 16px;
            background: #f0f0f0;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .dept-filter-btn:hover {
            background: #e0e0e0;
        }
        
        .dept-filter-btn.active {
            background: #007bff;
            color: white;
        }
        
        @media (max-width: 768px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .stats-info {
                flex-direction: column;
                gap: 15px;
            }
        }
        
        .permission-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h1>Department Reports</h1>
            <p>Access and download ministry department reports and documents.</p>
        </div>
        
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
        
        <div class="stats-info">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-text">
                    <div class="stat-number"><?php echo number_format($total_reports); ?></div>
                    <div>Total Reports</div>
                </div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-text">
                    <div class="stat-number"><?php echo count($reports_by_department); ?></div>
                    <div>Departments</div>
                </div>
            </div>
            
            <?php 
            $total_downloads_sql = "SELECT SUM(download_count) as total FROM reports";
            $total_downloads_result = mysqli_query($conn, $total_downloads_sql);
            $total_downloads = mysqli_fetch_assoc($total_downloads_result)['total'] ?? 0;
            ?>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-download"></i>
                </div>
                <div class="stat-text">
                    <div class="stat-number"><?php echo number_format($total_downloads); ?></div>
                    <div>Total Downloads</div>
                </div>
            </div>
        </div>
        
        <?php if (!$can_edit): ?>
        <?php endif; ?>
        
        <div class="department-selector">
            <button class="dept-filter-btn active" onclick="filterByDepartment('all')">All Departments</button>
            <?php foreach ($departments as $key => $name): ?>
                <button class="dept-filter-btn" onclick="filterByDepartment('<?php echo $key; ?>')">
                    <?php echo $name; ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div class="reports-container">
            <div class="section-header">
                <h2>Department Reports</h2>
                <?php if ($can_edit): ?>
                <button class="add-report-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Report
                </button>
                <?php endif; ?>
            </div>
            
            <?php if ($total_reports > 0): ?>
                <?php foreach ($reports_by_department as $department => $dept_reports): ?>
                <div class="department-section" data-department="<?php echo htmlspecialchars($department); ?>">
                    <div class="department-header">
                        <div class="department-title">
                            <i class="fas fa-building department-icon"></i>
                            <span class="department-name"><?php echo $departments[$department] ?? $department; ?></span>
                        </div>
                        <span class="report-count"><?php echo $department_counts[$department]; ?> report(s)</span>
                    </div>
                    
                    <div class="reports-grid">
                        <?php foreach ($dept_reports as $report): ?>
                        <div class="report-card">
                            <div class="report-header">
                                <h3 class="report-title"><?php echo htmlspecialchars($report['title']); ?></h3>
                                <?php if ($report['description']): ?>
                                <p class="report-description"><?php echo htmlspecialchars($report['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="report-meta">
                                <div class="report-info">
                                    <div>Added by: <?php echo htmlspecialchars($report['added_by_name']); ?></div>
                                    <div>Date: <?php echo date('M d, Y', strtotime($report['created_at'])); ?></div>
                                    <?php if ($report['file_size']): ?>
                                    <div class="file-size">
                                        <?php echo formatFileSize($report['file_size']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="download-count">
                                    <i class="fas fa-download"></i> <?php echo $report['download_count']; ?>
                                </div>
                            </div>
                            
                            <div class="report-actions">
                                <a href="<?php echo $upload_dir . htmlspecialchars($report['pdf_filename']); ?>" 
                                   download="<?php echo htmlspecialchars($report['title'] . '.pdf'); ?>" 
                                   class="download-btn"
                                   onclick="trackDownload(<?php echo $report['id']; ?>, '<?php echo htmlspecialchars($report['title']); ?>')">
                                    <i class="fas fa-download"></i> Download PDF
                                </a>
                                
                                <?php if ($can_edit): ?>
                                <button class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($report)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if (isAdmin()): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this report?');">
                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                    <button type="submit" name="delete_report" class="delete-btn" title="Delete Report">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>No Reports Available</h3>
                <p>No department reports have been added yet.</p>
                <?php if ($can_edit): ?>
                <button class="add-report-btn" onclick="openAddModal()" style="margin-top: 15px;">
                    <i class="fas fa-plus"></i> Add First Report
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="page-footer">
            <p>Lighthouse Ministers Department Reports &copy; <?php echo date('Y'); ?></p>
            <p>All reports are confidential and for internal ministry use only.</p>
        </div>
    </div>
    
    <?php if ($can_edit): ?>
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Report</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addReportForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="department" class="required">Department *</label>
                        <select id="department" name="department" class="form-control" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $key => $name): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>">
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="title" class="required">Report Title *</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               placeholder="Enter report title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description (Optional)</label>
                        <textarea id="description" name="description" class="form-control" 
                                  placeholder="Enter report description"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="pdf_file" class="required">PDF File *</label>
                        <div class="file-upload" onclick="document.getElementById('pdf_file').click()">
                            <i class="fas fa-file-pdf"></i>
                            <div>Click to upload PDF file</div>
                            <div class="file-requirements">Maximum file size: 10MB</div>
                            <div id="fileName" class="file-name"></div>
                        </div>
                        <input type="file" id="pdf_file" name="pdf_file" class="file-input" 
                               accept=".pdf" required onchange="showFileName(this)">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="add_report" class="btn-submit">
                            <i class="fas fa-plus"></i> Add Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Report</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editReportForm" enctype="multipart/form-data">
                    <input type="hidden" id="edit_report_id" name="report_id">
                    
                    <div class="form-group">
                        <label for="edit_department" class="required">Department *</label>
                        <select id="edit_department" name="department" class="form-control" required>
                            <?php foreach ($departments as $key => $name): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>">
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_title" class="required">Report Title *</label>
                        <input type="text" id="edit_title" name="title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_description">Description (Optional)</label>
                        <textarea id="edit_description" name="description" class="form-control"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_pdf_file">PDF File (Optional)</label>
                        <div>Current file: <span id="currentFileName" style="font-weight: 500;"></span></div>
                        <div class="file-upload" onclick="document.getElementById('edit_pdf_file').click()" style="margin-top: 10px;">
                            <i class="fas fa-file-pdf"></i>
                            <div>Click to upload new PDF file</div>
                            <div class="file-requirements">Leave empty to keep current file</div>
                            <div id="editFileName" class="file-name"></div>
                        </div>
                        <input type="file" id="edit_pdf_file" name="pdf_file" class="file-input" 
                               accept=".pdf" onchange="showEditFileName(this)">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="edit_report" class="btn-submit">
                            <i class="fas fa-save"></i> Update Report
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
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('addReportForm').reset();
            document.getElementById('fileName').textContent = '';
        }
        
        function openEditModal(report) {
            document.getElementById('editModal').style.display = 'flex';
            
            document.getElementById('edit_report_id').value = report.id;
            document.getElementById('edit_department').value = report.department;
            document.getElementById('edit_title').value = report.title;
            document.getElementById('edit_description').value = report.description || '';
            document.getElementById('currentFileName').textContent = report.original_filename || report.pdf_filename;
            document.getElementById('editFileName').textContent = '';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editReportForm').reset();
        }
        
        function showFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : '';
            document.getElementById('fileName').textContent = fileName;
        }
        
        function showEditFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : '';
            document.getElementById('editFileName').textContent = fileName;
        }
        
        function trackDownload(reportId, reportTitle) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'track_download.php?type=report', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('report_id=' + reportId);
            
            setTimeout(() => {
                alert('Downloading: ' + reportTitle + '\nThe download will start shortly...');
            }, 100);
        }
        
        function filterByDepartment(dept) {
            const sections = document.querySelectorAll('.department-section');
            const buttons = document.querySelectorAll('.dept-filter-btn');
            
            buttons.forEach(btn => {
                if (btn.textContent === 'All Departments' && dept === 'all') {
                    btn.classList.add('active');
                } else if (btn.getAttribute('onclick')?.includes(dept)) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            sections.forEach(section => {
                if (dept === 'all' || section.dataset.department === dept) {
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            });
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
        }
        
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
<?php
// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}
?>