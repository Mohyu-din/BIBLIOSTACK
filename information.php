<?php
session_start();
include 'db_connect.php';

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];

if (isset($_GET['book_id'])) {
    $id = (int)$_GET['book_id'];
    
    // Fetch Book Details
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    
    if (!$book) die("Book not found.");

    // --- FETCH & ORGANIZE COMMENTS (Parents & Replies) ---
    $comment_sql = "SELECT * FROM comments WHERE book_id = $id ORDER BY created_at DESC";
    $raw_comments = $conn->query($comment_sql);
    
    $parents = [];
    $replies = [];

    while ($row = $raw_comments->fetch_assoc()) {
        if ($row['parent_id'] == NULL || $row['parent_id'] == 0) {
            $parents[] = $row;
        } else {
            $replies[$row['parent_id']][] = $row;
        }
    }

} else {
    header("Location: user_dashboard.php");
    exit;
}

// --- 2. HANDLE COMMENT POST (Top Level Only) ---
if (isset($_POST['submit_comment'])) {
    $text = mysqli_real_escape_string($conn, $_POST['comment_text']);
    
    $stmt = $conn->prepare("INSERT INTO comments (book_id, user_name, comment_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $id, $current_username, $text);
    $stmt->execute();
    
    header("Location: information.php?book_id=$id"); 
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f8; margin: 0; padding-bottom: 50px; }
        .container { max-width: 1000px; margin: 40px auto; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; }
        
        .back-btn { display: inline-flex; align-items: center; gap: 5px; padding: 10px 20px; text-decoration: none; color: #555; font-weight: bold; margin: 20px auto; max-width: 1000px; display: block; }
        .back-btn:hover { color: #4285F4; }
        
        /* HERO SECTION */
        .book-hero { display: flex; padding: 50px; gap: 50px; background: white; border-bottom: 1px solid #eee; }
        .left-col img { width: 260px; height: 390px; object-fit: cover; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .right-col { flex: 1; display: flex; flex-direction: column; justify-content: center; }
        
        .category-badge { background: #e8f0fe; color: #1a73e8; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; align-self: flex-start; margin-bottom: 15px; }
        .book-title { font-size: 38px; font-weight: 800; margin: 0 0 10px 0; color: #1a1a1a; line-height: 1.2; }
        .book-author { font-size: 18px; color: #666; margin-bottom: 25px; }
        
        /* STATS */
        .stats-box { display: flex; gap: 40px; margin-bottom: 35px; border-top: 1px solid #eee; border-bottom: 1px solid #eee; padding: 20px 0; width: 100%; }
        .stat-item { text-align: center; }
        .stat-num { font-size: 20px; font-weight: 800; color: #333; }
        .stat-label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-top: 5px; }

        .btn-read { padding: 15px 40px; background: #111; color: white; text-decoration: none; font-weight: bold; border-radius: 8px; transition: 0.2s; display: inline-flex; align-items: center; gap: 10px; font-size: 16px; width: fit-content; }
        .btn-read:hover { background: #333; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

        /* CONTENT */
        .content-section { padding: 50px; }
        .section-header { font-size: 22px; font-weight: 700; margin-bottom: 20px; color: #333; }
        .description-text { font-size: 16px; line-height: 1.8; color: #555; }
        .no-data { font-style: italic; color: #999; padding: 20px; background: #f9f9f9; border-radius: 8px; text-align: center; }
        
        /* COMMENT STYLES */
        .comment-section { background-color: #fafafa; padding: 50px; border-top: 1px solid #eee; }
        .comment-box { background: white; padding: 20px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #eee; position: relative; }
        .user-name { font-weight: bold; color: #333; font-size: 14px; }
        .comment-date { font-size: 12px; color: #999; margin-left: 10px; font-weight: normal; }
        .comment-body { margin-top: 8px; color: #555; font-size: 14px; line-height: 1.5; }
        
        /* REPLY DROPDOWN STYLES */
        .reply-toggle-btn {
            background: none; border: none; color: #4285F4; 
            font-size: 12px; font-weight: 600; cursor: pointer; 
            margin-top: 10px; display: flex; align-items: center; gap: 5px;
            padding: 5px 0;
        }
        .reply-toggle-btn:hover { text-decoration: underline; }
        
        .replies-container {
            display: none; /* Hidden by default */
            margin-top: 15px;
            padding-left: 20px;
            border-left: 3px solid #e0e0e0;
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .reply-item {
            background: #f9f9f9; padding: 15px; border-radius: 6px; 
            margin-bottom: 10px; border: 1px solid #eee;
        }
        .reply-item .user-name { color: #555; font-size: 13px; }
        .reply-item .comment-body { font-size: 13px; color: #666; }

        /* FORM */
        .comment-form { margin-top: 40px; }
        textarea { width: 100%; padding: 15px; border: 1px solid #ddd; border-radius: 8px; min-height: 100px; font-family: inherit; margin-bottom: 15px; resize: vertical; }
        .btn-post { background: #4285F4; color: white; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-post:hover { background: #3367d6; }
    </style>
</head>
<body>

<a href="user_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

<div class="container">
    
    <div class="book-hero">
        <div class="left-col">
            <?php $cover = !empty($book['cover_path']) ? $book['cover_path'] : 'https://placehold.co/260x390?text=No+Cover'; ?>
            <img src="<?php echo $cover; ?>" alt="Book Cover">
        </div>
        
        <div class="right-col">
            <span class="category-badge"><?php echo htmlspecialchars($book['category']); ?></span>
            <h1 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h1>
            <p class="book-author">By <?php echo htmlspecialchars($book['author']); ?></p>
            
            <div class="stats-box">
                <div class="stat-item">
                    <div class="stat-num"><?php echo number_format($book['rating'], 1); ?> <i class="fas fa-star" style="color:#FFD700; font-size:16px;"></i></div>
                    <div class="stat-label">Rating</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num"><?php echo $book['views']; ?></div>
                    <div class="stat-label">Reads</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num"><?php echo $book['total_pages']; ?></div>
                    <div class="stat-label">Pages</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num"><?php echo $book['likes']; ?></div>
                    <div class="stat-label">Likes</div>
                </div>
            </div>

            <a href="read.php?book_id=<?php echo $book['id']; ?>" class="btn-read">
                <i class="fas fa-book-open"></i> Read Now
            </a>
            
            <div style="margin-top: 15px; font-size: 12px; color: #888;">
                Published by: <?php echo htmlspecialchars($book['publisher_name']); ?>
            </div>
        </div>
    </div>

    <div class="content-section">
        <h3 class="section-header">About this Book</h3>
        <?php if (!empty($book['description'])): ?>
            <p class="description-text"><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
        <?php else: ?>
            <div class="no-data">No description available.</div>
        <?php endif; ?>
    </div>

    <div class="comment-section">
        <h3 class="section-header">Community Reviews (<?php echo count($parents) + count($replies, COUNT_RECURSIVE) - count($replies); ?>)</h3>
        
        <?php if (count($parents) > 0): ?>
            <?php foreach ($parents as $parent): ?>
                <div class="comment-box">
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <i class="fas fa-user-circle" style="color: #ccc; font-size: 20px; margin-right: 8px;"></i>
                        <span class="user-name"><?php echo htmlspecialchars($parent['user_name']); ?></span>
                        <span class="comment-date"><?php echo date('M d, Y', strtotime($parent['created_at'])); ?></span>
                    </div>
                    <div class="comment-body"><?php echo htmlspecialchars($parent['comment_text']); ?></div>

                    <?php if (isset($replies[$parent['id']])): ?>
                        <button class="reply-toggle-btn" onclick="toggleReplies(<?php echo $parent['id']; ?>)">
                            <i class="fas fa-caret-down"></i> View <?php echo count($replies[$parent['id']]); ?> Replies
                        </button>

                        <div id="replies-<?php echo $parent['id']; ?>" class="replies-container">
                            <?php foreach ($replies[$parent['id']] as $reply): ?>
                                <div class="reply-item">
                                    <div style="display: flex; align-items: center; margin-bottom: 3px;">
                                        <i class="fas fa-reply" style="color: #bbb; font-size: 12px; margin-right: 6px; transform: rotate(180deg);"></i>
                                        <span class="user-name"><?php echo htmlspecialchars($reply['user_name']); ?></span>
                                        <span class="comment-date"><?php echo date('M d', strtotime($reply['created_at'])); ?></span>
                                    </div>
                                    <div class="comment-body"><?php echo htmlspecialchars($reply['comment_text']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">No reviews yet. Be the first to review!</div>
        <?php endif; ?>

        <div class="comment-form">
            <h4 style="margin-bottom: 10px; color:#444;">Leave a Review</h4>
            <form method="POST">
                <textarea name="comment_text" placeholder="Share your thoughts about this book..." required></textarea>
                <button type="submit" name="submit_comment" class="btn-post">Post Review</button>
            </form>
        </div>
    </div>

</div>

<script>
    function toggleReplies(id) {
        var x = document.getElementById("replies-" + id);
        if (x.style.display === "block") {
            x.style.display = "none";
        } else {
            x.style.display = "block";
        }
    }
</script>

</body>
</html>