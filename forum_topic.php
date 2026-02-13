<?php
session_start();
include 'db_connect.php';

// --- 1. SECURITY & SETUP ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: forum.php");
    exit;
}

$topic_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Determine Dashboard Link & Profile Info for Navbar
$role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : 'user';
$dashboard_link = ($role === 'publisher' || $role === 'admin') ? 'publisher_dashboard.php' : 'user_dashboard.php';
$nav_image = isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : null;
$nav_name = isset($_SESSION['username']) ? $_SESSION['username'] : "User";

function getNavInitials($name) {
    return strtoupper(substr($name, 0, 1));
}

// --- 2. HANDLE ACTIONS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Post Reply
    if (isset($_POST['post_reply'])) {
        $reply = mysqli_real_escape_string($conn, $_POST['reply_content']);
        if (!empty($reply)) {
            $stmt = $conn->prepare("INSERT INTO forum_replies (topic_id, user_id, user_name, content) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $topic_id, $user_id, $username, $reply);
            $stmt->execute();
            // Refresh to avoid resubmission
            header("Location: forum_topic.php?id=$topic_id");
            exit;
        }
    }

    // B. AJAX Actions (Like/Save)
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'save') {
            $check = $conn->query("SELECT id FROM forum_saves WHERE user_id=$user_id AND topic_id=$topic_id");
            if ($check->num_rows == 0) {
                $conn->query("INSERT INTO forum_saves (user_id, topic_id) VALUES ($user_id, $topic_id)");
            } else {
                $conn->query("DELETE FROM forum_saves WHERE user_id=$user_id AND topic_id=$topic_id");
            }
        } elseif ($action === 'like') {
            $check = $conn->query("SELECT id FROM forum_likes WHERE user_id=$user_id AND topic_id=$topic_id");
            if ($check->num_rows == 0) {
                $conn->query("INSERT INTO forum_likes (user_id, topic_id) VALUES ($user_id, $topic_id)");
            } else {
                $conn->query("DELETE FROM forum_likes WHERE user_id=$user_id AND topic_id=$topic_id");
            }
        }
        exit; // Stop script for AJAX calls
    }
}

// --- 3. FETCH DATA ---

// Increment View Count
$conn->query("UPDATE forum_topics SET views = views + 1 WHERE id = $topic_id");

// Get Topic Details
$stmt = $conn->prepare("SELECT * FROM forum_topics WHERE id = ?");
$stmt->bind_param("i", $topic_id);
$stmt->execute();
$topic = $stmt->get_result()->fetch_assoc();

if (!$topic) die("Topic not found.");

// Get Replies
$replies = $conn->query("SELECT * FROM forum_replies WHERE topic_id = $topic_id ORDER BY created_at ASC");

// Check Interaction State (For buttons color)
$is_liked = ($conn->query("SELECT id FROM forum_likes WHERE user_id=$user_id AND topic_id=$topic_id")->num_rows > 0);
$is_saved = ($conn->query("SELECT id FROM forum_saves WHERE user_id=$user_id AND topic_id=$topic_id")->num_rows > 0);
$like_count = $conn->query("SELECT COUNT(*) as c FROM forum_likes WHERE topic_id=$topic_id")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($topic['title']); ?> - Bibliostack Forum</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 50px; }
        
        /* NAVBAR */
        .navbar { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; position: sticky; top: 0; z-index: 100; }
        .logo { font-weight: 800; font-size: 20px; color: #333; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        
        .profile-link { display: flex; align-items: center; text-decoration: none; padding: 5px; border-radius: 50%; transition: 0.2s; }
        .profile-link:hover { transform: scale(1.05); }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; }
        .profile-initials { width: 40px; height: 40px; background-color: #4285F4; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid #ddd; }

        .container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
        .back-btn { text-decoration: none; color: #555; font-weight: 600; margin-bottom: 20px; display: inline-flex; align-items: center; gap: 5px; }
        .back-btn:hover { color: #1a73e8; }

        /* MAIN POST CARD */
        .main-post { background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        
        .post-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .author-info { display: flex; align-items: center; gap: 12px; }
        .avatar { width: 45px; height: 45px; background: #e8f0fe; color: #1a73e8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; border: 1px solid #d0e2ff; }
        .cat-badge { background: #f1f3f4; color: #555; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }

        .post-title { font-size: 28px; font-weight: 800; color: #222; margin: 0 0 15px 0; line-height: 1.3; }
        .post-image { width: 100%; max-height: 400px; object-fit: cover; border-radius: 8px; margin-bottom: 20px; border: 1px solid #eee; }
        .post-content { font-size: 16px; line-height: 1.7; color: #333; margin-bottom: 30px; white-space: pre-wrap; }

        /* ACTIONS BAR */
        .actions-bar { display: flex; gap: 15px; border-top: 1px solid #eee; padding-top: 20px; }
        .action-btn { border: 1px solid #ddd; background: white; padding: 8px 18px; border-radius: 20px; cursor: pointer; color: #555; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .action-btn:hover { background: #f9f9f9; transform: translateY(-1px); }
        
        .action-btn.active { border-color: transparent; color: white; }
        .action-btn.like-active { background: #e0245e; box-shadow: 0 4px 10px rgba(224, 36, 94, 0.3); }
        .action-btn.save-active { background: #fbbc05; color: #333; box-shadow: 0 4px 10px rgba(251, 188, 5, 0.3); border-color: #fbbc05; }

        /* REPLY SECTION */
        .reply-section { margin-top: 40px; }
        .section-label { font-size: 18px; font-weight: 700; color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        .reply-card { background: white; padding: 20px 25px; border-radius: 12px; margin-bottom: 15px; border: 1px solid #eee; position: relative; }
        .reply-card::before { content: ''; position: absolute; left: 0; top: 20px; bottom: 20px; width: 4px; background: #1a73e8; border-radius: 0 4px 4px 0; }
        
        .reply-meta { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px; }
        .reply-author { font-weight: bold; color: #333; font-size: 14px; }
        .reply-date { color: #999; }
        .reply-content { color: #444; line-height: 1.6; }

        /* REPLY FORM */
        .reply-form-card { background: white; padding: 25px; border-radius: 12px; margin-top: 30px; box-shadow: 0 -4px 20px rgba(0,0,0,0.03); border: 1px solid #eee; }
        textarea { width: 100%; padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; font-family: inherit; font-size: 14px; resize: vertical; box-sizing: border-box; }
        textarea:focus { outline: none; border-color: #1a73e8; }
        .btn-reply { background: #1a73e8; color: white; border: none; padding: 12px 30px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 15px; transition: 0.2s; display: flex; align-items: center; gap: 8px; }
        .btn-reply:hover { background: #1557b0; }
    </style>
</head>
<body>

<div class="navbar">
    <div style="display:flex; align-items:center; gap:20px;">
        <a href="<?php echo $dashboard_link; ?>" style="text-decoration:none; color:#555; font-size:24px;" title="Dashboard"><i class="fas fa-home"></i></a>
        <a href="forum.php" class="logo"><i class="fas fa-comments" style="color: #1a73e8;"></i> BIBLIOSTACK FORUM</a>
    </div>
    
    <a href="profile.php" class="profile-link" title="My Profile: <?php echo htmlspecialchars($nav_name); ?>">
        <?php if ($nav_image): ?>
            <img src="<?php echo htmlspecialchars($nav_image); ?>" class="profile-img">
        <?php else: ?>
            <div class="profile-initials"><?php echo getNavInitials($nav_name); ?></div>
        <?php endif; ?>
    </a>
</div>

<div class="container">
    <a href="forum.php" class="back-btn">← Back to Feed</a>

    <div class="main-post">
        <div class="post-header">
            <div class="author-info">
                <div class="avatar"><?php echo strtoupper(substr($topic['user_name'],0,1)); ?></div>
                <div>
                    <div style="font-weight:bold; color:#333;"><?php echo htmlspecialchars($topic['user_name']); ?></div>
                    <div style="font-size:12px; color:#888;">Posted on <?php echo date('M d, Y', strtotime($topic['created_at'])); ?></div>
                </div>
            </div>
            <span class="cat-badge"><?php echo htmlspecialchars($topic['category']); ?></span>
        </div>

        <h1 class="post-title"><?php echo htmlspecialchars($topic['title']); ?></h1>
        
        <?php if (!empty($topic['image_path'])): ?>
            <img src="<?php echo htmlspecialchars($topic['image_path']); ?>" class="post-image" alt="Topic Image">
        <?php endif; ?>

        <div class="post-content">
            <?php echo nl2br(htmlspecialchars($topic['content'])); ?>
        </div>

        <div class="actions-bar">
            <button id="likeBtn" class="action-btn <?php echo $is_liked?'like-active active':''; ?>" onclick="doAction('like')">
                <i class="<?php echo $is_liked?'fas':'far'; ?> fa-heart"></i> <span id="likeText"><?php echo $like_count; ?> Likes</span>
            </button>

            <button id="saveBtn" class="action-btn <?php echo $is_saved?'save-active active':''; ?>" onclick="doAction('save')">
                <i class="<?php echo $is_saved?'fas':'far'; ?> fa-bookmark"></i> <span id="saveText"><?php echo $is_saved?'Saved':'Save'; ?></span>
            </button>

            <button class="action-btn" onclick="sharePost()">
                <i class="fas fa-share-alt"></i> Share
            </button>
        </div>
    </div>

    <div class="reply-section">
        <div class="section-label"><i class="fas fa-comment-dots"></i> Responses (<?php echo $replies->num_rows; ?>)</div>
        
        <?php if ($replies->num_rows > 0): ?>
            <?php while($row = $replies->fetch_assoc()): ?>
                <div class="reply-card">
                    <div class="reply-meta">
                        <span class="reply-author"><?php echo htmlspecialchars($row['user_name']); ?></span>
                        <span class="reply-date"><?php echo date('M d, g:i a', strtotime($row['created_at'])); ?></span>
                    </div>
                    <div class="reply-content">
                        <?php echo nl2br(htmlspecialchars($row['content'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color:#777; text-align:center; padding:20px;">No replies yet. Be the first to join the conversation!</p>
        <?php endif; ?>

        <div class="reply-form-card">
            <h4 style="margin-top:0; margin-bottom:15px; color:#444;">Add a Reply</h4>
            <form method="POST">
                <textarea name="reply_content" rows="4" placeholder="Type your reply here..." required></textarea>
                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" name="post_reply" class="btn-reply">
                        <i class="fas fa-paper-plane"></i> Post Reply
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function doAction(type) {
        const formData = new FormData();
        formData.append('action', type);
        
        // Send AJAX Request
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(() => { 
            location.reload(); // Reload to update button states (Simple approach)
        })
        .catch(err => console.error("Error:", err));
    }

    function sharePost() {
        navigator.clipboard.writeText(window.location.href);
        alert("Link copied to clipboard! You can paste it anywhere.");
    }
</script>

</body>
</html>