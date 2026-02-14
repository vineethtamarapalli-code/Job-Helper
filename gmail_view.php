<?php
require_once 'config.php'; // Your main app config for DB/Auth
require_once 'gmail_config.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Gmail Auth Check
if (!isset($_SESSION['gmail_access_token'])) {
    header('Location: gmail_login.php');
    exit();
}

// Token Refresh Logic (Simple version)
if (time() >= ($_SESSION['gmail_token_expires'] ?? 0)) {
    // Ideally, use refresh token here. For this demo, force re-login if expired.
    header('Location: gmail_login.php');
    exit();
}

$access_token = $_SESSION['gmail_access_token'];

// 1. Fetch Message List (Top 10)
$list_url = GMAIL_API_URL . '/messages?maxResults=10&q=in:inbox';
$headers = ['Authorization: Bearer ' . $access_token];
$message_list = make_curl_request($list_url, [], 'GET', $headers);

$emails = [];

// 2. Fetch Details for each message
if (isset($message_list['messages']) && !empty($message_list['messages'])) {
    foreach ($message_list['messages'] as $msg) {
        $msg_id = $msg['id'];
        $detail_url = GMAIL_API_URL . '/messages/' . $msg_id . '?format=metadata&metadataHeaders=Subject&metadataHeaders=From&metadataHeaders=Date';
        $details = make_curl_request($detail_url, [], 'GET', $headers);
        
        $headers_map = [];
        if (isset($details['payload']['headers'])) {
            foreach ($details['payload']['headers'] as $h) {
                $headers_map[$h['name']] = $h['value'];
            }
        }

        $emails[] = [
            'id' => $msg_id,
            'snippet' => $details['snippet'] ?? '',
            'subject' => $headers_map['Subject'] ?? '(No Subject)',
            'from' => $headers_map['From'] ?? '(Unknown)',
            'date' => $headers_map['Date'] ?? '',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Helper - My Emails</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .email-container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .email-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .email-list { display: flex; flex-direction: column; gap: 1rem; }
        .email-card { background: white; padding: 1.25rem; border-radius: 10px; border: 1px solid #f0f0f0; transition: transform 0.2s, box-shadow 0.2s; text-decoration: none; color: inherit; display: block; }
        .email-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); border-color: #4A00E0; }
        .email-top { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem; color: #666; }
        .email-from { font-weight: 600; color: #333; }
        .email-subject { font-weight: 700; font-size: 1.1rem; margin-bottom: 0.5rem; color: #4A00E0; }
        .email-snippet { font-size: 0.9rem; color: #555; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .empty-inbox { text-align: center; padding: 4rem; color: #888; }
        .back-btn { text-decoration: none; color: #666; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; }
        .back-btn:hover { color: #4A00E0; }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">Job Helper</h1>
        <nav class="nav">
            <a href="profile.php" class="nav-link active">Back to Profile</a>
        </nav>
    </header>

    <main class="main-content">
        <div class="email-container">
            <div class="email-header">
                <div>
                    <a href="profile.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                    <h2 style="margin-top: 0.5rem;">My Inbox</h2>
                    <p style="color: #666;">Recent emails from your connected Gmail account</p>
                </div>
                <a href="gmail_login.php?action=logout" class="btn-danger" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                    <i class="fas fa-sign-out-alt"></i> Disconnect
                </a>
            </div>

            <div class="email-list">
                <?php if (empty($emails)): ?>
                    <div class="email-card empty-inbox">
                        <i class="fas fa-envelope-open" style="font-size: 3rem; margin-bottom: 1rem; color: #ddd;"></i>
                        <h3>Inbox is empty</h3>
                        <p>Or unable to fetch messages at this time.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($emails as $email): ?>
                        <div class="email-card">
                            <div class="email-top">
                                <span class="email-from"><?php echo htmlspecialchars($email['from']); ?></span>
                                <span class="email-date"><?php echo htmlspecialchars($email['date']); ?></span>
                            </div>
                            <div class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></div>
                            <div class="email-snippet"><?php echo htmlspecialchars(html_entity_decode($email['snippet'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>