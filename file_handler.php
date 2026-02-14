<?php
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit();
}

// Fetch Signups Status
$stmt_settings = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'signup_enabled'");
$stmt_settings->execute();
$signup_enabled = $stmt_settings->fetchColumn() == '1';

// Fetch Suggestions Status
$stmt_sugg = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'suggestions_enabled'");
$stmt_sugg->execute();
$sugg_val = $stmt_sugg->fetchColumn();
$suggestions_enabled = ($sugg_val === false || $sugg_val == '1');

// Fetch Users
$stmt_users = $conn->query("SELECT *, (SELECT COUNT(id) FROM files WHERE user_id = users.id) as f_count, (SELECT COUNT(id) FROM shortcuts WHERE user_id = users.id) as s_count FROM users ORDER BY created_at DESC");
$users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

// Fetch User Suggestions
$stmt_msgs = $conn->query("
    SELECT m.*, u.suc_code, u.full_name as real_name 
    FROM messages m 
    LEFT JOIN users u ON m.sender_id = u.id 
    WHERE m.type = 'suggestion' 
    ORDER BY m.created_at DESC
");
$suggestions = $stmt_msgs->fetchAll(PDO::FETCH_ASSOC);
$sugg_count = count($suggestions);

// Fetch Jobs
$stmt_jobs = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC");
$jobs = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);

$total_users = count($users);
$total_files = array_sum(array_column($users, 'f_count'));
$total_urls = array_sum(array_column($users, 's_count'));

// Determine active tab
$default_tab = 'users';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'message_sent') $default_tab = 'messages';
    if ($_GET['msg'] === 'suggestions_cleared' || $_GET['msg'] === 'suggestion_deleted') $default_tab = 'suggestions';
    if ($_GET['msg'] === 'about_images_updated') $default_tab = 'about';
    if ($_GET['msg'] === 'job_added' || $_GET['msg'] === 'job_deleted') $default_tab = 'jobs';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'); body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-slate-900 text-white fixed h-full z-20 overflow-y-auto">
            <div class="p-6"><h1 class="text-xl font-bold tracking-wider">ADMIN<span class="text-blue-400">PANEL</span></h1></div>
            <nav class="px-4 space-y-2 mt-4">
                <button onclick="switchTab('users')" id="btn-users" class="w-full flex items-center p-3 rounded-lg bg-blue-600 text-white cursor-pointer transition">
                    <i class="fas fa-users w-6"></i><span class="font-medium ml-2">User Management</span>
                </button>
                <button onclick="switchTab('messages')" id="btn-messages" class="w-full flex items-center p-3 rounded-lg hover:bg-slate-800 text-slate-300 hover:text-white cursor-pointer transition">
                    <i class="fas fa-paper-plane w-6"></i><span class="font-medium ml-2">Send Message</span>
                </button>
                
                <!-- Jobs Tab -->
                <button onclick="switchTab('jobs')" id="btn-jobs" class="w-full flex items-center p-3 rounded-lg hover:bg-slate-800 text-slate-300 hover:text-white cursor-pointer transition">
                    <i class="fas fa-briefcase w-6"></i><span class="font-medium ml-2">Job Portal</span>
                </button>

                <button onclick="switchTab('suggestions')" id="btn-suggestions" class="w-full flex items-center p-3 rounded-lg hover:bg-slate-800 text-slate-300 hover:text-white cursor-pointer transition relative">
                    <i class="fas fa-lightbulb w-6"></i><span class="font-medium ml-2">Suggestions</span>
                    <?php if($sugg_count > 0): ?>
                        <span id="sugg-badge" class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-sm animate-pulse">
                            <?php echo $sugg_count; ?>
                        </span>
                    <?php endif; ?>
                </button>
                
                <button onclick="switchTab('about')" id="btn-about" class="w-full flex items-center p-3 rounded-lg hover:bg-slate-800 text-slate-300 hover:text-white cursor-pointer transition">
                    <i class="fas fa-images w-6"></i><span class="font-medium ml-2">About Page Images</span>
                </button>
                
                <div class="border-t border-slate-700 my-4 mx-2"></div>
                <div class="px-4 py-2">
                    <p class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-4">System Control</p>
                    
                    <!-- Signup Toggle -->
                    <div class="flex items-center justify-between bg-slate-800 p-3 rounded-lg border border-slate-700 mb-3">
                        <span class="text-sm font-medium">Signups</span>
                        <form action="admin_handler.php" method="POST">
                            <input type="hidden" name="action" value="toggle_signup">
                            <button type="submit" class="w-12 h-6 rounded-full relative transition-colors duration-300 <?php echo $signup_enabled ? 'bg-green-500' : 'bg-red-500'; ?>">
                                <div class="absolute w-4 h-4 bg-white rounded-full top-1 transition-all duration-300 <?php echo $signup_enabled ? 'left-7' : 'left-1'; ?>"></div>
                            </button>
                        </form>
                    </div>

                    <!-- Suggestions Toggle -->
                    <div class="flex items-center justify-between bg-slate-800 p-3 rounded-lg border border-slate-700">
                        <span class="text-sm font-medium">Suggestions</span>
                        <form action="admin_handler.php" method="POST">
                            <input type="hidden" name="action" value="toggle_suggestions">
                            <button type="submit" class="w-12 h-6 rounded-full relative transition-colors duration-300 <?php echo $suggestions_enabled ? 'bg-green-500' : 'bg-red-500'; ?>">
                                <div class="absolute w-4 h-4 bg-white rounded-full top-1 transition-all duration-300 <?php echo $suggestions_enabled ? 'left-7' : 'left-1'; ?>"></div>
                            </button>
                        </form>
                    </div>
                </div>
            </nav>
            <div class="p-4 border-t border-slate-800 mt-auto">
                <a href="admin_handler.php?action=logout" class="flex items-center text-slate-400 hover:text-white transition-colors"><i class="fas fa-sign-out-alt w-6"></i><span class="ml-2">Logout</span></a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="ml-64 flex-1 p-8">
            <?php if(isset($_GET['msg'])): ?>
                <div id="alert-box" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 flex justify-between items-center transition-opacity duration-1000">
                    <span>
                        <?php if($_GET['msg']=='message_sent') echo "Message sent successfully."; ?>
                        <?php if($_GET['msg']=='broadcast_sent') echo "URL shared successfully."; ?>
                        <?php if($_GET['msg']=='file_broadcast_sent') echo "File shared successfully."; ?>
                        <?php if($_GET['msg']=='suggestions_cleared') echo "All suggestions deleted successfully."; ?>
                        <?php if($_GET['msg']=='suggestion_deleted') echo "Suggestion deleted successfully."; ?>
                        <?php if($_GET['msg']=='about_images_updated') echo "About page images updated successfully."; ?>
                        <?php if($_GET['msg']=='job_added') echo "Job posted successfully."; ?>
                        <?php if($_GET['msg']=='job_deleted') echo "Job deleted successfully."; ?>
                    </span>
                    <button onclick="dismissAlert()" class="text-green-700 hover:text-green-900 focus:outline-none p-1">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                    <p class="text-sm text-slate-500">Users</p><p class="text-2xl font-bold"><?php echo $total_users; ?></p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                    <p class="text-sm text-slate-500">Files</p><p class="text-2xl font-bold"><?php echo $total_files; ?></p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                    <p class="text-sm text-slate-500">Active Jobs</p><p class="text-2xl font-bold text-blue-600"><?php echo count($jobs); ?></p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                    <p class="text-sm text-slate-500">Suggestions</p><p class="text-2xl font-bold"><?php echo count($suggestions); ?></p>
                </div>
            </div>

            <!-- JOBS SECTION -->
            <div id="jobs-section" class="hidden">
                <div class="max-w-4xl mx-auto space-y-6">
                    <!-- Post Job Form -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                        <h3 class="font-bold text-slate-800 text-lg mb-4"><i class="fas fa-plus-circle text-green-500"></i> Post New Job</h3>
                        <form action="admin_handler.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="hidden" name="action" value="add_job">
                            
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Job Title</label>
                                <input type="text" name="title" class="w-full p-2 border rounded" placeholder="e.g. Senior Developer" required>
                            </div>
                            
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Company Name</label>
                                <input type="text" name="company_name" class="w-full p-2 border rounded" placeholder="e.g. Tech Corp" required>
                            </div>

                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Job URL (Optional)</label>
                                <input type="url" name="job_url" class="w-full p-2 border rounded" placeholder="https://...">
                            </div>

                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Job Description</label>
                                <textarea name="description" rows="3" class="w-full p-2 border rounded" placeholder="Enter job details..."></textarea>
                            </div>

                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Company Document (PDF/Image)</label>
                                <input type="file" name="job_file" class="w-full border rounded p-1">
                            </div>

                            <div class="col-span-2 mt-2">
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium w-full md:w-auto">
                                    <i class="fas fa-paper-plane mr-2"></i> Post Job
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Active Jobs List -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 bg-slate-50 font-bold text-slate-700">Active Listings</div>
                        <div class="p-4 space-y-3">
                            <?php if(count($jobs) == 0): ?>
                                <p class="text-center text-slate-500">No jobs posted yet.</p>
                            <?php else: ?>
                                <?php foreach($jobs as $job): ?>
                                    <div class="flex items-center justify-between p-3 border rounded-lg hover:bg-slate-50 transition">
                                        <div>
                                            <div class="font-bold text-slate-800"><?php echo htmlspecialchars($job['title']); ?></div>
                                            <div class="text-sm text-slate-500">
                                                <i class="fas fa-building mr-1"></i> <?php echo htmlspecialchars($job['company_name']); ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-clock mr-1"></i> <?php echo date('M d', strtotime($job['created_at'])); ?>
                                            </div>
                                        </div>
                                        <form action="admin_handler.php" method="POST" onsubmit="return confirm('Delete this job posting?');">
                                            <input type="hidden" name="action" value="delete_job">
                                            <input type="hidden" name="id" value="<?php echo $job['id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 p-2" title="Delete Job">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ABOUT PAGE IMAGES SECTION -->
            <div id="about-section" class="hidden">
                <div class="max-w-4xl mx-auto">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                        <h3 class="font-bold text-slate-800 text-lg mb-6"><i class="fas fa-images text-purple-500"></i> Manage About Page Screenshots</h3>
                        <p class="text-slate-500 mb-6 text-sm">Upload new screenshots for the "How To Use" steps.</p>
                        
                        <form action="admin_handler.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                            <input type="hidden" name="action" value="update_about_images">
                            
                            <?php for($i=1; $i<=6; $i++): ?>
                            <div class="border p-4 rounded-lg bg-slate-50">
                                <label class="block font-medium text-slate-700 mb-2">Step <?php echo $i; ?></label>
                                <input type="file" name="step<?php echo $i; ?>_image" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100"/>
                            </div>
                            <?php endfor; ?>

                            <div class="pt-4">
                                <button type="submit" class="bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700 w-full transition shadow-lg shadow-purple-200">
                                    Update All Images
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- MESSAGES SECTION -->
            <div id="messages-section" class="hidden">
                <div class="max-w-3xl mx-auto">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
                        <h3 class="font-bold text-slate-800 text-lg mb-4"><i class="fas fa-paper-plane text-blue-500"></i> Send Message</h3>
                        <form action="admin_handler.php" method="POST">
                            <input type="hidden" name="action" value="send_message">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-slate-700 mb-2">Recipient</label>
                                <div class="flex gap-4">
                                    <label class="inline-flex items-center"><input type="radio" name="msg_type" value="all" checked onclick="document.getElementById('suc_input').style.display='none'"> All Users</label>
                                    <label class="inline-flex items-center"><input type="radio" name="msg_type" value="specific" onclick="document.getElementById('suc_input').style.display='block'"> Specific User</label>
                                </div>
                            </div>
                            <div id="suc_input" class="mb-4 hidden">
                                <label class="block text-xs text-slate-500 mb-1">Select User (Start typing to search)</label>
                                <input type="text" name="suc_code" list="users_list" placeholder="Search User Name or SUC Code..." class="w-full p-2 border rounded bg-slate-50 focus:bg-white focus:border-blue-500 focus:outline-none">
                                <datalist id="users_list">
                                    <?php foreach($users as $u): ?>
                                        <option value="<?php echo htmlspecialchars($u['suc_code']); ?>">
                                            <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo htmlspecialchars($u['suc_code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <textarea name="message" placeholder="Type your message..." class="w-full p-2 border rounded mb-4 h-32 resize-none" required></textarea>
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- SUGGESTIONS SECTION -->
            <div id="suggestions-section" class="hidden">
                 <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 h-[600px] flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-slate-800 text-lg"><i class="fas fa-lightbulb text-yellow-500"></i> User Suggestions Inbox</h3>
                        <?php if(count($suggestions) > 0): ?>
                        <form action="admin_handler.php" method="POST" onsubmit="return confirm('Are you sure you want to delete ALL messages? This cannot be undone.');">
                            <input type="hidden" name="action" value="delete_all_suggestions">
                            <button type="submit" class="text-xs bg-red-100 text-red-600 hover:bg-red-200 px-3 py-2 rounded font-medium transition-colors">
                                <i class="fas fa-trash-alt mr-1"></i> Delete All
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-y-auto flex-1 space-y-3">
                        <?php if(count($suggestions) == 0): ?><p class="text-slate-400 text-center mt-10">No suggestions yet.</p><?php endif; ?>
                        <?php foreach($suggestions as $msg): ?>
                            <div class="bg-slate-50 p-3 rounded border-l-4 border-yellow-400 relative group">
                                <div class="flex justify-between text-xs text-slate-500 mb-1">
                                    <span class="font-bold text-slate-700">
                                        <?php 
                                            if (!empty($msg['suc_code'])) {
                                                echo htmlspecialchars($msg['real_name']) . ' <span class="text-slate-400 font-normal">(' . htmlspecialchars($msg['suc_code']) . ')</span>';
                                            } else {
                                                echo htmlspecialchars($msg['sender_name']);
                                            }
                                        ?>
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span><?php echo date('M d, H:i', strtotime($msg['created_at'])); ?></span>
                                        <form action="admin_handler.php" method="POST" onsubmit="return confirm('Delete this message?');">
                                            <input type="hidden" name="action" value="delete_suggestion">
                                            <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                            <button type="submit" class="text-slate-400 hover:text-red-500 transition-colors" title="Delete">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <p class="text-sm text-slate-800 mt-1"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- USER MANAGEMENT SECTION -->
            <div id="users-section">
                <div class="bg-gradient-to-r from-slate-800 to-slate-900 rounded-xl p-6 text-white mb-8 flex items-center justify-between shadow-lg">
                    <div><h3 class="text-lg font-bold"><i class="fas fa-bullhorn text-blue-400"></i> Quick Broadcast</h3><p class="text-slate-400 text-sm">Share files/URLs instantly.</p></div>
                    <div class="flex gap-4">
                        <button onclick="document.getElementById('broadcastFileModal').classList.remove('hidden')" class="px-4 py-2 bg-blue-600 rounded">Share File</button>
                        <button onclick="document.getElementById('broadcastUrlModal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 rounded">Share URL</button>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100"><h3 class="font-bold text-slate-800 text-lg">User Registry</h3></div>
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase"><tr><th class="p-4">User</th><th class="p-4">Status</th><th class="p-4 text-right">Actions</th></tr></thead>
                        <tbody class="text-sm divide-y divide-slate-100">
                            <?php foreach($users as $u): ?>
                            <tr>
                                <td class="p-4 font-semibold"><?php echo htmlspecialchars($u['full_name']); ?> <span class="text-slate-400 font-normal">(<?php echo $u['suc_code']; ?>)</span></td>
                                <td class="p-4"><span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $u['status']=='Active'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'; ?>"><?php echo $u['status']; ?></span></td>
                                <td class="p-4 text-right">
                                    <form action="admin_handler.php" method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_block"><input type="hidden" name="user_id" value="<?php echo $u['id']; ?>"><input type="hidden" name="current_status" value="<?php echo $u['status']; ?>">
                                        <button type="submit" class="text-amber-500 hover:text-amber-700 mr-2"><i class="fas fa-ban"></i></button>
                                    </form>
                                    <form action="admin_handler.php" method="POST" class="inline" onsubmit="return confirm('Delete user?');">
                                        <input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div id="broadcastUrlModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl p-6 w-96">
            <h3 class="font-bold mb-4">Share URL</h3>
            <form action="admin_handler.php" method="POST">
                <input type="hidden" name="action" value="broadcast_url">
                <input type="text" name="name" placeholder="Name" class="w-full mb-3 p-2 border rounded" required>
                <input type="url" name="url" placeholder="URL" class="w-full mb-4 p-2 border rounded" required>
                <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" class="mr-2 text-slate-500">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded">Send</button>
            </form>
        </div>
    </div>

    <div id="broadcastFileModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl p-6 w-96">
            <h3 class="font-bold mb-4">Share File</h3>
            <form action="admin_handler.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="broadcast_file">
                <input type="file" name="file" class="w-full mb-4 border rounded" required>
                <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" class="mr-2 text-slate-500">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Send</button>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const sections = ['users', 'messages', 'suggestions', 'about', 'jobs'];
            const activeClass = "bg-blue-600 text-white";
            const inactiveClass = "hover:bg-slate-800 text-slate-300 hover:text-white";

            // If switching to suggestions, hide the notification badge
            if (tab === 'suggestions') {
                const badge = document.getElementById('sugg-badge');
                if (badge) {
                    badge.style.display = 'none';
                }
            }

            sections.forEach(section => {
                const btn = document.getElementById('btn-' + section);
                const sec = document.getElementById(section + '-section');
                
                if (section === tab) {
                    sec.classList.remove('hidden');
                    btn.className = `w-full flex items-center p-3 rounded-lg cursor-pointer transition ${activeClass} relative`;
                } else {
                    sec.classList.add('hidden');
                    btn.className = `w-full flex items-center p-3 rounded-lg cursor-pointer transition ${inactiveClass} relative`;
                }
            });
        }

        // Notification dismiss logic
        function dismissAlert() {
            const alertBox = document.getElementById('alert-box');
            if (alertBox) {
                alertBox.style.opacity = '0';
                setTimeout(() => {
                    if (alertBox.parentNode) {
                        alertBox.parentNode.removeChild(alertBox);
                    }
                }, 1000);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const defaultTab = "<?php echo $default_tab; ?>";
            switchTab(defaultTab);
            const alertBox = document.getElementById('alert-box');
            if (alertBox) {
                setTimeout(dismissAlert, 5000);
            }
        });
    </script>
</body>
</html>