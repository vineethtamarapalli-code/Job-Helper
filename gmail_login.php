<?php
require_once 'gmail_config.php';

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['gmail_access_token']);
    unset($_SESSION['gmail_refresh_token']);
    unset($_SESSION['gmail_token_expires']);
    header('Location: profile.php');
    exit();
}

// Check if already logged in
if (isset($_SESSION['gmail_access_token']) && !empty($_SESSION['gmail_access_token'])) {
    header('Location: gmail_view.php');
    exit();
}

// Generate the Google Login URL
$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => GOOGLE_SCOPE,
    'access_type' => 'offline', // Required to get a refresh token
    'prompt' => 'consent'       // Force consent to ensure we get a refresh token
];

$login_url = GOOGLE_OAUTH_URL . '?' . http_build_query($params);

// Redirect user to Google
header('Location: ' . $login_url);
exit();
?>