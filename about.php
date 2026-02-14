<?php
require_once 'config.php';

// Check if user is logged in to determine navigation links
$is_logged_in = isset($_SESSION['user_id']);

// --- Fetch Custom Images from Settings ---
// UPDATED: Now supports up to step 6
$about_images = [];
$defaults = [
    'step1' => 'Screenshot%202025-11-27%20103641.jpg', // Was Step 2, now Step 1 (Secure Access)
    'step2' => 'page%20step%201.png', // Was Step 1, now Step 2 (Dashboard)
    'step3' => 'https://placehold.co/800x500/f8fafc/4A00E0?text=Document+Creator', // New Step 3
    'step4' => 'step%203.png', // Was Step 3, now Step 4 (Messages)
    'step5' => 'step%204.png', // Was Step 4, now Step 5 (Profile)
    'step6' => 'step%205.png'  // Was Step 5, now Step 6 (Text Studio)
];

try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'about_img_step%'");
    $stmt->execute();
    $db_images = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Map DB keys to simple keys (step1, step2...)
    foreach ($defaults as $step => $defaultPath) {
        $dbKey = 'about_img_' . $step;
        if (isset($db_images[$dbKey]) && !empty($db_images[$dbKey])) {
            $about_images[$step] = 'uploads/' . $db_images[$dbKey];
        } else {
            $about_images[$step] = $defaultPath;
        }
    }
} catch (Exception $e) {
    // If table doesn't exist or error, fallback to defaults
    $about_images = $defaults;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - Job Helper</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        /* --- About Page Specific Styles --- */
        body {
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        /* Hero Section */
        .about-hero {
            background: linear-gradient(135deg, #8E2DE2, #4A00E0); /* Job Helper Theme */
            color: white;
            padding: 140px 20px 180px;
            text-align: center;
            border-radius: 0 0 50% 50% / 40px;
            margin-bottom: 80px;
            box-shadow: 0 20px 40px rgba(74, 0, 224, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        /* Animated Background Effect */
        .about-hero::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%; right: -50%; bottom: -50%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 10%, transparent 10%);
            background-size: 30px 30px;
            animation: moveBackground 20s linear infinite;
            opacity: 0.3;
            z-index: 0;
        }
        
        @keyframes moveBackground {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .about-hero h1 {
            font-size: 4.5rem;
            font-weight: 900;
            margin-bottom: 20px;
            letter-spacing: -2px;
            position: relative;
            z-index: 1;
            text-shadow: 0 4px 15px rgba(0,0,0,0.2);
            line-height: 1.1;
        }
        
        .about-hero p {
            font-size: 1.4rem;
            opacity: 0.95;
            max-width: 800px;
            margin: 0 auto 40px;
            position: relative;
            z-index: 1;
            font-weight: 300;
        }

        /* Container */
        .content-container {
            max-width: 1200px;
            margin: -100px auto 0;
            padding: 0 30px 80px;
            position: relative;
            z-index: 2;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 40px;
            margin-bottom: 120px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 50px 40px;
            border-radius: 24px;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.1), 0 10px 20px -10px rgba(0, 0, 0, 0.05);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
            border: 1px solid rgba(255,255,255,0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .feature-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, #8E2DE2, #4A00E0);
            transform: scaleX(0);
            transition: transform 0.4s ease;
            transform-origin: left;
        }

        .feature-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 30px 60px -15px rgba(74, 0, 224, 0.3);
        }
        
        .feature-card:hover::after {
            transform: scaleX(1);
        }

        .feature-icon-wrapper {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            color: #4A00E0;
            font-size: 3rem;
            transform: rotate(-10deg);
            transition: transform 0.4s;
            box-shadow: 0 15px 30px rgba(74, 0, 224, 0.2);
        }
        
        .feature-card:hover .feature-icon-wrapper {
            transform: rotate(0deg) scale(1.1);
        }

        .feature-card h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: #1e293b;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .feature-card p {
            color: #64748b;
            font-size: 1.1rem;
            line-height: 1.7;
        }

        /* Section Titles */
        .section-title {
            text-align: center;
            font-size: 3.5rem;
            font-weight: 900;
            color: #1e293b;
            margin: 120px 0 100px;
            position: relative;
            letter-spacing: -1.5px;
        }
        
        .section-title span {
            background: linear-gradient(135deg, #8E2DE2, #4A00E0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            padding-bottom: 10px;
        }

        /* How To Use Section */
        .step-section {
            display: flex;
            align-items: center;
            gap: 100px;
            margin-bottom: 160px;
        }

        .step-section:nth-child(even) {
            flex-direction: row-reverse;
        }

        .step-text {
            flex: 1;
        }

        .step-badge {
            background: linear-gradient(135deg, #4A00E0, #8E2DE2);
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 30px;
            display: inline-block;
            box-shadow: 0 10px 20px rgba(74, 0, 224, 0.3);
        }

        .step-text h3 {
            font-size: 2.8rem;
            color: #0f172a;
            margin-bottom: 25px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1px;
        }

        .step-text p {
            color: #475569;
            font-size: 1.2rem;
            line-height: 1.8;
            margin-bottom: 35px;
            font-weight: 400;
        }
        
        .step-list {
            list-style: none;
            padding: 0;
        }
        
        .step-list li {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            font-size: 1.15rem;
            color: #334155;
            font-weight: 500;
        }
        
        .step-list li i {
            color: #10b981;
            margin-right: 18px;
            font-size: 1.5rem;
            margin-top: 4px; 
            background: #ecfdf5;
            padding: 5px;
            border-radius: 50%;
        }

        .step-image {
            flex: 1.2;
            background: white;
            padding: 25px;
            border-radius: 30px;
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.2);
            border: 1px solid #e2e8f0;
            transform: perspective(1500px) rotateY(5deg) rotateX(2deg);
            transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            z-index: 1;
        }
        
        .step-section:nth-child(even) .step-image {
             transform: perspective(1500px) rotateY(-5deg) rotateX(2deg);
        }
        
        .step-image:hover {
            transform: perspective(1500px) rotateY(0deg) rotateX(0deg) translateY(-10px) scale(1.02);
            box-shadow: 0 40px 80px -20px rgba(74, 0, 224, 0.2);
            z-index: 10;
        }

        .step-image img {
            width: 100%;
            height: auto;
            border-radius: 18px;
            display: block;
            border: 1px solid #f1f5f9;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .step-caption {
            text-align: center;
            margin-top: 25px;
            font-size: 1rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Main CTA Button */
        .btn-main-cta {
            background: white; 
            color: #4A00E0; 
            padding: 20px 60px; 
            border-radius: 50px; 
            font-weight: 800; 
            text-decoration: none; 
            font-size: 1.3rem; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.2); 
            transition: all 0.3s ease;
            display: inline-block;
            position: relative;
            z-index: 2;
        }
        
        .btn-main-cta:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            background: #fff;
            color: #8E2DE2;
        }

        /* Smaller CTA Button */
        .btn-cta {
            display: inline-block;
            background: linear-gradient(135deg, #8E2DE2, #4A00E0);
            color: white;
            padding: 16px 40px;
            border-radius: 40px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 10px 25px rgba(74, 0, 224, 0.4);
            font-size: 1.1rem;
            margin-top: 20px;
        }
        
        .btn-cta:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(74, 0, 224, 0.5);
        }

        /* Navigation Button Style */
        .nav-link.about-btn {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 10px 22px;
            border-radius: 25px;
            font-weight: 700;
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.3s;
        }
        
        .nav-link.about-btn:hover {
             background-color: rgba(255, 255, 255, 0.3);
             transform: translateY(-2px);
        }
        
        /* Stats Section */
        .stats-bar {
            background: #0f172a;
            color: white;
            padding: 100px 0;
            margin-top: 100px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Decorative circle in stats */
        .stats-bar::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(74, 0, 224, 0.2) 0%, transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            max-width: 1000px;
            margin: 0 auto;
            gap: 60px;
            position: relative;
            z-index: 1;
        }
        
        .stat-item h4 {
            font-size: 4rem;
            font-weight: 900;
            background: linear-gradient(to right, #4facfe 0%, #00f2fe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            line-height: 1;
        }
        
        .stat-item p {
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-size: 1.1rem;
            font-weight: 700;
        }

        @media (max-width: 900px) {
            .about-hero { padding: 100px 20px; }
            .about-hero h1 { font-size: 3rem; }
            .step-section, .step-section:nth-child(even) {
                flex-direction: column;
                text-align: center;
                gap: 50px;
                margin-bottom: 100px;
            }
            .step-image { transform: none !important; }
            .step-list li { justify-content: center; text-align: left; }
            .stats-grid { grid-template-columns: 1fr; gap: 60px; }
        }
    </style>
</head>
<body>

    <header class="header">
        <h1 class="header-title">Job Helper</h1>
        <nav class="nav">
            <?php if ($is_logged_in): ?>
                <a href="home.php" class="nav-link">Home</a>
                <a href="profile.php" class="nav-link">Profile</a>
                <a href="about.php" class="nav-link about-btn">About</a>
                <a href="auth.php?action=logout" class="nav-link logout" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
            <?php else: ?>
                <a href="index.php" class="nav-link">Login</a>
                <a href="about.php" class="nav-link about-btn">About</a>
            <?php endif; ?>
        </nav>
        <button class="mobile-nav-toggle" onclick="document.querySelector('.nav').classList.toggle('active')">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <!-- Hero Section -->
    <section class="about-hero">
        <h1>Your Digital Command Center</h1>
        <p>Job Helper brings files, productivity, and communication into one seamless, beautiful workspace.</p>
        <?php if (!$is_logged_in): ?>
            <div style="margin-top: 50px;">
                <a href="index.php" class="btn-main-cta">Start Your Free Journey</a>
            </div>
        <?php endif; ?>
    </section>

    <div class="content-container">
        
        <!-- Features Cards Grid -->
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon-wrapper"><i class="fas fa-cloud-upload-alt"></i></div>
                <h3>Cloud Storage</h3>
                <p>Securely upload, preview, and manage your PDF resumes and documents from any device, anywhere in the world.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon-wrapper"><i class="fas fa-folder-open"></i></div>
                <h3>Smart Organization</h3>
                <p>Group related files into custom folders. Keep your workspace clutter-free and find exactly what you need in seconds.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper"><i class="fas fa-star"></i></div>
                <h3>Add to Favorites</h3>
                <p>Pin your most critical files and links to a dedicated Favorites tab for instant, one-click access.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper"><i class="fas fa-share-alt"></i></div>
                <h3>Share Content</h3>
                <p>Seamlessly share files and URL shortcuts with other users on the platform or broadcast them to everyone.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper"><i class="fas fa-magic"></i></div>
                <h3>AI Text Studio</h3>
                <p>Experience next-gen productivity. Dictate notes with your voice or convert text into natural-sounding speech.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper"><i class="fas fa-link"></i></div>
                <h3>Smart Shortcuts</h3>
                <p>Never lose track of a job posting again. Save URLs with custom titles and icons directly to your dashboard.</p>
            </div>
        </div>

        <!-- How To Use Guide -->
        <h2 class="section-title">Mastering <span>Job Helper</span></h2>

        <!-- Step 1: Secure Access & Profile (MOVED FROM STEP 2) -->
        <div class="step-section">
            <div class="step-text">
                <span class="step-badge">Step 01</span>
                <h3>Secure Access & Profile</h3>
                <p>Start your journey securely. Create a unique account to access your personal workspace and manage your identity.</p>
                <ul class="step-list">
                    <li><i class="fas fa-check-circle"></i> <strong>Easy Sign Up:</strong> Register with your personal details to unlock all features.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Secure Login:</strong> End-to-end authentication ensures your data stays private.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Identity Hub:</strong> Your profile generates a unique SUC Code for easy sharing.</li>
                </ul>
            </div>
            <div class="step-image">
                <img src="<?php echo htmlspecialchars($about_images['step1']); ?>" onerror="this.onerror=null; this.src='https://placehold.co/800x500/f8fafc/4A00E0?text=Login+Screen';" alt="Login and Sign Up Screenshot">
                <div class="step-caption">Secure gateway to your personal command center.</div>
            </div>
        </div>

        <!-- Step 2: Dashboard (MOVED FROM STEP 1) -->
        <div class="step-section">
            <div class="step-text">
                <span class="step-badge">Step 02</span>
                <h3>Your Personal Dashboard</h3>
                <p>The Dashboard is your mission control. It's designed for speed and simplicity, giving you immediate access to your digital assets.</p>
                <ul class="step-list">
                    <li><i class="fas fa-check-circle"></i> <strong>Drag & Drop:</strong> Effortlessly upload files by dragging them onto the cloud zone.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Tabbed View:</strong> Switch instantly between All items, Files, Shortcuts, and Favorites.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Quick Actions:</strong> Use the floating buttons to add links or create folders on the fly.</li>
                </ul>
            </div>
            <div class="step-image">
                <img src="<?php echo htmlspecialchars($about_images['step2']); ?>" onerror="this.onerror=null; this.src='https://placehold.co/800x500/f8fafc/4A00E0?text=Dashboard+View';" alt="Dashboard Screenshot">
                <div class="step-caption">A centralized hub for all your files and links.</div>
            </div>
        </div>

        <!-- Step 3: Document Creator (NEW) -->
        <div class="step-section">
            <div class="step-text">
                <span class="step-badge">Step 03</span>
                <h3>Document Creator</h3>
                <p>Draft, edit, and format professional documents directly within the platform. No need for external software.</p>
                <ul class="step-list">
                    <li><i class="fas fa-check-circle"></i> <strong>Rich Text Editing:</strong> Create beautifully formatted documents with ease.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Instant PDF:</strong> Export your creations to PDF with a single click.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Cloud Save:</strong> Your drafts are automatically saved to your secure cloud storage.</li>
                </ul>
            </div>
            <div class="step-image">
                <img src="<?php echo htmlspecialchars($about_images['step3']); ?>" onerror="this.onerror=null; this.src='https://placehold.co/800x500/f8fafc/4A00E0?text=Document+Creator';" alt="Document Creator Screenshot">
                <div class="step-caption">Integrated tools for professional document creation.</div>
            </div>
        </div>

        <!-- Step 4: Messages (MOVED FROM STEP 3) -->
        <div class="step-section">
            <div class="step-text">
                <span class="step-badge">Step 04</span>
                <h3>Communication Center</h3>
                <p>Stay in the loop with built-in messaging. Whether it's a system update or a personal note, you'll never miss a beat.</p>
                <ul class="step-list">
                    <li><i class="fas fa-check-circle"></i> <strong>Admin Inbox:</strong> Receive important announcements and personal messages from admins.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Send Suggestions:</strong> Have feedback? Send it directly to the admin team.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Smart Alerts:</strong> A red notification badge alerts you to unread messages instantly.</li>
                </ul>
                <a href="home.php#messages" class="btn-cta">Open Messages</a>
            </div>
            <div class="step-image">
                <img src="<?php echo htmlspecialchars($about_images['step4']); ?>" onerror="this.onerror=null; this.src='https://placehold.co/800x500/f8fafc/4A00E0?text=Messages+View';" alt="Messages Screenshot">
                <div class="step-caption">Direct line of communication with administrators.</div>
            </div>
        </div>

        <!-- Step 5: Profile Manager (MOVED FROM STEP 4) -->
        <div class="step-section">
            <div class="step-text">
                <span class="step-badge">Step 05</span>
                <h3>Content Manager</h3>
                <p>Take full control of your data. The Profile Manager allows you to curate your content, ensuring your workspace remains organized and relevant.</p>
                <ul class="step-list">
                    <li><i class="fas fa-check-circle"></i> <strong>Search & Filter:</strong> Quickly locate specific files or links using the search bar.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Bulk Actions:</strong> Select multiple items to delete or organize them into folders in one go.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Detailed View:</strong> See file types, upload dates, and manage individual item settings.</li>
                </ul>
            </div>
            <div class="step-image">
                <img src="<?php echo htmlspecialchars($about_images['step5']); ?>" onerror="this.onerror=null; this.src='https://placehold.co/800x500/f8fafc/4A00E0?text=Content+Manager';" alt="Profile Manager Screenshot">
                <div class="step-caption">Advanced tools to manage and curate your uploaded content.</div>
            </div>
        </div>

        <!-- Step 6: Text Studio (MOVED FROM STEP 5) -->
        <div class="step-section">
            <div class="step-text">
                <span class="step-badge">Step 06</span>
                <h3>AI Text Studio</h3>
                <p>Boost your productivity with cutting-edge AI tools. The Text Studio transforms how you interact with text and documents.</p>
                <ul class="step-list">
                    <li><i class="fas fa-check-circle"></i> <strong>Voice Dictation:</strong> Speak your thoughts and watch them turn into text instantly.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Text-to-Speech:</strong> Paste articles or notes and listen to them on the go.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>PDF Extraction:</strong> Upload a PDF to automatically extract and edit its text content.</li>
                </ul>
            </div>
            <div class="step-image">
                <img src="<?php echo htmlspecialchars($about_images['step6']); ?>" onerror="this.onerror=null; this.src='https://placehold.co/800x500/f8fafc/4A00E0?text=Text+Studio';" alt="Text Studio Screenshot">
                <div class="step-caption">Powerful AI tools for voice, speech, and text extraction.</div>
            </div>
        </div>

    </div>

    <!-- Stats Footer -->
    <div class="stats-bar">
        <div class="stats-grid">
            <div class="stat-item">
                <h4>Secure</h4>
                <p>End-to-End Encryption</p>
            </div>
            <div class="stat-item">
                <h4>Fast</h4>
                <p>Lightning Speed CDN</p>
            </div>
            <div class="stat-item">
                <h4>Smart</h4>
                <p>Advanced AI Core</p>
            </div>
        </div>
    </div>

    <footer style="background: #0f172a; color: #94a3b8; padding: 60px 0; text-align: center; border-top: 1px solid #334155;">
        <div class="container">
            <h2 style="color: white; margin-bottom: 15px; font-size: 2rem; font-weight: 800;">Job Helper</h2>
            <p style="margin-bottom: 30px; font-size: 1.1rem;">Empowering your career journey, one file at a time.</p>
            <p style="font-size: 0.9rem; opacity: 0.7;">&copy; <?php echo date('Y'); ?> Developed by Vineeth.</p>
        </div>
    </footer>

</body>
</html>