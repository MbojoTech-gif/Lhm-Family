<?php
// load_replies.php - Load replies for a post
require_once 'db.php';
requireLogin();

if (isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    $user_id = $_SESSION['user_id'];
    
    // Get replies for this post
    $sql = "SELECT s.*, u.full_name, u.username 
            FROM suggestions s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE s.parent_id = '$post_id' 
            ORDER BY s.created_at ASC";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        while ($reply = mysqli_fetch_assoc($result)) {
            ?>
            <div class="reply-item">
                <div class="reply-header">
                    <div class="reply-user">
                        <div class="reply-user-avatar">
                            <?php 
                            $initials = '';
                            $names = explode(' ', $reply['full_name']);
                            foreach ($names as $name) {
                                $initials .= strtoupper(substr($name, 0, 1));
                            }
                            echo substr($initials, 0, 2);
                            ?>
                        </div>
                        <div class="reply-user-info">
                            <h5><?php echo htmlspecialchars($reply['full_name']); ?></h5>
                            <div class="reply-time">
                                <?php echo date('M j, Y \a\t g:i A', strtotime($reply['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($reply['user_id'] == $user_id || isAdmin()): ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this reply?');">
                        <input type="hidden" name="suggestion_id" value="<?php echo $reply['id']; ?>">
                        <button type="submit" name="delete_suggestion" class="delete-btn" style="font-size: 0.8rem; padding: 3px 8px;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                
                <div class="reply-content">
                    <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                </div>
            </div>
            <?php
        }
    } else {
        echo '<div class="empty-state" style="padding: 20px;">No replies yet. Be the first to reply!</div>';
    }
}
?>