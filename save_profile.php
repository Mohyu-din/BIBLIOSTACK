<?php
session_start();
include 'db_connect.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // 3. Sanitize Inputs
    $name = mysqli_real_escape_string($conn, $_POST['username']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $bio = mysqli_real_escape_string($conn, $_POST['bio']);

    // 4. Update Database
    $sql = "UPDATE users SET 
            username = '$name', 
            phone = '$phone', 
            address = '$address', 
            bio = '$bio' 
            WHERE id = $user_id";

    if ($conn->query($sql)) {
        // Update Session Name immediately so Navbar updates
        $_SESSION['username'] = $name;
        
        // Redirect back to profile with success message
        header("Location: profile.php?msg=saved");
    } else {
        echo "Error updating record: " . $conn->error;
    }
}
?>