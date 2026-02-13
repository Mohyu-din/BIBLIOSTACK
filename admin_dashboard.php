<?php
session_start();
include 'db_connect.php';

// --- 1. SECURITY: ADMIN CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// --- 2. HANDLE ACTIONS (POST/GET REQUESTS) ---

// A. Approve Update (Merge Changes) - NEW
if (isset($_GET['approve_update'])) {
    $update_id = (int)$_GET['approve_update'];
    
    // Fetch the update data
    $update_req = $conn->query("SELECT * FROM book_updates WHERE id = $update_id")->fetch_assoc();
    
    if ($update_req) {
        $bid = $update_req['book_id'];
        $t = mysqli_real_escape_string($conn, $update_req['title']);
        $a = mysqli_real_escape_string($conn, $update_req['author']);
        $c = mysqli_real_escape_string($conn, $update_req['category']);
        $d = mysqli_real_escape_string($conn, $update_req['description']);
        $cp = mysqli_real_escape_string($conn, $update_req['cover_path']);
        $pdf = mysqli_real_escape_string($conn, $update_req['book_pdf_path']);

        // Update the main 'books' table
        $sql = "UPDATE books SET title='$t', author='$a', category='$c', description='$d', cover_path='$cp', book_pdf_path='$pdf' WHERE id=$bid";
        
        if ($conn->query($sql)) {
            // Delete the request after approval
            $conn->query("DELETE FROM book_updates WHERE id = $update_id");
            echo "<script>alert('Changes Approved & Merged!'); window.location.href='admin_dashboard.php?tab=updates';</script>";
        }
    }
}

// B. Reject Update - NEW
if (isset($_GET['reject_update'])) {
    $update_id = (int)$_GET['reject_update'];
    $conn->query("DELETE FROM book_updates WHERE id = $update_id");
    echo "<script>alert('Update Rejected.'); window.location.href='admin_dashboard.php?tab=updates';</script>";
}

// C. Approve Book (Make it Live)
if (isset($_GET['approve_book'])) {
    $id = (int)$_GET['approve_book'];
    $conn->query("UPDATE books SET status = 'approved' WHERE id = $id");
    header("Location: admin_dashboard.php?tab=review&msg=book_approved");
    exit;
}

// D. Reject Book (Delete it)
if (isset($_GET['reject_book'])) {
    $id = (int)$_GET['reject_book'];
    $conn->query("DELETE FROM bookmarks WHERE book_id = $id"); 
    $conn->query("DELETE FROM books WHERE id = $id");
    header("Location: admin_dashboard.php?tab=review&msg=book_rejected");
    exit;
}

// E. Approve Publisher
if (isset($_GET['approve_id'])) {
    $id = (int)$_GET['approve_id'];
    $conn->query("UPDATE users SET is_approved = 1 WHERE id = $id");
    header("Location: admin_dashboard.php?tab=approvals&msg=approved");
    exit;
}

// F. Delete User
if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    if ($id != $_SESSION['user_id']) {
        $conn->query("DELETE FROM bookmarks WHERE user_name = (SELECT username FROM users WHERE id=$id)");
        $conn->query("DELETE FROM books WHERE author = (SELECT username FROM users WHERE id=$id)");
        $conn->query("DELETE FROM users WHERE id = $id");
        header("Location: admin_dashboard.php?tab=users&msg=deleted");
    }
    exit;
}

// G. Delete Book (From Live List)
if (isset($_GET['delete_book'])) {
    $id = (int)$_GET['delete_book'];
    $conn->query("DELETE FROM bookmarks WHERE book_id = $id");
    $conn->query("DELETE FROM books WHERE id = $id");
    header("Location: admin_dashboard.php?tab=books&msg=book_deleted");
    exit;
}

// H. Update User Role
if (isset($_POST['update_role'])) {
    $uid = (int)$_POST['user_id'];
    $new_role = mysqli_real_escape_string($conn, $_POST['role']);
    $conn->query("UPDATE users SET role = '$new_role' WHERE id = $uid");
    header("Location: admin_dashboard.php?tab=users&msg=role_updated");
    exit;
}

// --- 3. FETCH DATA ---

// Stats Counts
$count_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$count_books = $conn->query("SELECT COUNT(*) as c FROM books WHERE status='approved'")->fetch_assoc()['c'];
$count_pending_books = $conn->query("SELECT COUNT(*) as c FROM books WHERE status='pending'")->fetch_assoc()['c'];
$count_pending_pubs = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='publisher' AND is_approved=0")->fetch_assoc()['c'];
$count_pending_updates = $conn->query("SELECT COUNT(*) as c FROM book_updates")->fetch_assoc()['c']; // NEW

// Data Lists
$users_list = $conn->query("SELECT * FROM users ORDER BY id DESC");
$books_list = $conn->query("SELECT * FROM books WHERE status='approved' ORDER BY created_at DESC");
$pending_books_list = $conn->query("SELECT * FROM books WHERE status='pending' ORDER BY created_at ASC");
$pending_pubs_list = $conn->query("SELECT * FROM users WHERE role='publisher' AND is_approved=0");
$pending_updates_list = $conn->query("SELECT u.*, b.title as old_title FROM book_updates u JOIN books b ON u.book_id = b.id ORDER BY u.submitted_at ASC"); // NEW

// Current Tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Panel - Bibliostack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS RESET & VARS */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; background: #f0f2f5; min-height: 100vh; }
        
        /* SIDEBAR */
        .sidebar { width: 250px; background: #1a1a1a; color: white; display: flex; flex-direction: column; position: fixed; height: 100vh; }
        .logo { padding: 20px; font-size: 20px; font-weight: bold; border-bottom: 1px solid #333; text-align: center; letter-spacing: 1px; }
        .nav-links { list-style: none; padding: 0; margin-top: 20px; }
        .nav-links li a { display: block; padding: 15px 25px; color: #bbb; text-decoration: none; transition: 0.3s; display: flex; align-items: center; gap: 10px; }
        .nav-links li a:hover, .nav-links li a.active { background: #333; color: white; border-left: 4px solid #4285F4; }
        
        /* MAIN CONTENT */
        .main-content { margin-left: 250px; flex: 1; padding: 30px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h2 { color: #333; }
        .admin-badge { background: #4285F4; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; }

        /* CARDS (STATS) */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; }
        .stat-info h3 { font-size: 32px; color: #333; margin: 0; }
        .stat-info p { color: #666; font-size: 14px; margin: 5px 0 0; }
        .stat-icon { font-size: 40px; opacity: 0.2; }

        /* TABLES */
        .table-container { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { color: #555; font-size: 13px; text-transform: uppercase; background: #f9f9f9; }
        tr:hover { background: #f8f9fa; }
        
        /* BUTTONS & BADGES */
        .btn { padding: 6px 12px; border-radius: 5px; text-decoration: none; font-size: 12px; font-weight: bold; border: none; cursor: pointer; display: inline-block; margin-right: 5px; }
        .btn-green { background: #e6f4ea; color: #137333; }
        .btn-green:hover { background: #ceead6; }
        .btn-red { background: #fce8e6; color: #c5221f; }
        .btn-red:hover { background: #fad2cf; }
        .btn-blue { background: #e8f0fe; color: #1a73e8; }
        .btn-search { background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc; }
        .btn-search:hover { background: #d2e3fc; }

        .role-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .role-admin { background: #333; color: white; }
        .role-publisher { background: #e8f0fe; color: #1a73e8; }
        .role-user { background: #eee; color: #555; }

        .notification-badge { background: #c5221f; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: auto; font-weight: bold; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">BIBLIOSTACK</div>
        <ul class="nav-links">
            <li><a href="?tab=dashboard" class="<?php echo $tab == 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-chart-pie"></i> Dashboard</a></li>
            
            <li><a href="?tab=review" class="<?php echo $tab == 'review' ? 'active' : ''; ?>">
                <i class="fas fa-gavel"></i> Review Books 
                <?php if($count_pending_books > 0): ?>
                    <span class="notification-badge"><?php echo $count_pending_books; ?></span>
                <?php endif; ?>
            </a></li>

            <li><a href="?tab=updates" class="<?php echo $tab == 'updates' ? 'active' : ''; ?>">
                <i class="fas fa-sync-alt"></i> Content Updates
                <?php if($count_pending_updates > 0): ?>
                    <span class="notification-badge"><?php echo $count_pending_updates; ?></span>
                <?php endif; ?>
            </a></li>

            <li><a href="?tab=users" class="<?php echo $tab == 'users' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="?tab=books" class="<?php echo $tab == 'books' ? 'active' : ''; ?>"><i class="fas fa-book"></i> Live Books</a></li>
            <li><a href="?tab=approvals" class="<?php echo $tab == 'approvals' ? 'active' : ''; ?>"><i class="fas fa-user-check"></i> Approvals <?php if($count_pending_pubs > 0) echo "($count_pending_pubs)"; ?></a></li>
            <li style="margin-top: auto; border-top: 1px solid #333;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        
        <div class="header">
            <h2>
                <?php 
                    if($tab == 'dashboard') echo "Admin Dashboard";
                    elseif($tab == 'review') echo "Review & Copyright Check";
                    elseif($tab == 'updates') echo "Approve Content Changes";
                    elseif($tab == 'users') echo "Manage Users";
                    elseif($tab == 'books') echo "Manage Live Books";
                    elseif($tab == 'approvals') echo "Publisher Approvals";
                ?>
            </h2>
            <span class="admin-badge">SUPER ADMIN</span>
        </div>

        <?php if ($tab == 'dashboard'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $count_pending_books; ?></h3>
                        <p style="color: #c5221f; font-weight: bold;">Pending Review</p>
                    </div>
                    <i class="fas fa-gavel stat-icon" style="color: #c5221f;"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $count_pending_updates; ?></h3>
                        <p style="color: #fbbc05; font-weight: bold;">Pending Updates</p>
                    </div>
                    <i class="fas fa-sync-alt stat-icon" style="color: #fbbc05;"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $count_users; ?></h3>
                        <p>Total Users</p>
                    </div>
                    <i class="fas fa-users stat-icon" style="color: #4285F4;"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $count_books; ?></h3>
                        <p>Live Books</p>
                    </div>
                    <i class="fas fa-book stat-icon" style="color: #34A853;"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3><?php echo $count_pending_pubs; ?></h3>
                        <p>Pending Publishers</p>
                    </div>
                    <i class="fas fa-clock stat-icon" style="color: #FBBC05;"></i>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($tab == 'updates'): ?>
            <div class="table-container">
                <h3>🔄 Pending Content Updates</h3>
                <p style="margin-bottom: 20px; color: #666; font-size: 14px;">
                    Publishers have submitted changes to these books. Review and merge.
                </p>
                <?php if ($pending_updates_list->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Original Title</th>
                            <th>New Title</th>
                            <th>New Author</th>
                            <th>Submitted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $pending_updates_list->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['old_title']); ?></td>
                            <td style="color:#1a73e8; font-weight:bold;"><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['author']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['submitted_at'])); ?></td>
                            <td>
                                <a href="?approve_update=<?php echo $row['id']; ?>" class="btn btn-green" onclick="return confirm('Merge these changes to the live book?');">Approve</a>
                                <a href="?reject_update=<?php echo $row['id']; ?>" class="btn btn-red" onclick="return confirm('Reject changes?');">Reject</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="color:#888; padding: 20px; text-align: center;">No pending content updates.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($tab == 'review'): ?>
            <div class="table-container">
                <h3>📚 Pending Book Review</h3>
                <p style="margin-bottom: 20px; color: #666; font-size: 14px;">
                    Check these books for copyright infringement before approving.
                </p>

                <?php if ($pending_books_list->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>Title / Author</th>
                                <th>Uploader</th>
                                <th>Copyright Check</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($book = $pending_books_list->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo !empty($book['cover_path']) ? $book['cover_path'] : 'https://placehold.co/40'; ?>" 
                                         style="width: 40px; height: 60px; object-fit: cover; border-radius: 4px;">
                                </td>
                                <td>
                                    <b><?php echo htmlspecialchars($book['title']); ?></b><br>
                                    <span style="color:#888;">By <?php echo htmlspecialchars($book['author']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($book['publisher_name'] ?? 'Unknown'); ?></td>
                                
                                <td>
                                    <a href="https://www.google.com/search?q=<?php echo urlencode($book['title'] . ' ' . $book['author'] . ' book copyright'); ?>" 
                                       target="_blank" class="btn btn-search">
                                       <i class="fab fa-google"></i> Search Google
                                    </a>
                                </td>

                                <td>
                                    <a href="?approve_book=<?php echo $book['id']; ?>" class="btn btn-green">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="?reject_book=<?php echo $book['id']; ?>" class="btn btn-red" onclick="return confirm('Reject and delete this book?');">
                                        <i class="fas fa-trash"></i> Reject
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #888;">
                        <i class="fas fa-check-circle" style="font-size: 40px; margin-bottom: 10px; color: #ccc;"></i>
                        <p>All clean! No books waiting for review.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($tab == 'users'): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Change Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $users_list->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td>
                                <b><?php echo htmlspecialchars($row['username']); ?></b><br>
                                <span style="font-size:12px; color:#888;"><?php echo htmlspecialchars($row['email']); ?></span>
                            </td>
                            <td><span class="role-badge role-<?php echo $row['role']; ?>"><?php echo $row['role']; ?></span></td>
                            
                            <td>
                                <?php if($row['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                    <select name="role" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px; border: 1px solid #ddd;">
                                        <option value="user" <?php echo $row['role']=='user'?'selected':''; ?>>User</option>
                                        <option value="publisher" <?php echo $row['role']=='publisher'?'selected':''; ?>>Publisher</option>
                                        <option value="admin" <?php echo $row['role']=='admin'?'selected':''; ?>>Admin</option>
                                    </select>
                                    <input type="hidden" name="update_role" value="1">
                                </form>
                                <?php else: ?>
                                    <span style="color:#aaa; font-style:italic;">You</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if($row['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete_user=<?php echo $row['id']; ?>" class="btn btn-red" onclick="return confirm('Are you sure? This will delete their books too!');">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($tab == 'books'): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>Title / Author</th>
                            <th>Category</th>
                            <th>Views</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($book = $books_list->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <img src="<?php echo !empty($book['cover_path']) ? $book['cover_path'] : 'https://placehold.co/40'; ?>" 
                                     style="width: 40px; height: 60px; object-fit: cover; border-radius: 4px;">
                            </td>
                            <td>
                                <b><?php echo htmlspecialchars($book['title']); ?></b><br>
                                <span style="font-size:12px; color:#888;">By <?php echo htmlspecialchars($book['author']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($book['category']); ?></td>
                            <td><?php echo $book['views']; ?></td>
                            <td>
                                <a href="?delete_book=<?php echo $book['id']; ?>" class="btn btn-red" onclick="return confirm('Permanently delete this book?');">
                                    <i class="fas fa-trash"></i> Remove
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($tab == 'approvals'): ?>
            <div class="table-container">
                <?php if ($pending_pubs_list->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Requested</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $pending_pubs_list->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <a href="?approve_id=<?php echo $row['id']; ?>" class="btn btn-green">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #888;">
                        <i class="fas fa-check-circle" style="font-size: 40px; margin-bottom: 10px; color: #ccc;"></i>
                        <p>All caught up! No pending publisher requests.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>