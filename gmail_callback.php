<?php
require_once 'gmail_config.php';

if (isset($_GET['code'])) {
    // Exchange the authorization code for an access token
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
        'code' => $_GET['code']
    ];

    $response = make_curl_request(GOOGLE_TOKEN_URL, $params, 'POST');

    if (isset($response['access_token'])) {
        // Store tokens in session (In production, save Refresh Token to DB)
        $_SESSION['gmail_access_token'] = $response['access_token'];
        $_SESSION['gmail_token_expires'] = time() + $response['expires_in'];
        
        if (isset($response['refresh_token'])) {
            $_SESSION['gmail_refresh_token'] = $response['refresh_token'];
        }

        header('Location: gmail_view.php');
        exit();
    } else {
        $error = $response['error_description'] ?? 'Unknown error during token exchange';
        die('Error: ' . $error . ' <a href="profile.php">Back to Profile</a>');
    }
} elseif (isset($_GET['error'])) {
    die('Error: ' . $_GET['error'] . ' <a href="profile.php">Back to Profile</a>');
} else {
    header('Location: profile.php');
    exit();
}
?>