-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2025 at 03:22 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `job_helper_db`
--
CREATE DATABASE IF NOT EXISTS `job_helper_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `job_helper_db`;

-- --------------------------------------------------------

--
-- Table structure for table `favorites_folders`
--

CREATE TABLE `favorites_folders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `favorite_items`
--

CREATE TABLE `favorite_items` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_id` int(11) DEFAULT NULL,
  `shortcut_id` int(11) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL COMMENT 'Unique name on server',
  `original_name` varchar(255) NOT NULL COMMENT 'Original name from user',
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `upload_date` date NOT NULL,
  `display_name` varchar(255) DEFAULT NULL COMMENT 'Editable display name'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shortcuts`
--

CREATE TABLE `shortcuts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `url` text NOT NULL,
  `favicon` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `suc_code` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_pic` varchar(255) DEFAULT NULL COMMENT 'Filename of the profile picture'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `favorites_folders`
--
ALTER TABLE `favorites_folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `favorite_items`
--
ALTER TABLE `favorite_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `folder_file_user` (`folder_id`,`user_id`,`file_id`),
  ADD UNIQUE KEY `folder_shortcut_user` (`folder_id`,`user_id`,`shortcut_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `shortcut_id` (`shortcut_id`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `shortcuts`
--
ALTER TABLE `shortcuts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `suc_code` (`suc_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `favorites_folders`
--
ALTER TABLE `favorites_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `favorite_items`
--
ALTER TABLE `favorite_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shortcuts`
--
ALTER TABLE `shortcuts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `favorites_folders`
--
ALTER TABLE `favorites_folders`
  ADD CONSTRAINT `favorites_folders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `favorite_items`
--
ALTER TABLE `favorite_items`
  ADD CONSTRAINT `favorite_items_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `favorites_folders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `favorite_items_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `favorite_items_ibfk_3` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `favorite_items_ibfk_4` FOREIGN KEY (`shortcut_id`) REFERENCES `shortcuts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `shortcuts`
--
ALTER TABLE `shortcuts`
  ADD CONSTRAINT `shortcuts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

<?php
require_once 'config.php'; // Needs to be included to access $_SESSION

// --- NEW: Redirect if already logged in ---
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}
// --- END NEW ---

$step = $_GET['step'] ?? 'login'; // 'login', 'forgot', 'reset'
$suc_code = $_GET['suc_code'] ?? '';
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Helper - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- MODIFIED: Added cache-busting query string -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .nav-link {
            text-decoration: none;
            color: #0c0c0cff;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        .nav-link:hover {
            color: #f1f7f7ff;
        }
        
        /* New Style for About Button */
        .nav-link.about-btn {
            background-color: rgba(255, 255, 255, 0.15);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .nav-link.about-btn:hover {
            background-color: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        @media (max-width: 600px) {
            .header-title { flex: 1; }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">Job Helper</h1>
        <!-- NEW: About Link in Header -->
        <nav class="nav">
            <a href="about.php" class="nav-link about-btn">About</a>
        </nav>
    </header>

    <div class="auth-container">
        
        <?php if ($error): ?>
            <p class="form-message error" style="max-width: 768px; width: 100%; margin: 0 auto 1.5rem;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="form-message success" style="max-width: 768px; width: 100%; margin: 0 auto 1.5rem;"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>


        <?php if ($step === 'login'): ?>
            <div class="auth-box" id="authBox">
                <!-- Sign In Form -->
                <div class="form-container sign-in-container">
                    <form action="auth.php" method="POST">
                        <!-- SECURITY: CSRF Token Added -->
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="login">
                        <h1>Sign In</h1>
                    
                        <div class="input-group">
                            <i class="fas fa-hashtag"></i>
                            <input type="text" name="suc_code" placeholder="SUC Code" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="loginPassword" placeholder="Password" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('loginPassword', this)"></i>
                        </div>
                        
                        <!-- NEW: Forgot Password Link -->
                        <a href="index.php?step=forgot" class="forgot-link">Forgot your password?</a>
                        
                        <button type="submit" class="btn-primary">SIGN IN</button>
                    </form>
                </div>

                <!-- Sign Up Form -->
                <div class="form-container sign-up-container">
                    <form action="auth.php" method="POST">
                        <!-- SECURITY: CSRF Token Added -->
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="register">
                        <h1>Create Account</h1>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" name="full_name" placeholder="Full Name" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-hashtag"></i>
                            <input type="text" name="suc_code" placeholder="SUC Code" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="signupPassword" placeholder="Password" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('signupPassword', this)"></i>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm Password" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('confirmPassword', this)"></i>
                        </div>
                        <button type="submit" class="btn-primary">SIGN UP</button>
                    </form>
                </div>

                <!-- Overlay for switching between Sign In and Sign Up -->
                <div class="overlay-container">
                    <div class="overlay">
                        <div class="overlay-panel overlay-left">
                            <h1>Welcome Back!</h1>
                            <p>To keep connected with us please login with your personal info</p>
                            <button class="btn-ghost" onclick="showSignIn()">SIGN IN</button>
                        </div>
                        <div class="overlay-panel overlay-right">
                            <h1>Hello, Friend!</h1>
                            <p>Register with your personal details to use all of site features</p>
                            <button class="btn-ghost" onclick="showSignUp()">SIGN UP</button>
                        </div>
                    </div>
                </div>
            </div>
        
        <?php elseif ($step === 'forgot'): ?>
            <!-- NEW: Forgot Password Form -->
            <div class="auth-box auth-box-single">
                <form action="auth.php" method="POST" class="form-container" style="width: 100%; opacity: 1; z-index: 5; position: relative;">
                    <!-- SECURITY: CSRF Token Added -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="check_suc">
                    <h1>Forgot Password</h1>
                    <p style="color: #4b5563; margin-bottom: 1.5rem;">Enter your SUC Code to reset your password.</p>
                    <div class="input-group">
                        <i class="fas fa-hashtag"></i>
                        <input type="text" name="suc_code" placeholder="SUC Code" required>
                    </div>
                    <button type="submit" class="btn-primary">Check Account</button>
                    <a href="index.php" class="forgot-link" style="margin-top: 1.5rem;">Back to Sign In</a>
                </form>
            </div>

        <?php elseif ($step === 'reset' && !empty($suc_code)): ?>
            <!-- NEW: Reset Password Form -->
             <div class="auth-box auth-box-single">
                <form action="auth.php" method="POST" class="form-container" style="width: 100%; opacity: 1; z-index: 5; position: relative;">
                    <!-- SECURITY: CSRF Token Added -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="suc_code" value="<?php echo htmlspecialchars($suc_code); ?>">
                    <h1>Set New Password</h1>
                    <p style="color: #4b5563; margin-bottom: 1.5rem;">Please enter and confirm your new password.</p>
                    
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="resetPassword" placeholder="New Password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('resetPassword', this)"></i>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" id="resetConfirmPassword" placeholder="Confirm New Password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('resetConfirmPassword', this)"></i>
                    </div>
                    <button type="submit" class="btn-primary">Reset Password</button>
                </form>
            </div>
        
        <?php else: ?>
            <!-- Fallback if step is invalid -->
            <script>window.location.href = 'index.php';</script>
        <?php endif; ?>

    </div>

    <script>
        // --- UI Interaction for Login/Signup Form ---
        function showSignUp() {
            document.getElementById('authBox').classList.add('right-panel-active');
        }

        function showSignIn() {
            document.getElementById('authBox').classList.remove('right-panel-active');
        }

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>