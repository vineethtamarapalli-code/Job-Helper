<?php
require_once 'config.php';

// Redirect to login page if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$return_page = ($_POST['return_url'] ?? $_GET['return_url'] ?? 'home.php');


// Handle adding a new shortcut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name']);
    $url = trim($_POST['url']);
    
    // Add "https://://" to the URL if it's missing a protocol
    if (!empty($url) && !preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "https://" . $url;
    }
    
    // Fetch the favicon for the website
    $parsed_url = parse_url($url);
    $domain = ($parsed_url['host'] ?? '');
    $favicon = "https://www.google.com/s2/favicons?domain=" . $domain . "&sz=64";
    
    // Insert the new shortcut into the database
    if (!empty($name) && !empty($url)) {
        $stmt = $conn->prepare("INSERT INTO shortcuts (user_id, name, url, favicon, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $name, $url, $favicon]);
    }
    
    header('Location: ' . $return_page);
    exit();
}

// Handle editing a shortcut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $shortcut_id = $_POST['shortcut_id'];
    $name = trim($_POST['name']);
    $url = trim($_POST['url']);

    // Add "https://://" to the URL if it's missing a protocol
    if (!empty($url) && !preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "https://" . $url;
    }

    // Fetch the new favicon
    $parsed_url = parse_url($url);
    $domain = ($parsed_url['host'] ?? '');
    $favicon = "https://www.google.com/s2/favicons?domain=" . $domain . "&sz=64";
    
    if (!empty($shortcut_id) && !empty($name) && !empty($url)) {
        $stmt = $conn->prepare("UPDATE shortcuts SET name = ?, url = ?, favicon = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$name, $url, $favicon, $shortcut_id, $user_id]);
    }

    header('Location: ' . $return_page);
    exit();
}

// Handle shortcut deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Delete the shortcut.
    $stmt = $conn->prepare("DELETE FROM shortcuts WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    
    // --- MODIFICATION: Also delete from favorites ---
    // This makes the logic consistent with file_handler.php
    $stmt_fav = $conn->prepare("DELETE FROM favorite_items WHERE shortcut_id = ? AND user_id = ?");
    $stmt_fav->execute([$id, $user_id]);
    // --- END MODIFICATION ---
    
    header('Location: ' . $return_page);
    exit();
}
?>
