<?php
include 'db_connect.php';

$category = isset($_POST['category']) ? $_POST['category'] : 'All';

// LOGIC: Select books, but ONLY if status is 'approved'
if ($category === 'All') {
    $sql = "SELECT * FROM books WHERE status='approved' ORDER BY created_at DESC LIMIT 20";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT * FROM books WHERE category = ? AND status='approved' ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $category);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $cover = !empty($row['cover_path']) ? $row['cover_path'] : 'https://placehold.co/160x230?text=No+Cover';
        
        // --- LINK POINTS TO information.php ---
        echo '
        <div class="book-card" onclick="window.location.href=\'information.php?book_id='.$row['id'].'\'">
            <img src="'.$cover.'" class="book-cover-small">
            <div class="book-title">'.htmlspecialchars($row['title']).'</div>
            <div style="font-size: 12px; color: #666;">'.htmlspecialchars($row['category']).'</div>
        </div>';
    }
} else {
    echo '<p style="color: #666; width: 100%; text-align: center; padding: 20px;">No books found in <strong>'.htmlspecialchars($category).'</strong>.</p>';
}
?>