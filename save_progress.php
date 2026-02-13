<?php
session_start();
include 'db_connect.php';

// --- 1. SECURITY & VALIDATION ---
if (!isset($_SESSION['user_id'])) {
    // Return a simple error string for the JavaScript console
    echo "Error: User not logged in";
    exit;
}

if (!isset($_POST['book_id']) || !isset($_POST['page'])) {
    echo "Error: Missing book ID or page number";
    exit;
}

// --- 2. SANITIZE INPUTS ---
$user_id = (int)$_SESSION['user_id'];
$book_id = (int)$_POST['book_id'];
$page = (int)$_POST['page'];

// --- 3. UPSERT QUERY (Insert or Update) ---
// This relies on the UNIQUE KEY (user_id, book_id) in your database.
// If the record exists, it updates it. If not, it creates it.
$sql = "INSERT INTO save_progress (user_id, book_id, current_page, last_accessed) 
        VALUES ($user_id, $book_id, $page, NOW()) 
        ON DUPLICATE KEY UPDATE current_page = $page, last_accessed = NOW()";

if ($conn->query($sql)) {
    echo "Success: Saved page $page";
} else {
    echo "Database Error: " . $conn->error;
}
?>