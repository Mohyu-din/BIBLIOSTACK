<?php
session_start();

// 1. Unset all session values
$_SESSION = array();

// 2. Delete the session cookie
// This forces the browser to forget the Session ID
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session
session_destroy();

// 4. Redirect to Login
header("Location: login.php");
exit;
?>