<?php
// File: load_more_books.php
include 'db_connect.php';

// Get the offset (how many books are already shown)
$offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;

// Fetch next 10 books
$sql = "SELECT * FROM books ORDER BY created_at DESC LIMIT 10 OFFSET $offset";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Use a default image if cover_path is empty
        $imagePath = !empty($row['cover_path']) ? $row['cover_path'] : 'https://placehold.co/180x260?text=No+Cover';
        
        echo '
        <div class="book-card" onclick="window.location.href=\'read.php?book_id='.$row['id'].'\'">
            <img src="'.$imagePath.'" class="book-cover-small" style="object-fit: cover;">
            <div class="book-title">'.htmlspecialchars($row['title']).'</div>
            <div style="font-size: 12px; color: #666;">Added: '.date('M d', strtotime($row['created_at'])).'</div>
        </div>';
    }
} else {
    // Return empty string if no more books
    echo "";
}
?>