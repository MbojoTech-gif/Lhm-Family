<?php
require_once 'db.php';
requireLogin();

if (!checkPageAccess('songs.php')) {
    header('Location: access_denied.php');
    exit();
}

$can_edit = checkPageAccess('songs.php', true);
$user_id = $_SESSION['user_id'];

$upload_dir = "assets/uploads/songs/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($can_edit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_song'])) {
        $title = sanitize($_POST['title']);
        
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
            $sql = "INSERT INTO songs (title, pdf_filename, original_filename, file_size, added_by) 
                    VALUES ('$title', '$pdf_filename', '$original_filename', '$file_size', '$user_id')";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Song added successfully!";
            } else {
                $error = "Error adding song: " . mysqli_error($conn);
                if (file_exists($target_file)) {
                    unlink($target_file);
                }
            }
        }
    } elseif (isset($_POST['edit_song'])) {
        $song_id = intval($_POST['song_id']);
        $title = sanitize($_POST['title']);
        
        $pdf_update_sql = '';
        $old_pdf = '';
        
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
            $file = $_FILES['pdf_file'];
            $original_filename = basename($file['name']);
            $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
            
            if ($file_extension == 'pdf') {
                $old_sql = "SELECT pdf_filename FROM songs WHERE id = '$song_id'";
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
            $sql = "UPDATE songs SET title = '$title' $pdf_update_sql WHERE id = '$song_id'";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Song updated successfully!";
            } else {
                $error = "Error updating song: " . mysqli_error($conn);
            }
        }
    } elseif (isset($_POST['delete_song'])) {
        if (!isAdmin()) {
            $error = "Only administrators can delete songs.";
        } else {
            $song_id = intval($_POST['song_id']);
            
            $song_sql = "SELECT pdf_filename FROM songs WHERE id = '$song_id'";
            $song_result = mysqli_query($conn, $song_sql);
            $song_data = mysqli_fetch_assoc($song_result);
            
            if ($song_data['pdf_filename'] && file_exists($upload_dir . $song_data['pdf_filename'])) {
                unlink($upload_dir . $song_data['pdf_filename']);
            }
            
            $sql = "DELETE FROM songs WHERE id = '$song_id'";
            if (mysqli_query($conn, $sql)) {
                $success = "Song deleted successfully!";
            } else {
                $error = "Error deleting song: " . mysqli_error($conn);
            }
        }
    }
}

$songs_sql = "SELECT s.*, u.full_name as added_by_name 
              FROM songs s 
              LEFT JOIN users u ON s.added_by = u.id 
              ORDER BY s.title ASC";
$songs_result = mysqli_query($conn, $songs_sql);
$total_songs = mysqli_num_rows($songs_result);

$songs_per_column = 10;
$total_columns = ceil($total_songs / $songs_per_column);
$songs_by_column = [];

if ($total_songs > 0) {
    mysqli_data_seek($songs_result, 0);
    $song_counter = 0;
    $column_index = 0;
    
    while ($song = mysqli_fetch_assoc($songs_result)) {
        $songs_by_column[$column_index][] = $song;
        $song_counter++;
        
        if ($song_counter % $songs_per_column == 0) {
            $column_index++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Songs Library - Lighthouse Ministers</title>
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
        
        .songs-container {
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
        
        .add-song-btn {
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
        
        .add-song-btn:hover {
            background: #333;
        }
        
        .songs-grid {
            padding: 25px;
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
        }
        
        .song-column {
            flex: 1;
            min-width: 300px;
        }
        
        .song-list {
            list-style: none;
        }
        
        .song-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .song-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .song-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .song-number {
            font-weight: 600;
            color: #007bff;
            min-width: 25px;
        }
        
        .song-title {
            font-size: 1rem;
            font-weight: 500;
            color: #222;
        }
        
        .song-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .download-btn {
            background: #28a745;
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .download-btn:hover {
            background: #218838;
        }
        
        .edit-btn, .delete-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
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
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h1>Songs Library</h1>
            <p>Browse and download ministry songs in PDF format.</p>
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
                    <i class="fas fa-music"></i>
                </div>
                <div class="stat-text">
                    <div class="stat-number"><?php echo number_format($total_songs); ?></div>
                    <div>Total Songs</div>
                </div>
            </div>
            
            <?php 
            $total_downloads_sql = "SELECT SUM(download_count) as total FROM songs";
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
        
        <div class="songs-container">
            <div class="section-header">
                <h2>All Songs</h2>
                <?php if ($can_edit): ?>
                <button class="add-song-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Song
                </button>
                <?php endif; ?>
            </div>
            
            <?php if ($total_songs > 0): ?>
            <div class="songs-grid">
                <?php foreach ($songs_by_column as $column_index => $column_songs): ?>
                <div class="song-column">
                    <ul class="song-list">
                        <?php $song_counter = ($column_index * $songs_per_column) + 1; ?>
                        <?php foreach ($column_songs as $song): ?>
                        <li class="song-item">
                            <div class="song-info">
                                <span class="song-number"><?php echo $song_counter++; ?>.</span>
                                <span class="song-title"><?php echo htmlspecialchars($song['title']); ?></span>
                            </div>
                            
                            <div class="song-actions">
                                <a href="<?php echo $upload_dir . htmlspecialchars($song['pdf_filename']); ?>" 
                                   download="<?php echo htmlspecialchars($song['title'] . '.pdf'); ?>" 
                                   class="download-btn"
                                   onclick="trackDownload(<?php echo $song['id']; ?>, '<?php echo htmlspecialchars($song['title']); ?>')"
                                   title="Download PDF">
                                    <i class="fas fa-download"></i>
                                </a>
                                
                                <?php if ($can_edit): ?>
                                <button class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($song)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if (isAdmin()): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this song?');">
                                    <input type="hidden" name="song_id" value="<?php echo $song['id']; ?>">
                                    <button type="submit" name="delete_song" class="delete-btn" title="Delete Song">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-music"></i>
                <h3>No Songs Available</h3>
                <p>No songs have been added to the library yet.</p>
                <?php if ($can_edit): ?>
                <button class="add-song-btn" onclick="openAddModal()" style="margin-top: 15px;">
                    <i class="fas fa-plus"></i> Add First Song
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="page-footer">
            <p>Lighthouse Ministers Songs Library &copy; <?php echo date('Y'); ?></p>
            <p>All songs are available in PDF format for easy download and printing.</p>
        </div>
    </div>
    
    <?php if ($can_edit): ?>
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Song</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addSongForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title" class="required">Song Title *</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               placeholder="Enter song title" required>
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
                        <button type="submit" name="add_song" class="btn-submit">
                            <i class="fas fa-plus"></i> Add Song
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Song</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editSongForm" enctype="multipart/form-data">
                    <input type="hidden" id="edit_song_id" name="song_id">
                    
                    <div class="form-group">
                        <label for="edit_title" class="required">Song Title *</label>
                        <input type="text" id="edit_title" name="title" class="form-control" required>
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
                        <button type="submit" name="edit_song" class="btn-submit">
                            <i class="fas fa-save"></i> Update Song
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
            document.getElementById('addSongForm').reset();
            document.getElementById('fileName').textContent = '';
        }
        
        function openEditModal(song) {
            document.getElementById('editModal').style.display = 'flex';
            
            document.getElementById('edit_song_id').value = song.id;
            document.getElementById('edit_title').value = song.title;
            document.getElementById('currentFileName').textContent = song.original_filename || song.pdf_filename;
            document.getElementById('editFileName').textContent = '';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editSongForm').reset();
        }
        
        function showFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : '';
            document.getElementById('fileName').textContent = fileName;
        }
        
        function showEditFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : '';
            document.getElementById('editFileName').textContent = fileName;
        }
        
        function trackDownload(songId, songTitle) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'track_download.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('song_id=' + songId);
            
            setTimeout(() => {
                alert('Downloading: ' + songTitle + '\nThe download will start shortly...');
            }, 100);
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