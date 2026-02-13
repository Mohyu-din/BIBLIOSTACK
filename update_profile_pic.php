<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

if (isset($_FILES['profile_pic'])) {
    $user_id = $_SESSION['user_id'];
    $file = $_FILES['profile_pic'];
    
    // 1. Validation
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowed)) {
        // 2. Create Upload Folder
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // 3. Generate Unique Name (user_id_timestamp.jpg)
        $new_filename = $user_id . '_' . time() . '.' . $ext;
        $target_file = $target_dir . $new_filename;
        
        // 4. Move File & Update DB
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            
            // Update Database
            $conn->query("UPDATE users SET profile_image = '$target_file' WHERE id = $user_id");
            
            // Update Session immediately so it shows up in Navbar
            $_SESSION['profile_image'] = $target_file;
            
            header("Location: profile.php"); // Refresh page
            exit;
        } else {
            echo "Error uploading file.";
        }
    } else {
        echo "Invalid file type. Only JPG, PNG, GIF allowed.";
    }
}
?>