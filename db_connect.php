<?php
$servername = "localhost";
$username = "root";
$password = "";

// UPDATE THIS LINE TO YOUR NEW NAME:
$dbname = "BiblioStack"; 

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>