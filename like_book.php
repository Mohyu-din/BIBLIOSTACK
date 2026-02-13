<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['book_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$book_id = (int)$_POST['book_id'];

// 1. Check if user already liked this book
$check = $conn->query("SELECT id FROM book_likes WHERE user_id=$user_id AND book_id=$book_id");

if ($check->num_rows > 0) {
    // --- UNLIKE ---
    $conn->query("DELETE FROM book_likes WHERE user_id=$user_id AND book_id=$book_id");
    $conn->query("UPDATE books SET likes = likes - 1 WHERE id=$book_id");
    $action = 'unliked';
} else {
    // --- LIKE ---
    $conn->query("INSERT INTO book_likes (user_id, book_id) VALUES ($user_id, $book_id)");
    $conn->query("UPDATE books SET likes = likes + 1 WHERE id=$book_id");
    $action = 'liked';
}

// Get new total likes
$new_count = $conn->query("SELECT likes FROM books WHERE id=$book_id")->fetch_assoc()['likes'];

echo json_encode(['status' => 'success', 'action' => $action, 'new_count' => $new_count]);
?>