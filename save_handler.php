<?php
require_once 'config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$upload_dir = 'uploads/';

// Ensure upload directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Get JSON data from the request
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

$content = $input['content'] ?? '';
$title = $input['title'] ?? 'Untitled Document';
$file_id = $input['file_id'] ?? null;

// Basic validation
if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Document content is empty']);
    exit();
}

try {
    $filename = '';
    $is_new_file = true;

    // CASE 1: Overwriting an existing file (Edit Mode)
    if ($file_id) {
        // Verify ownership
        $stmt = $conn->prepare("SELECT filename, original_name FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$file_id, $user_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file) {
            $current_filename = $file['filename'];
            $current_ext = pathinfo($current_filename, PATHINFO_EXTENSION);
            $is_new_file = false;

            // SMART CONVERSION LOGIC:
            // If we are saving a .docx file, we must rename it to .html because the content is now HTML.
            // If we leave it as .docx, downloading it will create a "Corrupt File" error in Word.
            if ($current_ext !== 'html' && $current_ext !== 'htm') {
                // Generate new filename with .html extension
                $new_filename = pathinfo($current_filename, PATHINFO_FILENAME) . '.html';
                
                // Rename the original file for backup (optional) or just delete/overwrite logic
                if (file_exists($upload_dir . $current_filename)) {
                    unlink($upload_dir . $current_filename); // Remove old binary file
                }
                
                $filename = $new_filename;
                
                // Update filename in DB to reflect change
                $stmt_update_name = $conn->prepare("UPDATE files SET filename = ?, file_type = 'text/html' WHERE id = ?");
                $stmt_update_name->execute([$filename, $file_id]);
            } else {
                $filename = $current_filename;
            }
            
            // Update the display name and upload date
            $stmt_update = $conn->prepare("UPDATE files SET display_name = ?, upload_date = CURRENT_DATE WHERE id = ?");
            $stmt_update->execute([$title, $file_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found or permission denied']);
            exit();
        }
    }

    // CASE 2: Creating a new file
    if ($is_new_file) {
        $extension = 'html'; // We save rich text as HTML files
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $original_name = $title . '.' . $extension;

        // Insert into DB
        $stmt_insert = $conn->prepare("INSERT INTO files (user_id, filename, original_name, file_size, file_type, upload_date, display_name) VALUES (?, ?, ?, 0, 'text/html', CURRENT_DATE, ?)");
        $stmt_insert->execute([$user_id, $filename, $original_name, $title]);
        $file_id = $conn->lastInsertId();
    }

    // Write content to disk
    if (file_put_contents($upload_dir . $filename, $content) !== false) {
        // Update file size in DB
        $size = filesize($upload_dir . $filename);
        $stmt_size = $conn->prepare("UPDATE files SET file_size = ? WHERE id = ?");
        $stmt_size->execute([$size, $file_id]);

        echo json_encode([
            'success' => true, 
            'message' => 'Document saved successfully',
            'file_id' => $file_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write file to disk']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>