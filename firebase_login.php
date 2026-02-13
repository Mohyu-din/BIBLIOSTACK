<?php
session_start();
include 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['uid']) && isset($data['email'])) {
    
    // --- ADMIN CONFIGURATION ---
    $ADMIN_UID = "YOUR_ADMIN_UID_GOES_HERE"; // <--- PASTE YOUR ADMIN UID HERE
    // ---------------------------

    $uid = mysqli_real_escape_string($conn, $data['uid']);
    $email = mysqli_real_escape_string($conn, $data['email']);
    $name = mysqli_real_escape_string($conn, $data['displayName']);
    $photo = isset($data['photoURL']) ? mysqli_real_escape_string($conn, $data['photoURL']) : '';

    // Extra Fields
    $phone = isset($data['phone']) ? mysqli_real_escape_string($conn, $data['phone']) : '';
    $bio = isset($data['bio']) ? mysqli_real_escape_string($conn, $data['bio']) : '';
    $address = isset($data['address']) ? mysqli_real_escape_string($conn, $data['address']) : '';

    // --- ROLE LOGIC ---
    $role = 'user'; // Default
    if (isset($data['role']) && $data['role'] === 'publisher') {
        $role = 'publisher';
    }
    // Force Admin if UID matches
    if ($uid === $ADMIN_UID) {
        $role = 'admin';
    }

    // Check Database
    $check = $conn->query("SELECT * FROM users WHERE firebase_uid='$uid' OR email='$email'");

    if ($check->num_rows > 0) {
        // --- LOGIN ---
        $user = $check->fetch_assoc();
        
        // Update Admin role in DB if UID matches
        if ($role === 'admin' && $user['role'] !== 'admin') {
            $conn->query("UPDATE users SET role='admin' WHERE id=".$user['id']);
        }
        
        // Update Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // Use role from DB
        $_SESSION['profile_image'] = $photo;
        
        // SEND ROLE BACK TO FRONTEND
        echo json_encode(["status" => "success", "role" => $user['role']]); 

    } else {
        // --- SIGNUP ---
        $dummy_pass = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password, role, is_approved, firebase_uid, profile_image, phone, bio, address) 
                VALUES ('$name', '$email', '$dummy_pass', '$role', 1, '$uid', '$photo', '$phone', '$bio', '$address')";
        
        if ($conn->query($sql)) {
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['username'] = $name;
            $_SESSION['role'] = $role;
            $_SESSION['profile_image'] = $photo;
            
            // SEND ROLE BACK TO FRONTEND
            echo json_encode(["status" => "success", "role" => $role]);
        } else {
            echo json_encode(["status" => "error", "msg" => $conn->error]);
        }
    }
} else {
    echo json_encode(["status" => "error"]);
}
?>