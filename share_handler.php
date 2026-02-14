<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Helper function to share an item ---
function shareItem($itemId, $itemType, $recipientId, $senderId, $conn) {
    // Prevent users from sharing content with themselves
    if ($recipientId == $senderId) {
        return false;
    }
    
    if ($itemType === 'file') {
        // Fetch the original file details
        $stmt_get = $conn->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
        $stmt_get->execute([$itemId, $senderId]);
        $file = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if ($file) {
            // Insert a new record for the recipient pointing to the same physical file
            $stmt_insert = $conn->prepare(
                "INSERT INTO files (user_id, filename, original_name, file_size, file_type, upload_date, display_name) 
                 VALUES (?, ?, ?, ?, ?, CURRENT_DATE, ?)"
            );
            $stmt_insert->execute([
                $recipientId, $file['filename'], $file['original_name'], 
                $file['file_size'], $file['file_type'], $file['display_name'] . ' (Shared)'
            ]);
            return true;
        }
    } elseif ($itemType === 'shortcut') {
        // Fetch the original shortcut details
        $stmt_get = $conn->prepare("SELECT * FROM shortcuts WHERE id = ? AND user_id = ?");
        $stmt_get->execute([$itemId, $senderId]);
        $shortcut = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if ($shortcut) {
            // Insert a new shortcut record for the recipient
            $stmt_insert = $conn->prepare(
                "INSERT INTO shortcuts (user_id, name, url, favicon, created_at) 
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt_insert->execute([
                $recipientId, $shortcut['name'] . ' (Shared)', 
                $shortcut['url'], $shortcut['favicon']
            ]);
            return true;
        }
    }
    return false;
}

// --- Handle Single Item Share Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'share') {
    $item_id = $_POST['item_id'];
    $item_type = $_POST['item_type'];
    $share_option = $_POST['share_option'];
    
    if ($share_option === 'specific') {
        $suc_code = trim($_POST['suc_code']);
        if (empty($suc_code)) {
            header('Location: profile.php?share_error=' . urlencode("SUC Code is required."));
            exit();
        }
        
        // Find the recipient user
        $stmt = $conn->prepare("SELECT id FROM users WHERE suc_code = ?");
        $stmt->execute([$suc_code]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($recipient) {
            if (shareItem($item_id, $item_type, $recipient['id'], $user_id, $conn)) {
                header('Location: profile.php?share_success=' . urlencode("Content shared successfully."));
            } else {
                header('Location: profile.php?share_error=' . urlencode("Failed to share content. You cannot share with yourself."));
            }
        } else {
            header('Location: profile.php?share_error=' . urlencode("User with that SUC Code not found."));
        }
        exit();

    } elseif ($share_option === 'all') {
        // Fetch all users except the current one
        $stmt = $conn->prepare("SELECT id FROM users WHERE id != ?");
        $stmt->execute([$user_id]);
        $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $shared_count = 0;
        foreach($all_users as $user) {
            if (shareItem($item_id, $item_type, $user['id'], $user_id, $conn)) {
                $shared_count++;
            }
        }
        header('Location: profile.php?share_success=' . urlencode("Content shared with " . $shared_count . " users."));
        exit();
    }
}

// --- Handle Bulk Share Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_share') {
    $file_ids = !empty($_POST['selected_files']) ? explode(',', $_POST['selected_files']) : [];
    $shortcut_ids = !empty($_POST['selected_shortcuts']) ? explode(',', $_POST['selected_shortcuts']) : [];
    $share_option = $_POST['share_option'];
    
    if (empty($file_ids) && empty($shortcut_ids)) {
        header('Location: profile.php?share_error=' . urlencode("No items were selected."));
        exit();
    }

    $recipients = [];
    if ($share_option === 'specific') {
        $suc_code = trim($_POST['suc_code']);
        if (empty($suc_code)) {
            header('Location: profile.php?share_error=' . urlencode("SUC Code is required for specific user sharing."));
            exit();
        }
        $stmt = $conn->prepare("SELECT id FROM users WHERE suc_code = ?");
        $stmt->execute([$suc_code]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipient) {
            header('Location: profile.php?share_error=' . urlencode("User with SUC Code not found."));
            exit();
        }
        $recipients[] = $recipient;

    } elseif ($share_option === 'all') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE id != ?");
        $stmt->execute([$user_id]);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($recipients as $recipient) {
        $recipientId = $recipient['id'];
        
        foreach ($file_ids as $item_id) {
            if(trim($item_id) !== '') shareItem(trim($item_id), 'file', $recipientId, $user_id, $conn);
        }
        foreach ($shortcut_ids as $item_id) {
            if(trim($item_id) !== '') shareItem(trim($item_id), 'shortcut', $recipientId, $user_id, $conn);
        }
    }
    
    header('Location: profile.php?share_success=' . urlencode("Selected items shared with " . count($recipients) . " user(s)."));
    exit();
}


// Default redirect if action is not recognized
header('Location: profile.php');
exit();
?>
