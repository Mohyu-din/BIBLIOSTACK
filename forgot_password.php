<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password - Bibliostack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: white; padding: 40px; border-radius: 10px; width: 350px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h3 { margin-top: 0; color: #333; }
        p { color: #666; font-size: 14px; margin-bottom: 20px; }
        
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        
        .btn-reset { width: 100%; padding: 12px; background: #333; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-reset:hover { background: #000; }
        
        .back-link { display: block; margin-top: 20px; font-size: 13px; color: #666; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }

        /* Status Messages */
        .msg-box { padding: 10px; border-radius: 5px; font-size: 14px; margin-bottom: 15px; display: none; text-align: left; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <div class="box">
        <h3>Reset Password</h3>
        <p>Enter your email address and Google will send you a secure reset link.</p>
        
        <div id="status-msg" class="msg-box"></div>

        <input type="email" id="reset-email" placeholder="Enter your email" required>
        <button onclick="sendResetLink()" class="btn-reset">Send Reset Link</button>
        
        <a href="login.php" class="back-link">← Back to Login</a>
    </div>

    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
        import { getAuth, sendPasswordResetEmail } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

        // YOUR SPECIFIC CONFIG
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

        window.sendResetLink = function() {
            const email = document.getElementById('reset-email').value;
            const btn = document.querySelector('.btn-reset');

            if (!email) {
                showMsg("Please enter your email address.", "error");
                return;
            }

            // 1. Show Loading State
            btn.innerText = "Sending...";
            btn.disabled = true;

            // 2. Ask Firebase to send the email
            sendPasswordResetEmail(auth, email)
                .then(() => {
                    // Success!
                    showMsg("✅ Reset link sent! Check your inbox.", "success");
                    btn.innerText = "Link Sent";
                })
                .catch((error) => {
                    // Error Handling
                    btn.innerText = "Send Reset Link";
                    btn.disabled = false;
                    
                    if (error.code === 'auth/user-not-found') {
                        showMsg("❌ No account found with this email.", "error");
                    } else if (error.code === 'auth/invalid-email') {
                        showMsg("❌ Invalid email format.", "error");
                    } else {
                        showMsg("❌ Error: " + error.message, "error");
                    }
                });
        };

        function showMsg(text, type) {
            const msgBox = document.getElementById('status-msg');
            msgBox.innerText = text;
            msgBox.className = "msg-box " + type; 
            msgBox.style.display = "block";
        }
    </script>
</body>
</html>