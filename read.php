<?php
session_start();
include 'db_connect.php';

// --- 1. SECURITY & SETUP ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if (!isset($_GET['book_id'])) { header("Location: user_dashboard.php"); exit; }

$id = (int)$_GET['book_id'];
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- 2. FETCH BOOK DATA ---
$stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
if (!$book) { die("Book not found."); }

$pdf_path = $book['book_pdf_path'];
$pdf_url = str_replace(' ', '%20', $pdf_path); 

// --- 3. GET SAVED PROGRESS (Resume Logic) ---
$saved_page = 1; // Default
$prog_query = $conn->query("SELECT current_page FROM save_progress WHERE user_id=$user_id AND book_id=$id");
if ($prog_query && $prog_query->num_rows > 0) {
    $saved_page = $prog_query->fetch_assoc()['current_page'];
} else {
    // If no record exists, create one starting at page 1
    // Using INSERT IGNORE to prevent errors if a record was created concurrently
    $conn->query("INSERT IGNORE INTO save_progress (user_id, book_id, current_page) VALUES ($user_id, $id, 1)");
}

// --- 4. HANDLE FORMS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Rating
    if (isset($_POST['rating'])) {
        $stars = (int)$_POST['rating'];
        $check_rate = $conn->query("SELECT id FROM ratings WHERE user_id=$user_id AND book_id=$id");
        
        if ($check_rate->num_rows > 0) {
            $conn->query("UPDATE ratings SET stars=$stars WHERE user_id=$user_id AND book_id=$id");
        } else {
            $conn->query("INSERT INTO ratings (user_id, book_id, stars) VALUES ($user_id, $id, $stars)");
        }
        $avg_query = $conn->query("SELECT AVG(stars) as avg_r FROM ratings WHERE book_id=$id");
        $new_avg = $avg_query->fetch_assoc()['avg_r'];
        $conn->query("UPDATE books SET rating=$new_avg WHERE id=$id");
    }

    // B. Comment & Reply Logic
    if (isset($_POST['post_comment'])) {
        $comment = mysqli_real_escape_string($conn, $_POST['comment_text']);
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : "NULL";
        
        if(!empty($comment)) {
            $sql = "INSERT INTO comments (book_id, user_name, comment_text, parent_id) VALUES ($id, '$username', '$comment', $parent_id)";
            $conn->query($sql);
        }
    }
    
    // C. Note
    if (isset($_POST['save_note'])) {
        $note = mysqli_real_escape_string($conn, $_POST['note_text']);
        if(!empty($note)) {
            $conn->query("INSERT INTO notes (book_id, user_name, user_id, note_text) VALUES ($id, '$username', $user_id, '$note')");
        }
    }

    // D. Bookmark
    if (isset($_POST['toggle_bookmark'])) {
        $check = $conn->query("SELECT id FROM bookmarks WHERE book_id=$id AND user_name='$username'");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO bookmarks (book_id, user_name) VALUES ($id, '$username')");
        } else {
            $conn->query("DELETE FROM bookmarks WHERE book_id=$id AND user_name='$username'");
        }
    }

    header("Location: read.php?book_id=$id");
    exit;
}

// Check Interactions
$is_liked = ($conn->query("SELECT id FROM book_likes WHERE user_id=$user_id AND book_id=$id")->num_rows > 0);
$is_bookmarked = ($conn->query("SELECT id FROM bookmarks WHERE book_id=$id AND user_name='$username'")->num_rows > 0);
$user_rating_query = $conn->query("SELECT stars FROM ratings WHERE user_id=$user_id AND book_id=$id");
$user_rating = ($user_rating_query->num_rows > 0) ? $user_rating_query->fetch_assoc()['stars'] : 0;

// --- FETCH COMMENTS ---
$comments_query = $conn->query("SELECT * FROM comments WHERE book_id = $id ORDER BY created_at DESC");
$parent_comments = [];
$replies = [];

while ($row = $comments_query->fetch_assoc()) {
    if ($row['parent_id'] == NULL || $row['parent_id'] == 0) {
        $parent_comments[] = $row;
    } else {
        $replies[$row['parent_id']][] = $row;
    }
}

$notes = $conn->query("SELECT * FROM notes WHERE book_id = $id AND user_id = $user_id ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reading: <?php echo htmlspecialchars($book['title']); ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';</script>

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; height: 100vh; display: flex; font-family: 'Segoe UI', sans-serif; overflow: hidden; background: #333; }

        /* PDF Section */
        .pdf-section { flex: 7; height: 100%; display: flex; flex-direction: column; background: #525659; border-right: 1px solid #222; position: relative; }
        .pdf-container { flex: 1; overflow: auto; display: flex; justify-content: center; padding: 30px; }
        canvas { box-shadow: 0 5px 25px rgba(0,0,0,0.5); background: white; }

        /* Controls */
        .pdf-controls { background: #2c2c2c; padding: 12px 20px; display: flex; justify-content: center; align-items: center; gap: 15px; color: white; border-bottom: 1px solid #444; }
        .control-btn { background: #444; color: white; border: 1px solid #555; padding: 8px 25px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.2s; font-size: 14px; }
        .control-btn:hover { background: #666; }
        .page-info { font-size: 14px; font-weight: 600; font-variant-numeric: tabular-nums; }

        /* Sidebar */
        .sidebar { flex: 3; height: 100%; background: #fff; display: flex; flex-direction: column; min-width: 350px; z-index: 10; border-left: 1px solid #ddd; }
        
        /* NEW HEADER WITH BACK BUTTON */
        .sb-header { padding: 15px 20px; border-bottom: 1px solid #eee; background: #f9f9f9; display: flex; flex-direction: column; gap: 10px; }
        .btn-back-dashboard { 
            display: flex; align-items: center; justify-content: center; gap: 8px;
            background: #eee; color: #333; text-decoration: none; 
            padding: 10px; border-radius: 6px; font-weight: bold; font-size: 13px; transition: 0.2s; 
        }
        .btn-back-dashboard:hover { background: #ddd; }

        .book-mini-title { font-size: 16px; font-weight: bold; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center; }

        .action-grid { display: flex; justify-content: space-around; padding: 15px 10px; border-bottom: 1px solid #eee; position: relative; }
        .action-btn { background: none; border: 1px solid transparent; cursor: pointer; color: #555; display: flex; flex-direction: column; align-items: center; gap: 5px; font-size: 11px; padding: 5px 10px; border-radius: 6px; transition: 0.2s; }
        .action-btn:hover { background: #f0f0f0; }
        .action-btn i { font-size: 18px; }
        
        .action-btn.liked i { color: #e0245e; }
        .action-btn.liked { color: #e0245e; }
        .action-btn.bookmarked i { color: #fbbc05; }
        .action-btn.rated i { color: #fbbc05; }

        .rating-dropdown { display: none; position: absolute; top: 60px; left: 50%; transform: translateX(-50%); background: white; border: 1px solid #ddd; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-radius: 8px; z-index: 100; width: 120px; text-align: left; }
        .rating-dropdown.show { display: block; }
        .rate-option { display: block; width: 100%; padding: 8px 15px; border: none; background: none; text-align: left; cursor: pointer; font-size: 13px; color: #333; }
        .rate-option:hover { background: #f5f5f5; color: #fbbc05; }

        /* Tabs */
        .tabs { display: flex; background: #f1f1f1; border-bottom: 1px solid #ddd; }
        .tab-btn { flex: 1; padding: 12px; border: none; background: none; cursor: pointer; font-weight: 600; color: #666; border-bottom: 3px solid transparent; }
        .tab-btn.active { border-bottom: 3px solid #4285F4; color: #4285F4; background: white; }
        .tab-content { flex: 1; overflow-y: auto; padding: 20px; display: none; }
        .tab-content.active { display: block; }

        /* Comments */
        .item-box { background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #ddd; }
        .reply-box { margin-left: 30px; border-left-color: #34a853 !important; background: #f1f8f3; font-size: 0.95em; }
        .reply-link { float: right; font-size: 11px; color: #4285F4; text-decoration: none; font-weight: bold; cursor: pointer; margin-top: -18px; }
        
        .reply-indicator { background: #e8f0fe; color: #1a73e8; padding: 5px 10px; font-size: 12px; border-radius: 4px; margin-bottom: 10px; display: none; align-items: center; justify-content: space-between; }
        
        textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 70px; margin-bottom: 10px; resize: vertical; }
        .submit-btn { width: 100%; padding: 10px; background: #333; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

    <div class="pdf-section">
        <div class="pdf-controls">
            <button class="control-btn" id="prev">Previous</button>
            <span class="page-info">
                Page <span id="page_num"><?php echo $saved_page; ?></span> / <span id="page_count">--</span>
            </span>
            <button class="control-btn" id="next">Next</button>
        </div>

        <div class="pdf-container">
            <canvas id="the-canvas"></canvas>
            <div id="error-msg" style="display:none; color:white; margin-top:50px; text-align:center;">
                <h3>⚠️ Unable to load PDF</h3>
                <p>Ensure the file path is correct.</p>
                <a href="<?php echo $pdf_url; ?>" target="_blank" style="color:#4285F4;">Download File</a>
            </div>
        </div>
    </div>

    <div class="sidebar">
        <div class="sb-header">
            <a href="user_dashboard.php" class="btn-back-dashboard">
                <i class="fas fa-arrow-left"></i> Exit Reading
            </a>
            <div class="book-mini-title"><?php echo htmlspecialchars($book['title']); ?></div>
        </div>

        <div class="action-grid">
            <button id="likeBtn" class="action-btn <?php echo $is_liked ? 'liked' : ''; ?>" onclick="toggleLike()">
                <i class="<?php echo $is_liked ? 'fas' : 'far'; ?> fa-heart"></i> 
                <span id="likeText"><?php echo $book['likes']; ?> Likes</span>
            </button>

            <button class="action-btn" onclick="shareBook()">
                <i class="fas fa-share-alt"></i> <span>Share</span>
            </button>

            <div style="position:relative;">
                <button class="action-btn <?php echo ($user_rating > 0) ? 'rated' : ''; ?>" onclick="toggleRatingMenu()">
                    <i class="<?php echo ($user_rating > 0) ? 'fas' : 'far'; ?> fa-star"></i>
                    <span><?php echo ($user_rating > 0) ? $user_rating . '/5' : 'Rate'; ?></span>
                </button>
                <div id="ratingMenu" class="rating-dropdown">
                    <form method="POST">
                        <button type="submit" name="rating" value="5" class="rate-option">⭐⭐⭐⭐⭐</button>
                        <button type="submit" name="rating" value="4" class="rate-option">⭐⭐⭐⭐</button>
                        <button type="submit" name="rating" value="3" class="rate-option">⭐⭐⭐</button>
                        <button type="submit" name="rating" value="2" class="rate-option">⭐⭐</button>
                        <button type="submit" name="rating" value="1" class="rate-option">⭐</button>
                    </form>
                </div>
            </div>

            <form method="POST" style="display:inline;">
                <input type="hidden" name="toggle_bookmark" value="1">
                <button type="submit" class="action-btn <?php echo $is_bookmarked ? 'bookmarked' : ''; ?>">
                    <i class="<?php echo $is_bookmarked ? 'fas' : 'far'; ?> fa-bookmark"></i> <span>Save</span>
                </button>
            </form>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="openTab('tab-comments', this)">Comments</button>
            <button class="tab-btn" onclick="openTab('tab-notes', this)">My Notes</button>
        </div>

        <div id="tab-comments" class="tab-content active">
            <form method="POST" id="commentForm">
                <input type="hidden" name="parent_id" id="parentIdField" value="">
                <div id="replyIndicator" class="reply-indicator">
                    <span>Replying to <b id="replyToUser">User</b></span>
                    <i class="fas fa-times" style="cursor:pointer;" onclick="cancelReply()"></i>
                </div>
                <textarea name="comment_text" id="commentBox" placeholder="Share your thoughts..." required></textarea>
                <button type="submit" name="post_comment" class="submit-btn">Post</button>
            </form>
            <br>
            <?php foreach ($parent_comments as $parent): ?>
                <div class="item-box" style="border-left-color: #4285F4;">
                    <strong><?php echo htmlspecialchars($parent['user_name']); ?></strong>
                    <span class="reply-link" onclick="replyTo('<?php echo htmlspecialchars($parent['user_name']); ?>', <?php echo $parent['id']; ?>)">Reply</span>
                    <p style="margin:5px 0; font-size:13px;"><?php echo htmlspecialchars($parent['comment_text']); ?></p>
                </div>
                <?php if (isset($replies[$parent['id']])): ?>
                    <?php foreach ($replies[$parent['id']] as $reply): ?>
                        <div class="item-box reply-box">
                            <strong><i class="fas fa-reply" style="transform: rotate(180deg);"></i> <?php echo htmlspecialchars($reply['user_name']); ?></strong>
                            <p style="margin:5px 0; font-size:13px;"><?php echo htmlspecialchars($reply['comment_text']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (empty($parent_comments)): ?>
                <p style="text-align:center; color:#999; font-size:13px;">No comments yet.</p>
            <?php endif; ?>
        </div>

        <div id="tab-notes" class="tab-content">
            <div style="font-size:12px; color:#666; margin-bottom:10px;"><i class="fas fa-lock"></i> Private Notes</div>
            <form method="POST">
                <textarea name="note_text" placeholder="Type a note..." required></textarea>
                <button type="submit" name="save_note" class="submit-btn" style="background:#e0a800; color:black;">Save Note</button>
            </form>
            <br>
            <?php while($row = $notes->fetch_assoc()): ?>
                <div class="item-box" style="border-left-color: #ffc107; background: #fffbf0;">
                    <p style="margin:0; font-size:13px;"><?php echo htmlspecialchars($row['note_text']); ?></p>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
        // --- REPLY LOGIC ---
        function replyTo(username, commentId) {
            document.getElementById('parentIdField').value = commentId;
            document.getElementById('replyToUser').innerText = username;
            document.getElementById('replyIndicator').style.display = 'flex';
            document.getElementById('commentBox').placeholder = "Write a reply...";
            document.getElementById('commentBox').focus();
        }

        function cancelReply() {
            document.getElementById('parentIdField').value = "";
            document.getElementById('replyIndicator').style.display = 'none';
            document.getElementById('commentBox').placeholder = "Share your thoughts...";
        }

        // --- PDF VIEWER LOGIC ---
        const url = "<?php echo $pdf_url; ?>";
        const bookId = <?php echo $id; ?>;
        
        let pdfDoc = null,
            pageNum = <?php echo $saved_page; ?>,
            pageRendering = false,
            pageNumPending = null,
            scale = 1.0,
            canvas = document.getElementById('the-canvas'),
            ctx = canvas.getContext('2d');

        pdfjsLib.getDocument(url).promise.then(function(pdfDoc_) {
            pdfDoc = pdfDoc_;
            document.getElementById('page_count').textContent = pdfDoc.numPages;
            renderPage(pageNum);
        }).catch(function(error) {
            document.getElementById('the-canvas').style.display = 'none';
            document.getElementById('error-msg').style.display = 'block';
        });

        function renderPage(num) {
            pageRendering = true;
            
            // AUTO SAVE PAGE TO DB
            saveProgressToDB(num);

            pdfDoc.getPage(num).then(function(page) {
                var viewport = page.getViewport({scale: scale});
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                var renderContext = { canvasContext: ctx, viewport: viewport };
                var renderTask = page.render(renderContext);
                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) { renderPage(pageNumPending); pageNumPending = null; }
                });
            });
            document.getElementById('page_num').textContent = num;
        }

        function queueRenderPage(num) { if (pageRendering) pageNumPending = num; else renderPage(num); }
        document.getElementById('prev').addEventListener('click', () => { if (pageNum > 1) { pageNum--; queueRenderPage(pageNum); } });
        document.getElementById('next').addEventListener('click', () => { if (pageNum < pdfDoc.numPages) { pageNum++; queueRenderPage(pageNum); } });

        // --- API CALLS ---
        function saveProgressToDB(pNum) {
            const formData = new FormData();
            formData.append('book_id', bookId);
            formData.append('page', pNum);
            
            console.log("Saving progress for book ID " + bookId + " at page " + pNum); // Debugging

            fetch('save_progress.php', { method: 'POST', body: formData })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                console.log("Save response: ", data); // Debugging
            })
            .catch(error => {
                console.error('Error saving progress:', error);
            });
        }

        function toggleLike() {
            const btn = document.getElementById('likeBtn');
            const icon = btn.querySelector('i');
            const txt = document.getElementById('likeText');
            const formData = new FormData();
            formData.append('book_id', bookId);
            fetch('like_book.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    txt.innerText = data.new_count + " Likes";
                    if(data.action === 'liked') { btn.classList.add('liked'); icon.classList.remove('far'); icon.classList.add('fas'); }
                    else { btn.classList.remove('liked'); icon.classList.remove('fas'); icon.classList.add('far'); }
                }
            });
        }

        function shareBook() {
            navigator.clipboard.writeText(window.location.href);
            alert("Link copied to clipboard!");
        }

        function openTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }

        function toggleRatingMenu() { document.getElementById('ratingMenu').classList.toggle('show'); }
        window.onclick = function(event) {
            if (!event.target.closest('.action-btn.rated') && !event.target.closest('.action-btn') && !event.target.closest('.rating-dropdown')) {
                document.getElementById('ratingMenu').classList.remove('show');
            }
        }

        setTimeout(() => {
            const formData = new FormData();
            formData.append('book_id', bookId);
            fetch('record_view.php', { method: 'POST', body: formData });
        }, 60000);
    </script>
</body>
</html>