<?php
session_start();
include 'db_connect.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'publisher') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: publisher_dashboard.php");
    exit;
}

$book_id = (int)$_GET['id'];
$publisher_name = $_SESSION['username'];

// 2. Fetch Existing Book Data
// We verify the publisher owns this book
$sql = "SELECT * FROM books WHERE id = $book_id AND publisher_name = '$publisher_name'";
$result = $conn->query($sql);
if ($result->num_rows == 0) { die("Book not found or access denied."); }
$book = $result->fetch_assoc();

// 3. Handle Form Submission
if (isset($_POST['submit_update'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $author = mysqli_real_escape_string($conn, $_POST['author']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $pages = (int)$_POST['pages']; // New Page Count Field
    
    // File Uploads (Optional - keep old if empty)
    $cover_path = $book['cover_path'];
    $pdf_path = $book['book_pdf_path'];

    // Handle New Cover
    if (!empty($_FILES['cover']['name'])) {
        $target = "uploads/covers/" . uniqid() . "_" . basename($_FILES['cover']['name']);
        if (move_uploaded_file($_FILES['cover']['tmp_name'], $target)) {
             $cover_path = $target;
        }
    }

    // Handle New PDF
    if (!empty($_FILES['pdf']['name'])) {
        $target = "uploads/books/" . uniqid() . "_" . basename($_FILES['pdf']['name']);
        if (move_uploaded_file($_FILES['pdf']['tmp_name'], $target)) {
            $pdf_path = $target;
        }
    }

    // Check if update request already exists
    $check = $conn->query("SELECT id FROM book_updates WHERE book_id = $book_id");
    
    // Note: Ensure your 'book_updates' table has a 'pages' column. If not, run:
    // ALTER TABLE book_updates ADD COLUMN total_pages INT(11) NOT NULL DEFAULT 0;
    
    if ($check->num_rows > 0) {
        // Update the pending request
        $sql = "UPDATE book_updates SET 
                title='$title', 
                author='$author', 
                category='$category', 
                description='$desc', 
                total_pages=$pages, 
                cover_path='$cover_path', 
                book_pdf_path='$pdf_path', 
                submitted_at=NOW() 
                WHERE book_id=$book_id";
    } else {
        // Create new request
        $sql = "INSERT INTO book_updates (book_id, title, author, category, description, total_pages, cover_path, book_pdf_path) 
                VALUES ($book_id, '$title', '$author', '$category', '$desc', $pages, '$cover_path', '$pdf_path')";
    }

    if ($conn->query($sql)) {
        echo "<script>alert('Update submitted for approval!'); window.location.href='publisher_dashboard.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Book - Bibliostack</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f8; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        input, select, textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: #1a73e8; color: white; border: none; font-weight: bold; cursor: pointer; border-radius: 5px; }
        .btn:hover { background: #1557b0; }
        label { font-weight: bold; font-size: 13px; color: #555; }
        .preview-img { width: 80px; height: 120px; object-fit: cover; margin-bottom: 10px; border: 1px solid #ddd; }
        .file-info { font-size: 13px; color: #666; margin-bottom: 5px; display: block; }
        .file-info a { color: #1a73e8; text-decoration: none; }
    </style>
</head>
<body>

<div class="container">
    <h2>Edit Book Details</h2>
    <p style="color:#666; font-size:13px; margin-bottom:20px;">Changes will be reviewed by an Admin before going live.</p>

    <form method="POST" enctype="multipart/form-data">
        <label>Book Title</label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required>

        <label>Author</label>
        <input type="text" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required>

        <div style="display: flex; gap: 15px;">
            <div style="flex: 1;">
                <label>Category</label>
                <select name="category">
                    <option value="<?php echo $book['category']; ?>" selected><?php echo $book['category']; ?> (Current)</option>
                    <option value="Programming">Programming</option>
                    <option value="Fiction">Fiction</option>
                    <option value="Science">Science</option>
                    <option value="Business">Business</option>
                </select>
            </div>
            <div style="flex: 1;">
                <label>Total Pages</label>
                <input type="number" name="pages" value="<?php echo isset($book['total_pages']) ? $book['total_pages'] : ''; ?>" required placeholder="e.g. 350">
            </div>
        </div>

        <label>Description</label>
        <textarea name="description" rows="5" required><?php echo htmlspecialchars($book['description']); ?></textarea>

        <label>Cover Image (Leave empty to keep current)</label><br>
        <?php if(!empty($book['cover_path'])): ?>
            <img src="<?php echo $book['cover_path']; ?>" class="preview-img">
        <?php endif; ?>
        <input type="file" name="cover" accept="image/*">

        <label>Book PDF (Leave empty to keep current)</label><br>
        <?php if(!empty($book['book_pdf_path'])): ?>
            <span class="file-info">Current File: <a href="<?php echo $book['book_pdf_path']; ?>" target="_blank">View PDF</a></span>
        <?php else: ?>
            <span class="file-info">No PDF currently uploaded.</span>
        <?php endif; ?>
        <input type="file" name="pdf" accept="application/pdf">

        <button type="submit" name="submit_update" class="btn">Submit Changes for Approval</button>
        <br><br>
        <a href="publisher_dashboard.php" style="text-decoration:none; color:#666; display:block; text-align:center;">Cancel</a>
    </form>
</div>

</body>
</html>