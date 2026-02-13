<?php
// Run this file once to force-clear everything
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time()-1000);
        setcookie($name, '', time()-1000, '/');
    }
}

echo "<h1>All Cookies Deleted.</h1>";
echo "<a href='login.php'>Go back to Login</a>";
?>