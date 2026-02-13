<?php
include 'db_connect.php';

if (isset($_POST['action']) && isset($_POST['book_id'])) {
    $id = (int)$_POST['book_id'];
    $action = $_POST['action'];

    // --- HANDLE LIKE ---
    if ($action === 'like') {
        $sql = "UPDATE books SET likes = likes + 1 WHERE id = $id";
        if ($conn->query($sql)) {
            echo "Like success";
        } else {
            echo "Error";
        }
    } 
    
    // --- HANDLE RATING ---
    elseif ($action === 'rate' && isset($_POST['stars'])) {
        $stars = (int)$_POST['stars'];
        
        // Ensure valid 1-5 star input
        if ($stars >= 1 && $stars <= 5) {
            // 1. Add new rating to the total sum and count
            $conn->query("UPDATE books SET rating_sum = rating_sum + $stars, rating_count = rating_count + 1 WHERE id = $id");

            // 2. Calculate the new average
            $conn->query("UPDATE books SET rating = (rating_sum / rating_count) WHERE id = $id");

            // 3. Return the new average so the UI updates instantly
            $result = $conn->query("SELECT rating FROM books WHERE id = $id");
            $row = $result->fetch_assoc();
            echo number_format($row['rating'], 1); // Returns e.g., "4.5"
        }
    }
}
?>