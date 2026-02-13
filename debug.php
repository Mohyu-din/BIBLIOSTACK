<?php
session_start();
echo "<h1>Session Debugger</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<br><hr><br>";
echo "<a href='logout.php'>Force Logout</a>";
?>