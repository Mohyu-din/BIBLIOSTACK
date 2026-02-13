<?php
session_start();
include 'db_connect.php';

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- 2. SAFE REDIRECT (THE FIX) ---
// Normalize role to lowercase to prevent loops (e.g. "Publisher" vs "publisher")
$role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : 'user';

// If the user is NOT a publisher AND NOT an admin, send them back to the User Dashboard
if ($role !== 'publisher' && $role !== 'admin') {
    header("Location: user_dashboard.php");
    exit;
}

// --- 3. HANDLE ACTIONS (CRUD) ---

// DELETE BOOK LOGIC
if (isset($_GET['delete_id'])) {
    $book_id = (int)$_GET['delete_id'];
    $publisher_name = $_SESSION['username'];
    
    // Security: Ensure this book actually belongs to the logged-in publisher
    // (Or allow if Admin)
    $check_sql = "SELECT * FROM books WHERE id = $book_id AND publisher_name = '$publisher_name'";
    if($role === 'admin') { $check_sql = "SELECT * FROM books WHERE id = $book_id"; }

    $check = $conn->query($check_sql);

    if ($check->num_rows > 0) {
        // Delete from DB
        $conn->query("DELETE FROM bookmarks WHERE book_id = $book_id"); // Remove bookmarks first
        $conn->query("DELETE FROM book_likes WHERE book_id = $book_id"); // Remove likes first
        $conn->query("DELETE FROM books WHERE id = $book_id"); // Delete book
        $msg = "✅ Book deleted successfully.";
    } else {
        $msg = "❌ Error: You don't have permission to delete this book.";
    }
}

// --- 4. FETCH DATA ---
$publisher_name = $_SESSION['username'];

// A. Stats
$sql_views = "SELECT SUM(views) as total_views FROM books WHERE publisher_name = '$publisher_name'";
$total_views = $conn->query($sql_views)->fetch_assoc()['total_views'] ?? 0;

$sql_count = "SELECT COUNT(*) as total_books FROM books WHERE publisher_name = '$publisher_name' AND status='approved'";
$total_books = $conn->query($sql_count)->fetch_assoc()['total_books'] ?? 0;

// B. Live Books
$live_books = $conn->query("SELECT * FROM books WHERE publisher_name = '$publisher_name' AND status='approved' ORDER BY created_at DESC");

// C. Pending Books
$pending_books = $conn->query("SELECT * FROM books WHERE publisher_name = '$publisher_name' AND status='pending' ORDER BY created_at DESC");

// Nav Profile Info
$nav_image = isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : null;
$nav_name = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
function getNavInitials($name) { return strtoupper(substr($name, 0, 1)); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publisher Dashboard - Bibliostack</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* BASE STYLES */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #f0f2f5; padding-bottom: 100px; display: flex; min-height: 100vh; }

        /* SIDEBAR */
        .sidebar { width: 250px; background: #fff; border-right: 1px solid #ddd; display: flex; flex-direction: column; position: fixed; height: 100vh; }
        .logo { padding: 25px; font-size: 24px; font-weight: 800; color: #222; border-bottom: 1px solid #eee; letter-spacing: 1px; }
        .nav-links { list-style: none; padding: 20px 0; }
        .nav-links li a { display: block; padding: 15px 25px; color: #555; text-decoration: none; display: flex; align-items: center; gap: 10px; font-weight: 500; transition: 0.2s; }
        .nav-links li a:hover, .nav-links li a.active { background: #e8f0fe; color: #1a73e8; border-right: 3px solid #1a73e8; }

        /* MAIN AREA */
        .main-content { margin-left: 250px; flex: 1; padding: 40px; width: calc(100% - 250px); }

        /* WELCOME & ACTION */
        .welcome-banner { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .btn-new-book { background: #1a73e8; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(26, 115, 232, 0.3); transition: 0.2s; }
        .btn-new-book:hover { background: #1557b0; transform: translateY(-2px); }

        /* STATS */
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; border: 1px solid #eee; display: flex; flex-direction: column; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-num { font-size: 32px; font-weight: 800; color: #333; }
        .stat-label { font-size: 14px; color: #666; font-weight: 600; text-transform: uppercase; margin-top: 5px; }

        /* TABLES */
        .section-header { font-size: 20px; font-weight: 700; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; margin-top: 30px;}
        .table-wrapper { background: white; border: 1px solid #eee; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f9f9f9; color: #555; font-size: 13px; text-transform: uppercase; }
        tr:last-child td { border-bottom: none; }
        
        .book-mini-cover { width: 40px; height: 60px; object-fit: cover; border-radius: 4px; background: #ddd; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-approved { background: #e6f4ea; color: #137333; }
        .status-pending { background: #fef7e0; color: #b06000; }

        .action-btn { padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: bold; margin-right: 5px; display: inline-block; cursor: pointer; }
        .btn-delete { background: #fce8e6; color: #c5221f; }
        .btn-delete:hover { background: #fad2cf; }
        
        .alert { padding: 15px; background: #e6f4ea; color: #137333; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ceead6; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">BIBLIOSTACK</div>
        
        <ul class="nav-links">
            <li><a href="#" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            
            <li><a href="publish_book.php"><i class="fas fa-cloud-upload-alt"></i> Publish Book</a></li>
            
            <li><a href="publisher_library.php"><i class="fas fa-book-open"></i> My Library</a></li>            
            
            <li><a href="forum.php"><i class="fas fa-comments"></i> Community Forum</a></li>

            <li style="margin-top: auto; border-top: 1px solid #eee;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">

        <?php if(isset($msg)) echo "<div class='alert'>$msg</div>"; ?>

        <div class="welcome-banner">
            <div>
                <h1 style="margin-bottom: 5px;">Publisher Dashboard</h1>
                <p style="color:#666;">Welcome, <?php echo htmlspecialchars($nav_name); ?>. Manage your library.</p>
            </div>
            <a href="publish_book.php" class="btn-new-book">
                <i class="fas fa-plus"></i> Publish New Book
            </a>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-num"><?php echo $total_books; ?></div>
                <div class="stat-label">Live Books</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?php echo $total_views; ?></div>
                <div class="stat-label">Total Reads</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?php echo $pending_books->num_rows; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
        </div>

        <div class="section-header">
            <i class="fas fa-clock" style="color: #fbbc05;"></i> Pending Approval
        </div>
        <?php if ($pending_books->num_rows > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Cover</th>
                        <th>Title</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $pending_books->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <img src="<?php echo !empty($row['cover_path']) ? $row['cover_path'] : 'https://placehold.co/40'; ?>" class="book-mini-cover">
                        </td>
                        <td>
                            <b><?php echo htmlspecialchars($row['title']); ?></b><br>
                            <span style="font-size:12px; color:#888;"><?php echo htmlspecialchars($row['category']); ?></span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td><span class="status-badge status-pending">Pending</span></td>
                        <td>
                            <a href="?delete_id=<?php echo $row['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Cancel this submission?');">Cancel</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p style="color:#888; font-style:italic; margin-bottom:20px;">No pending books.</p>
        <?php endif; ?>

        <div class="section-header">
            <i class="fas fa-check-circle" style="color: #34a853;"></i> Live Books
        </div>
        <?php if ($live_books->num_rows > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Cover</th>
                        <th>Title</th>
                        <th>Views</th>
                        <th>Likes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $live_books->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <img src="<?php echo !empty($row['cover_path']) ? $row['cover_path'] : 'https://placehold.co/40'; ?>" class="book-mini-cover">
                        </td>
                        <td>
                            <b><?php echo htmlspecialchars($row['title']); ?></b><br>
                            <span style="font-size:12px; color:#888;"><?php echo htmlspecialchars($row['category']); ?></span>
                        </td>
                        <td><?php echo $row['views']; ?></td>
                        <td><?php echo $row['likes']; ?></td>
                        <td><span class="status-badge status-approved">Live</span></td>
                        <td>
                            <a href="edit_book.php?id=<?php echo $row['id']; ?>" class="action-btn" style="background:#e8f0fe; color:#1a73e8; margin-right: 5px;">Edit</a>
                            
                            <a href="?delete_id=<?php echo $row['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Permanently delete this book?');">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div style="text-align: center; padding: 30px; border: 1px dashed #ccc; border-radius: 8px;">
                <p style="color: #666;">You haven't published any books yet.</p>
            </div>
        <?php endif; ?>

    </div>
    
</body>
</html>