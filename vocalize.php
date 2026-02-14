<?php
require_once 'config.php';

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vocalize - Translate & Speak</title>
    
    <!-- Tailwind CSS (for the tool UI) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Document Parsing Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>

    <!-- Custom Styles for Vocalize -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }

        /* --- Responsive Layout Logic --- */
        /* Mobile (Default): Scrollable body, Gray Background for "Card" effect */
        body {
            overflow-y: auto;
            height: auto;
            background-color: #f3f4f6; /* Ensure background is gray on mobile */
        }

        .site-header {
            background-color: #4A00E0;
            background: linear-gradient(135deg, #8E2DE2, #4A00E0);
            height: 60px;
            width: 100%;
            z-index: 1000;
            padding: 0 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
        }

        .main-container {
            display: flex;
            flex-direction: column; /* Stack on mobile */
            width: 100%;
        }

        /* Desktop: Fixed height, no window scroll */
        @media (min-width: 768px) {
            body {
                overflow: hidden;
                height: 100vh;
                background-color: white;
            }
            .site-header {
                position: static;
            }
            .main-container {
                flex-direction: row; /* Side by side on desktop */
                height: calc(100vh - 60px);
                background-color: white;
            }
        }

        /* Audio Wave Animation */
        .audio-wave {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 30px;
            gap: 3px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .audio-wave.active {
            opacity: 1;
        }

        .bar {
            width: 4px;
            height: 100%;
            background: #4f46e5;
            border-radius: 2px;
            animation: wave 1s ease-in-out infinite;
        }

        .bar:nth-child(1) { animation-duration: 0.8s; height: 40%; }
        .bar:nth-child(2) { animation-duration: 0.6s; height: 70%; }
        .bar:nth-child(3) { animation-duration: 0.9s; height: 50%; }
        .bar:nth-child(4) { animation-duration: 0.5s; height: 80%; }
        .bar:nth-child(5) { animation-duration: 0.7s; height: 60%; }

        @keyframes wave {
            0%, 100% { transform: scaleY(0.5); }
            50% { transform: scaleY(1); }
        }

        .loader {
            border: 2px solid #e0e7ff;
            border-top: 2px solid #4f46e5;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Recording Pulse Animation */
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        
        .recording-active {
            color: #ef4444 !important; /* red-500 */
            animation: pulse-red 2s infinite;
            border-radius: 50%;
        }

        /* Select styling */
        .custom-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
            -webkit-appearance: none;
            appearance: none;
        }
        
        /* Drag and Drop Highlight */
        .drag-active {
            border-color: #6366f1 !important; /* indigo-500 */
            background-color: #e0e7ff !important; /* indigo-100 */
        }

        /* --- Cross-Browser Range Slider Styling --- */
        input[type=range] {
            -webkit-appearance: none;
            background: transparent;
            cursor: pointer;
        }

        /* Webkit (Chrome, Safari, Edge) */
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 16px;
            width: 16px;
            border-radius: 50%;
            background: #4f46e5;
            margin-top: -6px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        input[type=range]::-webkit-slider-runnable-track {
            width: 100%;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
        }

        /* Firefox */
        input[type=range]::-moz-range-thumb {
            height: 16px;
            width: 16px;
            border: none;
            border-radius: 50%;
            background: #4f46e5;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        input[type=range]::-moz-range-track {
            width: 100%;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
        }

        /* Seek Slider Specifics */
        input[type=range].seek-slider::-webkit-slider-thumb {
            height: 12px;
            width: 12px;
            margin-top: -4px;
        }
        input[type=range].seek-slider::-webkit-slider-runnable-track {
            background: #e0e7ff;
        }
        input[type=range].seek-slider::-moz-range-thumb {
            height: 12px;
            width: 12px;
        }
        input[type=range].seek-slider::-moz-range-track {
            background: #e0e7ff;
        }

        /* Search Highlight */
        ::selection {
            background: #c7d2fe; /* indigo-200 */
            color: #1e1b4b; /* indigo-950 */
        }
        
        /* Search Bar Transition */
        #search-bar {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            transform-origin: top right;
        }
        #search-bar.hidden {
            opacity: 0;
            transform: scale(0.95);
            pointer-events: none;
            display: none; 
        }
        #search-bar:not(.hidden) {
            opacity: 1;
            transform: scale(1);
            display: flex;
        }

        /* Chat Window Transition */
        #chat-interface {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        #chat-interface.hidden-chat {
            opacity: 0;
            transform: translateY(110%); /* Slide down completely off-screen on mobile */
            pointer-events: none;
        }
        @media (min-width: 768px) {
            #chat-interface.hidden-chat {
                transform: translateY(20px) scale(0.95); /* Subtle slide/fade on desktop */
                opacity: 0;
            }
        }
        #chat-interface:not(.hidden-chat) {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        /* Chat Message Styles */
        .chat-msg {
            margin-bottom: 0.75rem;
            max-width: 85%;
            padding: 0.75rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        .chat-msg-user {
            background-color: #4f46e5;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 0.25rem;
        }
        .chat-msg-ai {
            background-color: #f3f4f6;
            color: #1f2937;
            margin-right: auto;
            border-bottom-left-radius: 0.25rem;
            border: 1px solid #e5e7eb;
        }

        /* Translation Preview Transitions */
        #translated-preview {
            transition: height 0.4s cubic-bezier(0.16, 1, 0.3, 1); /* Smooth spring-like transition */
            will-change: height;
        }
        .preview-minimized {
            height: 48px !important; /* Collapsed height (header only) */
            overflow: hidden !important;
        }

        /* Button Touch Feedback */
        button:active {
            transform: scale(0.95);
        }

        /* History Item Styles */
        .history-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-bottom: 8px;
            transition: all 0.2s;
        }
        .history-item:hover {
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-color: #c7d2fe;
        }

    </style>
</head>
<body>

    <!-- Header -->
    <header class="site-header">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white">
                <i class="fas fa-wave-square text-sm"></i>
            </div>
            <h1 class="text-white text-lg font-bold tracking-wide">Vocalize</h1>
        </div>
        <nav class="flex gap-4 items-center">
            <a href="home.php" class="text-white/80 hover:text-white text-sm font-medium transition-colors">Home</a>
            <a href="profile.php" class="text-white/80 hover:text-white text-sm font-medium transition-colors">Profile</a>
            <a href="auth.php?action=logout" class="text-white/80 hover:text-white text-lg transition-colors" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </nav>
    </header>

    <div class="main-container">
        
        <!-- Left Panel: All Controls (SETTINGS) -->
        <!-- Responsive width: Full on mobile, fixed on desktop -->
        <!-- Added margin-top on mobile to separate from the big box -->
        <div class="w-full md:w-[320px] lg:w-[350px] bg-slate-50 border-r border-gray-200 flex flex-col md:h-full overflow-y-auto shrink-0 z-10 order-2 md:order-1 mt-6 md:mt-0 pb-10">
            <div class="p-6 pb-10 md:pb-20">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Settings & Controls</h2>

                <!-- Language Selection -->
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Target Language</label>
                    <select id="language-select" class="custom-select w-full p-3 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm cursor-pointer transition-shadow hover:shadow">
                        <option value="English">English</option>
                        <option value="Spanish">Spanish (Español)</option>
                        <option value="French">French (Français)</option>
                        <option value="German">German (Deutsch)</option>
                        <option value="Hindi">Hindi (हिन्दी)</option>
                        <option value="Telugu">Telugu (తెలుగు)</option>
                        <option value="Japanese">Japanese (日本語)</option>
                        <option value="Chinese">Chinese (中文)</option>
                        <option value="Russian">Russian (Русский)</option>
                        <option value="Portuguese">Portuguese (Português)</option>
                        <option value="Italian">Italian (Italiano)</option>
                        <option value="Korean">Korean (한국어)</option>
                        <option value="Arabic">Arabic (العربية)</option>
                    </select>
                </div>

                <!-- Voice Selection -->
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Voice Tone</label>
                    <select id="voice-select" class="custom-select w-full p-3 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm cursor-pointer transition-shadow hover:shadow">
                        <option value="Kore">Kore (Female, Balanced)</option>
                        <option value="Fenrir">Fenrir (Male, Deep)</option>
                        <option value="Puck">Puck (Male, Energetic)</option>
                        <option value="Aoede">Aoede (Female, Soft)</option>
                    </select>
                </div>

                <!-- File Upload -->
                <div class="mb-6">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Upload Document</label>
                    <label id="drop-zone" class="flex items-center justify-center w-full p-4 bg-white border border-dashed border-indigo-300 rounded-lg cursor-pointer hover:bg-indigo-50 hover:border-indigo-400 transition-all group relative">
                        <div class="flex flex-col items-center gap-1 text-indigo-600 group-hover:text-indigo-700 pointer-events-none">
                            <i class="fas fa-cloud-upload-alt text-xl"></i>
                            <span class="text-xs font-medium">Tap or Drop File</span>
                        </div>
                        <input type="file" id="file-upload" accept=".txt,.pdf,.docx,.doc,image/png,image/jpeg,image/webp" class="hidden" />
                    </label>
                </div>

                <!-- Timeline Audio Controls -->
                <div id="timeline-controls" class="hidden mb-6 p-4 bg-white rounded-xl border border-gray-200 shadow-sm flex flex-col gap-2">
                    <div class="flex justify-between items-center text-xs font-medium text-gray-500 mb-1">
                        <span id="current-time">0:00</span>
                        <span id="duration-time">0:00</span>
                    </div>
                    <input type="range" id="seek-bar" value="0" max="100" class="seek-slider w-full">
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3">
                    <!-- Speak (No Translate) -->
                    <button id="speak-btn" class="w-full bg-indigo-100 hover:bg-indigo-200 text-indigo-700 px-4 py-3 rounded-xl font-bold shadow-sm flex items-center justify-center gap-2 transition-transform">
                        <i class="fas fa-volume-up"></i> <span>Speak (Read Aloud)</span>
                    </button>

                    <!-- Translate (RENAMED TO JUST TRANSLATE) -->
                    <button id="play-btn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-xl font-bold shadow-lg shadow-indigo-200 flex items-center justify-center gap-2 transition-transform">
                        <i class="fas fa-language"></i> <span>Translate Text</span>
                    </button>

                    <!-- Pause/Resume (Dynamic) -->
                    <div class="grid grid-cols-2 gap-3 hidden" id="playback-controls">
                         <button id="pause-audio-btn" class="col-span-1 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg font-medium transition-transform">
                            <i class="fas fa-pause"></i> Pause
                        </button>
                        <button id="resume-audio-btn" class="col-span-1 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg font-medium hidden transition-transform">
                            <i class="fas fa-play"></i> Resume
                        </button>
                        <button id="stop-btn" class="col-span-1 bg-red-50 border border-red-200 hover:bg-red-100 text-red-600 px-4 py-2 rounded-lg font-medium transition-transform">
                             <i class="fas fa-stop"></i> Stop
                        </button>
                    </div>

                    <!-- Download -->
                    <button id="download-btn" class="w-full bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-3 rounded-xl font-medium flex items-center justify-center gap-2 transition-transform">
                        <i class="fas fa-download"></i> <span>Download Audio</span>
                    </button>
                </div>

                <!-- Sliders -->
                <div class="mt-6 space-y-4">
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Speed</label>
                            <span id="speed-value" class="text-xs text-indigo-600 font-bold bg-indigo-50 px-2 py-0.5 rounded">1.0x</span>
                        </div>
                        <input type="range" id="speed-range" min="0.5" max="2.0" step="0.1" value="1.0" class="w-full">
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Volume</label>
                            <span id="volume-value" class="text-xs text-indigo-600 font-bold bg-indigo-50 px-2 py-0.5 rounded">100%</span>
                        </div>
                        <input type="range" id="volume-range" min="0" max="1" step="0.1" value="1" class="w-full">
                    </div>
                </div>

                <!-- Status Indicator -->
                <div class="mt-6 border-t border-gray-200 pt-4">
                     <div class="flex items-center justify-between h-8">
                        <span id="status-text" class="text-xs font-medium text-gray-400">Ready</span>
                        <div class="audio-wave" id="visualizer">
                            <div class="bar"></div>
                            <div class="bar"></div>
                            <div class="bar"></div>
                            <div class="bar"></div>
                            <div class="bar"></div>
                        </div>
                     </div>
                </div>

                 <!-- Loading / Status Bar -->
                <div id="loading-overlay" class="mt-4 w-full bg-indigo-50 border border-indigo-100 rounded-xl p-4 hidden flex-col items-center justify-center gap-2 transition-all">
                    <div class="flex items-center gap-3">
                        <div class="loader" style="width: 16px; height: 16px; border-width: 2px;"></div>
                        <p id="loading-text" class="text-xs font-medium text-indigo-700">Processing...</p>
                    </div>
                    <button id="cancel-btn" class="text-[10px] font-semibold text-indigo-600 hover:text-indigo-800 underline">
                        Cancel
                    </button>
                </div>

                <!-- History Section (NEW) -->
                <div id="history-section" class="mt-6 border-t border-gray-200 pt-4 hidden">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Audio History</h3>
                    <div id="history-list" class="space-y-2">
                        <!-- Items appended here -->
                    </div>
                </div>

            </div>
        </div>

        <!-- Right Panel: Full Scale Text Area (THE BIG DOCUMENT BOX) -->
        <!-- Responsive order: Top on mobile, right on desktop -->
        <!-- Added 'm-3 rounded-2xl shadow-xl' to create a floating Card/Box effect on mobile -->
        <!-- Added 'min-h-[85vh]' to make it massive and consistent -->
        <div class="flex-1 flex flex-col m-3 md:m-0 rounded-2xl md:rounded-none shadow-xl md:shadow-none min-h-[85vh] md:h-full bg-white relative order-1 md:order-2 border border-gray-200 md:border-0 z-0 overflow-hidden">
            
            <!-- Toolbar -->
            <!-- Increased height to h-16 on mobile for "Big" touch targets, styled like an App Header -->
            <div class="h-16 md:h-14 border-b border-gray-100 flex items-center justify-between px-5 md:px-6 bg-white shrink-0 sticky top-0 z-20">
                <span class="text-lg md:text-sm font-bold text-gray-700">Editor</span>
                
                <div class="flex items-center gap-2 md:gap-3">
                    <!-- Search Toggle -->
                    <button id="search-toggle-btn" class="text-gray-500 hover:text-indigo-600 font-medium transition-colors flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100" title="Find in Text">
                        <i class="fas fa-search text-xl md:text-sm"></i>
                    </button>

                    <!-- Chat AI Toggle -->
                    <button id="chat-toggle-btn" class="text-indigo-600 hover:text-indigo-800 font-medium transition-colors flex items-center justify-center w-10 h-10 rounded-full bg-indigo-50 hover:bg-indigo-100" title="Ask Questions">
                        <i class="fas fa-robot text-xl md:text-sm"></i>
                    </button>

                    <div class="h-6 w-px bg-gray-200 mx-1"></div>

                    <!-- Dictate Button -->
                    <button id="mic-btn" class="text-gray-500 hover:text-indigo-600 font-medium transition-colors flex items-center justify-center w-10 h-10 rounded-full hover:bg-gray-100 hidden" title="Voice Typing">
                        <i class="fas fa-microphone text-xl md:text-sm"></i>
                    </button>
                    
                    <button id="clear-btn" class="text-red-500 hover:text-red-700 font-medium transition-colors flex items-center justify-center w-10 h-10 rounded-full hover:bg-red-50">
                        <i class="fas fa-trash-alt text-xl md:text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- Floating Search Bar -->
            <div id="search-bar" class="hidden absolute top-20 md:top-16 left-2 right-2 md:left-auto md:right-6 z-20 bg-white border border-indigo-100 shadow-xl rounded-lg p-2 flex items-center gap-2 animate-fade-in-down">
                <input type="text" id="search-input" placeholder="Find..." class="text-base p-2 flex-1 md:w-48 bg-gray-50 border border-gray-200 rounded focus:outline-none focus:border-indigo-500 focus:bg-white transition-all text-gray-700">
                <div class="flex items-center border-l border-gray-200 pl-2 gap-1">
                    <button id="search-prev-btn" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Previous">
                        <i class="fas fa-chevron-up text-sm"></i>
                    </button>
                    <button id="search-next-btn" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Next">
                        <i class="fas fa-chevron-down text-sm"></i>
                    </button>
                </div>
                <button id="search-close-btn" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors ml-1">
                    <i class="fas fa-times text-base"></i>
                </button>
            </div>

            <!-- Chat Interface Floating Panel -->
            <!-- Bottom Sheet Style on Mobile -->
            <div id="chat-interface" class="hidden-chat fixed md:absolute bottom-0 md:bottom-6 left-0 md:left-auto md:right-6 z-30 w-full md:w-96 bg-white border-t md:border border-gray-200 rounded-t-2xl md:rounded-xl shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.2)] md:shadow-2xl flex flex-col overflow-hidden h-[60vh] md:h-[500px] md:max-h-[60vh]">
                <div class="bg-indigo-600 p-4 flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-2 text-white">
                        <i class="fas fa-robot"></i>
                        <span class="text-sm font-bold">AI Assistant</span>
                    </div>
                    <div class="flex gap-3">
                        <button id="chat-clear-btn" class="text-white/70 hover:text-white transition-colors" title="Clear Chat">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <button id="chat-close-btn" class="text-white/70 hover:text-white transition-colors" title="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div id="chat-messages" class="flex-1 p-4 overflow-y-auto bg-gray-50">
                    <div class="chat-msg chat-msg-ai">
                        Hello! I'm here to help. Ask me to summarize, find details, or explain parts of your text.
                    </div>
                </div>
                
                <div class="p-4 bg-white border-t border-gray-100 shrink-0">
                    <div class="relative">
                        <input type="text" id="chat-input" placeholder="Type a question..." class="w-full pl-4 pr-12 py-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-gray-50 focus:bg-white transition-all">
                        <button id="chat-send-btn" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-indigo-600 hover:text-indigo-800 p-1">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Full Scale Text Area -->
            <!-- Large text, spacious padding, white clean background -->
            <div class="flex-1 relative bg-white">
                 <textarea id="text-input" 
                    class="w-full h-full p-6 sm:p-8 md:p-12 bg-white resize-none focus:outline-none text-gray-900 leading-relaxed text-xl sm:text-2xl font-normal placeholder-gray-400 overflow-y-auto"
                    placeholder="Start typing..."></textarea>
                
                <!-- Translation Overlay (Expanded functionality) -->
                <!-- Added Up/Down toggle support via 'toggleTranslationSize' and onclick handler on header -->
                <div id="translated-preview" class="hidden absolute bottom-0 left-0 w-full h-1/2 md:h-1/3 bg-indigo-50 border-t border-indigo-100 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] z-10 flex flex-col transition-all duration-300">
                    <div class="flex justify-between items-center p-3 px-4 bg-indigo-100 border-b border-indigo-200 cursor-pointer select-none" onclick="toggleTranslationSize()">
                        <div class="flex items-center gap-2">
                             <i class="fas fa-language text-indigo-600"></i>
                             <span class="text-xs font-bold text-indigo-700 uppercase">Translation Preview</span>
                        </div>
                        <div class="flex items-center gap-4">
                             <!-- NEW SPEAK BUTTON: Allows manual speaking of translated text -->
                             <button onclick="event.stopPropagation(); handleAction('speak_translated')" class="text-indigo-500 hover:text-indigo-800 transition-colors focus:outline-none p-1" title="Speak Translation">
                                 <i class="fas fa-volume-up text-lg"></i>
                             </button>
                             <!-- Toggle Button -->
                             <button id="preview-toggle-btn" class="text-indigo-500 hover:text-indigo-800 transition-colors focus:outline-none p-1">
                                 <i class="fas fa-chevron-down text-sm"></i>
                             </button>
                             <!-- Close Button (Cancel) -->
                             <button onclick="event.stopPropagation(); closeTranslationPreview()" class="text-indigo-400 hover:text-red-500 transition-colors focus:outline-none p-1">
                                 <i class="fas fa-times text-lg"></i>
                             </button>
                        </div>
                    </div>
                    <div id="translated-text-content" class="flex-1 p-6 overflow-y-auto text-indigo-900 text-base leading-relaxed bg-indigo-50/50"></div>
                </div>
            </div>

        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-6 py-3 rounded-full shadow-xl text-sm opacity-0 transition-opacity duration-300 pointer-events-none z-50 whitespace-nowrap font-medium">
        Notification
    </div>

    <script>
        // API Key (Runtime)
        const apiKey = "AIzaSyCUK8eF8U1as_9FDAEJMrxWf0LfeDZYJYo"; 

        // Set Worker for PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        // DOM Elements
        const textInput = document.getElementById('text-input');
        const languageSelect = document.getElementById('language-select');
        const voiceSelect = document.getElementById('voice-select');
        const playBtn = document.getElementById('play-btn'); // Translate & Speak
        const speakBtn = document.getElementById('speak-btn'); // Speak Only
        const downloadBtn = document.getElementById('download-btn');
        const stopBtn = document.getElementById('stop-btn');
        const playbackControls = document.getElementById('playback-controls');
        
        const clearBtn = document.getElementById('clear-btn');
        const micBtn = document.getElementById('mic-btn');
        const visualizer = document.getElementById('visualizer');
        const statusText = document.getElementById('status-text');
        const loadingOverlay = document.getElementById('loading-overlay'); 
        const loadingText = document.getElementById('loading-text');
        const cancelBtn = document.getElementById('cancel-btn'); 
        
        const translatedPreview = document.getElementById('translated-preview');
        const translatedTextContent = document.getElementById('translated-text-content');
        const previewToggleBtn = document.getElementById('preview-toggle-btn');
        
        const fileUpload = document.getElementById('file-upload');
        const dropZone = document.getElementById('drop-zone');
        
        // History Elements
        const historySection = document.getElementById('history-section');
        const historyList = document.getElementById('history-list');
        
        // Search Elements
        const searchToggleBtn = document.getElementById('search-toggle-btn');
        const searchBar = document.getElementById('search-bar');
        const searchInput = document.getElementById('search-input');
        const searchNextBtn = document.getElementById('search-next-btn');
        const searchPrevBtn = document.getElementById('search-prev-btn');
        const searchCloseBtn = document.getElementById('search-close-btn');

        // Chat Elements
        const chatToggleBtn = document.getElementById('chat-toggle-btn');
        const chatInterface = document.getElementById('chat-interface');
        const chatCloseBtn = document.getElementById('chat-close-btn');
        const chatMessages = document.getElementById('chat-messages');
        const chatInput = document.getElementById('chat-input');
        const chatSendBtn = document.getElementById('chat-send-btn');
        const chatClearBtn = document.getElementById('chat-clear-btn');

        // Controls
        const speedRange = document.getElementById('speed-range');
        const speedValue = document.getElementById('speed-value');
        const volumeRange = document.getElementById('volume-range');
        const volumeValue = document.getElementById('volume-value');
        const timelineControls = document.getElementById('timeline-controls');
        const seekBar = document.getElementById('seek-bar');
        const currentTimeDisplay = document.getElementById('current-time');
        const durationTimeDisplay = document.getElementById('duration-time');
        const pauseAudioBtn = document.getElementById('pause-audio-btn');
        const resumeAudioBtn = document.getElementById('resume-audio-btn');

        // State
        let currentAudio = null;
        let recognition = null;
        let isRecording = false;
        let isStopped = false;
        
        let audioQueue = [];
        let allPcmChunks = [];
        let isPlayingQueue = false;
        let isDownloadMode = false;
        let isPreviewMinimized = false;

        // --- Helper Functions ---

        function showLoading(show, text = "Processing...") {
            loadingText.textContent = text;
            if (show) {
                loadingOverlay.classList.remove('hidden');
                loadingOverlay.classList.add('flex');
            } else {
                loadingOverlay.classList.add('hidden');
                loadingOverlay.classList.remove('flex');
            }
        }

        function showNotification(msg) {
            const notif = document.getElementById('notification');
            notif.textContent = msg;
            notif.style.opacity = '1';
            setTimeout(() => { notif.style.opacity = '0'; }, 3000);
        }

        function resetUI() {
            visualizer.classList.remove('active');
            playbackControls.classList.add('hidden');
            statusText.textContent = "Ready";
            statusText.classList.remove('text-indigo-600');
            // Show start buttons again
            playBtn.disabled = false;
            speakBtn.disabled = false;
            downloadBtn.disabled = false;
        }

        function togglePlaybackUI(active) {
            if(active) {
                playbackControls.classList.remove('hidden');
                timelineControls.classList.remove('hidden');
                pauseAudioBtn.classList.remove('hidden');
                resumeAudioBtn.classList.add('hidden');
            } else {
                playbackControls.classList.add('hidden');
                timelineControls.classList.add('hidden');
            }
        }

        // --- Translation Preview Logic ---
        function toggleTranslationSize() {
            isPreviewMinimized = !isPreviewMinimized;
            const icon = previewToggleBtn.querySelector('i');
            
            if (isPreviewMinimized) {
                translatedPreview.classList.add('preview-minimized');
                icon.className = 'fas fa-chevron-up text-sm'; // Show up arrow when minimized
            } else {
                translatedPreview.classList.remove('preview-minimized');
                icon.className = 'fas fa-chevron-down text-sm'; // Show down arrow when expanded
            }
        }

        function closeTranslationPreview() {
            translatedPreview.classList.add('hidden');
            // Reset to default expanded state for next time
            isPreviewMinimized = false;
            translatedPreview.classList.remove('preview-minimized');
            previewToggleBtn.querySelector('i').className = 'fas fa-chevron-down text-sm';
        }

        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
        }

        function base64ToArrayBuffer(base64) {
            const binaryString = window.atob(base64);
            const len = binaryString.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            return bytes.buffer;
        }

        function writeWavHeader(samples, sampleRate = 24000, numChannels = 1, bitDepth = 16) {
            const buffer = new ArrayBuffer(44 + samples.length * 2);
            const view = new DataView(buffer);
            const writeString = (view, offset, string) => {
                for (let i = 0; i < string.length; i++) view.setUint8(offset + i, string.charCodeAt(i));
            };
            writeString(view, 0, 'RIFF');
            view.setUint32(4, 36 + samples.length * 2, true);
            writeString(view, 8, 'WAVE');
            writeString(view, 12, 'fmt ');
            view.setUint32(16, 16, true);
            view.setUint16(20, 1, true);
            view.setUint16(22, numChannels, true);
            view.setUint32(24, sampleRate, true);
            view.setUint32(28, sampleRate * numChannels * (bitDepth / 8), true);
            view.setUint16(32, numChannels * (bitDepth / 8), true);
            view.setUint16(34, bitDepth, true);
            writeString(view, 36, 'data');
            view.setUint32(40, samples.length * 2, true);
            let offset = 44;
            for (let i = 0; i < samples.length; i++, offset += 2) view.setInt16(offset, samples[i], true);
            return buffer;
        }

        // --- Speech Recognition Setup ---
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            recognition.continuous = true;
            recognition.interimResults = true;
            
            micBtn.classList.remove('hidden');

            recognition.onresult = (event) => {
                let interimTranscript = '';
                let finalTranscript = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) finalTranscript += event.results[i][0].transcript;
                    else interimTranscript += event.results[i][0].transcript;
                }
                if (finalTranscript) {
                    const currentText = textInput.value;
                    const spacer = currentText.length > 0 && !currentText.endsWith(' ') ? ' ' : '';
                    textInput.value = currentText + spacer + finalTranscript;
                }
            };
            recognition.onerror = (event) => { stopRecording(); showNotification("Mic Error: " + event.error); };
            recognition.onend = () => { if (isRecording) stopRecording(); };
        }

        function toggleRecording() { isRecording ? stopRecording() : startRecording(); }

        function startRecording() {
            if (!recognition) return;
            try {
                recognition.start();
                isRecording = true;
                micBtn.innerHTML = '<i class="fas fa-stop"></i>'; // simplified icon
                micBtn.classList.add('recording-active');
                textInput.placeholder = "Listening...";
            } catch (e) { console.error(e); }
        }

        function stopRecording() {
            if (!recognition) return;
            recognition.stop();
            isRecording = false;
            micBtn.innerHTML = '<i class="fas fa-microphone text-xl md:text-sm"></i>';
            micBtn.classList.remove('recording-active');
            textInput.placeholder = "Start typing...";
        }

        // --- Search Functionality ---
        function toggleSearchBar() {
            const isHidden = searchBar.classList.contains('hidden');
            if (isHidden) { searchBar.classList.remove('hidden'); searchInput.focus(); } 
            else { searchBar.classList.add('hidden'); textInput.focus(); }
        }

        function searchText(direction) {
            const query = searchInput.value;
            if (!query) return;
            const content = textInput.value.toLowerCase();
            const queryLower = query.toLowerCase();
            const currentPos = textInput.selectionStart;
            let nextPos = -1;

            if (direction === 'next') {
                nextPos = content.indexOf(queryLower, currentPos + 1);
                if (nextPos === -1) { nextPos = content.indexOf(queryLower, 0); showNotification("Wrapped to top"); }
            } else {
                nextPos = content.lastIndexOf(queryLower, currentPos - 1);
                if (nextPos === -1) { nextPos = content.lastIndexOf(queryLower); showNotification("Wrapped to bottom"); }
            }

            if (nextPos !== -1) {
                textInput.focus();
                textInput.setSelectionRange(nextPos, nextPos + query.length);
            } else {
                showNotification("Text not found");
            }
        }

        // --- Chat AI Functionality ---
        function toggleChat() {
            const isHidden = chatInterface.classList.contains('hidden-chat');
            if (isHidden) {
                chatInterface.classList.remove('hidden-chat');
                chatInput.focus();
            } else {
                chatInterface.classList.add('hidden-chat');
            }
        }

        function addChatMessage(text, type) {
            const div = document.createElement('div');
            div.className = `chat-msg ${type === 'user' ? 'chat-msg-user' : 'chat-msg-ai'}`;
            div.textContent = text;
            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        async function handleChatSubmit() {
            const query = chatInput.value.trim();
            const contextText = textInput.value.trim();
            
            if (!query) return;

            addChatMessage(query, 'user');
            chatInput.value = '';
            chatInput.disabled = true;
            
            // Add loading placeholder
            const loadingId = 'chat-loading-' + Date.now();
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'chat-msg chat-msg-ai';
            loadingDiv.id = loadingId;
            loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Thinking...';
            chatMessages.appendChild(loadingDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            try {
                let prompt;
                if (contextText) {
                    prompt = `You are an intelligent assistant helping a user with a document.\n\nDOCUMENT CONTENT:\n"""${contextText}"""\n\nUSER QUESTION: ${query}\n\nINSTRUCTIONS:\n- Answer the question comprehensively based on the document provided.\n- If the document lacks specific details, use your general knowledge to supplement the answer, but clearly distinguish between what is in the text and what is external knowledge.\n- Provide clear, detailed, and accurate information.`;
                } else {
                    prompt = `You are a helpful AI assistant. User Question: ${query}\nAnswer comprehensively and helpfully.`;
                }
                
                const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=${apiKey}`;
                const payload = {
                    contents: [{ parts: [{ text: prompt }] }]
                };

                const response = await callGeminiApi(url, payload, "Chat AI");
                const answer = response.candidates?.[0]?.content?.parts?.[0]?.text || "I couldn't generate an answer.";
                
                document.getElementById(loadingId).remove();
                addChatMessage(answer, 'ai');

            } catch (error) {
                document.getElementById(loadingId).remove();
                addChatMessage("Error: " + error.message, 'ai');
            } finally {
                chatInput.disabled = false;
                chatInput.focus();
            }
        }

        // --- File Processing Functions ---
        async function readTextFile(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => resolve(e.target.result);
                reader.onerror = (e) => reject(e);
                reader.readAsText(file);
            });
        }

        async function extractTextFromPdf(file) {
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument(arrayBuffer).promise;
            let fullText = "";
            for (let i = 1; i <= pdf.numPages; i++) {
                const page = await pdf.getPage(i);
                const textContent = await page.getTextContent();
                const pageText = textContent.items.map(item => item.str).join(" ");
                fullText += pageText + "\n";
            }
            return fullText;
        }

        async function extractTextFromDocx(file) {
            const arrayBuffer = await file.arrayBuffer();
            const result = await mammoth.extractRawText({ arrayBuffer: arrayBuffer });
            return result.value;
        }

        async function extractTextFromImage(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onloadend = async () => {
                    const base64Data = reader.result.split(',')[1];
                    const mimeType = file.type;
                    try {
                        showLoading(true, "Scanning image for text...");
                        const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=${apiKey}`;
                        const payload = {
                            contents: [{
                                parts: [
                                    { text: "Extract all text from this image exactly as it appears. Return ONLY the text, no explanations." },
                                    { inlineData: { mimeType: mimeType, data: base64Data } }
                                ]
                            }]
                        };
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const result = await response.json();
                        const extractedText = result.candidates?.[0]?.content?.parts?.[0]?.text;
                        if (extractedText) resolve(extractedText);
                        else reject(new Error("No text found in image"));
                    } catch (error) { reject(error); }
                };
                reader.readAsDataURL(file);
            });
        }

        // --- Core Application Logic ---

        // Universal API caller with retry for 429 errors
        async function callGeminiApi(url, payload, contextName = "API") {
            let attempts = 0;
            const maxAttempts = 5;
            let delay = 2000; 

            while (attempts < maxAttempts) {
                if(isStopped) throw new Error("Stopped by user");
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(payload)
                    });

                    if (response.status === 429) {
                        const retryHeader = response.headers.get('Retry-After');
                        const waitTime = retryHeader ? parseInt(retryHeader) * 1000 : (delay * Math.pow(2, attempts));
                        const effectiveWait = Math.max(waitTime, 15000); 
                        
                        showLoading(true, `${contextName}: Quota hit. Retrying in ${Math.ceil(effectiveWait/1000)}s...`);
                        await new Promise(r => setTimeout(r, effectiveWait));
                        attempts++;
                        continue;
                    }

                    if (!response.ok) {
                        const err = await response.json();
                        throw new Error(err.error?.message || `${contextName} failed`);
                    }

                    return await response.json();

                } catch (e) {
                    if (e.message.includes("Stopped")) throw e;
                    console.error(`${contextName} error:`, e);
                    if(attempts === maxAttempts - 1) throw e;
                    attempts++;
                    await new Promise(r => setTimeout(r, 2000));
                }
            }
            throw new Error(`${contextName}: Max retries exceeded.`);
        }

        // --- HUGE CAPACITY UPDATE: Chunk size increased to 4000 characters ---
        // This dramatically reduces API calls for very large files (e.g. 20+ pages)
        function splitTextIntoChunks(text, maxLength = 4000) {
            const chunks = [];
            const sentences = text.match(/[^.!?]+[.!?]+|[^.!?]+$/g) || [text];
            let currentChunk = "";
            for (const sentence of sentences) {
                if ((currentChunk + sentence).length > maxLength) {
                    if (currentChunk.trim()) chunks.push(currentChunk.trim());
                    currentChunk = sentence;
                } else {
                    currentChunk += sentence;
                }
            }
            if (currentChunk.trim()) chunks.push(currentChunk.trim());
            return chunks;
        }

        async function translateText(text, targetLang) {
            const transUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=${apiKey}`;
            const transPayload = {
                contents: [{ parts: [{ text: `Translate to ${targetLang}. Return ONLY translated text. Text: "${text}"` }] }]
            };
            
            const transResult = await callGeminiApi(transUrl, transPayload, "Translation");
            const translatedText = transResult.candidates?.[0]?.content?.parts?.[0]?.text;
            if (!translatedText) throw new Error("Translation failed.");
            return translatedText;
        }

        async function generateAndStreamAudio(text, voiceName) {
            // Updated to 4000 char capacity per request
            const chunks = splitTextIntoChunks(text, 4000); 
            const ttsUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent?key=${apiKey}`;

            for (let i = 0; i < chunks.length; i++) {
                if(isStopped) break;
                // Simplified loading message
                showLoading(true, `Processing part ${i+1}/${chunks.length}...`);
                
                try {
                    const ttsPayload = {
                        contents: [{ parts: [{ text: chunks[i] }] }],
                        generationConfig: { responseModalities: ["AUDIO"], speechConfig: { voiceConfig: { prebuiltVoiceConfig: { voiceName: voiceName } } } }
                    };

                    const ttsResult = await callGeminiApi(ttsUrl, ttsPayload, `Audio Part ${i+1}`);
                    const audioData = ttsResult.candidates?.[0]?.content?.parts?.[0]?.inlineData?.data;
                    
                    if (!audioData) throw new Error(`Audio Part ${i+1} returned no data.`);
                    
                    const pcmData = new Int16Array(base64ToArrayBuffer(audioData));
                    allPcmChunks.push(pcmData);
                    
                    if (!isDownloadMode) {
                        const wavBuffer = writeWavHeader(pcmData);
                        const blob = new Blob([wavBuffer], { type: 'audio/wav' });
                        const url = URL.createObjectURL(blob);
                        audioQueue.push(url);
                        
                        if (!isPlayingQueue) {
                            playNextInQueue();
                        }
                    }
                    
                    // --- SPEED OPTIMIZATION: Removed artificial 10s delays ---
                    // We only pause briefly to let the browser breathe. Rate limits are handled by callGeminiApi.
                    if (i < chunks.length - 1) {
                        await new Promise(r => setTimeout(r, 500)); 
                    }
                    
                } catch (e) { console.error("Chunk failed", e); throw e; }
            }
            
            // Add to History automatically when done
            if (!isDownloadMode && !isStopped && allPcmChunks.length > 0) {
                const finalBlob = mergePcmToWav(allPcmChunks);
                addHistoryItem(finalBlob, text.substring(0, 20) + (text.length > 20 ? "..." : ""));
            }

            if(isDownloadMode) downloadMergedAudio();
            else { showLoading(false); statusText.textContent = "Playback active"; }
        }

        function playNextInQueue() {
            if (audioQueue.length === 0) {
                isPlayingQueue = false;
                if(!isStopped && allPcmChunks.length > 0) prepareMergedAudioForReplay();
                return;
            }
            isPlayingQueue = true;
            const url = audioQueue.shift();
            if(currentAudio) { currentAudio.pause(); URL.revokeObjectURL(currentAudio.src); }

            currentAudio = new Audio(url);
            currentAudio.playbackRate = parseFloat(speedRange.value); 
            currentAudio.volume = parseFloat(volumeRange.value);
            
            togglePlaybackUI(true);
            visualizer.classList.add('active');
            statusText.textContent = "Speaking...";

            currentAudio.onloadedmetadata = () => { if (currentAudio) { durationTimeDisplay.textContent = formatTime(currentAudio.duration); seekBar.max = currentAudio.duration; }};
            currentAudio.ontimeupdate = () => { if (currentAudio) { seekBar.value = currentAudio.currentTime; currentTimeDisplay.textContent = formatTime(currentAudio.currentTime); }};
            currentAudio.onended = () => { playNextInQueue(); };
            currentAudio.play();
        }

        function prepareMergedAudioForReplay() {
            const mergedBlob = mergePcmToWav(allPcmChunks);
            const url = URL.createObjectURL(mergedBlob);
            currentAudio = new Audio(url);
            currentAudio.playbackRate = parseFloat(speedRange.value);
            currentAudio.volume = parseFloat(volumeRange.value);
            statusText.textContent = "Full Audio Ready";
            statusText.classList.add('text-green-600');
            visualizer.classList.remove('active');
            
            pauseAudioBtn.classList.add('hidden');
            resumeAudioBtn.classList.remove('hidden'); 
            
            currentAudio.onended = () => { resetUI(); togglePlaybackUI(false); };
        }

        function downloadMergedAudio() {
            if (allPcmChunks.length === 0) return;
            const blob = mergePcmToWav(allPcmChunks);
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `vocalize-full-${Date.now()}.wav`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showNotification("Full Download Started!");
            showLoading(false);
        }

        function mergePcmToWav(chunks) {
            const totalLength = chunks.reduce((acc, part) => acc + part.length, 0);
            const mergedPcm = new Int16Array(totalLength);
            let offset = 0;
            for (const part of chunks) { mergedPcm.set(part, offset); offset += part.length; }
            const buffer = writeWavHeader(mergedPcm);
            return new Blob([buffer], { type: 'audio/wav' });
        }

        // --- NEW: Add History Item Function ---
        function addHistoryItem(blob, label) {
            const url = URL.createObjectURL(blob);
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            const div = document.createElement('div');
            div.className = 'history-item';
            div.innerHTML = `
                <div class="flex flex-col overflow-hidden mr-2">
                    <span class="text-xs font-bold text-gray-700 truncate" title="${label}">${label}</span>
                    <span class="text-[10px] text-gray-400">${time}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button class="text-indigo-600 hover:text-indigo-800 p-1" title="Play" onclick="playHistoryItem('${url}')">
                        <i class="fas fa-play"></i>
                    </button>
                    <a href="${url}" download="vocalize-${Date.now()}.wav" class="text-green-600 hover:text-green-800 p-1" title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                    <button class="text-red-500 hover:text-red-700 p-1" title="Delete" onclick="deleteHistoryItem(this, '${url}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            
            historyList.insertBefore(div, historyList.firstChild);
            historySection.classList.remove('hidden');
        }

        function playHistoryItem(url) {
            if(currentAudio) { currentAudio.pause(); URL.revokeObjectURL(currentAudio.src); }
            currentAudio = new Audio(url);
            currentAudio.playbackRate = parseFloat(speedRange.value); 
            currentAudio.volume = parseFloat(volumeRange.value);
            
            statusText.textContent = "Playing History...";
            togglePlaybackUI(true);
            visualizer.classList.add('active');
            
            currentAudio.onloadedmetadata = () => { if (currentAudio) { durationTimeDisplay.textContent = formatTime(currentAudio.duration); seekBar.max = currentAudio.duration; }};
            currentAudio.ontimeupdate = () => { if (currentAudio) { seekBar.value = currentAudio.currentTime; currentTimeDisplay.textContent = formatTime(currentAudio.currentTime); }};
            currentAudio.onended = () => { resetUI(); togglePlaybackUI(false); };
            currentAudio.play();
        }

        function deleteHistoryItem(btn, url) {
            if(confirm('Delete this audio from history?')) {
                URL.revokeObjectURL(url);
                const item = btn.closest('.history-item');
                item.remove();
                if(historyList.children.length === 0) {
                    historySection.classList.add('hidden');
                }
            }
        }

        async function handleAction(type) {
            let text = textInput.value.trim();
            
            // Special handling for translated speech which gets text from div, not input
            if (type === 'speak_translated') {
                text = translatedTextContent.textContent.trim();
                if (!text) { showNotification("No translation to speak."); return; }
            } else {
                if (!text) { showNotification("Please enter or upload text."); return; }
            }
            
            isStopped = true; 
            if (currentAudio) { currentAudio.pause(); currentAudio = null; }
            await new Promise(r => setTimeout(r, 50));
            isStopped = false;

            audioQueue = [];
            allPcmChunks = [];
            isPlayingQueue = false;
            isDownloadMode = (type === 'download');

            try {
                const targetLang = languageSelect.value;
                const voiceName = voiceSelect.value;
                
                let textToSpeak = text;

                // If type is 'translate', just translate and show preview, DON'T SPEAK
                if (type === 'translate') {
                    showLoading(true, `Translating...`);
                    const translatedText = await translateText(text, targetLang);
                    translatedTextContent.textContent = translatedText;
                    translatedPreview.classList.remove('hidden');
                    
                    // Reset preview state
                    isPreviewMinimized = false;
                    translatedPreview.classList.remove('preview-minimized');
                    previewToggleBtn.querySelector('i').className = 'fas fa-chevron-down text-sm';

                    showLoading(false); // Done
                    return; // STOP HERE
                }

                await generateAndStreamAudio(textToSpeak, voiceName);

            } catch (error) {
                if (error.message.includes("Stopped")) showNotification("Process Cancelled");
                else { console.error(error); showNotification("Error: " + error.message); }
                showLoading(false);
            }
        }

        async function processUploadedFile(file) {
            showLoading(true, "Processing document...");
            try {
                let extractedText = "";
                if (file.type === "application/pdf") extractedText = await extractTextFromPdf(file);
                else if (file.type === "application/vnd.openxmlformats-officedocument.wordprocessingml.document") extractedText = await extractTextFromDocx(file);
                else if (file.type.startsWith("image/")) extractedText = await extractTextFromImage(file);
                else extractedText = await readTextFile(file);

                if(!extractedText || extractedText.trim().length === 0) showNotification("Warning: No text found in file.");
                else { textInput.value = extractedText; showNotification("File processed successfully!"); }
                
                fileUpload.value = '';
            } catch (error) { console.error(error); showNotification("Error reading file."); } 
            finally { showLoading(false); }
        }

        // --- Event Listeners ---
        playBtn.addEventListener('click', () => handleAction('translate')); // Only Translate
        speakBtn.addEventListener('click', () => handleAction('speak')); // Read Aloud (No Translate)
        downloadBtn.addEventListener('click', () => handleAction('download'));

        const cancelAction = () => {
            isStopped = true;
            if (currentAudio) { currentAudio.pause(); currentAudio.currentTime = 0; currentAudio = null; }
            resetUI();
            togglePlaybackUI(false);
            showLoading(false);
            showNotification("Cancelled");
        };

        stopBtn.addEventListener('click', cancelAction);
        cancelBtn.addEventListener('click', cancelAction);
        clearBtn.addEventListener('click', () => {
            textInput.value = '';
            translatedPreview.classList.add('hidden');
            fileUpload.value = '';
            isStopped = true;
            if(currentAudio) { currentAudio.pause(); resetUI(); togglePlaybackUI(false); }
        });
        
        micBtn.addEventListener('click', toggleRecording);

        // Search Listeners
        searchToggleBtn.addEventListener('click', toggleSearchBar);
        searchCloseBtn.addEventListener('click', toggleSearchBar);
        searchNextBtn.addEventListener('click', () => searchText('next'));
        searchPrevBtn.addEventListener('click', () => searchText('prev'));
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') searchText('next');
            if (e.key === 'Escape') toggleSearchBar();
        });

        // Chat Listeners
        chatToggleBtn.addEventListener('click', toggleChat);
        chatCloseBtn.addEventListener('click', toggleChat);
        chatSendBtn.addEventListener('click', handleChatSubmit);
        chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') handleChatSubmit();
        });

        // Clear Chat Listener
        chatClearBtn.addEventListener('click', () => {
            chatMessages.innerHTML = '';
            addChatMessage("Chat history cleared. How can I help you?", 'ai');
        });

        speedRange.addEventListener('input', (e) => {
            speedValue.textContent = e.target.value + 'x';
            if (currentAudio) currentAudio.playbackRate = parseFloat(e.target.value);
        });
        volumeRange.addEventListener('input', (e) => {
            volumeValue.textContent = Math.round(e.target.value * 100) + '%';
            if (currentAudio) currentAudio.volume = parseFloat(e.target.value);
        });
        seekBar.addEventListener('input', (e) => { if (currentAudio) currentAudio.currentTime = e.target.value; });

        pauseAudioBtn.addEventListener('click', () => {
            if(currentAudio) {
                currentAudio.pause();
                pauseAudioBtn.classList.add('hidden');
                resumeAudioBtn.classList.remove('hidden');
                statusText.textContent = "Paused";
                visualizer.classList.remove('active');
            }
        });
        resumeAudioBtn.addEventListener('click', () => {
            if(currentAudio) {
                currentAudio.play();
                resumeAudioBtn.classList.add('hidden');
                pauseAudioBtn.classList.remove('hidden');
            }
        });

        fileUpload.addEventListener('change', async (e) => { if (e.target.files[0]) await processUploadedFile(e.target.files[0]); });
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drag-active'); });
        dropZone.addEventListener('dragleave', (e) => { e.preventDefault(); dropZone.classList.remove('drag-active'); });
        dropZone.addEventListener('drop', async (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-active');
            if (e.dataTransfer.files.length > 0) await processUploadedFile(e.dataTransfer.files[0]);
        });

        // --- NEW: Handle File Passed via URL Parameter ---
        window.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const fileUrl = params.get('file');
            
            if (fileUrl) {
                showLoading(true, "Loading file from Home...");
                
                fetch(fileUrl)
                    .then(response => {
                        if (!response.ok) throw new Error("File not found or access denied.");
                        return response.blob();
                    })
                    .then(blob => {
                        // Infer correct MIME type if missing or generic, based on extension
                        // This ensures processUploadedFile triggers the correct extractor (PDF.js vs Mammoth vs Text)
                        const ext = fileUrl.split('.').pop().toLowerCase();
                        let mime = blob.type;
                        
                        if (ext === 'pdf' && !mime.includes('pdf')) {
                            mime = 'application/pdf';
                        } else if ((ext === 'docx' || ext === 'doc') && !mime.includes('word')) {
                            mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                        } else if (ext === 'txt' && !mime.includes('text')) {
                            mime = 'text/plain';
                        }

                        // Create a File object to pass to existing processor
                        const file = new File([blob], "imported_file." + ext, { type: mime });
                        
                        processUploadedFile(file);
                    })
                    .catch(err => {
                        console.error(err);
                        showNotification("Failed to load file: " + err.message);
                        showLoading(false);
                    });
            }
        });

    </script>
</body>
</html>