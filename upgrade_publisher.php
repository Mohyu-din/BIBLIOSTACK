<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if already requested
$check = $conn->query("SELECT publisher_status FROM users WHERE id = $user_id")->fetch_assoc();

if ($check['publisher_status'] === 'pending') {
    echo "<script>
            alert('⚠️ You have already submitted a request. Please wait for Admin approval.');
            window.location.href = 'user_dashboard.php';
          </script>";
    exit;
}

// Send Request (Set status to 'pending')
$sql = "UPDATE users SET publisher_status = 'pending' WHERE id = $user_id";

if ($conn->query($sql) === TRUE) {
    // We do NOT change $_SESSION['role'] here. The Admin must do that.
    
    echo "<script>
            alert('✅ Request Sent! An Admin will review your application shortly.');
            window.location.href = 'user_dashboard.php';
          </script>";
} else {
    echo "Error: " . $conn->error;
}
?>