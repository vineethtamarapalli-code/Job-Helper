<?php
require_once 'config.php';

// Security Check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- 1. Logout ---
if ($action === 'logout') {
    $_SESSION = [];
    session_unset();
    session_destroy();
    header('Location: admin_login.php');
    exit();
}

// --- 2. Toggle Signup ---
if ($action === 'toggle_signup') {
    $stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'signup_enabled'");
    $current = $stmt->fetchColumn();
    $new_val = ($current == '1') ? '0' : '1';
    
    $update = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'signup_enabled'");
    $update->execute([$new_val]);
    
    session_write_close(); 
    header('Location: admin_panel.php');
    exit();
}

// --- 3. Toggle Suggestions ---
if ($action === 'toggle_suggestions') {
    $stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'suggestions_enabled'");
    $current = $stmt->fetchColumn();
    if ($current === false) $current = '1';
    $new_val = ($current == '1') ? '0' : '1';
    
    $check = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key = 'suggestions_enabled'");
    $check->execute();
    if ($check->rowCount() > 0) {
        $update = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'suggestions_enabled'");
        $update->execute([$new_val]);
    } else {
        $insert = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('suggestions_enabled', ?)");
        $insert->execute([$new_val]);
    }
    
    session_write_close(); 
    header('Location: admin_panel.php');
    exit();
}

// --- 4. Toggle User Block/Active ---
if ($action === 'toggle_block') {
    $user_id = $_POST['user_id'];
    $current_status = $_POST['current_status'];
    $new_status = ($current_status === 'Active') ? 'Blocked' : 'Active';

    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $user_id]);
    
    session_write_close();
    header('Location: admin_panel.php');
    exit();
}

// --- 5. Delete User ---
if ($action === 'delete_user') {
    $user_id = $_POST['user_id'];
    $conn->prepare("DELETE FROM files WHERE user_id = ?")->execute([$user_id]);
    $conn->prepare("DELETE FROM shortcuts WHERE user_id = ?")->execute([$user_id]);
    $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$user_id, $user_id]);
    $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);

    session_write_close();
    header('Location: admin_panel.php');
    exit();
}

// --- 6. Broadcast URL ---
if ($action === 'broadcast_url') {
    $name = $_POST['name'];
    $url = $_POST['url'];
    $domain = parse_url($url, PHP_URL_HOST);
    $favicon = "https://www.google.com/s2/favicons?domain=" . $domain . "&sz=64";
    $users = $conn->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);

    if ($users) {
        $stmt = $conn->prepare("INSERT INTO shortcuts (user_id, name, url, favicon, created_at) VALUES (?, ?, ?, ?, NOW())");
        foreach ($users as $uid) {
            $stmt->execute([$uid, $name, $url, $favicon]);
        }
    }
    session_write_close();
    header('Location: admin_panel.php?msg=broadcast_sent');
    exit();
}

// --- 7. Broadcast File ---
if ($action === 'broadcast_file') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $original_name = $file['name'];
        $file_type = $file['type'];
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $unique_name = uniqid() . '.' . $ext;
        $destination = 'uploads/' . $unique_name;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $users = $conn->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
            if ($users) {
                $stmt = $conn->prepare("INSERT INTO files (user_id, filename, original_name, file_type, display_name, upload_date) VALUES (?, ?, ?, ?, ?, NOW())");
                foreach ($users as $uid) {
                    $stmt->execute([$uid, $unique_name, $original_name, $file_type, $original_name]);
                }
            }
        }
    }
    session_write_close();
    header('Location: admin_panel.php?msg=file_broadcast_sent');
    exit();
}

// --- 8. Send Message ---
if ($action === 'send_message') {
    $msg_type = $_POST['msg_type']; 
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        if ($msg_type === 'all') {
            $stmt = $conn->prepare("INSERT INTO messages (sender_name, message, type, created_at) VALUES ('Admin', ?, 'broadcast', NOW())");
            $stmt->execute([$message]);
        } else {
            $suc_code = trim($_POST['suc_code']);
            $stmt_u = $conn->prepare("SELECT id FROM users WHERE suc_code = ?");
            $stmt_u->execute([$suc_code]);
            $user = $stmt_u->fetch();
            
            if ($user) {
                $stmt = $conn->prepare("INSERT INTO messages (receiver_id, sender_name, message, type, created_at) VALUES (?, 'Admin', ?, 'personal', NOW())");
                $stmt->execute([$user['id'], $message]);
            }
        }
    }
    session_write_close();
    header('Location: admin_panel.php?msg=message_sent');
    exit();
}

// --- 9. Delete All Suggestions ---
if ($action === 'delete_all_suggestions') {
    $stmt = $conn->prepare("DELETE FROM messages WHERE type = 'suggestion'");
    $stmt->execute();
    session_write_close();
    header('Location: admin_panel.php?msg=suggestions_cleared');
    exit();
}

// --- 10. Delete Single Suggestion ---
if ($action === 'delete_suggestion') {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$id]);
    
    session_write_close();
    header('Location: admin_panel.php?msg=suggestion_deleted');
    exit();
}

// --- 11. Update About Page Images ---
if ($action === 'update_about_images') {
    for ($i = 1; $i <= 6; $i++) {
        $fieldName = 'step' . $i . '_image';
        
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$fieldName];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFilename = 'about_step_' . $i . '_' . uniqid() . '.' . $ext;
            $destination = 'uploads/' . $newFilename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $settingKey = 'about_img_step' . $i;

                $check = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key = ?");
                $check->execute([$settingKey]);

                if ($check->rowCount() > 0) {
                    $update = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $update->execute([$newFilename, $settingKey]);
                } else {
                    $insert = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                    $insert->execute([$settingKey, $newFilename]);
                }
            }
        }
    }
    session_write_close();
    header('Location: admin_panel.php?msg=about_images_updated');
    exit();
}

// --- 12. Add Job (FIX: AUTO CREATE TABLE & UPDATE COLS) ---
if ($action === 'add_job') {
    
    // Auto-update schema for new columns (Self-healing for existing tables)
    try {
        $conn->exec("ALTER TABLE jobs ADD COLUMN apply_date DATE DEFAULT NULL");
    } catch (PDOException $e) { /* Ignore if exists */ }
    
    try {
        $conn->exec("ALTER TABLE jobs ADD COLUMN end_date DATE DEFAULT NULL");
    } catch (PDOException $e) { /* Ignore if exists */ }

    // Auto-Create Table if totally missing
    $sql = "CREATE TABLE IF NOT EXISTS jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        company_name VARCHAR(255) NOT NULL,
        job_url VARCHAR(500),
        description TEXT,
        document_path VARCHAR(255),
        apply_date DATE,
        end_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    try {
        $conn->exec($sql);
    } catch (PDOException $e) {
        // Continue even if error
    }

    // Process Data
    $title = $_POST['title'];
    $company = $_POST['company_name'];
    $url = $_POST['job_url'] ?? '';
    $desc = $_POST['description'] ?? '';
    $apply_date = !empty($_POST['apply_date']) ? $_POST['apply_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $docPath = null;

    if (isset($_FILES['job_file']) && $_FILES['job_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['job_file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = 'job_' . uniqid() . '.' . $ext;
        // Ensure uploads dir exists
        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
        
        if (move_uploaded_file($file['tmp_name'], 'uploads/' . $newFilename)) {
            $docPath = $newFilename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO jobs (title, company_name, job_url, description, document_path, apply_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $company, $url, $desc, $docPath, $apply_date, $end_date]);

    session_write_close();
    header('Location: admin_panel.php?msg=job_added');
    exit();
}

// --- 13. Edit Job (NEW) ---
if ($action === 'edit_job') {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $company = $_POST['company_name'];
    $url = $_POST['job_url'] ?? '';
    $desc = $_POST['description'] ?? '';
    $apply_date = !empty($_POST['apply_date']) ? $_POST['apply_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    $fileSql = "";
    $params = [$title, $company, $url, $desc, $apply_date, $end_date];

    // Handle File Upload if provided
    if (isset($_FILES['job_file']) && $_FILES['job_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['job_file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = 'job_' . uniqid() . '.' . $ext;
        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
        
        if (move_uploaded_file($file['tmp_name'], 'uploads/' . $newFilename)) {
            $fileSql = ", document_path = ?";
            $params[] = $newFilename;
        }
    }
    
    $params[] = $id;

    $sql = "UPDATE jobs SET title = ?, company_name = ?, job_url = ?, description = ?, apply_date = ?, end_date = ? $fileSql WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    session_write_close();
    header('Location: admin_panel.php?msg=job_updated');
    exit();
}

// --- 14. Delete Job ---
if ($action === 'delete_job') {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt->execute([$id]);
    
    session_write_close();
    header('Location: admin_panel.php?msg=job_deleted');
    exit();
}

session_write_close();
header('Location: admin_panel.php');
exit();
?>