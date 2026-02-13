<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Fetch User Details
$sql = "SELECT * FROM users WHERE id = $user_id";
$user = $conn->query($sql)->fetch_assoc();

// 2. Fetch Bookmarks
$bookmarks = $conn->query("
    SELECT books.id, books.title, books.cover_path 
    FROM bookmarks 
    JOIN books ON bookmarks.book_id = books.id 
    WHERE bookmarks.user_name = '{$user['username']}' 
    ORDER BY bookmarks.created_at DESC LIMIT 5
");

function getInitials($name) {
    return strtoupper(substr($name, 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f8; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        
        /* Layout Grid */
        .profile-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        
        @media(max-width: 768px) { .profile-grid { grid-template-columns: 1fr; } }

        /* Left Card (Avatar) */
        .card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        .avatar-section { text-align: center; }
        .avatar-wrapper { position: relative; width: 120px; height: 120px; margin: 0 auto 15px; }
        
        .avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .avatar-initials { width: 100%; height: 100%; border-radius: 50%; background: #4285F4; color: white; display: flex; align-items: center; justify-content: center; font-size: 40px; font-weight: bold; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        .edit-avatar-btn { position: absolute; bottom: 0; right: 0; width: 35px; height: 35px; background: #333; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid white; transition: 0.2s; }
        .edit-avatar-btn:hover { background: #000; transform: scale(1.1); }

        /* Form Styles */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: bold; color: #666; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; box-sizing: border-box; transition: 0.3s; }
        .form-control:focus { border-color: #4285F4; outline: none; background: #fdfdfd; }
        textarea.form-control { resize: vertical; min-height: 80px; }

        .btn-save { width: 100%; padding: 12px; background: #4285F4; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-save:hover { background: #3367d6; }

        .role-badge { display: inline-block; background: #e8f0fe; color: #1a73e8; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-top: 10px; }

        .section-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        
        .book-grid { display: flex; gap: 15px; overflow-x: auto; padding-bottom: 10px; }
        .book-card { min-width: 100px; text-align: center; text-decoration: none; color: inherit; }
        .book-card img { width: 90px; height: 130px; border-radius: 6px; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .book-title { font-size: 12px; color: #555; margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90px; }

        .alert { padding: 10px; background: #d4edda; color: #155724; border-radius: 6px; font-size: 14px; margin-bottom: 20px; text-align: center; }
        
        .back-link { display: inline-block; margin-bottom: 20px; color: #666; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

<div class="container">
    <a href="user_dashboard.php" class="back-link">← Back to Dashboard</a>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
        <div class="alert">✅ Profile updated successfully!</div>
    <?php endif; ?>

    <div class="profile-grid">
        
        <div class="card avatar-section">
            <div class="avatar-wrapper">
                <?php if (!empty($user['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" class="avatar">
                <?php else: ?>
                    <div class="avatar-initials"><?php echo getInitials($user['username']); ?></div>
                <?php endif; ?>
                
                <label for="fileUpload" class="edit-avatar-btn" title="Change Photo">
                    <i class="fas fa-camera" style="font-size: 14px;"></i>
                </label>
            </div>
            
            <form id="avatarForm" action="update_profile_pic.php" method="POST" enctype="multipart/form-data">
                <input type="file" id="fileUpload" name="profile_pic" accept="image/*" style="display:none;" onchange="document.getElementById('avatarForm').submit();">
            </form>

            <h2 style="margin: 10px 0 5px;"><?php echo htmlspecialchars($user['username']); ?></h2>
            <div style="color:#666; font-size: 14px;"><?php echo htmlspecialchars($user['email']); ?></div>
            <div class="role-badge"><?php echo ucfirst($user['role']); ?></div>
            
            <br><br>
            <a href="logout.php" style="color: #d93025; text-decoration: none; font-weight: bold; font-size: 14px;">Sign Out</a>
        </div>

        <div class="card">
            <div class="section-title">Edit Profile</div>
            
            <form action="save_profile.php" method="POST">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="+1 234 567 890" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Address / City</label>
                    <input type="text" name="address" class="form-control" placeholder="New York, USA" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" class="form-control" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn-save">Save Changes</button>
            </form>

            <br>
            <div class="section-title">My Library</div>
            <?php if ($bookmarks->num_rows > 0): ?>
                <div class="book-grid">
                    <?php while($book = $bookmarks->fetch_assoc()): ?>
                    <a href="read.php?book_id=<?php echo $book['id']; ?>" class="book-card">
                        <?php $cover = !empty($book['cover_path']) ? $book['cover_path'] : 'https://placehold.co/90x130?text=Book'; ?>
                        <img src="<?php echo $cover; ?>">
                        <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                    </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="color:#888; font-style:italic; font-size:14px;">No bookmarked books yet.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>