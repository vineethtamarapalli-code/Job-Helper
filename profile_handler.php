<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$return_url = 'profile.php';

// Helper function for redirection
function redirect_with_error($message, $page = 'profile.php') { // Allow specifying redirect page
    header('Location: ' . $page . '?upload_error=' . urlencode($message));
    exit();
}

function redirect_with_success($message, $page = 'profile.php') { // Allow specifying redirect page
    header('Location: ' . $page . '?upload_success=' . urlencode($message));
    exit();
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_pic') {

    if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
        redirect_with_error('No file was uploaded or an error occurred.');
    }

    $file = $_FILES['profile_pic'];
    $upload_dir = 'profile_pics/';

    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // --- File Validation ---
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        redirect_with_error('File is too large. Maximum size is 5MB.');
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    // --- More robust type checking ---
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $file_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    // --- End robust type checking ---

    if (!in_array($file_type, $allowed_types)) {
        redirect_with_error('Invalid file type. Only PNG, JPG, and JPEG are allowed.');
    }

    // --- Generate Unique Filename ---
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
    $new_filepath = $upload_dir . $new_filename;

    // --- Get Old Picture to Delete Later ---
    $stmt_old = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt_old->execute([$user_id]);
    $old_pic_filename = $stmt_old->fetchColumn();
    $old_filepath = $upload_dir . $old_pic_filename;

    // --- Move File and Update Database ---
    if (move_uploaded_file($file['tmp_name'], $new_filepath)) {
        try {
            $stmt_update = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt_update->execute([$new_filename, $user_id]);

            // --- Delete Old Picture ---
            if (!empty($old_pic_filename) && $old_pic_filename !== 'default_avatar.png' && file_exists($old_filepath)) {
                unlink($old_filepath);
            }

            redirect_with_success('Profile picture updated successfully!');

        } catch (PDOException $e) {
            // If DB update fails, delete the newly uploaded file to prevent orphans
            if (file_exists($new_filepath)) {
                unlink($new_filepath);
            }
            redirect_with_error('Database error. Could not update profile.');
        }
    } else {
        redirect_with_error('Failed to move uploaded file.');
    }
}

// --- NEW: Handle Profile Picture Removal ---
if (isset($_GET['action']) && $_GET['action'] === 'remove_pic') {
    $upload_dir = 'profile_pics/';
    
    // 1. Get Old Picture to Delete
    $stmt_old = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt_old->execute([$user_id]);
    $old_pic_filename = $stmt_old->fetchColumn();
    $old_filepath = $upload_dir . $old_pic_filename;

    if (empty($old_pic_filename) || $old_pic_filename === 'default_avatar.png') {
        // Nothing to remove
        redirect_with_success('You are already using the default picture.', $return_url);
    }

    try {
        // 2. Update Database to NULL (which profile.php interprets as default)
        $stmt_update = $conn->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?");
        $stmt_update->execute([$user_id]);

        // 3. Delete Old Picture File
        if (file_exists($old_filepath)) {
            unlink($old_filepath);
        }
        
        redirect_with_success('Profile picture removed.', $return_url);

    } catch (PDOException $e) {
        redirect_with_error('Database error. Could not remove picture.', $return_url);
    }
}


// --- NEW: Handle Account Deletion ---
if (isset($_GET['action']) && $_GET['action'] === 'delete_account') {
    $upload_dir = 'uploads/';
    $profile_pic_dir = 'profile_pics/';

    try {
        // Start transaction
        $conn->beginTransaction();

        // 1. Get user's files and profile picture
        $stmt_files = $conn->prepare("SELECT filename FROM files WHERE user_id = ?");
        $stmt_files->execute([$user_id]);
        $user_files = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

        $stmt_pic = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $stmt_pic->execute([$user_id]);
        $profile_pic = $stmt_pic->fetchColumn();

        // 2. Delete physical files (only if not shared)
        foreach ($user_files as $file) {
            $filename = $file['filename'];
            $filepath = $upload_dir . $filename;

            // Check if this file is used by any other user
            $stmt_check_share = $conn->prepare("SELECT COUNT(*) FROM files WHERE filename = ? AND user_id != ?");
            $stmt_check_share->execute([$filename, $user_id]);
            $is_shared = $stmt_check_share->fetchColumn() > 0;

            if (!$is_shared && file_exists($filepath)) {
                unlink($filepath);
            }
        }

        // 3. Delete profile picture file (if not default)
        if (!empty($profile_pic) && $profile_pic !== 'default_avatar.png') {
            $pic_filepath = $profile_pic_dir . $profile_pic;
            if (file_exists($pic_filepath)) {
                unlink($pic_filepath);
            }
        }

        // 4. Delete user record from DB (CASCADE should handle related tables)
        $stmt_delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_delete_user->execute([$user_id]);

        // Commit transaction
        $conn->commit();

        // 5. Log out user
        session_unset();
        session_destroy();

        // 6. Redirect to login page with success message
        $success_msg = urlencode("Account deleted successfully.");
        header("Location: index.php?success=$success_msg");
        exit();

    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        redirect_with_error('An error occurred while deleting the account: ' . $e->getMessage());
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        redirect_with_error('An error occurred: ' . $e->getMessage());
    }
}


// Default redirect if no action matches
header('Location: ' . $return_url);
exit();
?>

