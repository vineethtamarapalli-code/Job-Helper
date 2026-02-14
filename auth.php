<?php
require_once 'config.php';

$action = $_POST['action'] ?? null;

// --- User Registration ---
if ($action === 'register') {
    // 1. CHECK IF SIGNUP IS ENABLED
    $stmt_settings = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'signup_enabled'");
    $stmt_settings->execute();
    $is_enabled = $stmt_settings->fetchColumn();

    if ($is_enabled === '0') {
        header('Location: index.php?error=' . urlencode("Registration is currently disabled by Admin."));
        exit();
    }
    
    $full_name = trim($_POST['full_name']);
    $suc_code = trim($_POST['suc_code']);
    $email = trim($_POST['email']); // Capture the email
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if passwords match
    if ($password !== $confirm_password) {
        header('Location: index.php?error=' . urlencode("Passwords do not match."));
        exit();
    }

    // Check if SUC code OR Email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE suc_code = ? OR email = ?");
    $stmt->execute([$suc_code, $email]);
    if ($stmt->fetch()) {
        header('Location: index.php?error=' . urlencode("User with this SUC Code or Email already exists."));
        exit();
    }

    // Hash the password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $default_pic = 'default_avatar.png'; 

    try {
        // UPDATED: Added 'email' to the INSERT statement
        $stmt = $conn->prepare("INSERT INTO users (full_name, suc_code, email, password, profile_pic, created_at, status) VALUES (?, ?, ?, ?, ?, NOW(), 'Active')");
        $stmt->execute([$full_name, $suc_code, $email, $password_hash, $default_pic]);
        
        $_SESSION['user_id'] = $conn->lastInsertId();
        $_SESSION['suc_code'] = $suc_code;
        header('Location: home.php');
        exit();
        
    } catch (PDOException $e) {
        // Log error for debugging if needed: error_log($e->getMessage());
        header('Location: index.php?error=' . urlencode("Registration failed."));
        exit();
    }
}

// --- User Login ---
if ($action === 'login') {
    // UPDATED: Accept the combined identifier (Email or SUC)
    $login_input = trim($_POST['login_identifier']);
    $password = $_POST['password'];

    // UPDATED: Query checks both suc_code AND email columns
    $stmt = $conn->prepare("SELECT id, password, status, suc_code FROM users WHERE suc_code = ? OR email = ?");
    $stmt->execute([$login_input, $login_input]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 2. CHECK IF BLOCKED
        if ($user['status'] === 'Blocked') {
            header('Location: index.php?error=' . urlencode("Your account has been blocked by the Administrator."));
            exit();
        }

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['suc_code'] = $user['suc_code'];
            header('Location: home.php');
            exit();
        }
    }
    
    header('Location: index.php?error=' . urlencode("Invalid Email/SUC Code or Password."));
    exit();
}

// ... Rest of your logic ...

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit();
}

if ($action === 'check_suc') {
    $suc_code = trim($_POST['suc_code']);
    $stmt = $conn->prepare("SELECT id FROM users WHERE suc_code = ?");
    $stmt->execute([$suc_code]);
    if ($stmt->fetch()) {
        header('Location: index.php?step=reset&suc_code=' . urlencode($suc_code));
        exit();
    } else {
        header('Location: index.php?step=forgot&error=' . urlencode("User not found."));
        exit();
    }
}

if ($action === 'reset_password') {
    $suc_code = trim($_POST['suc_code']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        header('Location: index.php?step=reset&suc_code=' . urlencode($suc_code) . '&error=' . urlencode("Passwords do not match."));
        exit();
    }
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE suc_code = ?");
    $stmt->execute([$password_hash, $suc_code]);
    header('Location: index.php?step=login&success=' . urlencode("Password reset successfully."));
    exit();
}

header('Location: index.php');
exit();
?>