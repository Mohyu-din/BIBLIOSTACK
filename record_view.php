<?php
session_start();
include 'db_connect.php';

if (isset($_POST['book_id'])) {
    $book_id = (int)$_POST['book_id'];
    
    // Increment view count
    $conn->query("UPDATE books SET views = views + 1 WHERE id=$book_id");
    echo "View Recorded";
}
?>