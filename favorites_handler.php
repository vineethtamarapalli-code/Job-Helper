<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$return_page = ($_POST['return_url'] ?? $_GET['return_url'] ?? 'home.php');

// Handle creating a new folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $folder_name = trim($_POST['folder_name']);
    
    if (!empty($folder_name)) {
        $stmt = $conn->prepare("INSERT INTO favorites_folders (user_id, folder_name) VALUES (?, ?)");
        $stmt->execute([$user_id, $folder_name]);
    }
    
    header('Location: ' . $return_page . '?folder_success=1');
    exit();
}

// --- NEW: Handle renaming a folder ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename_folder') {
    $folder_id = $_POST['folder_id'];
    $folder_name = trim($_POST['folder_name']);
    
    if (!empty($folder_id) && !empty($folder_name)) {
        $stmt = $conn->prepare("UPDATE favorites_folders SET folder_name = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$folder_name, $folder_id, $user_id]);
    }
    
    header('Location: ' . $return_page . '?folder_renamed=1');
    exit();
}
// --- END NEW ---


// Handle deleting a folder (and all items in it)
if (isset($_GET['action']) && $_GET['action'] === 'delete_folder' && isset($_GET['id'])) {
    $folder_id = $_GET['id'];
    
    // Delete the folder (and cascade delete will handle items in favorite_items)
    $stmt = $conn->prepare("DELETE FROM favorites_folders WHERE id = ? AND user_id = ?");
    $stmt->execute([$folder_id, $user_id]);
    
    header('Location: ' . $return_page . '?folder_deleted=1');
    exit();
}

// Handle removing a single item from a folder
if (isset($_GET['action']) && $_GET['action'] === 'remove_item' && isset($_GET['id'])) {
    $favorite_item_id = $_GET['id'];
    
    // Delete the specific item from the favorites list
    $stmt = $conn->prepare("DELETE FROM favorite_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$favorite_item_id, $user_id]);
    
    header('Location: ' . $return_page . '?item_removed=1#favorites');
    exit();
}

// Handle adding bulk items to a folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_items') {
    $folder_id = $_POST['folder_id'];
    
    // --- FIX: Handle both array (from home.php) and string (from profile.php) ---
    $file_ids = [];
    if (!empty($_POST['selected_files'])) {
        if (is_array($_POST['selected_files'])) {
            $file_ids = $_POST['selected_files']; // Already an array
        } else {
            $file_ids = explode(',', $_POST['selected_files']); // Is a string, so explode
        }
    }
    
    $shortcut_ids = [];
    if (!empty($_POST['selected_shortcuts'])) {
        if (is_array($_POST['selected_shortcuts'])) {
            $shortcut_ids = $_POST['selected_shortcuts']; // Already an array
        } else {
            $shortcut_ids = explode(',', $_POST['selected_shortcuts']); // Is a string, so explode
        }
    }
    // --- END FIX ---

    if (empty($folder_id)) {
        header('Location: ' . $return_page . '?favorite_error=' . urlencode("Please select a folder."));
        exit();
    }
    if (empty($file_ids) && empty($shortcut_ids)) {
        header('Location: ' . $return_page . '?favorite_error=' . urlencode("No items were selected."));
        exit();
    }
    
    $added_count = 0;
    
    // Add files to the folder
    foreach ($file_ids as $file_id) {
        if (trim($file_id) === '') continue;
        
        // Check if it's already in the folder
        $stmt_check = $conn->prepare("SELECT id FROM favorite_items WHERE folder_id = ? AND file_id = ? AND user_id = ?");
        $stmt_check->execute([$folder_id, $file_id, $user_id]);
        
        if (!$stmt_check->fetch()) {
            $stmt_insert = $conn->prepare("INSERT INTO favorite_items (folder_id, user_id, file_id) VALUES (?, ?, ?)");
            $stmt_insert->execute([$folder_id, $user_id, $file_id]);
            $added_count++;
        }
    }
    
    // Add shortcuts to the folder
    foreach ($shortcut_ids as $shortcut_id) {
        if (trim($shortcut_id) === '') continue;

        // Check if it's already in the folder
        $stmt_check = $conn->prepare("SELECT id FROM favorite_items WHERE folder_id = ? AND shortcut_id = ? AND user_id = ?");
        $stmt_check->execute([$folder_id, $shortcut_id, $user_id]);
        
        if (!$stmt_check->fetch()) {
            $stmt_insert = $conn->prepare("INSERT INTO favorite_items (folder_id, user_id, shortcut_id) VALUES (?, ?, ?)");
            $stmt_insert->execute([$folder_id, $user_id, $shortcut_id]);
            $added_count++;
        }
    }
    
    header('Location: ' . $return_page . '?favorite_success=' . urlencode("$added_count items added to folder."));
    exit();
}


header('Location: ' . $return_page);
exit();
?>
