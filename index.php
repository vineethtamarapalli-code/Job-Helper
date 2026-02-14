<?php
// Check for config.php existence to prevent errors if you haven't uploaded it
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    // Basic session start fallback if config is missing during testing
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Mock csrf_token if missing
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

// --- AJAX Handler: Check Registration Status (Email & Settings) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_register_status') {
    header('Content-Type: application/json');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Email is required.']);
        exit;
    }

    try {
        // 1. Check if email exists in database
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'This email is already registered. Please use a different email or sign in.']);
            exit;
        }

        // 2. Check Global Settings (Signups Enabled / Manual Approval)
        $signup_enabled = true; // Default
        $manual_approval = false; // Default

        try {
            // Attempt to fetch settings if the table exists
            $stmt_set = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('signup_enabled', 'manual_approval')");
            $stmt_set->execute();
            $settings = $stmt_set->fetchAll(PDO::FETCH_KEY_PAIR);

            if (isset($settings['signup_enabled']) && $settings['signup_enabled'] == '0') {
                $signup_enabled = false;
            }
            if (isset($settings['manual_approval']) && $settings['manual_approval'] == '1') {
                $manual_approval = true;
            }
        } catch (Exception $e) {
            // Settings table might not exist yet, proceed with defaults
        }

        // 3. Respond based on settings
        if (!$signup_enabled) {
            echo json_encode(['status' => 'error', 'message' => 'Signups are currently disabled by the Administrator.']);
        } elseif ($manual_approval) {
            echo json_encode(['status' => 'approval_warn', 'message' => 'Note: Registration requires Admin Approval. After verifying your email, your account will be pending activation. Do you want to proceed?']);
        } else {
            echo json_encode(['status' => 'available']);
        }

    } catch (Exception $e) {
        // If DB fails, allow proceeding so we don't block users due to technical errors
        echo json_encode(['status' => 'available']); 
    }
    exit;
}
// ------------------------------------------------------------------

$step = $_GET['step'] ?? 'login'; // 'login', 'forgot', 'reset'
$suc_code = $_GET['suc_code'] ?? '';
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Helper - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        /* Ensure nav items are visible/styled correctly */
        .nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .nav-link {
            text-decoration: none;
            color: #080808ff;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        .nav-link:hover {
            color: #e2e8f0;
        }
        
        .nav-link.about-btn {
            background-color: rgba(255, 255, 255, 0.15);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            white-space: nowrap;
        }
        .nav-link.about-btn:hover {
            background-color: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .input-group {
            display: flex;
            align-items: center;
            position: relative;
        }
        
        .input-group input {
            flex: 1; 
            width: 100%; 
            padding-right: 40px;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            cursor: pointer;
            color: #aaa;
            z-index: 10;
        }

        .mobile-nav-toggle {
            display: none !important;
        }
        
        /* OTP Section Styles */
        #otp-verification-section, #forgot-otp-section, #forgot-reset-section {
            text-align: center;
            animation: fadeIn 0.5s;
        }
        
        .otp-message {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 15px;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            margin-top: 10px;
        }

        /* Resend Button Style */
        .resend-btn {
            background: none;
            border: none;
            color: #3498db;
            text-decoration: underline;
            cursor: pointer;
            font-size: 0.9rem;
            margin-top: 10px;
            display: inline-block;
        }
        .resend-btn:hover {
            color: #2980b9;
        }
        
        /* Notification/Message Styles - Top Fixed */
        .form-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            width: 90%;
            max-width: 500px;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 0.95rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideDown 0.4s ease-out;
            text-align: left;
        }

        @keyframes slideDown {
            from { top: -100px; opacity: 0; }
            to { top: 20px; opacity: 1; }
        }

        .form-message.error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .form-message.success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .close-btn {
            cursor: pointer;
            font-size: 1.2rem;
            line-height: 1;
            opacity: 0.6;
            margin-left: 15px;
            padding: 0 5px;
        }
        .close-btn:hover {
            opacity: 1;
        }

        .loader {
            border: 3px solid #f3f3f3;
            border-radius: 50%;
            border-top: 3px solid #3498db;
            width: 20px;
            height: 20px;
            -webkit-animation: spin 1s linear infinite;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 5px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Styles for mobile toggle links */
        .mobile-text {
            display: none;
            margin-top: 15px;
            font-size: 0.9rem;
            text-align: center;
            color: #666;
        }
        .mobile-text span {
            color: #333;
            font-weight: bold;
            cursor: pointer;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            /* Remove scrollbar and prevent horizontal scroll */
            body {
                overflow-x: hidden;
            }
            ::-webkit-scrollbar {
                display: none;
            }
            * {
                -ms-overflow-style: none;  /* IE and Edge */
                scrollbar-width: none;  /* Firefox */
            }

            .header {
                padding: 15px 20px;
                position: fixed; /* Fixed position */
                top: 0;
                left: 0;
                width: 100%;
                z-index: 1000;
                /* Background color and shadow removed to maintain original transparency/look */
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .header-title { 
                font-size: 1.5rem;
                flex: 1;
            }
            .nav {
                display: flex !important;
                position: static !important;
                width: auto !important;
                height: auto !important;
                background: transparent !important;
                flex-direction: row !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
                top: auto !important;
                right: auto !important;
            }
            .nav-link.about-btn {
                padding: 6px 14px; 
                font-size: 0.85rem;
                display: inline-block !important;
            }

            /* Show mobile toggle text */
            .mobile-text {
                display: block;
            }

            /* Ensure overlay is hidden on mobile to allow direct form interaction */
            .overlay-container {
                display: none; 
            }
        }
    </style>
</head>
<body>
    
    <!-- Flash Messages (Moved to Top) -->
    <?php if ($error): ?>
        <div class="form-message error">
            <span><?php echo htmlspecialchars($error); ?></span>
            <span class="close-btn" onclick="this.parentElement.style.display='none'">&times;</span>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="form-message success">
            <span><?php echo htmlspecialchars($success); ?></span>
            <span class="close-btn" onclick="this.parentElement.style.display='none'">&times;</span>
        </div>
    <?php endif; ?>

    <header class="header">
        <h1 class="header-title">Job Helper</h1>
        <nav class="nav">
            <a href="about.php" class="nav-link about-btn">About</a>
        </nav>
    </header>

    <div class="auth-container">
        
        <?php if ($step === 'login'): ?>
            <div class="auth-box" id="authBox">
                <!-- Sign In Form -->
                <div class="form-container sign-in-container">
                    <form action="auth.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="login">
                        <h1>Sign In</h1>
                    
                        <!-- Combined Email/SUC Field -->
                        <div class="input-group">
                            <i class="fas fa-user-circle"></i>
                            <input type="text" name="login_identifier" placeholder="Email or SUC Code" required>
                        </div>

                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="loginPassword" placeholder="Password" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('loginPassword', this)"></i>
                        </div>
                        
                        <a href="index.php?step=forgot" class="forgot-link">Forgot your password?</a>
                        
                        <button type="submit" class="btn-primary">SIGN IN</button>

                        <!-- Mobile Toggle Link -->
                        <p class="mobile-text">Don't have an account? <span onclick="showSignUp()">Sign Up</span></p>
                    </form>
                </div>

                <!-- Sign Up Form -->
                <div class="form-container sign-up-container">
                    <form id="signupForm" action="auth.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="register">
                        <input type="hidden" id="is_verified" name="is_verified" value="0">
                        
                        <h1>Create Account</h1>
                        
                        <!-- Initial Signup Inputs -->
                        <div id="signup-inputs">
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="full_name" id="fullName" placeholder="Full Name" required>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-hashtag"></i>
                                <input type="text" name="suc_code" id="sucCode" placeholder="SUC Code" required>
                            </div>
                            
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" id="signupEmail" placeholder="Email" required>
                            </div>

                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" id="signupPassword" placeholder="Password" required>
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('signupPassword', this)"></i>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm Password" required>
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('confirmPassword', this)"></i>
                            </div>
                            
                            <button type="button" class="btn-primary" onclick="initiateOtpProcess()" id="signupBtn">SIGN UP</button>
                            <p id="otpError" style="color: red; font-size: 0.8rem; margin-top: 5px; display: none;"></p>
                            
                            <!-- Mobile Toggle Link -->
                            <p class="mobile-text">Already have an account? <span onclick="showSignIn()">Sign In</span></p>
                        </div>

                        <!-- OTP Verification Inputs -->
                        <div id="otp-verification-section" style="display: none;">
                            <i class="fas fa-envelope-open-text" style="font-size: 3rem; color: #4CAF50; margin-bottom: 10px;"></i>
                            <h3>Verify Email</h3>
                            <p class="otp-message">We've sent a code to <br><b id="displayEmail"></b></p>
                            
                            <div class="input-group">
                                <i class="fas fa-key"></i>
                                <input type="text" id="otpInput" name="otp_code" placeholder="Enter 6-digit OTP">
                            </div>
                            
                            <button type="button" class="btn-primary" onclick="verifyOtpAndRegister()" id="verifyBtn">VERIFY & REGISTER</button>
                            
                            <!-- Resend OTP Button -->
                            <button type="button" id="resendBtn" class="resend-btn" onclick="resendOtp()" style="display: none;">Resend OTP</button>

                            <!-- Cancel Button -->
                            <button type="button" class="btn-primary btn-secondary" onclick="backToSignup()">Cancel</button>
                            <p id="verifyError" style="color: red; font-size: 0.8rem; margin-top: 10px; display: none;"></p>
                        </div>

                    </form>
                </div>

                <!-- Overlay -->
                <div class="overlay-container">
                    <div class="overlay">
                        <div class="overlay-panel overlay-left">
                            <h1>Welcome Back!</h1>
                            <p>To keep connected with us please login with your personal info</p>
                            <button class="btn-ghost" onclick="showSignIn()">SIGN IN</button>
                        </div>
                        <div class="overlay-panel overlay-right">
                            <h1>Hello, Friend!</h1>
                            <p>Register with your personal details to use all of site features</p>
                            <button class="btn-ghost" onclick="showSignUp()">SIGN UP</button>
                        </div>
                    </div>
                </div>
            </div>
        
        <?php elseif ($step === 'forgot'): ?>
            <!-- Forgot Password Form with OTP -->
            <div class="auth-box auth-box-single">
                <form id="forgotForm" action="auth.php" method="POST" class="form-container" style="width: 100%; opacity: 1; z-index: 5; position: relative;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="reset_password">
                    
                    <h1>Forgot Password</h1>
                    
                    <!-- Stage 1: Enter SUC and Email -->
                    <div id="forgot-details-section">
                        <p style="color: #4b5563; margin-bottom: 1.5rem;">Enter your SUC Code and Email to verify identity.</p>
                        <div class="input-group">
                            <i class="fas fa-hashtag"></i>
                            <input type="text" name="suc_code" id="forgotSucCode" placeholder="SUC Code" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" id="forgotEmail" placeholder="Registered Email" required>
                        </div>
                        <button type="button" class="btn-primary" onclick="initiateForgotOtp()" id="sendForgotOtpBtn">Send OTP</button>
                        <p id="forgotError" style="color: red; font-size: 0.8rem; margin-top: 5px; display: none;"></p>
                    </div>

                    <!-- Stage 2: Verify OTP -->
                    <div id="forgot-otp-section" style="display: none;">
                        <i class="fas fa-lock" style="font-size: 2rem; color: #4CAF50; margin-bottom: 10px;"></i>
                        <p class="otp-message">Enter the OTP sent to <br><b id="forgotDisplayEmail"></b></p>
                        <div class="input-group">
                            <i class="fas fa-key"></i>
                            <input type="text" id="forgotOtpInput" placeholder="Enter 6-digit OTP">
                        </div>
                        <button type="button" class="btn-primary" onclick="verifyForgotOtp()" id="verifyForgotBtn">Verify OTP</button>
                        <button type="button" class="btn-primary btn-secondary" onclick="resetForgotFlow()">Cancel</button>
                        <p id="forgotOtpError" style="color: red; font-size: 0.8rem; margin-top: 5px; display: none;"></p>
                    </div>

                    <!-- Stage 3: Reset Password (Shown after OTP) -->
                    <div id="forgot-reset-section" style="display: none;">
                        <p style="color: #4b5563; margin-bottom: 1.5rem;">Identity Verified. Set your new password.</p>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="newPassword" placeholder="New Password" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('newPassword', this)"></i>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="confirm_password" id="confirmNewPassword" placeholder="Confirm New Password" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('confirmNewPassword', this)"></i>
                        </div>
                        <button type="submit" class="btn-primary">Reset Password</button>
                    </div>

                    <a href="index.php" class="forgot-link" style="margin-top: 1.5rem;">Back to Sign In</a>
                </form>
            </div>
        
        <?php else: ?>
            <script>window.location.href = 'index.php';</script>
        <?php endif; ?>

    </div>

    <script>
        function showSignUp() {
            document.getElementById('authBox').classList.add('right-panel-active');
        }

        function showSignIn() {
            document.getElementById('authBox').classList.remove('right-panel-active');
        }

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Updated Function: Displays all messages as floating "Toast" notifications at the top
        function showTemporaryMessage(elementId, message, duration = 5000) {
            // Note: 'elementId' is ignored in favor of a global top-level notification
            
            // 1. Remove ANY existing messages (PHP or JS) to prevent overlap/clutter
            const existingMessages = document.querySelectorAll('.form-message');
            existingMessages.forEach(el => el.remove());

            // 2. Create the new message container
            const div = document.createElement('div');
            
            // Determine if success or error based on message content (heuristic)
            const isSuccess = message.toLowerCase().includes('success');
            div.className = 'form-message ' + (isSuccess ? 'success' : 'error');
            div.style.display = 'flex'; // Ensure flex layout matches PHP rendered ones
            
            // 3. Set content
            div.innerHTML = `
                <span>${message}</span>
                <span class="close-btn" onclick="this.parentElement.remove()">&times;</span>
            `;
            
            // 4. Append to body
            document.body.appendChild(div);
            
            // 5. Auto-remove after duration
            setTimeout(() => {
                if (div.parentNode) {
                    div.remove();
                }
            }, duration);
        }

        let otpAttempts = 0;
        const MAX_ATTEMPTS = 2; 

        function initiateOtpProcess() {
            const name = document.getElementById('fullName').value;
            const suc = document.getElementById('sucCode').value;
            const email = document.getElementById('signupEmail').value;
            const pass = document.getElementById('signupPassword').value;
            const confirm = document.getElementById('confirmPassword').value;
            const btn = document.getElementById('signupBtn');
            
            if(!name || !suc || !email || !pass || !confirm) {
                showTemporaryMessage('otpError', "Please fill in all fields.");
                return;
            }
            if(pass !== confirm) {
                showTemporaryMessage('otpError', "Passwords do not match.");
                return;
            }

            // --- CHECK REGISTRATION STATUS FIRST (EMAIL & SETTINGS) ---
            btn.disabled = true;
            btn.innerHTML = '<div class="loader"></div> Checking...';
            // document.getElementById('otpError').style.display = 'none'; // No longer needed with global message

            const checkData = new FormData();
            checkData.append('action', 'check_register_status');
            checkData.append('email', email);

            // Trigger the backend check
            fetch('index.php', {
                method: 'POST',
                body: checkData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'error') {
                    // Blocked (Email exists OR Signups disabled)
                    showTemporaryMessage('otpError', data.message);
                    btn.disabled = false;
                    btn.innerHTML = 'SIGN UP';
                } else if (data.status === 'approval_warn') {
                    // Warn about Approval Requirement
                    btn.innerHTML = 'SIGN UP'; // Reset temporarily
                    if (confirm(data.message)) {
                         // User accepted the warning, proceed to OTP
                         startSendingOtp(email, suc, btn);
                    } else {
                         // User cancelled
                         btn.disabled = false;
                    }
                } else {
                    // All good (Available & No approval needed), PROCEED
                    startSendingOtp(email, suc, btn);
                }
            })
            .catch(error => {
                console.error("Error checking registration:", error);
                // Fallback: If check fails (e.g., network), assume safe to proceed
                startSendingOtp(email, suc, btn);
            });
        }

        // Helper to perform the actual OTP sending
        function startSendingOtp(email, suc, btn) {
            btn.disabled = true;
            btn.innerHTML = '<div class="loader"></div> Sending OTP...';
            otpAttempts = 0;
            document.getElementById('resendBtn').style.display = 'none';
            sendOtpAjax(email, 'signup', suc); 
        }

        function sendOtpAjax(email, context, sucCode = '') {
            const formData = new FormData();
            formData.append('email', email);
            formData.append('type', context); // Add type: 'signup' or 'forgot'
            formData.append('suc_code', sucCode); // Add SUC Code for verification

            const btnId = context === 'signup' ? 'signupBtn' : 'sendForgotOtpBtn';
            const errorId = context === 'signup' ? 'otpError' : 'forgotError';
            const btn = document.getElementById(btnId);

            fetch('send_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try { return JSON.parse(text); } catch (e) { throw new Error("Server Error: " + text); }
            })
            .then(data => {
                if(data.status === 'success') {
                    if (context === 'signup') {
                        document.getElementById('signup-inputs').style.display = 'none';
                        document.getElementById('otp-verification-section').style.display = 'block';
                        document.getElementById('displayEmail').innerText = email;
                    } else {
                        document.getElementById('forgot-details-section').style.display = 'none';
                        document.getElementById('forgot-otp-section').style.display = 'block';
                        document.getElementById('forgotDisplayEmail').innerText = email;
                    }
                } else {
                    showTemporaryMessage(errorId, data.message);
                }
            })
            .catch(error => {
                showTemporaryMessage(errorId, error.message || "Error sending OTP.");
            })
            .finally(() => {
                if(btn) {
                    btn.disabled = false;
                    btn.innerHTML = context === 'signup' ? 'SIGN UP' : 'Send OTP';
                }
            });
        }

        function resendOtp() {
            const email = document.getElementById('signupEmail').value;
            const suc = document.getElementById('sucCode').value; // Get SUC for resend verification
            const btn = document.getElementById('resendBtn');
            btn.innerText = "Sending...";
            
            const formData = new FormData();
            formData.append('email', email);
            formData.append('type', 'signup');
            formData.append('suc_code', suc);

            fetch('send_otp.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    showTemporaryMessage('verifyError', "OTP Resent Successfully!");
                } else {
                    showTemporaryMessage('verifyError', data.message);
                }
            })
            .catch(err => showTemporaryMessage('verifyError', "Failed to resend."))
            .finally(() => btn.innerText = "Resend OTP");
        }

        function verifyOtpAndRegister() {
            const otp = document.getElementById('otpInput').value;
            const email = document.getElementById('signupEmail').value;
            const btn = document.getElementById('verifyBtn');

            if(!otp) {
                showTemporaryMessage('verifyError', "Please enter the OTP.");
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<div class="loader"></div> Verifying...';

            const formData = new FormData();
            formData.append('email', email);
            formData.append('otp', otp);

            fetch('verify.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try { return JSON.parse(text); } catch (e) { throw new Error("Server Error: " + text); }
            })
            .then(data => {
                if(data.status === 'success') {
                    document.getElementById('is_verified').value = "1";
                    document.getElementById('signupForm').submit();
                } else {
                    otpAttempts++;
                    showTemporaryMessage('verifyError', data.message);
                    btn.disabled = false;
                    btn.innerHTML = 'VERIFY & REGISTER';

                    if (otpAttempts === 1) {
                        document.getElementById('resendBtn').style.display = 'inline-block';
                    }

                    if (otpAttempts >= MAX_ATTEMPTS) {
                        alert("Verification failed multiple times. Registration cancelled.");
                        backToSignup(); 
                        document.getElementById('signupForm').reset();
                        otpAttempts = 0; 
                    }
                }
            })
            .catch(error => {
                showTemporaryMessage('verifyError', error.message || "An error occurred.");
                btn.disabled = false;
                btn.innerHTML = 'VERIFY & REGISTER';
            });
        }

        function backToSignup() {
            document.getElementById('signup-inputs').style.display = 'block';
            document.getElementById('otp-verification-section').style.display = 'none';
            document.getElementById('otpInput').value = '';
        }

        // --- FORGOT PASSWORD LOGIC ---
        function initiateForgotOtp() {
            const suc = document.getElementById('forgotSucCode').value;
            const email = document.getElementById('forgotEmail').value;
            
            if(!suc || !email) {
                showTemporaryMessage('forgotError', "Please enter both SUC Code and Email.");
                return;
            }

            const btn = document.getElementById('sendForgotOtpBtn');
            btn.disabled = true;
            btn.innerHTML = '<div class="loader"></div> Sending...';
            
            // Pass 'forgot' type and SUC code for validation
            sendOtpAjax(email, 'forgot', suc);
        }

        function verifyForgotOtp() {
            const otp = document.getElementById('forgotOtpInput').value;
            const email = document.getElementById('forgotEmail').value;
            const btn = document.getElementById('verifyForgotBtn');

            if(!otp) {
                showTemporaryMessage('forgotOtpError', "Please enter the OTP.");
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<div class="loader"></div> Verifying...';

            const formData = new FormData();
            formData.append('email', email);
            formData.append('otp', otp);

            fetch('verify.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    document.getElementById('forgot-otp-section').style.display = 'none';
                    document.getElementById('forgot-reset-section').style.display = 'block';
                } else {
                    showTemporaryMessage('forgotOtpError', "Invalid OTP. Please try again.");
                    btn.disabled = false;
                    btn.innerHTML = 'Verify OTP';
                }
            })
            .catch(err => {
                showTemporaryMessage('forgotOtpError', "Verification error.");
                btn.disabled = false;
                btn.innerHTML = 'Verify OTP';
            });
        }

        function resetForgotFlow() {
            document.getElementById('forgot-details-section').style.display = 'block';
            document.getElementById('forgot-otp-section').style.display = 'none';
            document.getElementById('forgot-reset-section').style.display = 'none';
            document.getElementById('forgotOtpInput').value = '';
        }

        window.addEventListener('load', function() {
            const flashMessages = document.querySelectorAll('.form-message');
            if (flashMessages.length > 0) {
                setTimeout(() => {
                    flashMessages.forEach(msg => msg.style.display = 'none');
                }, 5000); 
            }
        });
    </script>
</body>
</html>