<?php
session_start();
session_unset();
session_destroy();
echo "<h1>Session Cleared.</h1> <a href='login.php'>Click here to Log In again</a>";
?>