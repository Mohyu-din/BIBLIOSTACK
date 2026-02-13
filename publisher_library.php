<?php
session_start();
include 'db_connect.php';

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Ensure only Publishers/Admins access this
if ($_SESSION['role'] !== 'publisher' && $_SESSION['role'] !== 'admin') {
    header("Location: user_dashboard.php");
    exit;
}

$publisher_name = $_SESSION['username'];
$nav_image = isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : null;
$nav_name = $_SESSION['username'];

function getNavInitials($name) { return strtoupper(substr($name, 0, 1)); }

// --- 2. FETCH PUBLISHER'S BOOKS ---
$sql = "SELECT * FROM books WHERE publisher_name = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $publisher_name);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Library - Bibliostack</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reuse standard styles */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #f0f2f5; display: flex; min-height: 100vh; }

        /* SIDEBAR */
        .sidebar { width: 250px; background: #fff; border-right: 1px solid #ddd; display: flex; flex-direction: column; position: fixed; height: 100vh; }
        .logo { padding: 25px; font-size: 24px; font-weight: 800; color: #222; border-bottom: 1px solid #eee; letter-spacing: 1px; }
        .nav-links { list-style: none; padding: 20px 0; }
        .nav-links li a { display: block; padding: 15px 25px; color: #555; text-decoration: none; display: flex; align-items: center; gap: 10px; font-weight: 500; transition: 0.2s; }
        .nav-links li a:hover, .nav-links li a.active { background: #e8f0fe; color: #1a73e8; border-right: 3px solid #1a73e8; }

        /* MAIN CONTENT */
        .main-content { margin-left: 250px; flex: 1; padding: 40px; }
        
        .header-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .profile-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; }
        .profile-initials { width: 45px; height: 45px; background-color: #4285F4; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        /* GRID */
        .book-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 30px; }
        .book-card { background: white; border-radius: 12px; padding: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.2s; cursor: pointer; text-align: center; }
        .book-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .book-cover { width: 100%; height: 220px; object-fit: cover; border-radius: 8px; margin-bottom: 10px; }
        .book-title { font-weight: bold; color: #333; font-size: 15px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .book-status { font-size: 12px; margin-top: 5px; padding: 4px 8px; border-radius: 15px; display: inline-block; }
        .status-approved { background: #e6f4ea; color: #137333; }
        .status-pending { background: #fef7e0; color: #b06000; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">BIBLIOSTACK</div>
        
        <ul class="nav-links">
            <li><a href="publisher_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="publish_book.php"><i class="fas fa-cloud-upload-alt"></i> Publish Book</a></li>
            <li><a href="#" class="active"><i class="fas fa-book-open"></i> My Library</a></li>
            <li style="margin-top: auto; border-top: 1px solid #eee;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        
        <div class="header-area">
            <h1>My Published Books</h1>
            <a href="profile.php" style="text-decoration:none;">
                <?php if ($nav_image): ?>
                    <img src="<?php echo htmlspecialchars($nav_image); ?>" class="profile-img">
                <?php else: ?>
                    <div class="profile-initials"><?php echo getNavInitials($nav_name); ?></div>
                <?php endif; ?>
            </a>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="book-grid">
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="book-card" onclick="window.location.href='read.php?book_id=<?php echo $row['id']; ?>'">
                        <img src="<?php echo !empty($row['cover_path']) ? $row['cover_path'] : 'https://placehold.co/180x250'; ?>" class="book-cover">
                        <div class="book-title"><?php echo htmlspecialchars($row['title']); ?></div>
                        
                        <?php if($row['status'] == 'approved'): ?>
                            <span class="book-status status-approved">Live</span>
                        <?php else: ?>
                            <span class="book-status status-pending">Pending</span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:50px; color:#666;">
                <i class="fas fa-book" style="font-size:40px; margin-bottom:15px; color:#ddd;"></i>
                <p>You haven't uploaded any books yet.</p>
                <a href="publish_book.php" style="color:#4285F4; font-weight:bold;">Upload your first book</a>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>