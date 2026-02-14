<?php
require_once 'config.php';

if (isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_panel.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        header('Location: admin_panel.php');
        exit();
    } else {
        $error = "Invalid credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 flex items-center justify-center h-screen font-sans">
    <div class="bg-white p-8 rounded-xl shadow-2xl w-96">
        <h1 class="text-2xl font-bold text-slate-800 mb-6 text-center">Admin<span class="text-blue-600">Panel</span></h1>
        
        <?php if($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm text-center"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="block text-slate-600 text-sm font-bold mb-2">Username</label>
                <input type="text" name="username" class="w-full p-3 border border-slate-300 rounded focus:outline-none focus:border-blue-500" required>
            </div>
            <div class="mb-6">
                <label class="block text-slate-600 text-sm font-bold mb-2">Password</label>
                <input type="password" name="password" class="w-full p-3 border border-slate-300 rounded focus:outline-none focus:border-blue-500" required>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded hover:bg-blue-700 transition">Login</button>
        </form>
    </div>
</body>
</html>