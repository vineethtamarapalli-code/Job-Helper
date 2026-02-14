<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'config.php';

// Get SUC code and item details from URL
$suc_code = $_GET['user'] ?? null;
$type = $_GET['type'] ?? null;
$id = $_GET['id'] ?? null;

if (!$suc_code || !$type || !$id) {
    die("Invalid share link.");
}

// Find user by SUC code
$stmt = $conn->prepare("SELECT id, full_name FROM users WHERE suc_code = ?");
$stmt->execute([$suc_code]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

$item = null;
if ($type === 'file') {
    $stmt = $conn->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($type === 'shortcut') {
    $stmt = $conn->prepare("SELECT * FROM shortcuts WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$item) {
    die("Content not found or not available for sharing.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared by <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- MODIFIED: Added cache-busting query string -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    
    <!-- --- MODIFICATION --- -->
    <!-- All inline styles have been moved to style.css -->
    <!-- --- END MODIFICATION --- -->
</head>
<body>
    <div class="share-container">
        <p><strong><?php echo htmlspecialchars($user['full_name']); ?></strong> shared a <?php echo $type; ?> with you:</p>
        
        <?php if ($type === 'file'): ?>
            <h2><i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($item['display_name']); ?></h2>
            <a href="uploads/<?php echo htmlspecialchars($item['filename']); ?>" class="btn-primary" download="<?php echo htmlspecialchars($item['original_name']); ?>">
                <i class="fas fa-download"></i> Download File
            </a>
        <?php else: // shortcut ?>
            <h2><i class="fas fa-link"></i> <?php echo htmlspecialchars($item['name']); ?></h2>
            <a href="<?php echo htmlspecialchars($item['url']); ?>" class="btn-primary" target="_blank">
                <i class="fas fa-external-link-alt"></i> Visit Link
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
