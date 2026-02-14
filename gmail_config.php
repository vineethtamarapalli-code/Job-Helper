<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- GOOGLE API CONFIGURATION ---
define('GOOGLE_CLIENT_ID', '145586497121-gt7036dbqk5k2mta1vcfidjsg0su1l8p.apps.googleusercontent.com'); 
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOCSPX_SECRET_KEY_HERE'); // <--- PASTE THE SECRET KEY STARTING WITH GOCSPX HERE
define('GOOGLE_REDIRECT_URI', 'http://localhost/job_helper/gmail_callback.php'); // Ensure this matches your folder name

// API Endpoints
define('GOOGLE_OAUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v1/userinfo');
define('GMAIL_API_URL', 'https://gmail.googleapis.com/gmail/v1/users/me');

// Scopes
define('GOOGLE_SCOPE', 'https://www.googleapis.com/auth/gmail.readonly email profile');

function make_curl_request($url, $params = [], $method = 'GET', $headers = []) {
    $ch = curl_init();
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    } else {
        if (!empty($params)) $url .= '?' . http_build_query($params);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Local dev only
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
?>