<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- SECURITY: Verify CSRF Token for POST requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // For AJAX requests (like mark_read), return JSON error
        if($action === 'mark_read') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
            exit();
        }
        die("Security Error: Invalid CSRF Token.");
    }
}

// --- 1. Check Unread Count ---
if ($action === 'check_unread') {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $count = $stmt->fetchColumn();
    header('Content-Type: application/json');
    echo json_encode(['count' => $count]);
    exit();
}

// --- 2. Mark Messages as Read ---
if ($action === 'mark_read') {
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['status' => 'success']);
    exit();
}

// --- 3. Delete Message ---
if ($action === 'delete_message') {
    // Allow ID to come from POST (preferred) or GET (legacy)
    $msg_id = $_POST['id'] ?? $_GET['id'] ?? null;
    $user_id = $_SESSION['user_id'];
    
    if ($msg_id) {
        $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$msg_id, $user_id]);
    }
    
    header('Location: home.php?msg_success=Message deleted.#messages');
    exit();
}

// --- 4. Send Suggestion ---
if ($action === 'send_suggestion') {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'suggestions_enabled'");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    
    if ($val !== false && $val == '0') {
        header('Location: home.php?msg_error=Suggestions are currently closed.#messages');
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $message = trim($_POST['message']);
    
    if (empty($message)) {
        header('Location: home.php?msg_error=Message cannot be empty#messages');
        exit();
    }

    $stmt = $conn->prepare("SELECT full_name, suc_code FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $sender_name = $user['full_name'] . " (" . $user['suc_code'] . ")";

    $stmt = $conn->prepare("INSERT INTO messages (sender_id, sender_name, message, type, created_at) VALUES (?, ?, ?, 'suggestion', NOW())");
    $stmt->execute([$user_id, $sender_name, $message]);

    @mail("vineethtamarapalli0gmail.com", "New Suggestion", $message, "From: no-reply@jobhelper.local");

    header('Location: home.php?msg_success=Suggestion sent!#messages');
    exit();
}

header('Location: home.php');
exit();
?>