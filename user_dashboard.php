<?php
session_start();
include 'db_connect.php';

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- 2. RESTRICT PUBLISHERS ---
$role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : 'user';

if ($role === 'publisher') {
    // DISABLE THIS LINE TEMPORARILY:
    // header("Location: publisher_dashboard.php");
    // exit;
    
    echo "<h1>You are a Publisher!</h1>";
    echo "<a href='publisher_dashboard.php'>Click here to go to Publisher Dashboard</a>";
    exit;
}

// --- 3. PREPARE PROFILE DATA ---
$user_id = $_SESSION['user_id'];
$nav_image = isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : null;
$nav_name = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : ""; 

$req_sql = "SELECT publisher_status FROM users WHERE id = $user_id";
$req_res = $conn->query($req_sql);
$req_status = ($req_res && $req_res->num_rows > 0) ? $req_res->fetch_assoc()['publisher_status'] : 'none';

function getNavInitials($name) {
    return strtoupper(substr($name, 0, 1));
}

// --- 4. DATABASE QUERIES ---
// A. Progress
$sql_progress = "SELECT b.id, b.title, b.author, b.cover_path, sp.current_page, b.total_pages FROM save_progress sp JOIN books b ON sp.book_id = b.id WHERE sp.user_id = $user_id ORDER BY sp.last_accessed DESC LIMIT 1";
$res_progress = $conn->query($sql_progress);
$last_read = ($res_progress && $res_progress->num_rows > 0) ? $res_progress->fetch_assoc() : null;

$progress_percent = 0;
if ($last_read && isset($last_read['total_pages']) && $last_read['total_pages'] > 0) {
    $progress_percent = round(($last_read['current_page'] / $last_read['total_pages']) * 100);
    if($progress_percent > 100) $progress_percent = 100;
}

// B. Lists
$sql_recent = "SELECT * FROM books WHERE status='approved' ORDER BY created_at DESC LIMIT 10";
$result_recent = $conn->query($sql_recent);

$sql_library = "SELECT * FROM books WHERE status='approved' ORDER BY created_at DESC LIMIT 20";
$result_library = $conn->query($sql_library);

$sql_top = "SELECT * FROM books WHERE status='approved' ORDER BY views DESC LIMIT 10";
$result_top = $conn->query($sql_top);

$categories = ['All', 'Programming', 'Business', 'Fiction', 'Romance', 'Science', 'History', 'Other'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Bibliostack</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* GLOBAL STYLES */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #f0f2f5; padding-bottom: 100px; }
        .desktop-wrapper { max-width: 1200px; margin: 0 auto; background-color: #ffffff; min-height: 100vh; border-left: 1px solid #eee; border-right: 1px solid #eee; position: relative; overflow-x: hidden; }

        /* HEADER */
        .sticky-container { position: sticky; top: 0; z-index: 900; background-color: white; border-bottom: 1px solid #eee; }
        .header { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; }
        .menu-icon { font-size: 24px; cursor: pointer; color: #333; padding: 10px; border-radius: 50%; transition: 0.2s; }
        .menu-icon:hover { background: #f0f0f0; }
        .logo { font-size: 28px; font-weight: 800; letter-spacing: 1px; color: #222; }
        .header-actions { display: flex; align-items: center; gap: 20px; }
        .profile-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; }
        .profile-initials { width: 45px; height: 45px; background-color: #4285F4; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid #ddd; }

        /* SEARCH */
        .search-container { padding: 0 40px 20px 40px; background: #fff; }
        .search-bar { display: flex; align-items: center; border: 2px solid #eee; border-radius: 8px; padding: 12px 20px; background: #f9f9f9; }
        .search-bar input { border: none; outline: none; flex: 1; font-size: 16px; background: transparent; }

        /* SIDEBAR STYLES */
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; opacity: 0; visibility: hidden; transition: 0.3s; }
        .sidebar-overlay.active { opacity: 1; visibility: visible; }
        .slide-menu { position: fixed; top: 0; left: -300px; width: 300px; height: 100%; background: white; z-index: 1001; transition: 0.3s ease-in-out; box-shadow: 2px 0 10px rgba(0,0,0,0.1); display: flex; flex-direction: column; }
        .slide-menu.active { left: 0; }
        .menu-header { background: #2c3e50; color: white; padding: 30px 20px; display: flex; flex-direction: column; gap: 10px; }
        .menu-avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid white; background: #4285F4; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; }
        .btn-close-menu { position: absolute; top: 15px; right: 15px; background: none; border: none; color: white; font-size: 24px; cursor: pointer; }
        .menu-list { list-style: none; padding: 20px 0; flex: 1; }
        .menu-list li a { display: flex; align-items: center; gap: 15px; padding: 15px 25px; color: #333; text-decoration: none; font-size: 15px; font-weight: 500; transition: 0.2s; }
        .menu-list li a:hover { background: #f5f5f5; color: #4285F4; }

        /* DASHBOARD CONTENT STYLES */
        .section-title { padding: 40px 40px 20px 40px; font-size: 24px; font-weight: 700; color: #1a1a1a; }
        .horizontal-scroll { display: flex; overflow-x: auto; gap: 20px; padding: 0 40px 20px 40px; scrollbar-width: none; }
        .horizontal-scroll::-webkit-scrollbar { display: none; }
        .continue-card { min-width: 100%; background: white; border: 1px solid #e0e0e0; border-radius: 16px; padding: 25px; display: flex; gap: 30px; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .book-cover-large { width: 120px; height: 170px; background-color: #FF5733; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; object-fit: cover; }
        .progress-container { margin: 15px 0; background: #eee; height: 10px; border-radius: 5px; width: 100%; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #4285F4, #34A853); width: 0%; border-radius: 5px; transition: width 0.5s ease; }
        .btn-continue { display: inline-block; padding: 10px 20px; background: #111; color: #fff; text-decoration: none; font-size: 14px; font-weight: bold; border-radius: 6px; }
        .book-card { min-width: 160px; width: 160px; display: flex; flex-direction: column; gap: 10px; cursor: pointer; transition: transform 0.2s; margin-bottom: 10px; position: relative; }
        .book-card:hover { transform: translateY(-5px); }
        .book-cover-small { width: 160px; height: 230px; border-radius: 10px; background-color: #ddd; object-fit: cover; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .book-title { font-size: 15px; font-weight: 600; color: #222; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .recent-grid { display: flex; flex-wrap: wrap; gap: 30px; padding: 0 40px 30px 40px; min-height: 200px; }
        .cat-pill { padding: 12px 25px; background: #f1f3f4; border-radius: 30px; font-weight: 600; color: #333; cursor: pointer; white-space: nowrap; transition: 0.2s; border: 1px solid transparent; }
        .cat-pill.active { background: #4285F4; color: white; }
        .about-footer { margin-top: 60px; padding: 50px; background-color: #1a1a1a; color: #fff; text-align: center; }

        /* --- ASK AI CHAT STYLES --- */
        .ai-fab {
            position: fixed; bottom: 30px; right: 30px; width: 65px; height: 65px;
            background: #4285F4; color: white; border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
            box-shadow: 0 6px 15px rgba(0,0,0,0.3); cursor: pointer; z-index: 2000;
            transition: transform 0.2s, background 0.2s;
            font-size: 30px;
        }
        .ai-fab:hover { transform: scale(1.1); background: #3367d6; }
        
        .chat-window {
            position: fixed; bottom: 110px; right: 30px; width: 350px; height: 500px;
            background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 2000; display: none; flex-direction: column; overflow: hidden;
            border: 1px solid #eee;
        }
        .chat-window.active { display: flex; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .chat-header {
            background: #4285F4;
            color: white; padding: 15px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;
        }
        .close-chat { cursor: pointer; font-size: 18px; }

        .chat-messages { flex: 1; padding: 15px; overflow-y: auto; background: #f9f9f9; display: flex; flex-direction: column; gap: 10px; }
        
        .msg { padding: 10px 15px; border-radius: 15px; font-size: 13px; max-width: 80%; line-height: 1.4; word-wrap: break-word; }
        .msg.bot { background: white; border: 1px solid #ddd; align-self: flex-start; color: #333; border-bottom-left-radius: 2px; }
        .msg.user { background: #4285F4; color: white; align-self: flex-end; border-bottom-right-radius: 2px; }

        .chat-input-area { padding: 10px; background: white; border-top: 1px solid #eee; display: flex; gap: 10px; }
        .chat-input { flex: 1; border: 1px solid #ddd; padding: 10px; border-radius: 20px; outline: none; font-family: inherit; }
        .send-btn { background: #4285F4; color: white; border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .send-btn:hover { background: #3367d6; }
        
        .typing { font-style: italic; color: #888; font-size: 11px; margin-left: 10px; display: none; }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="overlay" onclick="toggleMenu()"></div>
<div class="slide-menu" id="slideMenu">
    <div class="menu-header">
        <button class="btn-close-menu" onclick="toggleMenu()">&times;</button>
        <?php if ($nav_image): ?>
            <img src="<?php echo htmlspecialchars($nav_image); ?>" class="menu-avatar">
        <?php else: ?>
            <div class="menu-avatar"><?php echo getNavInitials($nav_name); ?></div>
        <?php endif; ?>
        <div>
            <div style="font-size: 18px; font-weight: bold;"><?php echo htmlspecialchars($nav_name); ?></div>
            <div style="font-size: 13px; opacity: 0.8;"><?php echo htmlspecialchars($user_email); ?></div>
        </div>
    </div>
    
    <ul class="menu-list">
        <li><a href="user_dashboard.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
        
        <li><a href="forum.php"><i class="fas fa-comments"></i> Community Forum</a></li>
        <?php if ($user_role === 'admin'): ?>
            <li><a href="admin_dashboard.php"><i class="fas fa-cog"></i> Admin Panel</a></li>
        <?php elseif ($req_status === 'pending'): ?>
            <li><a href="#" style="color:#fbbc05; cursor: default;" onclick="alert('Pending approval.'); return false;"><i class="fas fa-clock"></i> Approval Pending...</a></li>
        <?php else: ?>
            <li><a href="upgrade_publisher.php" onclick="return confirm('Send request to become a Publisher?');"><i class="fas fa-arrow-up"></i> Register as Publisher</a></li>
        <?php endif; ?>

        <li style="margin-top: auto; border-top: 1px solid #eee;">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
        </li>
    </ul>
</div>
<div class="ai-fab" onclick="toggleChat()">
    <i class="fas fa-robot"></i> 
</div>

<div class="chat-window" id="chatWindow">
    <div class="chat-header">
        <span>🤖 Bibliostack AI</span>
        <span class="close-chat" onclick="toggleChat()">&times;</span>
    </div>
    <div class="chat-messages" id="chatMessages">
        <div class="msg bot">Hi! I'm your Bibliostack Assistant. Ask me about books, recommendations, or how to use the site!</div>
    </div>
    <div class="typing" id="typingIndicator">Thinking...</div>
    <div class="chat-input-area">
        <input type="text" id="chatInput" class="chat-input" placeholder="Ask AI..." onkeypress="handleEnter(event)">
        <button class="send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<div class="desktop-wrapper">
    <div class="sticky-container">
        <div class="header">
            <div style="display:flex; align-items:center; gap: 20px;">
                <div class="menu-icon" onclick="toggleMenu()"><i class="fas fa-bars"></i></div>
                <div class="logo">BIBLIOSTACK</div> 
            </div>
            <div class="header-actions">
                <a href="profile.php" class="profile-link" title="My Profile">
                    <?php if ($nav_image): ?>
                        <img src="<?php echo htmlspecialchars($nav_image); ?>" class="profile-img">
                    <?php else: ?>
                        <div class="profile-initials"><?php echo getNavInitials($nav_name); ?></div>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        <div class="search-container">
            <div class="search-bar"><span style="font-size: 20px; margin-right: 10px;">🔍</span><input type="text" placeholder="Search library..."></div>
        </div>
    </div>

    <?php if ($last_read): ?>
        <div class="section-title">Continue Reading</div>
        <div style="padding: 0 40px;">
            <div class="continue-card">
                <?php if(!empty($last_read['cover_path'])): ?>
                    <img src="<?php echo $last_read['cover_path']; ?>" class="book-cover-large">
                <?php else: ?>
                    <div class="book-cover-large" style="background:#FF5733; color:white; display:flex; justify-content:center; align-items:center;">No Cover</div>
                <?php endif; ?>
                <div class="book-info" style="flex:1;">
                    <h3><?php echo htmlspecialchars($last_read['title']); ?></h3>
                    <p style="color:#666;">By <?php echo htmlspecialchars($last_read['author']); ?></p>
                    <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:bold;">
                        <span>Page <?php echo $last_read['current_page']; ?> / <?php echo $last_read['total_pages']; ?></span>
                        <span><?php echo $progress_percent; ?>%</span>
                    </div>
                    <div class="progress-container"><div class="progress-fill" style="width: <?php echo $progress_percent; ?>%;"></div></div>
                    <div style="margin-top: 20px;"><a href="read.php?book_id=<?php echo $last_read['id']; ?>" class="btn-continue">▶ Resume Reading</a></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="section-title">Recently Added</div>
    <div class="horizontal-scroll">
        <?php 
        if ($result_recent->num_rows > 0) {
            while($row = $result_recent->fetch_assoc()) {
                $cover = !empty($row['cover_path']) ? $row['cover_path'] : 'https://placehold.co/160x230?text=No+Cover';
                echo '<div class="book-card" onclick="window.location.href=\'information.php?book_id='.$row['id'].'\'">
                        <img src="'.$cover.'" class="book-cover-small">
                        <div class="book-title">'.htmlspecialchars($row['title']).'</div>
                        <div style="font-size: 12px; color: #666;">'.date('M d', strtotime($row['created_at'])).'</div>
                      </div>';
            }
        } else { echo "<p style='color:#666; padding-left: 40px;'>No recently added books.</p>"; }
        ?>
    </div>

    <div class="section-title">Explore by Category</div>
    <div class="horizontal-scroll" id="category-container">
        <?php foreach($categories as $index => $cat): ?>
            <div class="cat-pill <?php echo $index === 0 ? 'active' : ''; ?>" onclick="filterCategory(this, '<?php echo $cat; ?>')"><?php echo $cat; ?></div>
        <?php endforeach; ?>
    </div>

    <div class="section-title" id="grid-title">All Books</div>
    <div class="recent-grid" id="book-grid">
        <?php 
        if ($result_library->num_rows > 0) {
            while($row = $result_library->fetch_assoc()) {
                $cover = !empty($row['cover_path']) ? $row['cover_path'] : 'https://placehold.co/160x230?text=No+Cover';
                echo '<div class="book-card" onclick="window.location.href=\'information.php?book_id='.$row['id'].'\'">
                        <img src="'.$cover.'" class="book-cover-small">
                        <div class="book-title">'.htmlspecialchars($row['title']).'</div>
                        <div style="font-size: 12px; color: #666;">'.htmlspecialchars($row['category']).'</div>
                      </div>';
            }
        } else { echo "<p style='color:#666; width:100%; padding-left: 40px;'>No books available.</p>"; }
        ?>
    </div>

    <div class="about-footer">
        <h3>BIBLIOSTACK</h3>
        <p>"The Enterprise Knowledge Engine"</p>
        <div style="margin-top: 30px; color: #555;">© 2026 Bibliostack Inc.</div>
    </div>
</div>

<script>
    // SIDEBAR
    function toggleMenu() {
        document.getElementById('slideMenu').classList.toggle('active');
        document.getElementById('overlay').classList.toggle('active');
    }

    // CHATBOT
    function toggleChat() {
        const chat = document.getElementById('chatWindow');
        chat.classList.toggle('active');
    }

    function handleEnter(e) {
        if (e.key === 'Enter') sendMessage();
    }

    function sendMessage() {
        const input = document.getElementById('chatInput');
        const msgText = input.value.trim();
        if (!msgText) return;

        // Add User Message
        const chatBox = document.getElementById('chatMessages');
        chatBox.innerHTML += `<div class="msg user">${msgText}</div>`;
        input.value = '';
        chatBox.scrollTop = chatBox.scrollHeight;

        // Show Indicator
        document.getElementById('typingIndicator').style.display = 'block';

        // AJAX Call to PHP
        fetch('chat_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ message: msgText })
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('typingIndicator').style.display = 'none';
            chatBox.innerHTML += `<div class="msg bot">${data.reply}</div>`;
            chatBox.scrollTop = chatBox.scrollHeight;
        })
        .catch(err => {
            document.getElementById('typingIndicator').style.display = 'none';
            chatBox.innerHTML += `<div class="msg bot">Error connecting to server.</div>`;
        });
    }

    // FILTER BOOKS
    function filterCategory(element, categoryName) {
        let pills = document.querySelectorAll('.cat-pill');
        pills.forEach(pill => pill.classList.remove('active'));
        element.classList.add('active');
        document.getElementById('grid-title').innerText = (categoryName === 'All') ? "All Books" : categoryName + " Books";
        
        const grid = document.getElementById('book-grid');
        grid.style.opacity = '0.5';
        const formData = new FormData();
        formData.append('category', categoryName);

        fetch('filter_books.php', { method: 'POST', body: formData })
        .then(response => response.text())
        .then(data => {
            grid.innerHTML = data;
            grid.style.opacity = '1';
        });
    }
</script>

</body>
</html>