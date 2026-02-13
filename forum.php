<?php
session_start();
include 'db_connect.php';

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- 2. DETERMINE DASHBOARD LINK & PROFILE DATA ---
$role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : 'user';
$dashboard_link = ($role === 'publisher' || $role === 'admin') ? 'publisher_dashboard.php' : 'user_dashboard.php';

$nav_image = isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : null;
$nav_name = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$user_id = $_SESSION['user_id'];

function getNavInitials($name) {
    return strtoupper(substr($name, 0, 1));
}

// --- 3. HANDLE NEW TOPIC CREATION ---
if (isset($_POST['create_topic'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $uname = $_SESSION['username'];
    
    $image_path = NULL;
    if (!empty($_FILES['topic_image']['name'])) {
        $target_dir = "uploads/forum/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        $file_ext = strtolower(pathinfo($_FILES["topic_image"]["name"], PATHINFO_EXTENSION));
        $new_name = uniqid('topic_', true) . '.' . $file_ext;
        $target_file = $target_dir . $new_name;
        if (move_uploaded_file($_FILES["topic_image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    $sql = "INSERT INTO forum_topics (user_id, user_name, title, content, category, image_path) VALUES ($user_id, '$uname', '$title', '$content', '$category', '$image_path')";
    if ($conn->query($sql)) {
        header("Location: forum.php"); 
        exit;
    }
}

// --- 4. FETCH TOPICS (Modified for Saved Filter) ---
$where_clauses = [];
$join_saved = ""; 

// Filter: Saved Posts
if (isset($_GET['filter']) && $_GET['filter'] == 'saved') {
    $join_saved = "JOIN forum_saves s ON t.id = s.topic_id";
    $where_clauses[] = "s.user_id = $user_id";
}

// Filter: Category
if (isset($_GET['cat']) && $_GET['cat'] != '') {
    $cat = mysqli_real_escape_string($conn, $_GET['cat']);
    $where_clauses[] = "t.category = '$cat'";
}

// Filter: Search
if (isset($_GET['q']) && $_GET['q'] != '') {
    $q = mysqli_real_escape_string($conn, $_GET['q']);
    $where_clauses[] = "(t.title LIKE '%$q%' OR t.content LIKE '%$q%')";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

$sql = "SELECT t.*, 
        (SELECT COUNT(*) FROM forum_replies r WHERE r.topic_id = t.id) as reply_count,
        (SELECT COUNT(*) FROM forum_likes l WHERE l.topic_id = t.id) as like_count
        FROM forum_topics t 
        $join_saved 
        $where_sql 
        ORDER BY t.created_at DESC";

$topics = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Forum - Bibliostack</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; margin: 0; }
        
        .navbar { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; position: sticky; top: 0; z-index: 100; }
        .logo { font-weight: 800; font-size: 20px; color: #333; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        
        .profile-link { display: flex; align-items: center; text-decoration: none; padding: 5px; border-radius: 50%; transition: 0.2s; }
        .profile-link:hover { transform: scale(1.05); }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; }
        .profile-initials { width: 40px; height: 40px; background-color: #4285F4; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid #ddd; }
        
        .container { max-width: 1100px; margin: 30px auto; display: flex; gap: 30px; padding: 0 20px; }
        .sidebar { width: 260px; flex-shrink: 0; }
        .sidebar-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #eee; margin-bottom: 20px; }
        
        .cat-link { display: block; padding: 12px 15px; color: #555; text-decoration: none; border-radius: 8px; margin-bottom: 5px; font-weight: 500; font-size: 14px; transition: 0.2s; }
        .cat-link:hover, .cat-link.active { background: #e8f0fe; color: #1a73e8; }
        
        .btn-create { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 14px; background: #1a73e8; color: white; border-radius: 8px; text-decoration: none; font-weight: bold; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(26, 115, 232, 0.3); border: none; cursor: pointer; transition: 0.2s; font-size: 15px; }
        .btn-create:hover { background: #1557b0; transform: translateY(-2px); }

        .search-box { display: flex; align-items: center; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 10px; margin-bottom: 20px; }
        .search-box input { border: none; background: transparent; outline: none; width: 100%; font-size: 14px; color: #333; margin-left: 10px; }

        .feed { flex: 1; }
        .topic-card { background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #eee; transition: 0.2s; cursor: pointer; position: relative; }
        .topic-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); border-color: #1a73e8; }
        .topic-cat { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #1a73e8; background: #e8f0fe; padding: 4px 10px; border-radius: 20px; display: inline-block; margin-bottom: 12px; }
        .topic-title { font-size: 18px; font-weight: 700; color: #222; margin: 0 0 10px 0; line-height: 1.3; }
        .topic-preview { color: #666; font-size: 14px; margin-bottom: 15px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .topic-meta { font-size: 13px; color: #888; display: flex; align-items: center; gap: 15px; border-top: 1px solid #f5f5f5; padding-top: 15px; }
        
        .topic-image-preview { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 15px; display: block; }

        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
        .modal-content { background: white; padding: 30px; width: 550px; border-radius: 12px; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 24px; cursor: pointer; color: #999; transition: 0.2s; }
        .close-modal:hover { color: #333; }
        
        input[type="text"], select, textarea { width: 100%; padding: 12px; margin: 8px 0 20px 0; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-family: inherit; font-size: 14px; }
        .file-input-wrapper { border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 8px; cursor: pointer; margin-bottom: 20px; color: #666; }
        .file-input-wrapper:hover { border-color: #1a73e8; background: #f0f7ff; }
        label { font-weight: 600; font-size: 13px; color: #444; }
    </style>
</head>
<body>

<div class="navbar">
    <div style="display:flex; align-items:center; gap:20px;">
        <a href="<?php echo $dashboard_link; ?>" style="text-decoration:none; color:#555; font-size:24px;" title="Back to Dashboard"><i class="fas fa-arrow-left"></i></a>
        <a href="<?php echo $dashboard_link; ?>" class="logo"><i class="fas fa-comments" style="color: #1a73e8;"></i> BIBLIOSTACK FORUM</a>
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
    <div class="sidebar">
        <button onclick="openModal()" class="btn-create"><i class="fas fa-plus-circle"></i> Start Discussion</button>
        
        <div class="sidebar-card">
            <div style="font-weight:bold; margin-bottom:15px; color:#333;">Menu</div>
            <a href="forum.php" class="cat-link <?php echo (!isset($_GET['filter']) && !isset($_GET['cat'])) ? 'active' : ''; ?>">
                <i class="fas fa-stream"></i> All Topics
            </a>
            <a href="forum.php?filter=saved" class="cat-link <?php echo (isset($_GET['filter']) && $_GET['filter']=='saved') ? 'active' : ''; ?>">
                <i class="fas fa-bookmark" style="color:#fbbc05;"></i> Saved Topics
            </a>
        </div>

        <div class="sidebar-card">
            <div style="font-weight:bold; margin-bottom:15px; color:#333;">Categories</div>
            <a href="forum.php?cat=General" class="cat-link <?php echo (isset($_GET['cat']) && $_GET['cat']=='General')?'active':''; ?>">💬 General Chat</a>
            <a href="forum.php?cat=Books" class="cat-link <?php echo (isset($_GET['cat']) && $_GET['cat']=='Books')?'active':''; ?>">📚 Book Recommendations</a>
            <a href="forum.php?cat=Authors" class="cat-link <?php echo (isset($_GET['cat']) && $_GET['cat']=='Authors')?'active':''; ?>">✍️ Writers Corner</a>
            <a href="forum.php?cat=Support" class="cat-link <?php echo (isset($_GET['cat']) && $_GET['cat']=='Support')?'active':''; ?>">🔧 Site Support</a>
        </div>
    </div>

    <div class="feed">
        <form action="forum.php" method="GET">
            <div class="search-box">
                <i class="fas fa-search" style="color:#888;"></i>
                <input type="text" name="q" placeholder="Search discussions..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
            </div>
        </form>

        <?php if ($topics->num_rows > 0): ?>
            <?php while($row = $topics->fetch_assoc()): ?>
                <div class="topic-card" onclick="window.location.href='forum_topic.php?id=<?php echo $row['id']; ?>'">
                    <div style="display:flex; justify-content:space-between;">
                        <span class="topic-cat"><?php echo htmlspecialchars($row['category']); ?></span>
                        <span style="font-size:12px; color:#999;"><?php echo date('M d', strtotime($row['created_at'])); ?></span>
                    </div>
                    
                    <h3 class="topic-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                    
                    <?php if (!empty($row['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($row['image_path']); ?>" class="topic-image-preview">
                    <?php endif; ?>

                    <div class="topic-preview">
                        <?php echo strip_tags(htmlspecialchars_decode($row['content'])); ?>
                    </div>
                    
                    <div class="topic-meta">
                        <span class="meta-item"><i class="fas fa-user-circle" style="color:#aaa;"></i> <?php echo htmlspecialchars($row['user_name']); ?></span>
                        <div style="flex:1;"></div>
                        <span class="meta-item" style="color:#e0245e; font-weight:600;"><i class="fas fa-heart"></i> <?php echo $row['like_count']; ?></span>
                        <span class="meta-item" style="color:#1a73e8; font-weight:600; margin-left:15px;"><i class="fas fa-comment-alt"></i> <?php echo $row['reply_count']; ?></span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; padding:60px; background:white; border-radius:12px; border:1px dashed #ccc;">
                <i class="far fa-comments" style="font-size:40px; color:#ccc; margin-bottom:15px;"></i>
                <p style="color:#666; margin:0;">
                    <?php echo (isset($_GET['filter']) && $_GET['filter']=='saved') ? "You haven't saved any topics yet." : "No topics found here yet."; ?>
                </p>
                <?php if(!isset($_GET['filter'])): ?>
                    <a href="#" onclick="openModal()" style="color:#1a73e8; font-size:14px; font-weight:bold; margin-top:10px; display:inline-block;">Be the first to post!</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="topicModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h2 style="margin-top:0; margin-bottom:20px; color:#333;">Start a Discussion</h2>
        <form method="POST" enctype="multipart/form-data">
            <label>Topic Title</label>
            <input type="text" name="title" required placeholder="e.g. What's the best Sci-Fi book of 2025?">
            
            <label>Category</label>
            <select name="category">
                <option value="General">General Chat</option>
                <option value="Books">Book Recommendations</option>
                <option value="Authors">Writers Corner</option>
                <option value="Support">Site Support</option>
            </select>
            
            <label>Upload Image (Optional)</label>
            <div class="file-input-wrapper" onclick="document.getElementById('imgInput').click()">
                <i class="fas fa-image" style="font-size:24px; display:block; margin-bottom:5px;"></i>
                <span id="fileName">Click to select an image</span>
            </div>
            <input type="file" id="imgInput" name="topic_image" accept="image/*" style="display:none;" onchange="document.getElementById('fileName').innerText = this.files[0].name">

            <label>Content</label>
            <textarea name="content" rows="5" required placeholder="Elaborate on your topic..."></textarea>
            
            <button type="submit" name="create_topic" class="btn-create" style="margin-bottom:0;">Post Topic</button>
        </form>
    </div>
</div>

<script>
    function openModal() { document.getElementById('topicModal').style.display = 'flex'; }
    function closeModal() { document.getElementById('topicModal').style.display = 'none'; }
    window.onclick = function(e) { if(e.target == document.getElementById('topicModal')) closeModal(); }
</script>

</body>
</html>