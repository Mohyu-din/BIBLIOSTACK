<!DOCTYPE html>
<html lang="en">
<head>
    <title>Sign Up - Bibliostack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .box { background: white; padding: 30px; border-radius: 10px; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #333; }
        
        input, select, textarea { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-family: inherit; font-size: 14px; }
        textarea { height: 60px; resize: none; }
        
        select { background-color: #fff; cursor: pointer; }
        
        .btn-signup { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin-top: 10px; font-size: 16px; transition: 0.2s; }
        .btn-signup:hover { background: #218838; }
        
        .error-msg { color: #dc3545; font-size: 13px; margin-bottom: 10px; display: none; background: #ffe6e6; padding: 8px; border-radius: 4px; border: 1px solid #f5c6cb; }
        
        .label-text { text-align: left; font-size: 12px; font-weight: bold; color: #666; margin-top: 10px; display: block; }
    </style>
</head>
<body>

    <div class="box">
        <h2>Join Bibliostack</h2>
        <p id="error-display" class="error-msg"></p>

        <label class="label-text">Account Details</label>
        <input type="text" id="username" placeholder="Full Name *" required>
        <input type="email" id="email" placeholder="Email Address *" required>
        <input type="password" id="password" placeholder="Create Password *" required>
        
        <label class="label-text">I want to be a:</label>
        <select id="roleSelect">
            <option value="user">Reader (Read & Bookmark)</option>
            <option value="publisher">Publisher (Publish Books)</option>
        </select>

        <label class="label-text">Profile Info (Optional)</label>
        <input type="text" id="phone" placeholder="Phone Number">
        <input type="text" id="address" placeholder="City / Address">
        <textarea id="bio" placeholder="Short Bio"></textarea>

        <button onclick="registerUser()" class="btn-signup">Create Account</button>

        <p style="margin-top:20px; font-size:14px;">
            Already have an account? <a href="login.php" style="color: #4285F4; text-decoration: none;">Login here</a>
        </p>
    </div>

    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
        import { getAuth, createUserWithEmailAndPassword, updateProfile } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

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

        window.registerUser = function() {
            const name = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const role = document.getElementById('roleSelect').value; // Get Selected Role
            
            const phone = document.getElementById('phone').value;
            const address = document.getElementById('address').value;
            const bio = document.getElementById('bio').value;
            
            const errorDisplay = document.getElementById('error-display');
            const photo = "https://ui-avatars.com/api/?name=" + encodeURIComponent(name) + "&background=random"; 

            if(!name || !email || !password) {
                errorDisplay.innerText = "Please fill in all required fields (*).";
                errorDisplay.style.display = "block";
                return;
            }

            const btn = document.querySelector('.btn-signup');
            btn.innerText = "Creating Account...";
            btn.disabled = true;

            // 1. Create in Firebase
            createUserWithEmailAndPassword(auth, email, password)
                .then((userCredential) => {
                    const user = userCredential.user;

                    // 2. Update Profile
                    updateProfile(user, { displayName: name, photoURL: photo })
                    .then(() => {
                        // 3. Send to MySQL
                        fetch("firebase_login.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({
                                uid: user.uid,
                                email: user.email,
                                displayName: name,
                                photoURL: photo,
                                role: role, // SEND ROLE
                                phone: phone,
                                bio: bio,
                                address: address
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if(data.status === "success") {
                                // Redirect based on role
                                if(role === 'publisher') {
                                    window.location.href = "publisher_dashboard.php"; 
                                } else {
                                    // UPDATED REDIRECT HERE
                                    window.location.href = "user_dashboard.php";
                                }
                            } else {
                                errorDisplay.innerText = "Database Error: " + data.msg;
                                errorDisplay.style.display = "block";
                                btn.disabled = false;
                                btn.innerText = "Create Account";
                            }
                        });
                    });
                })
                .catch((error) => {
                    if(error.code === 'auth/email-already-in-use') {
                        errorDisplay.innerText = "That email is already registered.";
                    } else if (error.code === 'auth/weak-password') {
                        errorDisplay.innerText = "Password should be at least 6 characters.";
                    } else {
                        errorDisplay.innerText = error.message;
                    }
                    errorDisplay.style.display = "block";
                    btn.disabled = false;
                    btn.innerText = "Create Account";
                });
        };
    </script>
</body>
</html>