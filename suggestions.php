<?php
require_once 'db.php';
requireLogin();

if (!checkPageAccess('suggestions.php')) {
    header('Location: access_denied.php');
    exit();
}

$can_edit = checkPageAccess('suggestions.php', true);
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_suggestion'])) {
        if (!$can_edit) {
            $error = "You don't have permission to post suggestions.";
        } else {
            $content = sanitize($_POST['content']);
            $type = sanitize($_POST['type']);
            $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : NULL;
            
            if (!empty($content)) {
                $sql = "INSERT INTO suggestions (user_id, content, type, parent_id) 
                        VALUES ('$user_id', '$content', '$type', " . ($parent_id ? "'$parent_id'" : "NULL") . ")";
                
                if (mysqli_query($conn, $sql)) {
                    $success = "Your " . ($parent_id ? "reply" : "suggestion") . " has been posted!";
                } else {
                    $error = "Error posting suggestion: " . mysqli_error($conn);
                }
            } else {
                $error = "Please enter some content";
            }
        }
    } elseif (isset($_POST['delete_suggestion'])) {
        $suggestion_id = intval($_POST['suggestion_id']);
        
        $check_sql = "SELECT user_id FROM suggestions WHERE id = '$suggestion_id'";
        $check_result = mysqli_query($conn, $check_sql);
        $check_data = mysqli_fetch_assoc($check_result);
        
        if ($check_data && ($user_id == $check_data['user_id'] || isAdmin())) {
            $sql = "DELETE FROM suggestions WHERE id = '$suggestion_id' OR parent_id = '$suggestion_id'";
            if (mysqli_query($conn, $sql)) {
                $success = "Suggestion deleted successfully!";
            } else {
                $error = "Error deleting suggestion: " . mysqli_error($conn);
            }
        } else {
            $error = "You can only delete your own suggestions";
        }
    } elseif (isset($_POST['like_suggestion']) || isset($_POST['dislike_suggestion'])) {
        $suggestion_id = intval($_POST['suggestion_id']);
        $reaction = isset($_POST['like_suggestion']) ? 'like' : 'dislike';
        
        $check_sql = "SELECT * FROM suggestion_likes WHERE suggestion_id = '$suggestion_id' AND user_id = '$user_id'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $existing = mysqli_fetch_assoc($check_result);
            if ($existing['reaction'] != $reaction) {
                $update_sql = "UPDATE suggestion_likes SET reaction = '$reaction' WHERE id = '{$existing['id']}'";
                mysqli_query($conn, $update_sql);
                
                $like_change = $reaction == 'like' ? 1 : -1;
                $dislike_change = $reaction == 'dislike' ? 1 : -1;
                $update_counts = "UPDATE suggestions SET 
                                 likes = likes + $like_change, 
                                 dislikes = dislikes + $dislike_change 
                                 WHERE id = '$suggestion_id'";
                mysqli_query($conn, $update_counts);
            }
        } else {
            $insert_sql = "INSERT INTO suggestion_likes (suggestion_id, user_id, reaction) 
                          VALUES ('$suggestion_id', '$user_id', '$reaction')";
            mysqli_query($conn, $insert_sql);
            
            $field = $reaction == 'like' ? 'likes' : 'dislikes';
            $update_sql = "UPDATE suggestions SET $field = $field + 1 WHERE id = '$suggestion_id'";
            mysqli_query($conn, $update_sql);
        }
        
        if (isset($_POST['ajax'])) {
            $counts_sql = "SELECT likes, dislikes FROM suggestions WHERE id = '$suggestion_id'";
            $counts_result = mysqli_query($conn, $counts_sql);
            $counts = mysqli_fetch_assoc($counts_result);
            
            $user_reaction_sql = "SELECT reaction FROM suggestion_likes WHERE suggestion_id = '$suggestion_id' AND user_id = '$user_id'";
            $user_reaction_result = mysqli_query($conn, $user_reaction_sql);
            $user_reaction = mysqli_num_rows($user_reaction_result) > 0 ? mysqli_fetch_assoc($user_reaction_result)['reaction'] : null;
            
            echo json_encode([
                'likes' => $counts['likes'],
                'dislikes' => $counts['dislikes'],
                'user_reaction' => $user_reaction
            ]);
            exit;
        }
    }
}

$suggestions_sql = "SELECT s.*, u.full_name, u.username, u.profile_pic,
                   (SELECT COUNT(*) FROM suggestions WHERE parent_id = s.id) as reply_count
                   FROM suggestions s 
                   LEFT JOIN users u ON s.user_id = u.id 
                   WHERE s.parent_id IS NULL 
                   ORDER BY 
                     CASE WHEN s.status = 'active' THEN 1
                          WHEN s.status = 'resolved' THEN 2
                          ELSE 3
                     END,
                     s.created_at DESC";
$suggestions_result = mysqli_query($conn, $suggestions_sql);

$user_reactions = [];
if ($suggestions_result && mysqli_num_rows($suggestions_result) > 0) {
    mysqli_data_seek($suggestions_result, 0);
    $suggestion_ids = [];
    while ($suggestion = mysqli_fetch_assoc($suggestions_result)) {
        $suggestion_ids[] = $suggestion['id'];
    }
    
    if (!empty($suggestion_ids)) {
        $ids_str = implode(',', $suggestion_ids);
        $reactions_sql = "SELECT suggestion_id, reaction FROM suggestion_likes WHERE user_id = '$user_id' AND suggestion_id IN ($ids_str)";
        $reactions_result = mysqli_query($conn, $reactions_sql);
        while ($reaction = mysqli_fetch_assoc($reactions_result)) {
            $user_reactions[$reaction['suggestion_id']] = $reaction['reaction'];
        }
    }
    mysqli_data_seek($suggestions_result, 0);
}

$stats_sql = "SELECT 
              COUNT(*) as total_suggestions,
              SUM(CASE WHEN type = 'suggestion' THEN 1 ELSE 0 END) as suggestions,
              SUM(CASE WHEN type = 'question' THEN 1 ELSE 0 END) as questions,
              SUM(CASE WHEN type = 'comment' THEN 1 ELSE 0 END) as comments,
              SUM(likes) as total_likes,
              SUM(dislikes) as total_dislikes
              FROM suggestions 
              WHERE parent_id IS NULL";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suggestions & Questions - Lighthouse Ministers</title>
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
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .suggestions .stat-icon { color: #007bff; }
        .questions .stat-icon { color: #28a745; }
        .comments .stat-icon { color: #ffc107; }
        .likes .stat-icon { color: #dc3546; }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .new-post-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .new-post-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .user-info h3 {
            font-size: 1.1rem;
            color: #222;
            margin-bottom: 3px;
        }
        
        .user-info p {
            font-size: 0.9rem;
            color: #666;
        }
        
        .post-form {
            margin-top: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .post-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .type-option {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .type-option.active {
            background: #007bff;
            color: white;
        }
        
        .type-option input {
            display: none;
        }
        
        .post-textarea {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            resize: vertical;
        }
        
        .post-textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .post-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .post-btn:hover {
            background: #0056b3;
        }
        
        .cancel-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .cancel-btn:hover {
            background: #5a6268;
        }
        
        .feed-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            color: #222;
            font-weight: 600;
        }
        
        .feed-content {
            padding: 25px;
        }
        
        .post-item {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
            background: white;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .post-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .post-header {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .post-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .post-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .post-user-info h4 {
            font-size: 1rem;
            color: #222;
            margin-bottom: 3px;
        }
        
        .post-meta {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .post-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .type-suggestion { background: rgba(0, 123, 255, 0.1); color: #007bff; }
        .type-question { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .type-comment { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        
        .post-content {
            padding: 20px;
            font-size: 1rem;
            color: #333;
            line-height: 1.6;
        }
        
        .post-actions {
            padding: 15px 20px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .reaction-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .like-btn, .dislike-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            color: #666;
            transition: color 0.3s;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .like-btn:hover {
            color: #28a745;
            background: rgba(40, 167, 69, 0.1);
        }
        
        .dislike-btn:hover {
            color: #dc3546;
            background: rgba(220, 53, 69, 0.1);
        }
        
        .like-btn.active {
            color: #28a745;
            font-weight: 600;
        }
        
        .dislike-btn.active {
            color: #dc3546;
            font-weight: 600;
        }
        
        .reply-btn, .delete-btn {
            background: none;
            border: 1px solid #ddd;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            color: #666;
            transition: all 0.3s;
        }
        
        .reply-btn:hover {
            background: #f8f9fa;
            border-color: #007bff;
            color: #007bff;
        }
        
        .delete-btn:hover {
            background: #f8f9fa;
            border-color: #dc3546;
            color: #dc3546;
        }
        
        .replies-container {
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            padding: 20px;
        }
        
        .reply-form {
            margin-bottom: 20px;
            background: white;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e0e0e0;
        }
        
        .reply-form textarea {
            width: 100%;
            min-height: 80px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            resize: vertical;
            margin-bottom: 10px;
        }
        
        .reply-form textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .reply-form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .replies-list {
            margin-top: 15px;
        }
        
        .reply-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 3px solid #007bff;
        }
        
        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .reply-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .reply-user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .reply-user-info h5 {
            font-size: 0.9rem;
            color: #222;
            margin-bottom: 2px;
        }
        
        .reply-time {
            font-size: 0.75rem;
            color: #888;
        }
        
        .reply-content {
            font-size: 0.95rem;
            color: #444;
            line-height: 1.5;
        }
        
        .reply-actions {
            margin-top: 10px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .view-replies-btn {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
        }
        
        .view-replies-btn:hover {
            text-decoration: underline;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .status-resolved { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
        .status-hidden { background: rgba(220, 53, 69, 0.1); color: #dc3546; }
        
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
        
        .page-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.85rem;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
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
            <h1>Suggestions & Questions</h1>
            <p>Share your ideas, ask questions, and discuss with the ministry family.</p>
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
        
        <div class="stats-cards">
            <div class="stat-card suggestions">
                <div class="stat-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['suggestions']); ?></div>
                <div class="stat-label">Suggestions</div>
            </div>
            
            <div class="stat-card questions">
                <div class="stat-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['questions']); ?></div>
                <div class="stat-label">Questions</div>
            </div>
            
            <div class="stat-card comments">
                <div class="stat-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['comments']); ?></div>
                <div class="stat-label">Comments</div>
            </div>
            
            <div class="stat-card likes">
                <div class="stat-icon">
                    <i class="fas fa-thumbs-up"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_likes']); ?></div>
                <div class="stat-label">Total Likes</div>
            </div>
        </div>
        
        <?php if (!$can_edit): ?>
        <div class="permission-notice">
            <i class="fas fa-info-circle"></i> 
            Note: As a <?php echo $_SESSION['role']; ?>, you can view suggestions but not post new ones.
        </div>
        <?php endif; ?>
        
        <?php if ($can_edit): ?>
        <div class="new-post-container">
            <div class="new-post-header">
                <div class="user-avatar">
                    <?php 
                    $user_initials = '';
                    $names = explode(' ', $_SESSION['full_name']);
                    foreach ($names as $name) {
                        $user_initials .= strtoupper(substr($name, 0, 1));
                    }
                    echo substr($user_initials, 0, 2);
                    ?>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                    <p>Share your thoughts with the ministry...</p>
                </div>
            </div>
            
            <form method="POST" class="post-form" id="newPostForm">
                <div class="post-type-selector">
                    <label class="type-option active">
                        <input type="radio" name="type" value="suggestion" checked>
                        <i class="fas fa-lightbulb"></i> Suggestion
                    </label>
                    <label class="type-option">
                        <input type="radio" name="type" value="question">
                        <i class="fas fa-question-circle"></i> Question
                    </label>
                    <label class="type-option">
                        <input type="radio" name="type" value="comment">
                        <i class="fas fa-comment"></i> Comment
                    </label>
                </div>
                
                <div class="form-group">
                    <textarea name="content" class="post-textarea" 
                              placeholder="What's on your mind? Share your suggestion, ask a question, or leave a comment..." 
                              required></textarea>
                </div>
                
                <div class="form-footer">
                    <button type="button" class="cancel-btn" onclick="clearPostForm()">
                        Clear
                    </button>
                    <button type="submit" name="add_suggestion" class="post-btn">
                        <i class="fas fa-paper-plane"></i> Post
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="feed-container">
            <div class="section-header">
                <h2>Recent Posts</h2>
            </div>
            
            <div class="feed-content" id="feedContent">
                <?php if (mysqli_num_rows($suggestions_result) > 0): ?>
                    <?php while ($post = mysqli_fetch_assoc($suggestions_result)): ?>
                    <div class="post-item" id="post-<?php echo $post['id']; ?>">
                        <div class="post-header">
                            <div class="post-user">
                                <div class="post-user-avatar">
                                    <?php 
                                    $initials = '';
                                    $names = explode(' ', $post['full_name']);
                                    foreach ($names as $name) {
                                        $initials .= strtoupper(substr($name, 0, 1));
                                    }
                                    echo substr($initials, 0, 2);
                                    ?>
                                </div>
                                <div class="post-user-info">
                                    <h4><?php echo htmlspecialchars($post['full_name']); ?></h4>
                                    <div class="post-meta">
                                        <span class="post-type type-<?php echo $post['type']; ?>">
                                            <?php echo ucfirst($post['type']); ?>
                                        </span>
                                        <span><?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?></span>
                                        <span class="status-badge status-<?php echo $post['status']; ?>">
                                            <?php echo ucfirst($post['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($post['user_id'] == $user_id || isAdmin()): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this post? This will also delete all replies.');">
                                <input type="hidden" name="suggestion_id" value="<?php echo $post['id']; ?>">
                                <button type="submit" name="delete_suggestion" class="delete-btn" title="Delete Post">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>
                        
                        <div class="post-actions">
                            <div class="reaction-buttons">
                                <form method="POST" class="like-form" onsubmit="likePost(event, <?php echo $post['id']; ?>)">
                                    <input type="hidden" name="suggestion_id" value="<?php echo $post['id']; ?>">
                                    <button type="button" class="like-btn <?php echo isset($user_reactions[$post['id']]) && $user_reactions[$post['id']] == 'like' ? 'active' : ''; ?>" 
                                            onclick="likePost(event, <?php echo $post['id']; ?>)">
                                        <i class="fas fa-thumbs-up"></i>
                                        <span class="like-count"><?php echo $post['likes']; ?></span>
                                    </button>
                                </form>
                                
                                <form method="POST" class="dislike-form" onsubmit="dislikePost(event, <?php echo $post['id']; ?>)">
                                    <input type="hidden" name="suggestion_id" value="<?php echo $post['id']; ?>">
                                    <button type="button" class="dislike-btn <?php echo isset($user_reactions[$post['id']]) && $user_reactions[$post['id']] == 'dislike' ? 'active' : ''; ?>" 
                                            onclick="dislikePost(event, <?php echo $post['id']; ?>)">
                                        <i class="fas fa-thumbs-down"></i>
                                        <span class="dislike-count"><?php echo $post['dislikes']; ?></span>
                                    </button>
                                </form>
                            </div>
                            
                            <div>
                                <?php if ($can_edit): ?>
                                <button type="button" class="reply-btn" onclick="toggleReplyForm(<?php echo $post['id']; ?>)">
                                    <i class="fas fa-reply"></i> Reply
                                    <?php if ($post['reply_count'] > 0): ?>
                                    <span>(<?php echo $post['reply_count']; ?>)</span>
                                    <?php endif; ?>
                                </button>
                                <?php else: ?>
                                <button type="button" class="reply-btn" onclick="toggleReplyForm(<?php echo $post['id']; ?>)" disabled title="You need permission to reply">
                                    <i class="fas fa-reply"></i> Reply
                                    <?php if ($post['reply_count'] > 0): ?>
                                    <span>(<?php echo $post['reply_count']; ?>)</span>
                                    <?php endif; ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="replies-container" id="replies-<?php echo $post['id']; ?>" style="display: none;">
                            <?php if ($can_edit): ?>
                            <div class="reply-form" id="reply-form-<?php echo $post['id']; ?>">
                                <form method="POST">
                                    <input type="hidden" name="parent_id" value="<?php echo $post['id']; ?>">
                                    <input type="hidden" name="type" value="comment">
                                    <textarea name="content" placeholder="Write a reply..." required></textarea>
                                    <div class="reply-form-buttons">
                                        <button type="button" class="cancel-btn" onclick="toggleReplyForm(<?php echo $post['id']; ?>)">
                                            Cancel
                                        </button>
                                        <button type="submit" name="add_suggestion" class="post-btn">
                                            Post Reply
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <?php endif; ?>
                            
                            <div class="replies-list" id="replies-list-<?php echo $post['id']; ?>">
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>No Posts Yet</h3>
                        <p>Be the first to share a suggestion or ask a question!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="page-footer">
            <p>Lighthouse Ministers Suggestions Forum &copy; <?php echo date('Y'); ?></p>
            <p>Share your ideas and help make our ministry better!</p>
        </div>
    </div>
    
    <script>
        document.querySelectorAll('.type-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.type-option').forEach(opt => {
                    opt.classList.remove('active');
                });
                this.classList.add('active');
                this.querySelector('input').checked = true;
            });
        });
        
        function clearPostForm() {
            document.querySelector('#newPostForm textarea').value = '';
            document.querySelectorAll('.type-option').forEach(opt => {
                opt.classList.remove('active');
            });
            document.querySelector('.type-option input[value="suggestion"]').parentElement.classList.add('active');
            document.querySelector('.type-option input[value="suggestion"]').checked = true;
        }
        
        function toggleReplyForm(postId) {
            const repliesContainer = document.getElementById(`replies-${postId}`);
            const replyForm = document.getElementById(`reply-form-${postId}`);
            
            if (repliesContainer.style.display === 'none') {
                repliesContainer.style.display = 'block';
                if (replyForm) replyForm.style.display = 'block';
                loadReplies(postId);
            } else {
                repliesContainer.style.display = 'none';
            }
        }
        
        function loadReplies(postId) {
            const repliesList = document.getElementById(`replies-list-${postId}`);
            
            repliesList.innerHTML = '<div class="loading">Loading replies...</div>';
            
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `load_replies.php?post_id=${postId}`, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    repliesList.innerHTML = xhr.responseText;
                } else {
                    repliesList.innerHTML = '<div class="empty-state">Error loading replies</div>';
                }
            };
            xhr.send();
        }
        
        function likePost(event, postId) {
            event.preventDefault();
            
            const likeBtn = event.target.closest('.like-btn');
            const dislikeBtn = likeBtn.closest('.post-actions').querySelector('.dislike-btn');
            const likeCount = likeBtn.querySelector('.like-count');
            const dislikeCount = dislikeBtn.querySelector('.dislike-count');
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'suggestions.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    
                    likeCount.textContent = response.likes;
                    dislikeCount.textContent = response.dislikes;
                    
                    likeBtn.classList.toggle('active', response.user_reaction === 'like');
                    dislikeBtn.classList.toggle('active', response.user_reaction === 'dislike');
                }
            };
            
            xhr.send(`suggestion_id=${postId}&like_suggestion=1&ajax=1`);
        }
        
        function dislikePost(event, postId) {
            event.preventDefault();
            
            const dislikeBtn = event.target.closest('.dislike-btn');
            const likeBtn = dislikeBtn.closest('.post-actions').querySelector('.like-btn');
            const likeCount = likeBtn.querySelector('.like-count');
            const dislikeCount = dislikeBtn.querySelector('.dislike-count');
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'suggestions.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    
                    likeCount.textContent = response.likes;
                    dislikeCount.textContent = response.dislikes;
                    
                    likeBtn.classList.toggle('active', response.user_reaction === 'like');
                    dislikeBtn.classList.toggle('active', response.user_reaction === 'dislike');
                }
            };
            
            xhr.send(`suggestion_id=${postId}&dislike_suggestion=1&ajax=1`);
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