<?php
session_start();

// --- 1. SMART REDIRECT (Prevents Loops) ---
if(isset($_SESSION['user_id'])){ 
    // Normalize role to lowercase to ensure "Publisher" and "publisher" are treated the same
    $role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : 'user';

    if($role === 'admin') {
        header("Location: admin_dashboard.php");
    } elseif($role === 'publisher') {
        header("Location: publisher_dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bibliostack</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: white; padding: 40px; border-radius: 10px; width: 350px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn-login { width: 100%; padding: 12px; background: #333; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        
        .btn-google { 
            width: 100%; padding: 12px; margin-top: 10px; 
            background: white; color: #444; border: 1px solid #ddd; 
            border-radius: 5px; cursor: pointer; font-weight: bold;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: 0.2s;
        }
        .btn-google:hover { background: #f1f1f1; }
        .btn-google img { width: 20px; height: 20px; }
        .error-msg { color: red; font-size: 13px; margin-bottom: 10px; display: none; }
    </style>
</head>
<body>

    <div class="box">
        <h2>Bibliostack Login</h2>
        <p id="error-display" class="error-msg"></p>

        <input type="email" id="email" placeholder="Email" required>
        <input type="password" id="password" placeholder="Password" required>
        <button onclick="manualLogin()" class="btn-login">Login</button>

        <p style="color:#aaa; font-size:14px; margin:15px 0;">OR</p>

        <button onclick="googleLogin()" class="btn-google">
            <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="G">
            Sign in with Google
        </button>

        <br><br>
        <a href="signup.php" style="text-decoration:none; color:#4285F4;">Create an Account</a>
        <br>
        <a href="forgot_password.php" style="text-decoration:none; color:#666; font-size:12px; display:block; margin-top:10px;">Forgot Password?</a>
    </div>

    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
        import { getAuth, signInWithPopup, GoogleAuthProvider, signInWithEmailAndPassword } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

        // YOUR CONFIG
        const firebaseConfig = {
            apiKey: "AIzaSyDddM2Mob8Ykfyv3HEOnLqvstvV3YzghFY",
            authDomain: "bibliostack-6748b.firebaseapp.com",
            projectId: "bibliostack-6748b",
            storageBucket: "bibliostack-6748b.firebasestorage.app",
            messagingSenderId: "304742403910",
            appId: "1:304742403910:web:4aa7c43ac3a9cb1b739f47",
            measurementId: "G-R27HEBJCJ1"
        };

        const app = initializeApp(firebaseConfig);
        const auth = getAuth();
        const provider = new GoogleAuthProvider();

        // SHARED FUNCTION: SYNC TO PHP & REDIRECT
        function syncToBackend(user) {
            fetch("firebase_login.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    uid: user.uid,
                    email: user.email,
                    displayName: user.displayName,
                    photoURL: user.photoURL
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === "success") {
                    
                    // --- SAFE REDIRECT LOGIC (Case Insensitive) ---
                    let role = data.role ? data.role.toLowerCase().trim() : 'user';

                    if(role === 'admin') {
                        window.location.href = "admin_dashboard.php";
                    } else if (role === 'publisher') {
                         window.location.href = "publisher_dashboard.php";
                    } else {
                        window.location.href = "user_dashboard.php";
                    }
                    // --------------------------

                } else {
                    alert("System Error: " + data.msg);
                }
            });
        }

        // 1. GOOGLE LOGIN
        window.googleLogin = function() {
            signInWithPopup(auth, provider)
                .then((result) => { syncToBackend(result.user); })
                .catch((error) => { alert("Google Error: " + error.message); });
        };

        // 2. MANUAL LOGIN
        window.manualLogin = function() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorDisplay = document.getElementById('error-display');

            signInWithEmailAndPassword(auth, email, password)
                .then((userCredential) => {
                    syncToBackend(userCredential.user);
                })
                .catch((error) => {
                    if(error.code === 'auth/invalid-credential') {
                        errorDisplay.innerText = "Incorrect email or password.";
                    } else {
                        errorDisplay.innerText = error.message;
                    }
                    errorDisplay.style.display = "block";
                });
        };
    </script>
</body>
</html>