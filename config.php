<?php
// --- CONFIGURATION ---

// 1. Detect Environment (Robustly handling Ports)
// $_SERVER['HTTP_HOST'] might contain a port (e.g. localhost:8080)
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$host_name = (strpos($host, ':') !== false) ? explode(':', $host)[0] : $host;

$is_localhost = ($host_name === 'localhost' || $host_name === '127.0.0.1' || strpos($host_name, '192.168') === 0);

// 2. Detect HTTPS
$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// 3. Set a Unique Session Name (V3)
// Changing this name forces the browser to create a fresh session, 
// fixing the "Too Many Redirects" loop caused by old stuck cookies.
session_name('JH_SESSION_V3'); 

// 4. Configure Session Parameters based on Environment
if ($is_localhost) {
    // --- LOCALHOST SETTINGS (Minimal & Safe) ---
    // We use basic settings here to ensure XAMPP/Localhost doesn't reject the cookie.
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'httponly' => true
        // 'secure' and 'domain' are intentionally omitted for localhost stability
    ]);
} else {
    // --- PRODUCTION SETTINGS (InfinityFree/Live) ---
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '', // Empty string handles subdomains correctly on most shared hosts
        'secure' => $is_https, 
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Global Security Headers for Production
    header("X-Frame-Options: SAMEORIGIN");                   
    header("X-Content-Type-Options: nosniff");               
    header("X-XSS-Protection: 1; mode=block");               
    header("Referrer-Policy: strict-origin-when-cross-origin"); 

    if ($is_https) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains"); 
    }
}

// 5. Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 6. Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Database Configuration ---
// DETECT DATABASE CREDENTIALS AUTOMATICALLY
if ($is_localhost) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'job_helper');
} else {
    // INFINITYFREE CREDENTIALS
    define('DB_HOST', 'sqlXXX.infinityfree.com'); // UPDATE THIS
    define('DB_USER', 'if0_XXXXXXX');             // UPDATE THIS
    define('DB_PASS', 'YOUR_V_PANEL_PASSWORD');   // UPDATE THIS
    define('DB_NAME', 'if0_XXXXXXX_job_helper');  // UPDATE THIS
}

// --- PDO Database Connection ---
try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    die("System Error: Could not connect to the database. " . $e->getMessage());
}
?>