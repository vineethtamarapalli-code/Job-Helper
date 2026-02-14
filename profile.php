<?php
require_once 'config.php';
// Include Gmail config to check session status for the button
require_once 'gmail_config.php'; 

// --- AUTHENTICATION CHECK ---
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT full_name, suc_code, email, created_at, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Set profile picture
$user_pic = $user['profile_pic'] ?? null;
$profile_pic_path = 'profile_pics/' . $user_pic;
$is_default_pic = empty($user_pic) || !file_exists($profile_pic_path);

if ($is_default_pic) {
    $profile_pic_path = 'profile_pics/default_avatar.png'; 
}

// Fetch other users
$stmt_all_users = $conn->prepare("SELECT suc_code, full_name FROM users WHERE id != ?");
$stmt_all_users->execute([$user_id]);
$all_users = $stmt_all_users->fetchAll(PDO::FETCH_ASSOC);

// Fetch files and shortcuts
$stmt_files = $conn->prepare("SELECT * FROM files WHERE user_id = ? ORDER BY upload_date DESC, id DESC");
$stmt_files->execute([$user_id]);
$files = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

$stmt_shortcuts = $conn->prepare("SELECT * FROM shortcuts WHERE user_id = ? ORDER BY created_at DESC");
$stmt_shortcuts->execute([$user_id]);
$shortcuts = $stmt_shortcuts->fetchAll(PDO::FETCH_ASSOC);

$file_count = count($files);
$shortcut_count = count($shortcuts);

// Fetch favorite folders
$stmt_folders = $conn->prepare("SELECT id, folder_name FROM favorites_folders WHERE user_id = ? ORDER BY folder_name ASC");
$stmt_folders->execute([$user_id]);
$favorite_folders = $stmt_folders->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Helper - Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">

    <style>
        /* Profile Grid Layout */
        .profile-content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.25rem;
        }

        .profile-item-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            position: relative;
            transition: all 0.3s ease-in-out;
            border: 1px solid #f0f0f0;
        }
        
        .profile-item-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }
        
        .profile-item-card.selected {
            box-shadow: 0 0 0 2px #4A00E0;
            background-color: #f4f0ff;
        }

        /* Class for soft deletion (Undo state) */
        .profile-item-card.soft-deleted {
            opacity: 0;
            transform: scale(0.9);
            pointer-events: none;
        }

        .select-checkbox {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            transform: scale(1);
            margin-right: 0; 
            z-index: 10;
        }
        
        .profile-item-icon {
            font-size: 1.75rem;
            margin-right: 0;
            margin-bottom: 0.5rem;
            color: #4A00E0;
            width: 100%;
            text-align: center;
            padding-top: 1.25rem;
        }
        
        .shortcut-favicon {
            width: 28px;
            height: 28px;
            margin-bottom: 0.5rem;
        }
        
        .profile-item-icon .shortcut-favicon,
        .profile-item-icon .fa-link {
            display: block;
            margin: 0 auto 0.5rem auto;
        }

        .profile-item-content {
            width: 100%;
        }

        .profile-item-content h4 {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            white-space: nowrap; 
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            font-weight: 600;
            color: #333;
        }
        
        .profile-item-content p {
            font-size: 0.75rem;
            margin-top: 0;
            color: #888;
        }

        .card-actions {
            margin-top: 0.75rem;
            padding-top: 0.5rem;
            border-top: 1px solid #f0f0f0;
            width: 100%;
            display: flex;
            justify-content: center;
            gap: 0.25rem;
        }

        .card-actions .action-btn {
            padding: 0.35rem;
            font-size: 0.8rem;
            background-color: #f4f4f4;
            border-radius: 6px;
            color: #555;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-actions .action-btn:hover {
            background-color: #e9e9e9;
            color: #333;
        }
        
        .card-actions .action-btn.delete:hover {
            background-color: #fee2e2;
            color: #ef4444;
        }

        .content-management-area .tab-pane {
            max-height: 42rem;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 0.5rem;
        }

        .profile-avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .profile-avatar-placeholder .fa-user {
            font-size: 60px;
            color: #adb5bd;
        }

        .content-management-area {
            background: rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        /* Vocalize CTA Style */
        .vocalize-cta-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
            text-align: center;
        }
        .vocalize-cta-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .vocalize-cta-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }

        .profile-email {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.25rem;
            margin-top: -0.25rem; 
        }
        .profile-suc {
            margin-bottom: 0.5rem;
        }

        /* Undo/Redo Toast Styles */
        .undo-toast-container {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            pointer-events: none; 
        }

        .undo-toast {
            background-color: #333;
            color: #fff;
            padding: 12px 24px;
            border-radius: 50px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 16px;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.3s, transform 0.3s;
            pointer-events: auto;
            min-width: 300px;
            justify-content: space-between;
        }

        .undo-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .undo-toast-message {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .undo-toast-actions {
            display: flex;
            gap: 10px;
        }

        .undo-btn, .redo-btn {
            background: none;
            border: none;
            color: #a78bfa; /* Light purple */
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 0.85rem;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .undo-btn:hover, .redo-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .toast-close-btn {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0 4px;
            line-height: 1;
        }
        .toast-close-btn:hover {
            color: #fff;
        }

    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">Job Helper</h1>
        <nav class="nav">
            <a href="home.php" class="nav-link">Home</a>
            <a href="profile.php" class="nav-link active">Profile</a>
                <a href="about.php" class="nav-link about-btn">About</a>
            <a href="auth.php?action=logout" class="nav-link logout" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </nav>
         <button class="mobile-nav-toggle" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h2>My Profile</h2>
                <p>Manage your account information and content</p>
            </div>

            <?php if (isset($_GET['upload_success'])): ?>
                <p class="form-message success"><?php echo htmlspecialchars($_GET['upload_success'], ENT_QUOTES); ?></p>
            <?php endif; ?>
             <?php if (isset($_GET['upload_error'])): ?>
                <p class="form-message error"><?php echo htmlspecialchars($_GET['upload_error'], ENT_QUOTES); ?></p>
            <?php endif; ?>

            <div class="profile-container">
                <div class="profile-card">
                    <div class="profile-avatar-container">
                        <?php if ($is_default_pic): ?>
                            <div class="profile-avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($profile_pic_path, ENT_QUOTES); ?>?v=<?php echo time(); ?>" alt="Profile Picture" class="profile-avatar-img">
                        <?php endif; ?>
                         
                         <button class="profile-avatar-edit-btn" title="Change picture" onclick="showProfilePicModal()">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>

                    <h2><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?></h2>
                    
                    <p class="profile-suc">SUC Code: <?php echo htmlspecialchars($user['suc_code'], ENT_QUOTES); ?></p>
                    
                    <?php if (!empty($user['email'])): ?>
                        <p class="profile-email"><i class="fas fa-envelope" style="font-size: 0.8em; margin-right: 5px;"></i> <?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?></p>
                    <?php endif; ?>
                    
                    <p class="profile-date">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                    
                     <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-file"></i></div>
                            <h3><span id="stat-file-count"><?php echo $file_count; ?></span></h3>
                            <p>Files</p>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-link"></i></div>
                            <h3><span id="stat-shortcut-count"><?php echo $shortcut_count; ?></span></h3>
                            <p>Shortcuts</p>
                        </div>
                    </div>

                    <div class="integration-section" style="margin-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.2); padding-top: 2rem;">
                        <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 1rem; color: #333;">Integrations</h3>
                        
                        <!-- GMAIL BUTTON LOGIC -->
                        <?php if(isset($_SESSION['gmail_access_token'])): ?>
                            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                <a href="gmail_view.php" class="btn-integration" title="View your emails" style="flex: 1;">
                                    <i class="fab fa-google"></i> View Gmail
                                </a>
                                <a href="gmail_login.php?action=logout" class="btn-integration" style="background: #fee2e2; color: #ef4444; width: auto; flex: 0 0 auto; border: 1px solid #fecaca;" title="Disconnect">
                                    <i class="fas fa-unlink"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <a href="gmail_login.php" class="btn-integration" title="Connect your Gmail account">
                                <i class="fab fa-google"></i> Connect Gmail
                            </a>
                        <?php endif; ?>

                    </div>

                     <button class="btn-danger delete-account-btn" onclick="confirmAccountDeletion()">
                        <i class="fas fa-trash-alt"></i> Delete Account
                     </button>

                </div>

                <div class="content-management-area">
                    <div class="content-management-header">
                        <h3>Manage Your Content</h3>
                        <div class="search-bar profile-search-bar">
                             <i class="fas fa-search"></i>
                             <input type="text" id="profileSearchInput" onkeyup="filterProfileContent()" placeholder="Search your items...">
                         </div>
                        <div class="bulk-actions" id="bulkActions" style="display: none;">
                             <button type="button" class="bulk-action-icon-btn select-all-btn" onclick="toggleSelectAll(this)" title="Select All">
                                <i class="fas fa-check-square"></i>
                             </button>
                             <button type="button" class="bulk-action-icon-btn favorite-btn" onclick="showAddToFavoriteModal()" title="Add to Favorite">
                                <i class="fas fa-star"></i>
                             </button>
                             <button type="button" class="bulk-action-icon-btn share-btn" onclick="showBulkShareModal()" title="Share Selected">
                                <i class="fas fa-share-alt"></i>
                             </button>
                            <button type="button" class="bulk-action-icon-btn delete-btn" onclick="confirmBulkDelete()" title="Delete Selected">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php if (isset($_GET['share_success'])): ?>
                        <p class="form-message success"><?php echo htmlspecialchars($_GET['share_success'], ENT_QUOTES); ?></p>
                    <?php endif; ?>
                     <?php if (isset($_GET['share_error'])): ?>
                        <p class="form-message error"><?php echo htmlspecialchars($_GET['share_error'], ENT_QUOTES); ?></p>
                    <?php endif; ?>
                     <?php if (isset($_GET['fav_success']) || isset($_GET['favorite_success'])): ?>
                        <p class="form-message success"><?php echo htmlspecialchars($_GET['fav_success'] ?? $_GET['favorite_success'], ENT_QUOTES); ?></p>
                    <?php endif; ?>
                     <?php if (isset($_GET['fav_error']) || isset($_GET['favorite_error'])): ?>
                        <p class="form-message error"><?php echo htmlspecialchars($_GET['fav_error'] ?? $_GET['favorite_error'], ENT_QUOTES); ?></p>
                    <?php endif; ?>

                    <!-- Tab Navigation -->
                    <div class="tab-navigation">
                        <button class="tab-btn active" onclick="openTab(event, 'profile-all')">All Items</button>
                        <button class="tab-btn" onclick="openTab(event, 'profile-files')">My Files</button>
                        <button class="tab-btn" onclick="openTab(event, 'profile-shortcuts')">My Shortcuts</button>
                        <button class="tab-btn" onclick="openTab(event, 'profile-vocalize')">Vocalize</button>
                    </div>

                    <?php if (count($files) === 0 && count($shortcuts) === 0): ?>
                        <div class="empty-state" id="profile-empty-state">
                            <i class="fas fa-box-open"></i>
                            <h3>No content yet</h3>
                            <p>Upload files or add shortcuts on the home page</p>
                        </div>
                    <?php endif; ?>

                    <!-- Hidden JS empty state -->
                    <div class="empty-state" id="js-empty-state" style="display: none;">
                        <i class="fas fa-box-open"></i>
                        <h3>No content yet</h3>
                        <p>Upload files or add shortcuts on the home page</p>
                    </div>
                    
                    <form id="bulkActionForm" action="file_handler.php" method="POST">
                             <input type="hidden" name="action" id="bulkActionInput" value="bulk_delete">
                             <input type="hidden" name="return_url" value="profile.php">
                             <input type="hidden" name="selected_files" id="bulkFileIds">
                             <input type="hidden" name="selected_shortcuts" id="bulkShortcutIds">

                             <!-- All Items Tab -->
                             <div id="profile-all" class="tab-pane active">
                                 <div class="profile-content-grid">
                                    <?php
                                    $item_index = 0;
                                    foreach($files as $file):
                                    ?>
                                        <div class="profile-item-card" id="card-all-file-<?php echo $file['id']; ?>" data-title="<?php echo htmlspecialchars($file['display_name'], ENT_QUOTES); ?>" style="animation-delay: <?php echo $item_index * 0.05; ?>s;">
                                            <input type="checkbox" data-type="file" data-id="<?php echo $file['id']; ?>" class="select-checkbox" onclick="toggleBulkActions(this)">
                                            <div class="profile-item-icon">
                                                <i class="fas <?php
                                                    $type = $file['file_type'];
                                                    if (strpos($type, 'image') !== false) echo 'fa-file-image';
                                                    elseif (strpos($type, 'video') !== false) echo 'fa-file-video';
                                                    elseif (strpos($type, 'pdf') !== false) echo 'fa-file-pdf';
                                                    elseif (strpos($type, 'audio') !== false) echo 'fa-file-audio';
                                                    elseif (strpos($type, 'word') !== false) echo 'fa-file-word';
                                                    elseif (strpos($type, 'excel') !== false) echo 'fa-file-excel';
                                                    elseif (strpos($type, 'powerpoint') !== false) echo 'fa-file-powerpoint';
                                                    elseif (strpos($type, 'zip') !== false || strpos($type, 'archive') !== false) echo 'fa-file-archive';
                                                    else echo 'fa-file-alt';
                                                ?>"></i>
                                            </div>
                                            <div class="profile-item-content">
                                                <h4 title="<?php echo htmlspecialchars($file['display_name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($file['display_name'], ENT_QUOTES); ?></h4>
                                                <p>File</p>
                                            </div>
                                            <div class="card-actions">
                                                <button type="button" class="action-btn" onclick="showSingleFavoriteModal(<?php echo $file['id']; ?>, 'file')" title="Add to Favorite"><i class="fas fa-star"></i></button>
                                                <button type="button" class="action-btn" onclick="showShareModal(<?php echo $file['id']; ?>, 'file')" title="Share"><i class="fas fa-share-alt"></i></button>
                                                <button type="button" class="action-btn" onclick="showEditFileModal(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars(addslashes($file['display_name'] ?? ''), ENT_QUOTES); ?>')" title="Edit"><i class="fas fa-pen"></i></button>
                                                <button type="button" class="action-btn delete" onclick="deleteFile(<?php echo $file['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                    <?php
                                    $item_index++;
                                    endforeach;
                                    ?>
                                     <?php foreach($shortcuts as $shortcut): ?>
                                        <div class="profile-item-card" id="card-all-shortcut-<?php echo $shortcut['id']; ?>" data-title="<?php echo htmlspecialchars($shortcut['name'], ENT_QUOTES); ?>" style="animation-delay: <?php echo $item_index * 0.05; ?>s;">
                                            <input type="checkbox" data-type="shortcut" data-id="<?php echo $shortcut['id']; ?>" class="select-checkbox" onclick="toggleBulkActions(this)">
                                            <div class="profile-item-icon">
                                                <img src="<?php echo htmlspecialchars($shortcut['favicon'], ENT_QUOTES); ?>" alt="" class="shortcut-favicon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                                <i class="fas fa-link" style="display:none;"></i>
                                            </div>
                                            <div class="profile-item-content">
                                                <h4 title="<?php echo htmlspecialchars($shortcut['name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($shortcut['name'], ENT_QUOTES); ?></h4>
                                                <p>URL Shortcut</p>
                                            </div>
                                             <div class="card-actions">
                                                <button type="button" class="action-btn" onclick="showSingleFavoriteModal(<?php echo $shortcut['id']; ?>, 'shortcut')" title="Add to Favorite"><i class="fas fa-star"></i></button>
                                                <button type="button" class="action-btn" onclick="showShareModal(<?php echo $shortcut['id']; ?>, 'shortcut')" title="Share"><i class="fas fa-share-alt"></i></button>
                                                <button type="button" class="action-btn" onclick="showEditShortcutModal(<?php echo $shortcut['id']; ?>, '<?php echo htmlspecialchars(addslashes($shortcut['name']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($shortcut['url']), ENT_QUOTES); ?>')" title="Edit"><i class="fas fa-pen"></i></button>
                                                <button type="button" class="action-btn delete" onclick="deleteShortcut(<?php echo $shortcut['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                    <?php
                                    $item_index++;
                                    endforeach;
                                    ?>
                                </div>
                             </div>

                             <!-- My Files Tab -->
                             <div id="profile-files" class="tab-pane">
                                <div class="profile-content-grid">
                                    <?php if (count($files) === 0): ?>
                                        <div class="empty-state" style="grid-column: 1 / -1;"><i class="fas fa-file-alt"></i><h3>No files uploaded</h3></div>
                                    <?php else: ?>
                                        <?php
                                        $item_index = 0;
                                        foreach($files as $file):
                                        ?>
                                            <div class="profile-item-card" id="card-files-file-<?php echo $file['id']; ?>" data-title="<?php echo htmlspecialchars($file['display_name'], ENT_QUOTES); ?>" style="animation-delay: <?php echo $item_index * 0.05; ?>s;">
                                                <input type="checkbox" data-type="file" data-id="<?php echo $file['id']; ?>" class="select-checkbox" onclick="toggleBulkActions(this)">
                                                <div class="profile-item-icon">
                                                    <i class="fas <?php
                                                        $type = $file['file_type'];
                                                        if (strpos($type, 'image') !== false) echo 'fa-file-image';
                                                        elseif (strpos($type, 'video') !== false) echo 'fa-file-video';
                                                        elseif (strpos($type, 'pdf') !== false) echo 'fa-file-pdf';
                                                        elseif (strpos($type, 'audio') !== false) echo 'fa-file-audio';
                                                        elseif (strpos($type, 'word') !== false) echo 'fa-file-word';
                                                        elseif (strpos($type, 'excel') !== false) echo 'fa-file-excel';
                                                        elseif (strpos($type, 'powerpoint') !== false) echo 'fa-file-powerpoint';
                                                        elseif (strpos($type, 'zip') !== false || strpos($type, 'archive') !== false) echo 'fa-file-archive';
                                                        else echo 'fa-file-alt';
                                                    ?>"></i>
                                                </div>
                                                <div class="profile-item-content">
                                                    <h4 title="<?php echo htmlspecialchars($file['display_name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($file['display_name'], ENT_QUOTES); ?></h4>
                                                    <p>File</p>
                                                </div>
                                                <div class="card-actions">
                                                    <button type="button" class="action-btn" onclick="showSingleFavoriteModal(<?php echo $file['id']; ?>, 'file')" title="Add to Favorite"><i class="fas fa-star"></i></button>
                                                    <button type="button" class="action-btn" onclick="showShareModal(<?php echo $file['id']; ?>, 'file')" title="Share"><i class="fas fa-share-alt"></i></button>
                                                    <button type="button" class="action-btn" onclick="showEditFileModal(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars(addslashes($file['display_name'] ?? ''), ENT_QUOTES); ?>')" title="Edit"><i class="fas fa-pen"></i></button>
                                                    <button type="button" class="action-btn delete" onclick="deleteFile(<?php echo $file['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </div>
                                        <?php
                                        $item_index++;
                                        endforeach;
                                        ?>
                                    <?php endif; ?>
                                </div>
                             </div>

                             <!-- My Shortcuts Tab -->
                             <div id="profile-shortcuts" class="tab-pane">
                                <div class="profile-content-grid">
                                    <?php if (count($shortcuts) === 0): ?>
                                        <div class="empty-state" style="grid-column: 1 / -1;"><i class="fas fa-link"></i><h3>No shortcuts added</h3></div>
                                    <?php else: ?>
                                        <?php
                                        $item_index = 0;
                                        foreach($shortcuts as $shortcut):
                                        ?>
                                            <div class="profile-item-card" id="card-shortcuts-shortcut-<?php echo $shortcut['id']; ?>" data-title="<?php echo htmlspecialchars($shortcut['name'], ENT_QUOTES); ?>" style="animation-delay: <?php echo $item_index * 0.05; ?>s;">
                                                <input type="checkbox" data-type="shortcut" data-id="<?php echo $shortcut['id']; ?>" class="select-checkbox" onclick="toggleBulkActions(this)">
                                                <div class="profile-item-icon">
                                                    <img src="<?php echo htmlspecialchars($shortcut['favicon'], ENT_QUOTES); ?>" alt="" class="shortcut-favicon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                                    <i class="fas fa-link" style="display:none;"></i>
                                                </div>
                                                <div class="profile-item-content">
                                                    <h4 title="<?php echo htmlspecialchars($shortcut['name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($shortcut['name'], ENT_QUOTES); ?></h4>
                                                    <p>URL Shortcut</p>
                                                </div>
                                                 <div class="card-actions">
                                                    <button type="button" class="action-btn" onclick="showSingleFavoriteModal(<?php echo $shortcut['id']; ?>, 'shortcut')" title="Add to Favorite"><i class="fas fa-star"></i></button>
                                                    <button type="button" class="action-btn" onclick="showShareModal(<?php echo $shortcut['id']; ?>, 'shortcut')" title="Share"><i class="fas fa-share-alt"></i></button>
                                                    <button type="button" class="action-btn" onclick="showEditShortcutModal(<?php echo $shortcut['id']; ?>, '<?php echo htmlspecialchars(addslashes($shortcut['name']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($shortcut['url']), ENT_QUOTES); ?>')" title="Edit"><i class="fas fa-pen"></i></button>
                                                    <button type="button" class="action-btn delete" onclick="deleteShortcut(<?php echo $shortcut['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </div>
                                        <?php
                                        $item_index++;
                                        endforeach;
                                        ?>
                                    <?php endif; ?>
                                </div>
                             </div>
                    </form>
                    
                    <!-- NEW: Vocalize Link Tab -->
                    <div id="profile-vocalize" class="tab-pane" style="display: none;">
                        <div class="vocalize-cta-container">
                            <h3 style="color: #4A00E0; margin-bottom: 1rem;">Translate & Speak Studio</h3>
                            <p style="color: #666; margin-bottom: 2rem;">Access advanced voice tools, translations, and audio generation.</p>
                            <a href="vocalize.php" class="vocalize-cta-btn">
                                <i class="fas fa-microphone-alt"></i> Open Vocalize Studio
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- Undo Toast Container -->
    <div class="undo-toast-container">
        <div id="undoToast" class="undo-toast">
            <span class="undo-toast-message" id="undoToastMessage">Item deleted</span>
            <div class="undo-toast-actions">
                <button type="button" class="undo-btn" id="undoBtn">Undo</button>
                <button type="button" class="redo-btn" id="redoBtn" style="display:none;">Redo</button>
                <button type="button" class="toast-close-btn" onclick="undoManager.dismissToast()">&times;</button>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="profilePicModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('profilePicModal')">&times;</span>
            <h2>Upload New Profile Picture</h2>
            <form action="profile_handler.php" method="POST" enctype="multipart/form-data" id="uploadPicForm">
                <input type="hidden" name="action" value="upload_pic">
                <div class="form-group" style="text-align: center;">
                    <label for="profilePicInputFile" class="btn-file-choose">
                        <i class="fas fa-upload"></i> Choose an image...
                    </label>
                    <input type="file" name="profile_pic" id="profilePicInputFile" class="form-group-input" accept="image/png, image/jpeg, image/jpg" required onchange="displaySelectedFileName(this)">
                    <p id="selectedFileName"></p>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Upload</button>
            </form>
            <?php if (!$is_default_pic): ?>
                <button type="button" class="btn-danger" onclick="confirmRemovePic()" style="width: 100%; margin-top: 1rem;">
                    <i class="fas fa-trash-alt"></i> Remove Current Picture
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="editFileModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('editFileModal')">&times;</span><h2>Edit File Name</h2><form action="file_handler.php" method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="file_id" id="editFileId"><input type="hidden" name="return_url" value="profile.php"><div class="form-group"><label for="editFileName">File Name</label><input type="text" name="display_name" id="editFileName" placeholder="Enter a new name" required></div><button type="submit" class="btn-primary">Save Changes</button></form></div></div>
    <div id="editShortcutModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('editShortcutModal')">&times;</span><h2>Edit URL Shortcut</h2><form action="shortcut_handler.php" method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="shortcut_id" id="editShortcutId"><input type="hidden" name="return_url" value="profile.php"><div class="form-group"><label for="editShortcutName">Name</label><input type="text" name="name" id="editShortcutName" placeholder="e.g., Google" required></div><div class="form-group"><label for="editShortcutUrl">URL</label><input type="text" name="url" id="editShortcutUrl" placeholder="e.g., google.com" required></div><button type="submit" class="btn-primary">Save Changes</button></form></div></div>

    <div id="shareModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('shareModal')">&times;</span><h2>Share Content</h2><form action="share_handler.php" method="POST"><input type="hidden" name="action" value="share"><input type="hidden" name="item_id" id="shareItemId"><input type="hidden" name="item_type" id="shareItemType"><div class="form-group share-options"><label><input type="radio" name="share_option" value="specific" checked onchange="toggleSucInput(true)"> Share with a specific user</label><label><input type="radio" name="share_option" value="all" onchange="toggleSucInput(false)"> Share with all users</label></div><div class="form-group" id="sucInputContainer"><label for="shareSucCode">User SUC Code</label><input type="text" name="suc_code" id="shareSucCode" placeholder="Enter SUC Code" list="usersDatalist"><datalist id="usersDatalist"><?php foreach($all_users as $u): ?><option value="<?php echo htmlspecialchars($u['suc_code'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($u['full_name'], ENT_QUOTES); ?></option><?php endforeach; ?></datalist></div><button type="submit" class="btn-primary">Share</button></form></div></div>
    <div id="bulkShareModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('bulkShareModal')">&times;</span><h2>Share Selected Content</h2><p><span id="bulkShareItemCount">0</span> items selected.</p><form action="share_handler.php" method="POST"><input type="hidden" name="action" value="bulk_share"><input type="hidden" name="selected_files" id="bulkShareFileIds"><input type="hidden" name="selected_shortcuts" id="bulkShareShortcutIds"><div class="form-group share-options"><label><input type="radio" name="share_option" value="specific" checked onchange="toggleBulkSucInput(true)"> Share with a specific user</label><label><input type="radio" name="share_option" value="all" onchange="toggleBulkSucInput(false)"> Share with all users</label></div><div class="form-group" id="bulkSucInputContainer"><label for="bulkShareSucCode">User SUC Code</label><input type="text" name="suc_code" id="bulkShareSucCode" placeholder="Enter SUC Code" list="usersDatalist"></div><button type="submit" class="btn-primary">Share Items</button></form></div></div>

    <div id="addToFavoriteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addToFavoriteModal')">&times;</span>
            <h2>Add to Favorite</h2>
            <p><span id="favoriteItemCount">0</span> items selected.</p>
            <form action="favorites_handler.php" method="POST">
                <input type="hidden" name="action" value="add_items">
                <input type="hidden" name="return_url" value="profile.php?favorite_success=Items added to folder!">
                <input type="hidden" name="selected_files" id="favoriteFileIds">
                <input type="hidden" name="selected_shortcuts" id="favoriteShortcutIds">

                <div class="form-group">
                    <label for="folderSelect">Select a Folder</label>
                    <select name="folder_id" id="folderSelect" class="form-group-input" required>
                        <?php if (count($favorite_folders) === 0): ?>
                            <option value="" disabled>You have no folders. Create one on the Home page.</option>
                        <?php else: ?>
                            <option value="" disabled selected>Choose a folder...</option>
                            <?php foreach($favorite_folders as $folder): ?>
                                <option value="<?php echo $folder['id']; ?>"><?php echo htmlspecialchars($folder['folder_name'], ENT_QUOTES); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary" <?php if (count($favorite_folders) === 0) echo 'disabled'; ?>>Add to Folder</button>
            </form>
        </div>
    </div>

    <div id="alertModal" class="modal">
        <div class="modal-content alert-modal-content">
            <h2 id="alertModalTitle" style="margin-bottom: 0.5rem;"></h2>
            <p id="alertModalMessage" style="margin: 1rem 0 2rem; color: #666;"></p>
            <div id="alertModalActions" style="display: flex; justify-content: center; gap: 1rem;">
                <button id="alertModalConfirmBtn" class="btn-primary"></button>
                <button id="alertModalCancelBtn" class="btn-outline" style="border: 1px solid #ccc;" onclick="closeModal('alertModal')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // --- UNDO MANAGER CLASS ---
        class UndoManager {
            constructor() {
                this.pendingAction = null;
                this.redoStack = [];
                this.toast = document.getElementById('undoToast');
                this.messageEl = document.getElementById('undoToastMessage');
                this.undoBtn = document.getElementById('undoBtn');
                this.redoBtn = document.getElementById('redoBtn');
                this.timeoutId = null;
                this.delay = 4000; 

                this.undo = this.undo.bind(this);
                this.redo = this.redo.bind(this);
                this.commit = this.commit.bind(this);
                this.undoBtn.addEventListener('click', this.undo);
                this.redoBtn.addEventListener('click', this.redo);

                window.addEventListener('beforeunload', () => {
                    if (this.pendingAction) {
                        this.commit();
                    }
                });
            }

            add(action) {
                if (this.pendingAction) {
                    this.commit();
                }
                this.redoStack = []; 
                this.redoBtn.style.display = 'none';
                
                this.pendingAction = action;
                this.applyVisuals(action, true); 
                this.startTimer();
                this.showToast(action.message || "Item deleted", true);
            }

            undo() {
                if (!this.pendingAction) return;
                const action = this.pendingAction;
                this.clearTimer();
                this.applyVisuals(action, false); 
                this.redoStack.push(action);
                this.pendingAction = null;
                this.messageEl.textContent = "Action undone";
                this.undoBtn.style.display = 'none';
                this.redoBtn.style.display = 'inline-block';
                this.timeoutId = setTimeout(() => {
                    this.dismissToast();
                }, 3000);
            }

            redo() {
                if (this.redoStack.length === 0) return;
                const action = this.redoStack.pop();
                this.pendingAction = action;
                this.applyVisuals(action, true);
                this.startTimer();
                this.undoBtn.style.display = 'inline-block';
                this.redoBtn.style.display = 'none';
                this.messageEl.textContent = "Item deleted";
            }

            commit() {
                if (!this.pendingAction) return;
                const action = this.pendingAction;
                this.clearTimer();

                if (action.type === 'bulk_delete') {
                    const formData = new FormData();
                    formData.append('action', 'bulk_delete');
                    formData.append('selected_files', action.fileIds.join(','));
                    formData.append('selected_shortcuts', action.shortcutIds.join(','));
                    formData.append('return_url', 'profile.php'); 

                    fetch('file_handler.php', {
                        method: 'POST',
                        body: formData,
                        keepalive: true
                    }).catch(err => console.error('Delete failed', err));

                } else {
                    fetch(action.url, {
                        method: 'GET',
                        keepalive: true 
                    }).catch(err => console.error('Delete failed', err));
                }

                action.elements.forEach(el => {
                    if(el) el.remove(); 
                });

                this.pendingAction = null;
                this.dismissToast();
            }

            applyVisuals(action, isDeleting) {
                action.elements.forEach(el => {
                    if (el) {
                        if (isDeleting) {
                            el.classList.add('soft-deleted');
                            const cb = el.querySelector('.select-checkbox');
                            if(cb) cb.checked = false;
                        } else {
                            el.classList.remove('soft-deleted');
                        }
                    }
                });

                const fileCountEl = document.getElementById('stat-file-count');
                const shortcutCountEl = document.getElementById('stat-shortcut-count');
                
                let fCount = parseInt(fileCountEl.textContent);
                let sCount = parseInt(shortcutCountEl.textContent);
                
                let fDelta = 0;
                let sDelta = 0;

                if (action.type === 'delete_file') fDelta = 1;
                else if (action.type === 'delete_shortcut') sDelta = 1;
                else if (action.type === 'bulk_delete') {
                    fDelta = action.fileIds.length;
                    sDelta = action.shortcutIds.length;
                }

                if (isDeleting) {
                    fileCountEl.textContent = Math.max(0, fCount - fDelta);
                    shortcutCountEl.textContent = Math.max(0, sCount - sDelta);
                } else {
                    fileCountEl.textContent = fCount + fDelta;
                    shortcutCountEl.textContent = sCount + sDelta;
                }

                this.checkEmptyState();
                if (isDeleting) {
                    toggleBulkActions(); 
                }
            }

            checkEmptyState() {
                const visibleFiles = document.querySelectorAll('#profile-files .profile-item-card:not(.soft-deleted)').length;
                const visibleShortcuts = document.querySelectorAll('#profile-shortcuts .profile-item-card:not(.soft-deleted)').length;
                
                const emptyState = document.getElementById('profile-empty-state'); 
                const jsEmptyState = document.getElementById('js-empty-state'); 
                
                if(emptyState) emptyState.style.display = 'none';

                if (visibleFiles === 0 && visibleShortcuts === 0) {
                    jsEmptyState.style.display = 'block';
                } else {
                    jsEmptyState.style.display = 'none';
                }
            }

            startTimer() {
                this.clearTimer();
                this.timeoutId = setTimeout(() => {
                    this.commit();
                }, this.delay);
            }

            clearTimer() {
                if (this.timeoutId) {
                    clearTimeout(this.timeoutId);
                    this.timeoutId = null;
                }
            }

            showToast(msg, showUndo) {
                this.messageEl.textContent = msg;
                this.undoBtn.style.display = showUndo ? 'inline-block' : 'none';
                this.redoBtn.style.display = 'none';
                this.toast.classList.add('show');
            }

            dismissToast() {
                this.toast.classList.remove('show');
            }
        }

        const undoManager = new UndoManager();

        document.addEventListener('DOMContentLoaded', () => {
            const navToggle = document.querySelector('.mobile-nav-toggle');
            const nav = document.querySelector('.nav');
            navToggle.addEventListener('click', () => {
                nav.classList.toggle('active');
            });
        });

        function displaySelectedFileName(input) {
            const fileNameDisplay = document.getElementById('selectedFileName');
            if (input.files && input.files.length > 0) {
                fileNameDisplay.textContent = input.files[0].name;
            } else {
                fileNameDisplay.textContent = '';
            }
        }

        function openTab(evt, tabName) {
            const tabcontent = document.querySelectorAll('.content-management-area .tab-pane');
            for (let i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
                tabcontent[i].classList.remove("active");
            }
            const tablinks = document.querySelectorAll('.content-management-area .tab-btn');
            for (let i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }

            const selectedTab = document.getElementById(tabName);
            selectedTab.style.display = "block";
            selectedTab.classList.add("active");
            evt.currentTarget.classList.add("active");

            if (tabName === 'profile-vocalize') {
                const emptyState = document.getElementById('profile-empty-state');
                if(emptyState) emptyState.style.display = 'none';
                document.getElementById('js-empty-state').style.display = 'none';
                document.getElementById('bulkActions').style.display = 'none';
            } else {
                 undoManager.checkEmptyState();
            }

            const newCards = selectedTab.querySelectorAll('.profile-item-card:not(.soft-deleted)');
            newCards.forEach((card, index) => {
                card.style.animation = 'none';
                card.style.opacity = '0';
                void card.offsetWidth;
                card.style.animation = `popIn 0.5s ease-out forwards`;
                card.style.animationDelay = `${index * 0.05}s`;
            });

             clearSelectionVisuals();
             if (tabName !== 'profile-vocalize') {
                 toggleBulkActions();
                 document.getElementById('profileSearchInput').value = '';
                 filterProfileContent();
             }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                if (modalId === 'profilePicModal') {
                    document.getElementById('profilePicInputFile').value = null;
                    document.getElementById('selectedFileName').textContent = '';
                }
            }
        }

        function showProfilePicModal() {
            document.getElementById('profilePicModal').style.display = 'block';
        }

        function showAlert(title, message) {
            document.getElementById('alertModalTitle').textContent = title;
            document.getElementById('alertModalMessage').textContent = message;

            const confirmBtn = document.getElementById('alertModalConfirmBtn');
            confirmBtn.textContent = 'OK';
            confirmBtn.onclick = () => closeModal('alertModal');
            confirmBtn.style.display = 'inline-block';
            confirmBtn.style.backgroundColor = '#4A00E0';
            confirmBtn.style.borderColor = '#4A00E0';

            document.getElementById('alertModalCancelBtn').style.display = 'none';
            document.getElementById('alertModal').style.display = 'block';
        }

        function showConfirm(title, message, onConfirm, confirmText = 'Confirm', confirmColor = '#ef4444') {
            document.getElementById('alertModalTitle').textContent = title;
            document.getElementById('alertModalMessage').textContent = message;

            const confirmBtn = document.getElementById('alertModalConfirmBtn');
            confirmBtn.textContent = confirmText;
            confirmBtn.onclick = function() {
                onConfirm();
                closeModal('alertModal');
            };
            confirmBtn.style.display = 'inline-block';
            confirmBtn.style.backgroundColor = confirmColor;
            confirmBtn.style.borderColor = confirmColor;

            document.getElementById('alertModalCancelBtn').style.display = 'inline-block';
            document.getElementById('alertModal').style.display = 'block';
        }

        function confirmAccountDeletion() {
             showConfirm(
                'Delete Account?',
                'Are you sure you want to permanently delete your account? This action cannot be undone. All your files, shortcuts, and folders will be removed.',
                function() {
                    window.location.href = 'profile_handler.php?action=delete_account';
                },
                'Delete My Account',
                '#ef4444'
            );
        }

        function confirmRemovePic() {
            closeModal('profilePicModal');
            showConfirm(
                'Remove Profile Picture?',
                'Are you sure you want to remove your profile picture and revert to the default avatar?',
                function() {
                    window.location.href = 'profile_handler.php?action=remove_pic';
                },
                'Remove Picture',
                '#ef4444'
            );
        }

        function showEditFileModal(id, name) { document.getElementById('editFileId').value = id; document.getElementById('editFileName').value = name; document.getElementById('editFileModal').style.display = 'block'; }
        function showEditShortcutModal(id, name, url) { document.getElementById('editShortcutId').value = id; document.getElementById('editShortcutName').value = name; document.getElementById('editShortcutUrl').value = url; document.getElementById('editShortcutModal').style.display = 'block'; }

        function deleteFile(id) {
            showConfirm('Delete File?', 'Are you sure you want to delete this file?', function() {
                const elements = [
                    document.getElementById('card-all-file-' + id),
                    document.getElementById('card-files-file-' + id)
                ];
                undoManager.add({
                    type: 'delete_file',
                    id: id,
                    elements: elements,
                    url: 'file_handler.php?action=delete&id=' + id + '&return_url=profile.php',
                    message: 'File deleted'
                });
            });
        }
        
        function deleteShortcut(id) {
            showConfirm('Delete Shortcut?', 'Are you sure you want to delete this shortcut?', function() {
                const elements = [
                    document.getElementById('card-all-shortcut-' + id),
                    document.getElementById('card-shortcuts-shortcut-' + id)
                ];
                undoManager.add({
                    type: 'delete_shortcut',
                    id: id,
                    elements: elements,
                    url: 'shortcut_handler.php?action=delete&id=' + id + '&return_url=profile.php',
                    message: 'Shortcut deleted'
                });
            });
        }

        function showShareModal(id, type) {
            document.getElementById('shareItemId').value = id;
            document.getElementById('shareItemType').value = type;
            document.getElementById('shareModal').style.display = 'block';
        }
        function toggleSucInput(show) { document.getElementById('sucInputContainer').style.display = show ? 'block' : 'none'; }

        function getSelectedIds() {
            const selectedFiles = [];
            const selectedShortcuts = [];
            const activeTab = document.querySelector('.content-management-area .tab-pane.active');
            if (!activeTab) return { selectedFiles, selectedShortcuts };

            const checkboxes = activeTab.querySelectorAll('.select-checkbox:checked');

            checkboxes.forEach(cb => {
                if (cb.dataset.type === 'file') {
                    selectedFiles.push(cb.dataset.id);
                } else if (cb.dataset.type === 'shortcut') {
                    selectedShortcuts.push(cb.dataset.id);
                }
            });
            return { selectedFiles, selectedShortcuts };
        }

         function clearSelectionVisuals() {
             const activeTab = document.querySelector('.content-management-area .tab-pane.active');
             if (!activeTab) return;
             activeTab.querySelectorAll('.profile-item-card.selected').forEach(card => {
                 card.classList.remove('selected');
             });
             activeTab.querySelectorAll('.select-checkbox:checked').forEach(cb => {
                 cb.checked = false;
             });
         }

        function toggleBulkActions(checkboxElement) {
             if (checkboxElement) {
                 const card = checkboxElement.closest('.profile-item-card');
                 if (card) {
                     card.classList.toggle('selected', checkboxElement.checked);
                 }
             }

            const activeTab = document.querySelector('.content-management-area .tab-pane.active');
            if (!activeTab) {
                 document.getElementById('bulkActions').style.display = 'none';
                 return;
            }
            const checkedCheckboxes = activeTab.querySelectorAll('.select-checkbox:checked');
            document.getElementById('bulkActions').style.display = checkedCheckboxes.length > 0 ? 'flex' : 'none';

             const selectAllButton = document.querySelector('#bulkActions .select-all-btn');
             if (selectAllButton) {
                 const icon = selectAllButton.querySelector('i');
                 if (icon) {
                     const visibleCheckboxes = activeTab.querySelectorAll('.profile-item-card:not(.soft-deleted):not([style*="display: none"]) .select-checkbox');
                     const allVisibleSelected = visibleCheckboxes.length > 0 && Array.from(visibleCheckboxes).every(cb => cb.checked);

                     if (allVisibleSelected) {
                         icon.className = 'fas fa-minus-square';
                         selectAllButton.title = 'Deselect All';
                     } else {
                         icon.className = 'fas fa-check-square';
                         selectAllButton.title = 'Select All';
                     }
                 }
             }
        }

        function toggleSelectAll(button) {
            const activeTab = document.querySelector('.content-management-area .tab-pane.active');
            if (!activeTab) return;

            const checkboxes = activeTab.querySelectorAll('.profile-item-card:not(.soft-deleted):not([style*="display: none"]) .select-checkbox');
            const allVisibleSelected = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
            const selectAllVisible = !allVisibleSelected;

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllVisible;
                 const card = checkbox.closest('.profile-item-card');
                 if (card) {
                     card.classList.toggle('selected', selectAllVisible);
                 }
            });

            const icon = button.querySelector('i');
            if (icon) {
                if (selectAllVisible) {
                    icon.className = 'fas fa-minus-square';
                    button.title = 'Deselect All';
                } else {
                    icon.className = 'fas fa-check-square';
                    button.title = 'Select All';
                }
            }
            toggleBulkActions();
        }

        function showBulkShareModal() {
            const { selectedFiles, selectedShortcuts } = getSelectedIds();
            const totalSelected = selectedFiles.length + selectedShortcuts.length;

            if (totalSelected === 0) {
                showAlert('No Items Selected', 'Please select one or more items to share.');
                return;
            }

            document.getElementById('bulkShareItemCount').textContent = totalSelected;
            document.getElementById('bulkShareFileIds').value = selectedFiles.join(',');
            document.getElementById('bulkShareShortcutIds').value = selectedShortcuts.join(',');

            document.getElementById('bulkShareModal').style.display = 'block';
        }

        function showAddToFavoriteModal() {
            const { selectedFiles, selectedShortcuts } = getSelectedIds.call(this);
            const totalSelected = selectedFiles.length + selectedShortcuts.length;

            if (totalSelected === 0) {
                showAlert('No Items Selected', 'Please select one or more items to add to a folder.');
                return;
            }

            document.getElementById('favoriteItemCount').textContent = totalSelected;
            document.getElementById('favoriteFileIds').value = selectedFiles.join(',');
            document.getElementById('favoriteShortcutIds').value = selectedShortcuts.join(',');

            document.getElementById('addToFavoriteModal').style.display = 'block';
        }

        function showSingleFavoriteModal(itemId, itemType) {
            document.getElementById('favoriteItemCount').textContent = 1;
            
            if (itemType === 'file') {
                document.getElementById('favoriteFileIds').value = itemId;
                document.getElementById('favoriteShortcutIds').value = '';
            } else {
                document.getElementById('favoriteFileIds').value = '';
                document.getElementById('favoriteShortcutIds').value = itemId;
            }
            document.getElementById('folderSelect').selectedIndex = 0;
            document.getElementById('addToFavoriteModal').style.display = 'block';
        }

        function confirmBulkDelete() {
            const { selectedFiles, selectedShortcuts } = getSelectedIds();
            const totalSelected = selectedFiles.length + selectedShortcuts.length;

            if (totalSelected === 0) {
                 showAlert('No Items Selected', 'Please select one or more items to delete.');
                return;
            }

            showConfirm('Delete Selected Items?', `Are you sure you want to delete the ${totalSelected} selected items?`, function() {
                let elements = [];
                
                selectedFiles.forEach(id => {
                    elements.push(document.getElementById('card-all-file-' + id));
                    elements.push(document.getElementById('card-files-file-' + id));
                });
                
                selectedShortcuts.forEach(id => {
                    elements.push(document.getElementById('card-all-shortcut-' + id));
                    elements.push(document.getElementById('card-shortcuts-shortcut-' + id));
                });

                undoManager.add({
                    type: 'bulk_delete',
                    fileIds: selectedFiles,
                    shortcutIds: selectedShortcuts,
                    elements: elements,
                    message: `${totalSelected} items deleted`
                });
            });
        }

        function toggleBulkSucInput(show) { document.getElementById('bulkSucInputContainer').style.display = show ? 'block' : 'none'; }

         function filterProfileContent() {
             const filter = document.getElementById('profileSearchInput').value.toUpperCase();
             const activeTab = document.querySelector('.content-management-area .tab-pane.active');
             if (!activeTab) return;

             const cards = activeTab.querySelectorAll('.profile-item-card:not(.soft-deleted)');
             let visibleIndex = 0; 

             cards.forEach(card => {
                 const title = (card.dataset.title || card.querySelector('h4').textContent).toUpperCase();
                 const isVisible = title.includes(filter);
                 card.style.display = isVisible ? 'flex' : 'none';
                 if (isVisible) {
                     card.style.animationDelay = `${visibleIndex * 0.05}s`;
                     visibleIndex++;
                 } else {
                    card.style.animationDelay = '0s';
                    const checkbox = card.querySelector('.select-checkbox');
                    if (checkbox && checkbox.checked) {
                        checkbox.checked = false;
                        card.classList.remove('selected');
                    }
                 }
             });
             toggleBulkActions();
         }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        }
    </script>
</body>
</html>