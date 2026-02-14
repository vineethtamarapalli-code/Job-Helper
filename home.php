<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// --- DEVICE LOCK LOGIC START ---

// 1. Ensure Device ID Cookie exists (Uniquely identifies this browser)
if (!isset($_COOKIE['device_id'])) {
    // Generate a long-lasting random ID for this device
    $device_id = bin2hex(random_bytes(16));
    setcookie('device_id', $device_id, time() + (86400 * 365), "/"); // Expires in 1 year
    $_COOKIE['device_id'] = $device_id;
} else {
    $device_id = $_COOKIE['device_id'];
}

// 2. Check/Create 'lock_token' column in users table (Lazy Migration)
// This ensures the database supports the lock feature without manual SQL changes
try {
    $conn->query("SELECT lock_token FROM users LIMIT 1");
} catch (Exception $e) {
    // Column doesn't exist, create it
    try {
        $conn->exec("ALTER TABLE users ADD COLUMN lock_token VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $ex) {
        // Handle error gracefully if needed
    }
}

// 3. Handle Lock/Unlock AJAX Request (For users who HAVE access)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_lock') {
    $stmt_lock = $conn->prepare("SELECT lock_token FROM users WHERE id = ?");
    $stmt_lock->execute([$user_id]);
    $current_token = $stmt_lock->fetchColumn();

    header('Content-Type: application/json');
    
    if ($current_token && $current_token === $device_id) {
        // Currently locked by me -> Unlock it
        $conn->prepare("UPDATE users SET lock_token = NULL WHERE id = ?")->execute([$user_id]);
        echo json_encode(['status' => 'unlocked']);
    } elseif (!$current_token) {
        // Not locked -> Lock to this device
        $conn->prepare("UPDATE users SET lock_token = ? WHERE id = ?")->execute([$device_id, $user_id]);
        echo json_encode(['status' => 'locked']);
    } else {
        // Locked by someone else
        echo json_encode(['status' => 'error', 'message' => 'Account is locked by another device.']);
    }
    exit;
}

// 4. Fetch Current Lock Status for Page Load
$stmt_check = $conn->prepare("SELECT lock_token FROM users WHERE id = ?");
$stmt_check->execute([$user_id]);
$user_lock_token = $stmt_check->fetchColumn();

// 5. ENFORCEMENT: If locked to another device, BLOCK ACCESS
if ($user_lock_token && $user_lock_token !== $device_id) {

    // --- EMERGENCY UNLOCK HANDLER (Final Step after Verification) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_action']) && $_POST['unlock_action'] === 'confirm_unlock') {
        header('Content-Type: application/json');
        
        // At this point, the client has verified the OTP via verify.php
        // We proceed to remove the lock for this authenticated user.
        $conn->prepare("UPDATE users SET lock_token = NULL WHERE id = ?")->execute([$user_id]);
        
        echo json_encode(['status' => 'success']);
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Locked</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body { display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #1e293b; }
            .lock-screen { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); text-align: center; max-width: 400px; width: 90%; transition: all 0.3s ease; }
            .lock-icon { font-size: 60px; color: #ef4444; margin-bottom: 20px; animation: shake 0.5s ease-in-out; }
            h1 { font-size: 24px; margin-bottom: 10px; }
            p { color: #64748b; line-height: 1.5; margin-bottom: 25px; }
            .btn { display: inline-block; padding: 12px 24px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; transition: background 0.2s; cursor: pointer; border: none; font-size: 1rem; width: 100%; box-sizing: border-box; }
            .btn:hover { background-color: #2563eb; }
            .btn-outline { background-color: transparent; border: 1px solid #cbd5e1; color: #64748b; margin-top: 10px; }
            .btn-outline:hover { background-color: #f8fafc; color: #334155; }
            .btn-link { background: none; border: none; color: #3b82f6; text-decoration: underline; cursor: pointer; font-size: 0.9rem; margin-top: 15px; }
            
            .input-group { margin-bottom: 15px; text-align: left; }
            .input-group label { display: block; margin-bottom: 5px; font-size: 0.9rem; font-weight: 600; color: #475569; }
            .input-group input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
            
            .hidden { display: none; }
            .loader { border: 3px solid #f3f3f3; border-radius: 50%; border-top: 3px solid #3b82f6; width: 20px; height: 20px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
            @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <div class="lock-screen" id="mainScreen">
            <i class="fas fa-lock lock-icon"></i>
            <h1>Access Denied</h1>
            <p>This account is currently <strong>locked</strong> to another device.</p>
            <p>Please unlock it from the original device or verify your identity to unlock it here.</p>
            
            <button onclick="showUnlockFlow()" class="btn">Unlock via Email</button>
            <a href="auth.php?action=logout" class="btn btn-outline" style="display:block; text-align:center; text-decoration:none;">Logout</a>
        </div>

        <!-- Unlock Flow Section -->
        <div class="lock-screen hidden" id="unlockScreen">
            <i class="fas fa-user-shield lock-icon" style="color: #3b82f6;"></i>
            <h1>Verify Identity</h1>
            <p id="unlockMsg">Enter your registered email address to receive an unlock code.</p>

            <div id="emailStep">
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" id="unlockEmail" placeholder="e.g. user@example.com">
                </div>
                <button onclick="sendUnlockOtp()" class="btn" id="sendBtn">Send OTP</button>
            </div>

            <div id="otpStep" class="hidden">
                <div class="input-group">
                    <label>Enter 6-Digit OTP</label>
                    <input type="text" id="unlockOtp" placeholder="123456" maxlength="6" style="letter-spacing: 5px; text-align: center; font-size: 1.2rem;">
                </div>
                <button onclick="verifyUnlockOtp()" class="btn" id="verifyBtn">Verify & Unlock</button>
            </div>

            <button onclick="cancelUnlock()" class="btn-link">Cancel</button>
        </div>

        <script>
            function showUnlockFlow() {
                document.getElementById('mainScreen').classList.add('hidden');
                document.getElementById('unlockScreen').classList.remove('hidden');
            }

            function cancelUnlock() {
                document.getElementById('unlockScreen').classList.add('hidden');
                document.getElementById('mainScreen').classList.remove('hidden');
                document.getElementById('emailStep').classList.remove('hidden');
                document.getElementById('otpStep').classList.add('hidden');
                document.getElementById('unlockEmail').value = '';
                document.getElementById('unlockOtp').value = '';
                document.getElementById('unlockMsg').textContent = 'Enter your registered email address to receive an unlock code.';
            }

            function sendUnlockOtp() {
                const email = document.getElementById('unlockEmail').value.trim();
                const btn = document.getElementById('sendBtn');
                
                if (!email) {
                    alert('Please enter your email.');
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<div class="loader"></div> Sending...';

                // Connecting to send_otp.php similar to index.php
                const formData = new FormData();
                formData.append('email', email);
                formData.append('type', 'unlock'); // Requesting OTP for unlock

                fetch('send_otp.php', { 
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(text => {
                    try { return JSON.parse(text); } catch (e) { throw new Error("Server Error: " + text); }
                })
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('emailStep').classList.add('hidden');
                        document.getElementById('otpStep').classList.remove('hidden');
                        document.getElementById('unlockMsg').textContent = 'We sent a code to ' + email + '. Enter it below.';
                    } else {
                        alert(data.message || 'Error sending OTP.');
                        btn.disabled = false;
                        btn.textContent = 'Send OTP';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Connection error or send_otp.php is missing.');
                    btn.disabled = false;
                    btn.textContent = 'Send OTP';
                });
            }

            function verifyUnlockOtp() {
                const otp = document.getElementById('unlockOtp').value.trim();
                const email = document.getElementById('unlockEmail').value.trim();
                const btn = document.getElementById('verifyBtn');
                
                if (!otp) {
                    alert('Please enter the OTP.');
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<div class="loader"></div> Verifying...';

                // Connecting to verify.php similar to index.php
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
                    if (data.status === 'success') {
                        // OTP Verified successfully. Now perform the Unlock on home.php
                        const unlockData = new FormData();
                        unlockData.append('unlock_action', 'confirm_unlock');
                        
                        fetch('home.php', {
                            method: 'POST',
                            body: unlockData
                        })
                        .then(r => r.json())
                        .then(d => {
                            if (d.status === 'success') {
                                btn.style.backgroundColor = '#10b981';
                                btn.textContent = 'Unlocked!';
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1000);
                            } else {
                                alert(d.message || 'Unlock failed.');
                                btn.disabled = false;
                                btn.textContent = 'Verify & Unlock';
                            }
                        });
                    } else {
                        alert(data.message || 'Invalid OTP.');
                        btn.disabled = false;
                        btn.textContent = 'Verify & Unlock';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Connection error or verify.php is missing.');
                    btn.disabled = false;
                    btn.textContent = 'Verify & Unlock';
                });
            }
        </script>
    </body>
    </html>
    <?php
    exit(); // Stop loading the rest of the page
}

// --- DEVICE LOCK LOGIC END ---


// --- SECURITY FIX: Generate CSRF Token ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 1. CHECK IF SUGGESTIONS ARE ENABLED ---
$stmt_settings = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'suggestions_enabled'");
$stmt_settings->execute();
$sugg_val = $stmt_settings->fetchColumn();
$suggestions_enabled = ($sugg_val === false || $sugg_val == '1'); 

// Fetch user files
$stmt_files = $conn->prepare("SELECT * FROM files WHERE user_id = ? ORDER BY upload_date DESC, id DESC");
$stmt_files->execute([$user_id]);
$files = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

// Fetch user shortcuts
$stmt_shortcuts = $conn->prepare("SELECT * FROM shortcuts WHERE user_id = ? ORDER BY created_at DESC");
$stmt_shortcuts->execute([$user_id]);
$shortcuts = $stmt_shortcuts->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch Favorite Folders and their items ---
$stmt_folders = $conn->prepare("SELECT * FROM favorites_folders WHERE user_id = ? ORDER BY folder_name ASC");
$stmt_folders->execute([$user_id]);
$favorite_folders = $stmt_folders->fetchAll(PDO::FETCH_ASSOC);

$favorites_data = [];
foreach ($favorite_folders as $folder) {
    $stmt_items = $conn->prepare("
        SELECT 
            fi.id as favorite_item_id, 'file' as type, 
            f.id, f.filename, f.original_name, f.file_type, f.upload_date, f.display_name, 
            NULL as name, NULL as url, NULL as favicon
         FROM favorite_items fi
        JOIN files f ON fi.file_id = f.id
        WHERE fi.folder_id = ? AND fi.user_id = ? AND fi.file_id IS NOT NULL
        
        UNION ALL
        
        SELECT 
            fi.id as favorite_item_id, 
            'shortcut' as type, 
            s.id, 
            NULL as filename, 
            NULL as original_name,
            NULL as file_type, 
            s.created_at as upload_date, 
            s.name as display_name, 
            s.name, 
            s.url, 
            s.favicon
        FROM favorite_items fi
        JOIN shortcuts s ON fi.shortcut_id = s.id
        WHERE fi.folder_id = ? AND fi.user_id = ? AND fi.shortcut_id IS NOT NULL
    ");
    $stmt_items->execute([$folder['id'], $user_id, $folder['id'], $user_id]);
    $favorites_data[$folder['id']] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
}

// --- Fetch Messages for Inbox ---
$stmt_msgs = $conn->prepare("SELECT * FROM messages WHERE receiver_id = ? OR type = 'broadcast' ORDER BY created_at DESC");
$stmt_msgs->execute([$user_id]);
$messages = $stmt_msgs->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch Placements (SAFE MODE) ---
$jobs = [];
try {
    $stmt_jobs = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC");
    if ($stmt_jobs !== false) {
        $jobs = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);
    } else {
         $conn->exec("CREATE TABLE IF NOT EXISTS jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            company_name VARCHAR(255) NOT NULL,
            job_url VARCHAR(500),
            description TEXT,
            document_path VARCHAR(255),
            apply_date DATE,
            end_date DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
} catch (PDOException $e) {
    // Suppress error
}

// --- Calculate Unread Count ---
$unread_count = 0;
foreach ($messages as $msg) {
    if ($msg['type'] === 'personal' && $msg['is_read'] == 0) {
        $unread_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Helper - Home</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        /* --- GLOBAL RESET FOR SIDE SCROLL ISSUE --- */
        html, body {
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
        }
        
        * {
            box-sizing: border-box;
        }

        /* Improved Header Layout */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 2rem;
            position: fixed; /* Changed to fixed */
            top: 0;
            left: 0;
            z-index: 100;
            width: 100%; /* Ensure header doesn't overflow */
        }
        
        /* Compensation for fixed header */
        .main-content {
            padding-top: 90px;
        }
        
        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        @media (max-width: 768px) {
            .header {
                padding: 12px 16px;
            }
            .header-title {
                font-size: 1.3rem;
            }
        }

        /* --- INTERNAL SCROLL ENABLED --- */
        .scrollable-area {
            max-height: 65vh; /* Sets the scrollable height */
            overflow-y: auto; /* Enables vertical scrolling */
            padding-right: 5px; /* Spacing for scrollbar */
            padding-bottom: 20px;
            
            /* Custom Scrollbar Styling */
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }

        /* Webkit Scrollbar Styling (Chrome/Safari) */
        .scrollable-area::-webkit-scrollbar { width: 6px; }
        .scrollable-area::-webkit-scrollbar-track { background: transparent; }
        .scrollable-area::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
        .scrollable-area::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }

        /* Internal Scrolling for Favorite Folders - Kept small for organization */
        .folder-scroll-container {
            max-height: 320px; 
            overflow-y: auto;
            padding-right: 5px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        .folder-scroll-container::-webkit-scrollbar { width: 6px; }
        .folder-scroll-container::-webkit-scrollbar-track { background: transparent; }
        .folder-scroll-container::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
        .folder-scroll-container::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }

        /* --- Message Box Styles --- */
        .msg-container { display: flex; gap: 20px; height: 400px; }
        .msg-inbox { flex: 2; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
        .msg-compose { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; }
        .msg-header { background: #f1f5f9; padding: 15px; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
        .msg-list { flex: 1; overflow-y: auto; padding: 15px; }
        .msg-item { background: #f8fafc; padding: 12px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #3b82f6; position: relative; }
        .msg-item.broadcast { border-left-color: #a855f7; }
        .msg-meta { font-size: 0.75rem; color: #64748b; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center; }
        .msg-body { font-size: 0.9rem; color: #334155; white-space: pre-wrap; margin-top: 5px; }
        .msg-compose textarea { width: 100%; flex: 1; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 15px; resize: none; font-family: inherit; }
        .msg-compose textarea:focus { outline: 2px solid #3b82f6; border-color: transparent; }
        
        .suggestions-disabled { text-align: center; padding-top: 40px; color: #94a3b8; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; }
        
        .fade-out { animation: fadeOut 1s forwards; }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; visibility: hidden; } }

        .notification-alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: opacity 0.5s ease-out; animation: slideIn 0.3s ease-out; }
        .notification-alert.success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .notification-alert.error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .notification-alert button { background: none; border: none; cursor: pointer; color: inherit; opacity: 0.6; font-size: 1.1rem; padding: 0 0.5rem; }
        .notification-alert button:hover { opacity: 1; }
        
        @keyframes slideIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .btn-delete-msg { background: none; border: none; color: #ef4444; cursor: pointer; margin-left: 10px; font-size: 1rem; padding: 2px 5px; transition: color 0.2s; }
        .btn-delete-msg:hover { color: #b91c1c; }

        /* Job Card Styles */
        .job-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .job-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; }
        .job-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: #cbd5e1; }
        .job-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .job-title { font-size: 1.1rem; font-weight: 600; color: #1e293b; margin: 0; }
        .job-company { font-size: 0.9rem; color: #64748b; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
        .job-body { font-size: 0.9rem; color: #334155; margin-bottom: 15px; flex-grow: 1; white-space: pre-wrap; }
        .job-footer { margin-top: auto; display: flex; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 12px; }
        .job-btn { flex: 1; text-align: center; padding: 8px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; transition: background-color 0.2s; }
        .job-btn-primary { background-color: #3b82f6; color: white; }
        .job-btn-primary:hover { background-color: #2563eb; }
        .job-btn-outline { background-color: white; border: 1px solid #cbd5e1; color: #475569; }
        .job-btn-outline:hover { background-color: #f8fafc; border-color: #94a3b8; }
        
        @media (max-width: 768px) { 
            .msg-container { flex-direction: column; height: auto; } 
            .msg-inbox, .msg-compose { height: 300px; } 
        }

        .vocalize-btn {
            position: absolute;
            top: 10px;
            right: 45px;
            background-color: #6366f1;
            color: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            text-decoration: none;
            z-index: 10;
        }
        .vocalize-btn:hover { background-color: #4f46e5; transform: scale(1.1); color: white; }

        .nav-link.placements-link { position: relative; }
        .nav-link.placements-link .notification-badge {
            position: absolute;
            top: 2px;
            right: -8px;
            background-color: #ef4444;
            color: white;
            border-radius: 10px;
            font-size: 0.7rem;
            padding: 1px 5px;
            font-weight: bold;
            border: 1px solid white;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .tab-btn .notification-badge {
            background-color: #ff0000;
            color: white;
            border-radius: 10px;
            font-size: 0.7rem;
            padding: 1px 5px;
            font-weight: bold;
            margin-left: 5px;
            vertical-align: top;
        }

        .btn-logo.dragover {
            background-color: #dbeafe !important;
            color: #3b82f6 !important;
            border: 2px dashed #3b82f6 !important;
            transform: scale(1.1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            animation: bounce 0.5s infinite alternate; 
        }
        
        .btn-logo > * { pointer-events: none; }

        @keyframes bounce { from { transform: scale(1.1); } to { transform: scale(1.15); } }
        
        #dropZone {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 9999; 
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 8px dashed #3b82f6; 
            border-radius: 20px;
            margin: 15px; 
            width: calc(100% - 30px); height: calc(100% - 30px);
            box-sizing: border-box; 
            align-items: center; justify-content: center; flex-direction: column;
            transition: all 0.3s ease;
        }

        #dropZone.active { display: flex; animation: pulseFade 0.3s ease-out; }
        
        #dropZone i {
            font-size: 6rem;
            color: #3b82f6;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 4px 6px rgba(59, 130, 246, 0.3));
            animation: bounceIcon 1s infinite alternate;
        }
        
        #dropZone p {
            font-size: 1.8rem;
            color: #1e293b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(255,255,255,0.8);
        }
        
        #dropZone * { pointer-events: none; }

        .content-card .card-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            display: block;
        }
        
        .content-card .card-subtitle {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            display: block;
        }

        .tab-btn i { font-size: 1.2rem; margin-right: 0; pointer-events: none; }
        
        .tab-navigation {
            display: flex;
            flex-wrap: nowrap; 
            overflow-x: auto;  
            scrollbar-width: none; 
            -ms-overflow-style: none; 
        }
        .tab-navigation::-webkit-scrollbar { display: none; }

        .tab-btn {
            padding: 10px 20px; 
            min-width: 60px;    
            display: inline-flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0; 
        }
        
        @media (max-width: 600px) {
            .tab-btn { padding: 10px 10px; min-width: 45px; }
        }
        
        @keyframes pulseFade { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        
        @keyframes bounceIcon { from { transform: translateY(0); } to { transform: translateY(-15px); } }

        /* Lock Button Styles */
        .lock-btn {
            color: #64748b;
            cursor: pointer;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
        }
        .lock-btn:hover { color: #3b82f6; }
        .lock-btn.locked { color: #ef4444; }
        .lock-btn.unlocked { color: #10b981; }

        /* --- MOBILE LOCK BUTTON STYLES --- */
        .mobile-lock-btn {
            display: none; /* Hidden on desktop */
            margin-right: 1.5rem; /* Space between lock and hamburger */
            font-size: 1.3rem;
            color: #64748b;
            cursor: pointer;
            transition: color 0.3s ease;
            text-decoration: none;
        }
        .mobile-lock-btn.locked { color: #ef4444; }
        .mobile-lock-btn.unlocked { color: #10b981; }

        @media (max-width: 768px) {
            .mobile-lock-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            /* Hide the duplicate lock inside the nav menu on mobile */
            .nav .lock-btn {
                display: none !important;
            }
        }

    </style>
</head>
<body>
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <header class="header">
        <h1 class="header-title">Job Helper</h1>
        
        <!-- MOBILE LOCK ICON (Visible on Mobile Only, Outside Nav) -->
        <a href="#" class="mobile-lock-btn device-lock-trigger <?php echo ($user_lock_token && $user_lock_token === $device_id) ? 'locked' : 'unlocked'; ?>" 
           title="<?php echo ($user_lock_token) ? 'Unlock Account' : 'Lock to this device'; ?>"
           onclick="toggleDeviceLock(event)">
            <i class="fas <?php echo ($user_lock_token && $user_lock_token === $device_id) ? 'fa-lock' : 'fa-lock-open'; ?>"></i>
        </a>

        <nav class="nav">
            <a href="home.php" class="nav-link active">Home</a>
            
            <a href="profile.php" class="nav-link">Profile</a>
            <a href="about.php" class="nav-link about-btn">About</a>

            <!-- DEVICE LOCK TOGGLE (Desktop/Inside Menu) -->
            <!-- Added 'device-lock-trigger' class for syncing -->
            <a href="#" class="nav-link lock-btn device-lock-trigger <?php echo ($user_lock_token && $user_lock_token === $device_id) ? 'locked' : 'unlocked'; ?>" 
               id="deviceLockBtn" 
               title="<?php echo ($user_lock_token) ? 'Unlock Account' : 'Lock to this device'; ?>"
               onclick="toggleDeviceLock(event)">
                <i class="fas <?php echo ($user_lock_token && $user_lock_token === $device_id) ? 'fa-lock' : 'fa-lock-open'; ?>"></i>
                <span class="lock-label" style="margin-left: 5px; font-weight: 500;"><?php echo ($user_lock_token && $user_lock_token === $device_id) ? 'Locked' : 'Lock'; ?></span>
            </a>

            <a href="auth.php?action=logout" class="nav-link logout" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </nav>
         <button class="mobile-nav-toggle" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <main class="main-content">
        <div class="container">
            <div id="alert-container">
                <?php if(isset($_GET['msg_success'])): ?>
                    <div class="notification-alert success auto-dismiss">
                        <span><i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($_GET['msg_success']); ?></span>
                        <button onclick="this.parentElement.style.display='none'"><i class="fas fa-times"></i></button>
                    </div>
                <?php endif; ?>
                <?php if(isset($_GET['msg_error'])): ?>
                    <div class="notification-alert error auto-dismiss">
                        <span><i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($_GET['msg_error']); ?></span>
                        <button onclick="this.parentElement.style.display='none'"><i class="fas fa-times"></i></button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="page-header">
                <h2>Dashboard</h2>
                <p>Manage your files, placements, and URL shortcuts</p>
            </div>
            
            <div class="top-controls">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="globalSearchInput" onkeyup="filterContent()" placeholder="Search files, placements and shortcuts...">
                </div>
                <div class="main-actions">
                    <a href="editor.php" class="btn-logo" title="Create Document" style="text-decoration:none;">
                        <i class="fas fa-file-word"></i>
                    </a>
                    
                    <button class="btn-logo" title="New Folder" onclick="showCreateFolderModal()">
                        <i class="fas fa-folder-plus"></i>
                    </button>
                    <button class="btn-logo" title="Add URL" onclick="showAddModal()">
                        <i class="fas fa-plus"></i>
                    </button>
                    <!-- ADDED ID TO BUTTON FOR DRAG AND DROP -->
                    <button id="uploadFileBtn" class="btn-logo" title="Upload File" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-upload"></i>
                    </button>
                    <form action="file_handler.php" method="POST" enctype="multipart/form-data" id="uploadForm" style="display:none;">
                        <input type="file" name="file" id="fileInput" onchange="showFileNameModal()">
                        <input type="hidden" name="file_name" id="fileNameInput">
                    </form>
                </div>
            </div>

            <div id="dropZone" class="drop-zone">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Release to Upload File</p>
            </div>

            <div class="tab-navigation">
                <button class="tab-btn active" onclick="openTab(event, 'all')" title="All">
                    <i class="fas fa-th"></i>
                </button>
                
                <button class="tab-btn" onclick="openTab(event, 'files')" title="Files">
                    <i class="fas fa-file-alt"></i>
                </button>
                <button class="tab-btn" onclick="openTab(event, 'shortcuts')" title="Shortcuts">
                    <i class="fas fa-link"></i>
                </button>
                <button class="tab-btn" onclick="openTab(event, 'favorites')" title="Favorites">
                    <i class="fas fa-star"></i>
                </button>
                
                <button class="tab-btn" onclick="openTab(event, 'messages')" title="Messages">
                    <i class="fas fa-envelope"></i>
                    <span id="msg-badge" style="background:red; color:white; border-radius:50%; padding:0 4px; font-size:10px; vertical-align:top; display: <?php echo $unread_count > 0 ? 'inline-block' : 'none'; ?>;">
                        <?php echo $unread_count; ?>
                    </span>
                </button>
                 <a href="#" onclick="openTab(event, 'jobs_tab'); return false;" class="tab-btn" id="nav-placements-tab" title="Placements" style="text-decoration: none !important; border-bottom: none !important;">
                    <i class="fas fa-briefcase"></i>
                    <span class="notification-badge" id="placements-badge-tab" style="display:none;">0</span>
                </a>
            </div>

            <!-- Tab: ALL -->
            <div id="all" class="tab-pane active">
                <div class="content-grid scrollable-area">
                    <?php if (count($files) === 0 && count($shortcuts) === 0): ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <i class="fas fa-box-open"></i>
                            <h3>No items yet</h3>
                            <p>Upload a file, add a URL, or create a document to get started</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach($files as $file): ?>
                        <div class="content-card" data-title="<?php echo htmlspecialchars($file['display_name'] ?? $file['original_name']); ?>" onclick="handleFileClick(<?php echo $file['id']; ?>, 'uploads/<?php echo htmlspecialchars($file['filename']); ?>', '<?php echo htmlspecialchars(addslashes($file['display_name'] ?? $file['original_name'])); ?>', '<?php echo htmlspecialchars($file['file_type']); ?>')">
                            <!-- Vocalize Button (Restricted to PDF, Word, Text) -->
                            <?php 
                                $ft = strtolower($file['file_type']); 
                                $fn = strtolower($file['filename']);
                                $can_vocalize = (
                                    strpos($ft, 'pdf') !== false || 
                                    strpos($ft, 'text') !== false || 
                                    strpos($ft, 'word') !== false || 
                                    strpos($ft, 'officedocument') !== false ||
                                    strpos($ft, 'msword') !== false ||
                                    strpos($fn, '.pdf') !== false ||
                                    strpos($fn, '.txt') !== false ||
                                    strpos($fn, '.doc') !== false ||
                                    strpos($fn, '.docx') !== false
                                );
                            ?>
                            <?php if ($can_vocalize): ?>
                                <a href="vocalize.php?file=uploads/<?php echo htmlspecialchars($file['filename']); ?>" class="vocalize-btn" onclick="event.stopPropagation()" title="Open in Vocalize"><i class="fas fa-microphone-lines"></i></a>
                            <?php endif; ?>
                            
                            <a href="uploads/<?php echo htmlspecialchars($file['filename']); ?>" download="<?php echo htmlspecialchars($file['original_name']); ?>" class="download-btn" onclick="event.stopPropagation()" title="Download"><i class="fas fa-download"></i></a>
                            <div class="card-icon"><i class="fas <?php 
                                $type = $file['file_type']; 
                                if (strpos($type, 'image') !== false) echo 'fa-file-image'; 
                                elseif (strpos($type, 'video') !== false) echo 'fa-file-video'; 
                                elseif (strpos($type, 'pdf') !== false) echo 'fa-file-pdf'; 
                                elseif (strpos($type, 'audio') !== false) echo 'fa-file-audio';
                                elseif (strpos($type, 'word') !== false) echo 'fa-file-word';
                                elseif (strpos($type, 'excel') !== false) echo 'fa-file-excel';
                                elseif (strpos($type, 'powerpoint') !== false) echo 'fa-file-powerpoint';
                                elseif (strpos($type, 'zip') !== false || strpos($type, 'archive') !== false) echo 'fa-file-archive';
                                else echo 'fa-file-alt'; 
                            ?>"></i></div>
                            <h4 class="card-title" title="<?php echo htmlspecialchars($file['display_name'] ?? $file['original_name']); ?>"><?php echo htmlspecialchars($file['display_name'] ?? $file['original_name']); ?></h4>
                            <p class="card-subtitle">Uploaded on <?php echo date('M d, Y', strtotime($file['upload_date'])); ?></p>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach($shortcuts as $shortcut): ?>
                        <div class="content-card shortcut-card" data-title="<?php echo htmlspecialchars($shortcut['name']); ?>" onclick="window.open('<?php echo htmlspecialchars($shortcut['url']); ?>', '_blank')">
                            <div class="card-icon"><img src="<?php echo htmlspecialchars($shortcut['favicon']); ?>" alt="" class="shortcut-favicon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';"><i class="fas fa-globe" style="display:none;"></i></div>
                            <h4 class="card-title" title="<?php echo htmlspecialchars($shortcut['name']); ?>"><?php echo htmlspecialchars($shortcut['name']); ?></h4>
                            <p class="card-subtitle" title="<?php echo htmlspecialchars($shortcut['url']); ?>"><?php echo htmlspecialchars(parse_url($shortcut['url'], PHP_URL_HOST)); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tab: PLACEMENTS (NEW) -->
            <div id="jobs_tab" class="tab-pane">
                <div class="job-grid scrollable-area">
                    <?php if (count($jobs) === 0): ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <i class="fas fa-briefcase"></i>
                            <h3>No Placements Posted</h3>
                            <p>New placement opportunities will appear here.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($jobs as $job): ?>
                            <div class="job-card content-card" data-title="<?php echo htmlspecialchars($job['title']); ?>">
                                <div class="job-header">
                                    <div>
                                        <h4 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h4>
                                        <div class="job-company"><i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?></div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-slate-400 block"><?php echo date('M d', strtotime($job['created_at'])); ?></span>
                                        <?php if(!empty($job['end_date'])): ?>
                                            <span class="text-xs font-medium text-red-500 block">Exp: <?php echo date('M d', strtotime($job['end_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="job-dates text-xs text-slate-500 mb-2 p-2 bg-slate-50 rounded">
                                    <span class="mr-3"><i class="fas fa-calendar-alt text-green-500"></i> Apply: <?php echo !empty($job['apply_date']) ? date('M d, Y', strtotime($job['apply_date'])) : 'ASAP'; ?></span>
                                    <span><i class="fas fa-hourglass-end text-red-400"></i> Ends: <?php echo !empty($job['end_date']) ? date('M d, Y', strtotime($job['end_date'])) : 'N/A'; ?></span>
                                </div>

                                <div class="job-body"><?php echo nl2br(htmlspecialchars($job['description'])); ?></div>
                                
                                <div class="job-footer">
                                    <?php if(!empty($job['document_path'])): ?>
                                        <a href="uploads/<?php echo htmlspecialchars($job['document_path']); ?>" target="_blank" class="job-btn job-btn-outline" title="View Document">
                                            <i class="fas fa-file-pdf text-red-500"></i> Info
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if(!empty($job['job_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($job['job_url']); ?>" target="_blank" class="job-btn job-btn-primary" title="Apply Now">
                                            Apply <i class="fas fa-external-link-alt text-xs"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab: FILES -->
            <div id="files" class="tab-pane">
                <div class="content-grid scrollable-area">
                    <?php if (count($files) === 0): ?>
                        <div class="empty-state" style="grid-column: 1 / -1;"><i class="fas fa-file-alt"></i><h3>No files uploaded</h3><p>Upload your first file to get started</p></div>
                    <?php else: ?>
                        <?php foreach($files as $file): ?>
                            <div class="content-card" data-title="<?php echo htmlspecialchars($file['display_name'] ?? $file['original_name']); ?>" onclick="handleFileClick(<?php echo $file['id']; ?>, 'uploads/<?php echo htmlspecialchars($file['filename']); ?>', '<?php echo htmlspecialchars(addslashes($file['display_name'] ?? $file['original_name'])); ?>', '<?php echo htmlspecialchars($file['file_type']); ?>')">
                                
                                <?php 
                                    $ft = strtolower($file['file_type']); 
                                    $fn = strtolower($file['filename']);
                                    $can_vocalize = (
                                        strpos($ft, 'pdf') !== false || 
                                        strpos($ft, 'text') !== false || 
                                        strpos($ft, 'word') !== false || 
                                        strpos($ft, 'officedocument') !== false ||
                                        strpos($ft, 'msword') !== false ||
                                        strpos($fn, '.pdf') !== false ||
                                        strpos($fn, '.txt') !== false ||
                                        strpos($fn, '.doc') !== false ||
                                        strpos($fn, '.docx') !== false
                                    );
                                ?>
                                <?php if ($can_vocalize): ?>
                                    <a href="vocalize.php?file=uploads/<?php echo htmlspecialchars($file['filename']); ?>" class="vocalize-btn" onclick="event.stopPropagation()" title="Open in Vocalize"><i class="fas fa-microphone-lines"></i></a>
                                <?php endif; ?>

                                <a href="uploads/<?php echo htmlspecialchars($file['filename']); ?>" download="<?php echo htmlspecialchars($file['original_name']); ?>" class="download-btn" onclick="event.stopPropagation()" title="Download"><i class="fas fa-download"></i></a>
                                <div class="card-icon"><i class="fas <?php 
                                    $type = $file['file_type']; 
                                    if (strpos($type, 'image') !== false) echo 'fa-file-image'; 
                                    else echo 'fa-file-alt'; 
                                ?>"></i></div>
                                <h4 class="card-title" title="<?php echo htmlspecialchars($file['display_name'] ?? $file['original_name']); ?>"><?php echo htmlspecialchars($file['display_name'] ?? $file['original_name']); ?></h4>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab: SHORTCUTS -->
            <div id="shortcuts" class="tab-pane">
                <div class="content-grid scrollable-area">
                     <?php if (count($shortcuts) === 0): ?>
                        <div class="empty-state" style="grid-column: 1 / -1;"><i class="fas fa-link"></i><h3>No shortcuts yet</h3><p>Add your first shortcut to get started</p></div>
                    <?php else: ?>
                        <?php foreach($shortcuts as $shortcut): ?>
                             <div class="content-card shortcut-card" data-title="<?php echo htmlspecialchars($shortcut['name']); ?>" onclick="window.open('<?php echo htmlspecialchars($shortcut['url']); ?>', '_blank')">
                                <div class="card-icon"><img src="<?php echo htmlspecialchars($shortcut['favicon']); ?>" alt="" class="shortcut-favicon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';"><i class="fas fa-globe" style="display:none;"></i></div>
                                <h4 class="card-title" title="<?php echo htmlspecialchars($shortcut['name']); ?>"><?php echo htmlspecialchars($shortcut['name']); ?></h4>
                                <p class="card-subtitle" title="<?php echo htmlspecialchars($shortcut['url']); ?>"><?php echo htmlspecialchars(parse_url($shortcut['url'], PHP_URL_HOST)); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab: FAVORITES -->
            <div id="favorites" class="tab-pane">
                <?php if (count($favorite_folders) === 0): ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <i class="fas fa-folder"></i>
                        <h3>No favorite folders yet</h3>
                        <p>Click "New Folder" to create one, then add items from your Profile page.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($favorite_folders as $folder): ?>
                        <section class="folder-section">
                            <div class="folder-header">
                                <h3><?php echo htmlspecialchars($folder['folder_name']); ?></h3>
                                <div class="folder-header-actions">
                                    <button class="btn-add-items" onclick="showAddItemsToFolderModal(<?php echo $folder['id']; ?>, '<?php echo htmlspecialchars(addslashes($folder['folder_name'])); ?>')">
                                        <i class="fas fa-plus"></i> Add Items
                                    </button>
                                    <button class="btn-rename-folder" onclick="showRenameFolderModal(<?php echo $folder['id']; ?>, '<?php echo htmlspecialchars(addslashes($folder['folder_name'])); ?>')">
                                        <i class="fas fa-pen"></i> Rename
                                    </button>
                                    <button class="btn-delete-folder" onclick="deleteFavoriteFolder(<?php echo $folder['id']; ?>, '<?php echo htmlspecialchars(addslashes($folder['folder_name'])); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                            
                            <?php $items = $favorites_data[$folder['id']] ?? []; ?>
                            <?php if (count($items) === 0): ?>
                                <div class="empty-state" style="padding: 2rem; background-color: #fdfdfd;">
                                    <i class="fas fa-box-open" style="font-size: 2rem;"></i>
                                    <h3 style="font-size: 1.1rem;">This folder is empty</h3>
                                    <p>Click "Add Items" to add your files and shortcuts.</p>
                                </div>
                            <?php else: ?>
                                <div class="content-grid folder-scroll-container">
                                    <?php foreach($items as $item): ?>
                                        <?php if ($item['type'] === 'file'): ?>
                                            <div class="content-card" data-title="<?php echo htmlspecialchars($item['display_name']); ?>" onclick="handleFileClick(<?php echo $item['id']; ?>, 'uploads/<?php echo htmlspecialchars($item['filename']); ?>', '<?php echo htmlspecialchars(addslashes($item['display_name'])); ?>', '<?php echo htmlspecialchars($item['file_type']); ?>')">
                                                
                                                <?php 
                                                    $ft = strtolower($item['file_type']); 
                                                    $fn = strtolower($item['filename']);
                                                    $can_vocalize = (
                                                        strpos($ft, 'pdf') !== false || 
                                                        strpos($ft, 'text') !== false || 
                                                        strpos($ft, 'word') !== false || 
                                                        strpos($ft, 'officedocument') !== false ||
                                                        strpos($ft, 'msword') !== false ||
                                                        strpos($fn, '.pdf') !== false ||
                                                        strpos($fn, '.txt') !== false ||
                                                        strpos($fn, '.doc') !== false ||
                                                        strpos($fn, '.docx') !== false
                                                    );
                                                ?>
                                                <?php if ($can_vocalize): ?>
                                                    <a href="vocalize.php?file=uploads/<?php echo htmlspecialchars($item['filename']); ?>" class="vocalize-btn" onclick="event.stopPropagation()" title="Open in Vocalize"><i class="fas fa-microphone-lines"></i></a>
                                                <?php endif; ?>

                                                <a href="favorites_handler.php?action=remove_item&id=<?php echo $item['favorite_item_id']; ?>&return_url=home.php" class="btn-remove-item" onclick="event.stopPropagation(); showConfirm('Remove Item?', 'Are you sure you want to remove this item from the folder?', () => { window.location.href=this.href; }); return false;" title="Remove"><i class="fas fa-times"></i></a>
                                                <div class="card-icon"><i class="fas <?php 
                                                    $type = $item['file_type']; 
                                                    if (strpos($type, 'image') !== false) echo 'fa-file-image'; 
                                                    else echo 'fa-file-alt'; 
                                                ?>"></i></div>
                                                <h4 class="card-title" title="<?php echo htmlspecialchars($item['display_name'] ?? $item['original_name']); ?>"><?php echo htmlspecialchars($item['display_name'] ?? $item['original_name']); ?></h4>
                                            </div>
                                        <?php elseif ($item['type'] === 'shortcut'): ?>
                                            <div class="content-card shortcut-card" data-title="<?php echo htmlspecialchars($item['name']); ?>" onclick="window.open('<?php echo htmlspecialchars(addslashes($item['url'])); ?>', '_blank')">
                                                <a href="favorites_handler.php?action=remove_item&id=<?php echo $item['favorite_item_id']; ?>&return_url=home.php" class="btn-remove-item" onclick="event.stopPropagation(); showConfirm('Remove Item?', 'Are you sure you want to remove this item from the folder?', () => { window.location.href=this.href; }); return false;" title="Remove"><i class="fas fa-times"></i></a>
                                                <div class="card-icon"><img src="<?php echo htmlspecialchars($item['favicon']); ?>" alt="" class="shortcut-favicon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';"><i class="fas fa-globe" style="display:none;"></i></div>
                                                <h4 class="card-title" title="<?php echo htmlspecialchars($item['name']); ?>"><?php echo htmlspecialchars($item['name']); ?></h4>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Messages Tab Pane (Unchanged) -->
            <div id="messages" class="tab-pane">
                <div class="msg-container">
                    <div class="msg-inbox">
                        <div class="msg-header"><i class="fas fa-inbox text-blue-500"></i> Inbox (Admin Messages)</div>
                        <div class="msg-list">
                            <?php if(count($messages) === 0): ?>
                                <div style="text-align:center; padding:20px; color:#94a3b8;">No messages from Admin yet.</div>
                            <?php else: ?>
                                <?php foreach($messages as $msg): ?>
                                    <div class="msg-item <?php echo $msg['type'] == 'broadcast' ? 'broadcast' : ''; ?>">
                                        <div class="msg-meta">
                                            <span><i class="fas <?php echo $msg['type'] == 'broadcast' ? 'fa-bullhorn' : 'fa-user-shield'; ?>"></i> <?php echo htmlspecialchars($msg['sender_name']); ?></span>
                                            <div style="display:flex; align-items:center;">
                                                <span><?php echo date('M d, H:i', strtotime($msg['created_at'])); ?></span>
                                                <?php if($msg['type'] !== 'broadcast'): ?>
                                                    <form action="message_handler.php" method="POST" onsubmit="return confirm('Delete this message?');" style="margin:0; padding:0; display:inline;">
                                                        <input type="hidden" name="action" value="delete_message">
                                                        <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <button type="submit" class="btn-delete-msg" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="msg-body"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="msg-compose">
                        <?php if($suggestions_enabled): ?>
                            <div style="font-weight:600; margin-bottom:10px; color:#334155;"><i class="fas fa-paper-plane text-green-500"></i> Send Suggestion</div>
                            <p style="font-size:0.8rem; color:#64748b; margin-bottom:15px;">Have an idea? Send it to the Admin. A copy will be emailed to support.</p>
                            
                            <form action="message_handler.php" method="POST" style="display:flex; flex-direction:column; flex:1;">
                                <input type="hidden" name="action" value="send_suggestion">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <textarea name="message" placeholder="Type your suggestion here..." required></textarea>
                                <button type="submit" class="btn-primary" style="align-self:flex-end;"><i class="fas fa-paper-plane"></i> Send Now</button>
                            </form>
                        <?php else: ?>
                            <div class="suggestions-disabled">
                                <i class="fas fa-ban" style="font-size:2.5rem; margin-bottom:15px;"></i>
                                <p style="font-weight: 600; font-size: 1.1rem;">Suggestions Closed</p>
                                <p style="font-size: 0.9rem;">The admin has currently disabled suggestions.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>
    
    <footer style="text-align: center; padding: 2rem 0; color: #999; font-size: 0.9em;">
        <p>developed by vineeth</p>
    </footer>

    <!-- Modals (Unchanged) -->
    <div id="fileNameModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('fileNameModal')">&times;</span><h2>Name Your File</h2><div id="fileNameForm"><div class="form-group"><label for="fileNameInputModal">File Name</label><input type="text" id="fileNameInputModal" placeholder="Enter a name for your file" required></div><button type="button" class="btn-primary" onclick="submitFileUpload()">Upload File</button></div></div></div>
    <div id="addModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('addModal')">&times;</span><h2>Add URL Shortcut</h2><p style="font-size: 0.85rem; color: #64748b; margin-bottom: 15px;">Make small names to find them easily.</p><form action="shortcut_handler.php" method="POST"><input type="hidden" name="action" value="add"><input type="hidden" name="return_url" value="home.php"><div class="form-group"><label for="addShortcutName">Name</label><input type="text" id="addShortcutName" name="name" placeholder="e.g., Google" required></div><div class="form-group"><label for="addShortcutUrl">URL</label><input type="text" id="addShortcutUrl" name="url" placeholder="e.g., google.com" required></div><button type="submit" class="btn-primary">Add Shortcut</button></form></div></div>
    <div id="fileViewerModal" class="modal"><div class="viewer-modal-content"><div class="viewer-header"><h2 id="viewerFileName"></h2><div style="display:flex; gap:10px;"><a id="viewerEditBtn" href="#" class="btn-primary" style="padding:5px 15px; font-size:0.9em; display:none;">Open in Editor</a><span class="close" onclick="closeFileViewer()">&times;</span></div></div><div class="viewer-body"><iframe id="viewerFrame" src="" frameborder="0"></iframe></div></div></div>
    <div id="createFolderModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('createFolderModal')">&times;</span><h2>Create New Folder</h2><form action="favorites_handler.php" method="POST"><input type="hidden" name="action" value="create_folder"><input type="hidden" name="return_url" value="home.php#favorites"><div class="form-group"><label for="folderName">Folder Name</label><input type="text" id="folderName" name="folder_name" placeholder="e.g., Project X" required></div><button type="submit" class="btn-primary">Create Folder</button></form></div></div>
    <div id="renameFolderModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('renameFolderModal')">&times;</span><h2>Rename Folder</h2><form action="favorites_handler.php" method="POST"><input type="hidden" name="action" value="rename_folder"><input type="hidden" name="return_url" value="home.php#favorites"><input type="hidden" name="folder_id" id="renameFolderId"><div class="form-group"><label for="renameFolderName">New Folder Name</label><input type="text" id="renameFolderName" name="folder_name" placeholder="Enter a new name" required></div><button type="submit" class="btn-primary">Save Changes</button></form></div></div>
    <div id="addItemsToFolderModal" class="modal"><div class="modal-content add-items-modal-content"><span class="close" onclick="closeModal('addItemsToFolderModal')">&times;</span><h2>Add items to "<span id="addItemsFolderName"></span>"</h2><form action="favorites_handler.php" method="POST"><input type="hidden" name="action" value="add_items"><input type="hidden" name="return_url" value="home.php#favorites"><input type="hidden" name="folder_id" id="addItemsFolderId"><div class="item-selection-list"><?php if (count($files) === 0 && count($shortcuts) === 0): ?><p style="text-align: center; color: #888;">You have no files or shortcuts to add.</p><?php else: ?><?php foreach($files as $file): ?><label><input type="checkbox" name="selected_files[]" value="<?php echo $file['id']; ?>"><i class="fas <?php $type = $file['file_type']; if (strpos($type, 'image') !== false) echo 'fa-file-image'; elseif (strpos($type, 'video') !== false) echo 'fa-file-video'; elseif (strpos($type, 'pdf') !== false) echo 'fa-file-pdf'; elseif (strpos($type, 'audio') !== false) echo 'fa-file-audio'; elseif (strpos($type, 'word') !== false) echo 'fa-file-word'; elseif (strpos($type, 'excel') !== false) echo 'fa-file-excel'; elseif (strpos($type, 'powerpoint') !== false) echo 'fa-file-powerpoint'; elseif (strpos($type, 'zip') !== false || strpos($type, 'archive') !== false) echo 'fa-file-archive'; else echo 'fa-file-alt'; ?>"></i><?php echo htmlspecialchars($file['display_name'] ?? $file['original_name']); ?></label><?php endforeach; ?><?php foreach($shortcuts as $shortcut): ?><label><input type="checkbox" name="selected_shortcuts[]" value="<?php echo $shortcut['id']; ?>"><i class="fas fa-link"></i><?php echo htmlspecialchars($shortcut['name']); ?></label><?php endforeach; ?><?php endif; ?></div><button type="submit" class="btn-primary" style="margin-top: 1.5rem;">Add Selected Items</button></form></div></div>
    <div id="alertModal" class="modal"><div class="modal-content alert-modal-content"><h2 id="alertModalTitle"></h2><p id="alertModalMessage"></p><div id="alertModalActions"><button id="alertModalConfirmBtn" class="btn-primary"></button><button id="alertModalCancelBtn" class="btn-outline" style="border: 1px solid #ccc;" onclick="closeModal('alertModal')">Cancel</button></div></div></div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const navToggle = document.querySelector('.mobile-nav-toggle');
            const nav = document.querySelector('.nav');
            
            // Toggle menu on click
            navToggle.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent immediate closing
                nav.classList.toggle('active');
            });
            
            // Close menu when clicking outside (on the "side")
            document.addEventListener('click', (e) => {
                if (nav.classList.contains('active') && !nav.contains(e.target) && !navToggle.contains(e.target)) {
                    nav.classList.remove('active');
                }
            });
            
            // Close menu when scrolling ("sliding" the page)
            window.addEventListener('scroll', () => {
                if (nav.classList.contains('active')) {
                    nav.classList.remove('active');
                }
            });

            // Close menu when a link inside it is clicked
            const navLinks = nav.querySelectorAll('a');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    nav.classList.remove('active');
                });
            });
            
            if (window.location.hash === '#favorites') {
                const favTabButton = document.querySelector('.tab-btn[onclick*="favorites"]');
                if (favTabButton) favTabButton.click();
            } else if (window.location.hash === '#messages') {
                const msgTabButton = document.querySelector('.tab-btn[onclick*="messages"]');
                if (msgTabButton) msgTabButton.click();
            } else if (window.location.hash === '#jobs') {
                const jobsTabButton = document.querySelector('.tab-btn[onclick*="jobs_tab"]');
                if (jobsTabButton) jobsTabButton.click();
            }
            
            setupDragAndDrop();

            const alerts = document.querySelectorAll('.notification-alert.auto-dismiss');
            if (alerts.length > 0) {
                setTimeout(() => {
                    alerts.forEach(alert => {
                        alert.style.opacity = '0';
                        setTimeout(() => alert.style.display = 'none', 500);
                    });
                }, 5000);
            }

            // --- Notification Logic for Placements ---
            const serverPlacementCount = <?php echo count($jobs); ?>;
            const storedCount = localStorage.getItem('seenPlacementsCount') || 0;
            const badgeTab = document.getElementById('placements-badge-tab');
            
            if (serverPlacementCount > storedCount) {
                const count = serverPlacementCount - storedCount;
                if(badgeTab) {
                    badgeTab.textContent = count;
                    badgeTab.style.display = 'inline-block';
                }
            }
        });

        function toggleDeviceLock(event) {
            event.preventDefault();
            
            // Select all lock buttons (both mobile and desktop versions)
            const btns = document.querySelectorAll('.device-lock-trigger');
            let isCurrentlyLocked = false;
            
            // Determine current state from the first found button
            if (btns.length > 0) {
                isCurrentlyLocked = btns[0].classList.contains('locked');
            }
            
            const message = isCurrentlyLocked 
                ? "Are you sure you want to unlock your account? This will allow other devices to access it."
                : "Are you sure you want to LOCK your account to THIS device? You will not be able to access it from any other browser or device until you unlock it here.";

            showConfirm('Device Lock', message, function() {
                // AJAX to toggle
                const formData = new FormData();
                formData.append('action', 'toggle_lock');
                
                fetch('home.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'locked' || data.status === 'unlocked') {
                        const newStatus = data.status;
                        const isLocked = (newStatus === 'locked');
                        
                        // Update ALL lock buttons on the page
                        btns.forEach(btn => {
                            btn.classList.remove('locked', 'unlocked');
                            btn.classList.add(newStatus);
                            
                            const icon = btn.querySelector('i');
                            if(icon) {
                                icon.className = 'fas ' + (isLocked ? 'fa-lock' : 'fa-lock-open');
                            }
                            
                            const label = btn.querySelector('.lock-label');
                            if(label) {
                                label.textContent = isLocked ? 'Locked' : 'Lock';
                            }
                            
                            btn.title = isLocked ? "Unlock Account" : "Lock to this device";
                        });
                        
                        showAlert(isLocked ? 'Locked' : 'Unlocked', 
                                  isLocked ? 'Account is now locked to this device.' : 'Account is now accessible from other devices.');
                    } else {
                        showAlert('Error', data.message || 'Something went wrong.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showAlert('Error', 'Could not connect to server.');
                });
            }, isCurrentlyLocked ? 'Unlock' : 'Lock', isCurrentlyLocked ? '#10b981' : '#ef4444');
        }

        function openTab(evt, tabName) {
            const tabcontent = document.getElementsByClassName("tab-pane");
            for (let i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            const tablinks = document.getElementsByClassName("tab-btn");
            for (let i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            
            // Handle the Placements link (which is not a tab button) specially
            if (evt.currentTarget.classList.contains('nav-link')) {
                 const jobsBtn = document.querySelector('.tab-btn[onclick*="jobs_tab"]');
                 if(jobsBtn) {
                     jobsBtn.className += " active";
                     // Remove other active classes from nav links
                     document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
                     evt.currentTarget.classList.add('active');
                 }
                 
                 // --- CLEAR NOTIFICATION ON CLICK ---
                 if (evt.currentTarget.id === 'nav-placements') {
                     const serverPlacementCount = <?php echo count($jobs); ?>;
                     localStorage.setItem('seenPlacementsCount', serverPlacementCount);
                     const badgeTab = document.getElementById('placements-badge-tab');
                     if(badgeTab) badgeTab.style.display = 'none';
                 }

            } else {
                evt.currentTarget.className += " active";
                // Reset nav links active state
                 document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
                 document.querySelector('a[href="home.php"]').classList.add('active');
            }

            document.getElementById(tabName).style.display = "block";
            
            if (tabName === 'favorites') {
                window.location.hash = 'favorites';
            } else if (tabName === 'messages') {
                window.location.hash = 'messages';
                const badge = document.getElementById('msg-badge');
                if (badge) {
                    badge.style.display = 'none';
                    fetch('message_handler.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=mark_read&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
                    });
                }
            } else if (tabName === 'jobs_tab') {
                window.location.hash = 'jobs';
                // Also clear if accessing via hash/direct tab button
                const serverPlacementCount = <?php echo count($jobs); ?>;
                localStorage.setItem('seenPlacementsCount', serverPlacementCount);
                const badgeTab = document.getElementById('placements-badge-tab');
                if(badgeTab) badgeTab.style.display = 'none';
            } else {
                history.pushState("", document.title, window.location.pathname + window.location.search);
            }
        }
        
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        function showAlert(title, message) { document.getElementById('alertModalTitle').textContent = title; document.getElementById('alertModalMessage').textContent = message; const confirmBtn = document.getElementById('alertModalConfirmBtn'); confirmBtn.textContent = 'OK'; confirmBtn.onclick = () => closeModal('alertModal'); confirmBtn.style.display = 'inline-block'; confirmBtn.style.backgroundColor = '#4A00E0'; confirmBtn.style.borderColor = '#4A00E0'; document.getElementById('alertModalCancelBtn').style.display = 'none'; document.getElementById('alertModal').style.display = 'block'; }
        function showConfirm(title, message, onConfirm, confirmText = 'Confirm', confirmColor = '#ef4444') { document.getElementById('alertModalTitle').textContent = title; document.getElementById('alertModalMessage').textContent = message; const confirmBtn = document.getElementById('alertModalConfirmBtn'); confirmBtn.textContent = confirmText; confirmBtn.onclick = function() { onConfirm(); closeModal('alertModal'); }; confirmBtn.style.display = 'inline-block'; confirmBtn.style.backgroundColor = confirmColor; confirmBtn.style.borderColor = confirmColor; document.getElementById('alertModalCancelBtn').style.display = 'inline-block'; document.getElementById('alertModal').style.display = 'block'; }
        function showFileNameModal() { const fileInput = document.getElementById('fileInput'); if (fileInput.files.length > 0) { document.getElementById('fileNameModal').style.display = 'block'; document.getElementById('fileNameInputModal').value = fileInput.files[0].name.split('.').slice(0, -1).join('.'); document.getElementById('fileNameInputModal').focus(); } }
        function submitFileUpload() { const fileName = document.getElementById('fileNameInputModal').value.trim(); if (fileName) { document.getElementById('fileNameInput').value = fileName; document.getElementById('uploadForm').submit(); } else { showAlert('Missing Name', 'Please enter a name for your file.'); } }
        function showAddModal() { document.getElementById('addModal').style.display = 'block'; }
        function showCreateFolderModal() { document.getElementById('createFolderModal').style.display = 'block'; }
        function handleFileClick(fileId, fileUrl, fileName, fileType) { if (fileType.includes('html') || fileType.includes('text') || fileType.includes('word') || fileType.includes('pdf') || fileType.includes('image')) { showFileViewer(fileUrl, fileName, fileId); } else { showFileViewer(fileUrl, fileName, null); } }
        function showFileViewer(fileUrl, fileName, editFileId) { const modal = document.getElementById('fileViewerModal'); const frame = document.getElementById('viewerFrame'); const name = document.getElementById('viewerFileName'); const editBtn = document.getElementById('viewerEditBtn'); name.textContent = fileName; frame.src = fileUrl; if (editFileId) { editBtn.style.display = 'inline-block'; editBtn.href = 'editor.php?id=' + editFileId; } else { editBtn.style.display = 'none'; } modal.style.display = 'block'; }
        function closeFileViewer() { const modal = document.getElementById('fileViewerModal'); const frame = document.getElementById('viewerFrame'); frame.src = 'about:blank'; modal.style.display = 'none'; }
        function deleteFavoriteFolder(folderId, folderName) { showConfirm('Delete Folder?', `Are you sure you want to delete the folder "${folderName}"? All items inside it will be removed from this folder.`, function() { window.location.href = `favorites_handler.php?action=delete_folder&id=${folderId}&return_url=home.php`; }, 'Delete', '#ef4444'); }
        function showRenameFolderModal(folderId, folderName) { document.getElementById('renameFolderId').value = folderId; document.getElementById('renameFolderName').value = folderName; document.getElementById('renameFolderModal').style.display = 'block'; document.getElementById('renameFolderName').focus(); }
        function removeItemFromFavorite(favoriteItemId) { showConfirm('Remove Item?', `Are you sure you want to remove this item from the folder?`, function() { window.location.href = `favorites_handler.php?action=remove_item&id=${favoriteItemId}&return_url=home.php`; }, 'Remove', '#ef4444'); }
        function showAddItemsToFolderModal(folderId, folderName) { document.getElementById('addItemsFolderName').textContent = folderName; document.getElementById('addItemsFolderId').value = folderId; const checkboxes = document.querySelectorAll('#addItemsToFolderModal input[type=checkbox]'); checkboxes.forEach(cb => cb.checked = false); document.getElementById('addItemsToFolderModal').style.display = 'block'; }
        
        function setupDragAndDrop() {
            const dropZone = document.getElementById('dropZone');
            const loadingOverlay = document.getElementById('loadingOverlay');
            let dragCounter = 0;

            // Common prevent defaults
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            // Events to prevent default browser behavior (opening file)
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                window.addEventListener(eventName, preventDefaults, false);
            });

            // Drag Enter (Window)
            window.addEventListener('dragenter', (e) => {
                dragCounter++;
                // Show zone if we have a file or if we just want to be reactive
                const dt = e.dataTransfer;
                // Safer check for file types
                if (dt && dt.types && (dt.types.indexOf ? dt.types.indexOf('Files') != -1 : dt.types.includes('Files'))) {
                    dropZone.classList.add('active');
                } else if (dt && !dt.types) {
                     // Fallback for some browsers if types is empty on enter
                     dropZone.classList.add('active');
                }
            });

            // Drag Leave (Window)
            window.addEventListener('dragleave', (e) => {
                dragCounter--;
                if (dragCounter === 0) {
                    dropZone.classList.remove('active');
                }
            });

            // Drag Over (Window) - Necessary for dropEffect
            window.addEventListener('dragover', (e) => {
                preventDefaults(e);
                if(dropZone.classList.contains('active')) {
                     e.dataTransfer.dropEffect = 'copy';
                }
            });

            // Drop (Window - catches bubbling from dropZone)
            window.addEventListener('drop', (e) => {
                preventDefaults(e);
                dragCounter = 0;
                dropZone.classList.remove('active');
                
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length > 0) {
                    handleFiles(files);
                }
            });
            
            function handleFiles(files) {
                if (files.length === 0) return;
                loadingOverlay.style.display = 'flex'; 
                const formData = new FormData();
                formData.append('action', 'ajax_upload');
                for (let i = 0; i < files.length; i++) { formData.append('files[]', files[i]); }
                fetch('file_handler.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => { if (data.success) { location.reload(); } else { loadingOverlay.style.display = 'none'; showAlert('Upload Error', data.message || 'An unknown error occurred during upload.'); } })
                .catch(error => { loadingOverlay.style.display = 'none'; console.error('Upload error:', error); showAlert('Upload Failed', 'Could not connect to the server. Please try again.'); });
            }
        }
        function filterContent() {
            const filter = document.getElementById('globalSearchInput').value.toUpperCase();
            document.querySelectorAll('.tab-pane').forEach(pane => {
                const cards = pane.querySelectorAll('.content-grid .content-card'); // Normal cards
                const jobs = pane.querySelectorAll('.job-grid .job-card'); // Job cards
                
                cards.forEach(card => {
                    const title = (card.dataset.title || card.querySelector('.card-title').textContent).toUpperCase();
                    if (title.includes(filter)) { card.style.display = 'flex'; } else { card.style.display = 'none'; }
                });

                jobs.forEach(job => {
                    const title = (job.dataset.title || job.querySelector('.job-title').textContent).toUpperCase();
                    if (title.includes(filter)) { job.style.display = 'flex'; } else { job.style.display = 'none'; }
                });
            });
        }
        window.onclick = function(event) { const modals = document.querySelectorAll('.modal'); modals.forEach(modal => { if (event.target == modal) { modal.style.display = 'none'; if (modal.id === 'fileViewerModal') { closeFileViewer(); } } }); }
    </script>
</body>
</html>