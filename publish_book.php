<?php
session_start();
include 'db_connect.php';

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Only Publishers and Admins can upload
if ($_SESSION['role'] !== 'publisher' && $_SESSION['role'] !== 'admin') {
    die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>
            <h2>Access Denied 🚫</h2>
            <p>You must be a <b>Publisher</b> to upload books.</p>
            <a href='publisher_dashboard.php'>Go Back to Dashboard</a>
         </div>");
}

$message = "";
$msg_type = "";

// --- 2. HANDLE FORM SUBMISSION ---
if (isset($_POST['submit_book'])) {
    
    if (empty($_FILES['cover_image']['name']) || empty($_FILES['book_pdf']['name'])) {
        $message = "❌ Error: Please select both a Cover Image and a PDF.";
        $msg_type = "error";
    } else {
        
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $author = mysqli_real_escape_string($conn, $_POST['author']);
        $desc = mysqli_real_escape_string($conn, $_POST['description']);
        $total_pages = (int)$_POST['total_pages'];
        $publisher_name = $_SESSION['username'];

        $category_input = $_POST['category'];
        $category = ($category_input === "Other") ? mysqli_real_escape_string($conn, $_POST['other_category_text']) : mysqli_real_escape_string($conn, $category_input);

        $clean_folder_name = preg_replace('/[^A-Za-z0-9]/', '-', trim($title));
        $clean_folder_name = preg_replace('/-+/', '-', $clean_folder_name);
        $target_dir = "books/" . $clean_folder_name . "/";

        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }

        $cover_ext = pathinfo($_FILES["cover_image"]["name"], PATHINFO_EXTENSION);
        $cover_file = $target_dir . "cover." . $cover_ext; 
        $pdf_file = $target_dir . "book.pdf";
        
        $uploadOk = 1;

        if (!move_uploaded_file($_FILES["cover_image"]["tmp_name"], $cover_file)) {
            $message = "❌ Error: Failed to upload Cover Image.";
            $msg_type = "error";
            $uploadOk = 0;
        }
        elseif (!move_uploaded_file($_FILES["book_pdf"]["tmp_name"], $pdf_file)) {
            $message = "❌ Error: Failed to upload PDF.";
            $msg_type = "error";
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
            $sql = "INSERT INTO books (title, author, category, description, cover_path, book_pdf_path, folder_name, total_pages, status, publisher_name) 
                    VALUES ('$title', '$author', '$category', '$desc', '$cover_file', '$pdf_file', '$clean_folder_name', '$total_pages', 'pending', '$publisher_name')";

            if ($conn->query($sql) === TRUE) {
                $message = "✅ <b>Upload Successful!</b><br>Your book is now <b>Pending Approval</b>.";
                $msg_type = "success";
            } else {
                $message = "❌ Database Error: " . $conn->error;
                $msg_type = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publish Book - Bibliostack</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; background: #f0f2f5; min-height: 100vh; }

        /* SIDEBAR */
        .sidebar { width: 250px; background: #fff; border-right: 1px solid #ddd; display: flex; flex-direction: column; position: fixed; height: 100vh; }
        .logo { padding: 25px; font-size: 24px; font-weight: 800; color: #222; border-bottom: 1px solid #eee; letter-spacing: 1px; }
        .nav-links { list-style: none; padding: 20px 0; }
        .nav-links li a { display: block; padding: 15px 25px; color: #555; text-decoration: none; display: flex; align-items: center; gap: 10px; font-weight: 500; transition: 0.2s; }
        .nav-links li a:hover, .nav-links li a.active { background: #e8f0fe; color: #1a73e8; border-right: 3px solid #1a73e8; }
        
        /* MAIN CONTENT */
        .main-content { margin-left: 250px; flex: 1; padding: 40px; }
        
        .upload-card { background: white; max-width: 750px; margin: 0 auto; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px; }
        
        /* FORM STYLES */
        label { font-weight: 600; font-size: 13px; color: #555; display: block; margin-top: 15px; margin-bottom: 5px; }
        input[type="text"], input[type="number"], select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; transition: 0.3s; }
        input:focus, textarea:focus { border-color: #4285F4; outline: none; }
        
        .btn-upload { width: 100%; margin-top: 25px; padding: 14px; background: #4285F4; color: white; border: none; border-radius: 6px; font-weight: bold; font-size: 16px; cursor: pointer; transition: 0.2s; }
        .btn-upload:hover { background: #3367d6; transform: translateY(-1px); }

        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; line-height: 1.5; }
        .alert.error { background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; }
        .alert.success { background: #e6f4ea; color: #137333; border: 1px solid #ceead6; }

        /* --- UPLOAD BOX STYLES (THE MAGIC) --- */
        .upload-container {
            position: relative;
            width: 100%;
            height: 250px; /* Fixed height to stop layout jumping */
            border: 2px dashed #ddd;
            border-radius: 8px;
            background: #fafafa;
            overflow: hidden;
            transition: 0.3s;
        }
        .upload-container:hover { border-color: #4285F4; }

        /* 1. The "Click to Select" View */
        .default-view {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #888;
        }
        .default-view i { font-size: 30px; margin-bottom: 10px; }
        
        /* 2. The "Preview" View (Hidden by default) */
        .preview-view {
            width: 100%;
            height: 100%;
            display: none; /* Hidden initially */
            position: absolute;
            top: 0; left: 0;
            background: #fff;
        }
        
        /* Image Preview Specifics */
        .preview-img { width: 100%; height: 100%; object-fit: cover; }
        
        /* PDF Preview Specifics */
        .pdf-info-box {
            width: 100%; height: 100%;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            background: #fdfdfd;
        }
        .pdf-filename { margin-top: 10px; font-weight: bold; color: #333; font-size: 14px; text-align: center; padding: 0 10px; }
        .verify-btn { margin-top: 10px; color: #4285F4; text-decoration: none; font-size: 13px; font-weight: bold; border: 1px solid #4285F4; padding: 5px 15px; border-radius: 20px; transition: 0.2s; }
        .verify-btn:hover { background: #4285F4; color: white; }

        /* 3. Action Buttons (Overlay) */
        .actions-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        .action-icon {
            width: 35px; height: 35px;
            border-radius: 50%;
            background: rgba(0,0,0,0.6);
            color: white;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: 0.2s;
            backdrop-filter: blur(2px);
        }
        .action-icon:hover { background: rgba(0,0,0,0.8); transform: scale(1.1); }
        .action-icon.remove:hover { background: #dc3545; }
        .action-icon.edit:hover { background: #4285F4; }

    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">BIBLIOSTACK</div>
        <ul class="nav-links">
            <li><a href="publisher_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="#" class="active"><i class="fas fa-cloud-upload-alt"></i> Publish Book</a></li>
            <li><a href="publisher_library.php"><i class="fas fa-book-open"></i> My Library</a></li>
            <li style="margin-top: auto; border-top: 1px solid #eee;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        
        <div class="upload-card">
            <h2><i class="fas fa-pen-fancy" style="color: #4285F4;"></i> Publish a New Book</h2>
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">
                Share your knowledge. Please ensure you have the rights to publish this content.
            </p>

            <?php if ($message != ""): ?>
                <div class="alert <?php echo $msg_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="publish_book.php" method="POST" enctype="multipart/form-data">
                
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label>Book Title</label>
                        <input type="text" name="title" required placeholder="e.g. The Great Algorithm">
                    </div>
                    <div style="flex: 1;">
                        <label>Author Name</label>
                        <input type="text" name="author" required placeholder="e.g. Ada Lovelace">
                    </div>
                </div>
                
                <label>Category</label>
                <select name="category" id="cat" onchange="checkCat()">
                    <option value="Programming">Programming</option>
                    <option value="Fiction">Fiction</option>
                    <option value="Business">Business</option>
                    <option value="Science">Science</option>
                    <option value="History">History</option>
                    <option value="Other">Other...</option>
                </select>
                <input type="text" name="other_category_text" id="other-cat" style="display:none; margin-top: 10px;" placeholder="Type Custom Category...">

                <label>Description</label>
                <textarea name="description" placeholder="What is this book about?" rows="4"></textarea>
                
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label>Total Pages</label>
                        <input type="number" name="total_pages" placeholder="0">
                    </div>
                </div>

                <div style="display: flex; gap: 20px; margin-top: 20px;">
                    
                    <div style="flex: 1;">
                        <label>Cover Image (JPG/PNG)</label>
                        
                        <div class="upload-container" id="cover-container">
                            <div class="default-view" id="cover-default" onclick="document.getElementById('cover_input').click()">
                                <i class="fas fa-image"></i>
                                <span>Click to Select Cover</span>
                            </div>

                            <div class="preview-view" id="cover-preview">
                                <div class="actions-overlay">
                                    <div class="action-icon edit" onclick="document.getElementById('cover_input').click()" title="Change Image"><i class="fas fa-pen"></i></div>
                                    <div class="action-icon remove" onclick="removeFile('cover')" title="Remove"><i class="fas fa-times"></i></div>
                                </div>
                                <img id="cover-img-tag" src="" class="preview-img">
                            </div>
                        </div>
                        <input type="file" id="cover_input" name="cover_image" required accept="image/*" style="display:none;" onchange="handleCoverSelect(this)">
                    </div>

                    <div style="flex: 1;">
                        <label>Book File (PDF)</label>
                        
                        <div class="upload-container" id="pdf-container">
                            <div class="default-view" id="pdf-default" onclick="document.getElementById('pdf_input').click()">
                                <i class="fas fa-file-pdf"></i>
                                <span>Click to Select PDF</span>
                            </div>

                            <div class="preview-view" id="pdf-preview">
                                <div class="actions-overlay">
                                    <div class="action-icon edit" onclick="document.getElementById('pdf_input').click()" title="Change PDF"><i class="fas fa-pen"></i></div>
                                    <div class="action-icon remove" onclick="removeFile('pdf')" title="Remove"><i class="fas fa-times"></i></div>
                                </div>
                                <div class="pdf-info-box">
                                    <i class="fas fa-file-pdf" style="font-size: 40px; color: #e74c3c;"></i>
                                    <div id="pdf-name" class="pdf-filename">filename.pdf</div>
                                    <a id="pdf-link" href="#" target="_blank" class="verify-btn"><i class="fas fa-external-link-alt"></i> Verify PDF</a>
                                </div>
                            </div>
                        </div>
                        <input type="file" id="pdf_input" name="book_pdf" required accept="application/pdf" style="display:none;" onchange="handlePDFSelect(this)">
                    </div>

                </div>

                <button type="submit" name="submit_book" class="btn-upload">
                    <i class="fas fa-cloud-upload-alt"></i> Upload for Review
                </button>
            </form>
        </div>

    </div>

<script>
    // --- 1. Category Logic ---
    function checkCat() {
        var val = document.getElementById('cat').value;
        document.getElementById('other-cat').style.display = (val === 'Other') ? 'block' : 'none';
    }

    // --- 2. Cover Image Logic ---
    function handleCoverSelect(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                // Update Image Source
                document.getElementById('cover-img-tag').src = e.target.result;
                // Swap Views
                document.getElementById('cover-default').style.display = 'none';
                document.getElementById('cover-preview').style.display = 'block';
                // Update Container Border
                document.getElementById('cover-container').style.border = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // --- 3. PDF Logic ---
    function handlePDFSelect(input) {
        if (input.files && input.files[0]) {
            var file = input.files[0];
            
            // Set Filename
            document.getElementById('pdf-name').innerText = file.name;
            
            // Create Blob URL for verification
            var fileURL = URL.createObjectURL(file);
            document.getElementById('pdf-link').href = fileURL;
            
            // Swap Views
            document.getElementById('pdf-default').style.display = 'none';
            document.getElementById('pdf-preview').style.display = 'block';
            document.getElementById('pdf-container').style.border = '2px solid #4285F4';
        }
    }

    // --- 4. Remove File Logic ---
    function removeFile(type) {
        // Reset Input Value
        document.getElementById(type + '_input').value = "";
        
        // Swap Views Back
        document.getElementById(type + '_preview').style.display = 'none';
        document.getElementById(type + '_default').style.display = 'flex';
        
        // Reset Styles
        document.getElementById(type + '_container').style.border = '2px dashed #ddd';
    }
</script>

</body>
</html>