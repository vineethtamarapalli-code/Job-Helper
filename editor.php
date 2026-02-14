<?php
// Prevent browser caching - CRITICAL for security on logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$file_data = null;
$is_editable = true; // Default for new files

// Fetch ALL user files for the sidebar/modal list
$stmt_all_files = $conn->prepare("SELECT id, display_name, original_name, filename, file_type, upload_date FROM files WHERE user_id = ? ORDER BY upload_date DESC, id DESC");
$stmt_all_files->execute([$user_id]);
$all_user_files = $stmt_all_files->fetchAll(PDO::FETCH_ASSOC);

// Check if we are editing an existing file
if (isset($_GET['id'])) {
    $file_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$file_id, $user_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($file) {
        $extension = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
        
        $file_data = [
            'id' => $file['id'],
            'name' => $file['display_name'],
            'url' => 'uploads/' . $file['filename'],
            'type' => $extension,
            'mime' => $file['file_type']
        ];

        // LOGIC: 
        // 1. Native files (text/html) -> Editable
        // 2. Word Files (.docx) -> Editable
        // 3. All others (PDF, Images, etc.) -> View Only
        $is_editable = false; 
        
        if ($file['file_type'] === 'text/html' || $extension === 'html' || $extension === 'htm' || $extension === 'txt') {
            $is_editable = true;
        }
        if ($extension === 'docx') {
            $is_editable = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Document Editor - Job Helper</title>
    
    <!-- React & ReactDOM -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    
    <!-- Babel for JSX -->
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4A00E0',
                        secondary: '#8E2DE2',
                        paper: '#ffffff',
                        canvas: '#E3E5E8'
                    },
                    boxShadow: {
                        'paper': '0 20px 50px -12px rgba(0, 0, 0, 0.25)',
                        'toolbar': '0 2px 4px 0 rgba(0, 0, 0, 0.05)',
                    }
                }
            }
        }
    </script>
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- PDF.js & Mammoth.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
    
    <style>
        body { margin: 0; overflow: hidden; background: #E3E5E8; touch-action: pan-x pan-y; }
        
        /* EDITOR TYPOGRAPHY */
        .prose { 
            max-width: none; 
            font-family: 'Calibri', 'Arial', sans-serif; 
            color: #1a1a1a; 
            font-size: 11pt; 
            line-height: 1.15;
            outline: none;
            min-height: 100%;
        }
        
        .prose p { margin-bottom: 10pt; }
        .prose h1 { font-size: 24pt; font-weight: bold; color: #2F5496; margin-top: 24pt; margin-bottom: 6pt; }
        .prose h2 { font-size: 18pt; font-weight: bold; color: #2F5496; margin-top: 18pt; margin-bottom: 6pt; }
        .prose h3 { font-size: 14pt; font-weight: bold; color: #1f3763; margin-top: 14pt; margin-bottom: 6pt; }
        
        /* Lists */
        .prose ul { list-style-type: disc; padding-left: 40px; margin-bottom: 10pt; }
        .prose ol { list-style-type: decimal; padding-left: 40px; margin-bottom: 10pt; }
        .prose li { margin-bottom: 0; }
        
        .prose strong, .prose b { font-weight: 700; }
        .prose em, .prose i { font-style: italic; }
        .prose u { text-decoration: underline; }
        .prose a { color: #0563c1; text-decoration: underline; cursor: pointer; }
        .prose img { max-width: 100%; height: auto; display: block; margin: 1em 0; }
        .prose blockquote { border-left: 4px solid #ccc; margin-left: 0; padding-left: 16px; color: #555; }
        
        /* Tables */
        .table-wrapper { overflow-x: auto; margin: 1em 0; }
        .prose table { width: 100%; border-collapse: collapse; min-width: 100%; border: 1px solid #aaa; }
        .prose th, .prose td { border: 1px solid #aaa; padding: 0.4em; text-align: left; vertical-align: top; }
        .prose th { background-color: #f2f2f2; font-weight: bold; }
        
        /* View Only */
        .view-only { cursor: default; }
        
        /* PDF specific styles */
        .pdf-page-container {
            position: relative;
            background-color: white;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .pdf-page-container img {
            display: block;
            width: 100%;
            height: auto;
        }

        /* Image Mode specific styles */
        .image-view-mode {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: transparent;
            min-height: 100%;
            width: 100%;
        }
        .image-view-mode img {
            max-width: 90%;
            max-height: 90vh;
            width: auto;
            height: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            border-radius: 4px;
        }
        
        /* Visual Page Break - REFINED */
        .visual-page-break {
            display: block;
            height: 30px;
            background-color: #E3E5E8;
            border-top: 1px solid #ccc;
            border-bottom: 1px solid #ccc;
            margin: 40px -96px; 
            width: calc(100% + 192px); /* Force full width on Desktop */
            position: relative;
            user-select: none;
            pointer-events: none;
            box-shadow: inset 0 4px 6px -2px rgba(0,0,0,0.1);
        }
        
        .visual-page-break::after {
            content: "Page Break";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            background-color: #E3E5E8;
            padding: 0 12px;
        }

        /* Mobile Adjustments - FIX FOR BROKEN LOOK */
        @media (max-width: 640px) {
            .visual-page-break { 
                margin: 20px -24px; /* Matches the padding exactly */
                width: calc(100% + 48px); /* Force full width on Mobile */
            }
            .page-content { padding: 24px !important; }
            
            /* Enhanced Touch Targets for Mobile Zoom */
            input[type=range] {
                height: 24px !important;
            }
            input[type=range]::-webkit-slider-thumb {
                height: 24px !important;
                width: 24px !important;
                margin-top: -10px !important; 
            }
        }
        
        .animate-fade-in { animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* Scrollbars */
        .custom-scrollbar::-webkit-scrollbar { width: 10px; height: 10px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 5px; border: 2px solid #E3E5E8; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }

        /* Ruler */
        .ruler-container {
            height: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
            position: relative;
            overflow: hidden;
            font-size: 9px;
            color: #888;
        }
        .ruler-tick {
            position: absolute;
            top: 0;
            border-left: 1px solid #ccc;
        }
        .ruler-tick.major { height: 100%; border-left: 1px solid #999; }
        .ruler-tick.minor { height: 30%; top: 70%; }
        .ruler-number { position: absolute; top: 2px; transform: translateX(2px); }
        
        /* Custom Range Slider for Zoom */
        input[type=range] {
            -webkit-appearance: none;
            width: 100px;
            background: transparent;
        }
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 14px;
            width: 14px;
            border-radius: 50%;
            background: #4A00E0;
            cursor: pointer;
            margin-top: -5px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        input[type=range]::-webkit-slider-runnable-track {
            width: 100%;
            height: 4px;
            cursor: pointer;
            background: #e2e8f0;
            border-radius: 2px;
        }

        /* Improved Print Styles - Sheet Only */
        @media print {
            @page { margin: 0; size: auto; }
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            header, footer, .z-50, .ruler-container, .visual-page-break, .fixed, .absolute, button, .no-print { display: none !important; }
            #root, .flex, .flex-col, .h-screen { display: block !important; height: auto !important; width: 100% !important; overflow: visible !important; }
            .workspace { padding: 0 !important; margin: 0 !important; overflow: visible !important; height: auto !important; background: white !important; display: block !important; width: 100% !important; }
            .scale-wrapper { width: 100% !important; height: auto !important; margin: 0 !important; transform: none !important; position: static !important; display: block !important; }
            .page-content { width: 100% !important; max-width: 100% !important; margin: 0 !important; box-shadow: none !important; border: none !important; transform: none !important; min-height: 0 !important; background: white !important; }
            .prose { min-height: 0 !important; height: auto !important; }
            
            /* Print Page Break */
            .visual-page-break { 
                height: 0; 
                margin: 0; 
                border: none; 
                background: none; 
                page-break-after: always; 
                break-after: page;
            }
            .visual-page-break::after { display: none; }
        }
    </style>
</head>
<body>
    <div id="root"></div>

    <script type="text/babel">
        const { useState, useRef, useEffect, useCallback } = React;

        const INITIAL_FILE = <?php echo json_encode($file_data); ?>;
        const IS_EDITABLE_INIT = <?php echo json_encode($is_editable); ?>;
        const USER_FILES = <?php echo json_encode($all_user_files); ?>;
        const A4_WIDTH_PX = 816; // Standard 96DPI A4 width

        const ToolbarButton = ({ onClick, isActive, icon, tooltip, disabled, className, style }) => (
            <button
                onMouseDown={(e) => { 
                    e.preventDefault(); // CRITICAL: Prevents focus loss from editor
                    if (!disabled) onClick(e); 
                }}
                disabled={disabled}
                title={tooltip}
                style={style}
                className={`w-9 h-9 rounded-lg transition-all flex items-center justify-center flex-shrink-0 ${
                    isActive 
                        ? 'bg-primary/10 text-primary shadow-sm' 
                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                } ${disabled ? 'opacity-40 cursor-not-allowed' : ''} ${className || ''}`}
            >
                <i className={`fas ${icon} text-base`}></i>
            </button>
        );

        const Divider = () => <div className="w-px h-6 bg-gray-200 mx-2 hidden sm:block" />;

        const Dropdown = ({ label, value, options, onChange }) => (
            <div className="relative group inline-block flex-shrink-0">
                <select
                    onMouseDown={(e) => {
                        e.stopPropagation(); 
                    }} 
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="appearance-none bg-transparent hover:bg-gray-100 px-3 py-1.5 pr-8 rounded-lg cursor-pointer text-sm font-medium focus:outline-none max-w-[110px] sm:max-w-[140px] text-gray-700 border border-transparent hover:border-gray-200 transition-colors"
                    title={label}
                >
                    {options.map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>
                <i className="fas fa-chevron-down absolute right-2.5 top-1/2 transform -translate-y-1/2 pointer-events-none text-gray-400 text-xs"></i>
            </div>
        );

        const Ruler = ({ width }) => {
            const ticks = [];
            const tickSpacing = 96 / 8; // 1/8th of an inch approx
            const numTicks = Math.floor(width / tickSpacing);

            for (let i = 0; i < numTicks; i++) {
                const isMajor = i % 8 === 0;
                ticks.push(
                    <div 
                        key={i} 
                        className={`ruler-tick ${isMajor ? 'major' : 'minor'}`} 
                        style={{ left: `${i * tickSpacing}px` }}
                    >
                        {isMajor && i > 0 && <span className="ruler-number">{i / 8}</span>}
                    </div>
                );
            }
            return <div className="ruler-container w-full" style={{ width }}>{ticks}</div>;
        };

        function WordProcessor() {
            const [fileName, setFileName] = useState(INITIAL_FILE ? INITIAL_FILE.name : 'Document1');
            const [currentFileId, setCurrentFileId] = useState(INITIAL_FILE ? INITIAL_FILE.id : null);
            const [isEditable, setIsEditable] = useState(IS_EDITABLE_INIT); 
            const [wordCount, setWordCount] = useState(0);
            const [pageCount, setPageCount] = useState(1);
            const [currentPage, setCurrentPage] = useState(1); // NEW: Track current page
            const [zoom, setZoom] = useState(100);
            const [isLoading, setIsLoading] = useState(false);
            const [statusMessage, setStatusMessage] = useState('');
            const [pdfBuffer, setPdfBuffer] = useState(null);
            const [pdfMode, setPdfMode] = useState('text');
            const [viewMode, setViewMode] = useState('editor'); // 'editor', 'pdf', 'image'
            const [showFindReplace, setShowFindReplace] = useState(false);
            const [findText, setFindText] = useState('');
            const [replaceText, setReplaceText] = useState('');
            const [showFileModal, setShowFileModal] = useState(false);
            const [showExportMenu, setShowExportMenu] = useState(false);
            const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
            
            // New Table Modal State
            const [showTableModal, setShowTableModal] = useState(false);
            const [tableRows, setTableRows] = useState(3);
            const [tableCols, setTableCols] = useState(3);

            // Image Context Menu
            const [selectedImage, setSelectedImage] = useState(null);

            const [contentHeight, setContentHeight] = useState(1056);
            
            // Separate state for User Selection vs Document State
            const [lastForeColor, setLastForeColor] = useState('#000000');
            const [lastHiliteColor, setLastHiliteColor] = useState('#ffff00');

            const [activeFormats, setActiveFormats] = useState({
                blockStyle: 'p', fontName: 'Arial', fontSize: '3',
                bold: false, italic: false, underline: false, strikethrough: false,
                justifyLeft: false, justifyCenter: false, justifyRight: false
            });

            const editorRef = useRef(null);
            const workspaceRef = useRef(null);
            const fileInputRef = useRef(null);
            const colorInputRef = useRef(null);
            const highlightInputRef = useRef(null);
            const savedRange = useRef(null);
            const autoSaveTimerRef = useRef(null);

            // Mobile Pinch Zoom Refs
            const touchStartDist = useRef(null);
            const touchStartZoom = useRef(null);

            // Helper to save current cursor position
            const saveSelection = () => {
                const sel = window.getSelection();
                if (sel.rangeCount > 0 && editorRef.current && editorRef.current.contains(sel.anchorNode)) {
                    savedRange.current = sel.getRangeAt(0);
                }
            };

            // Helper to restore cursor position
            const restoreSelection = () => {
                const range = savedRange.current;
                if (range) {
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            };

            // Initialize & History Management
            useEffect(() => {
                if (editorRef.current && !editorRef.current.innerHTML) {
                    editorRef.current.innerHTML = '<p><br></p>';
                }
                
                // Smart Zoom for Mobile
                const handleResize = () => {
                    const w = window.innerWidth;
                    // On Mobile/Tablet (<850px), auto fit to width with a small margin
                    if (w < 850) {
                        const fitZoom = Math.floor(((w - 32) / A4_WIDTH_PX) * 100);
                        setZoom(Math.max(20, fitZoom));
                    }
                };
                
                // Trigger on load
                handleResize();
                window.addEventListener('resize', handleResize);

                if (INITIAL_FILE) {
                    loadInitialFile(INITIAL_FILE.url, INITIAL_FILE.name, INITIAL_FILE.type);
                }

                const preventBackNavigation = () => {
                    window.history.pushState(null, document.title, window.location.href);
                };
                
                window.history.pushState(null, document.title, window.location.href);
                window.addEventListener('popstate', preventBackNavigation);

                const handleKeyDown = (e) => {
                    const isCmd = e.ctrlKey || e.metaKey;
                    if (isEditable && isCmd && e.key === 's') { e.preventDefault(); handleSaveToServer(); }
                    if (isCmd && e.key === 'p') { e.preventDefault(); window.print(); }
                    if (isEditable && isCmd && e.key === 'f') { e.preventDefault(); setShowFindReplace(prev => !prev); }
                    if (isCmd && (e.key === '=' || e.key === '+')) { e.preventDefault(); adjustZoom(10); }
                    if (isCmd && e.key === '-') { e.preventDefault(); adjustZoom(-10); }
                    if (isCmd && e.key === '0') { e.preventDefault(); setZoom(100); }
                };

                // Image Selection Listener
                const handleSelectionChange = () => {
                    const sel = window.getSelection();
                    if (sel.rangeCount > 0) {
                        const node = sel.anchorNode;
                        // Check if an image is selected
                        if (node.nodeName === 'IMG') {
                            setSelectedImage(node);
                        } else if (node.firstElementChild && node.firstElementChild.nodeName === 'IMG') {
                             setSelectedImage(node.firstElementChild);
                        } else {
                            // Check if inside editor but not image
                            if (editorRef.current && editorRef.current.contains(node)) {
                                setSelectedImage(null);
                            }
                        }
                    }
                };

                window.addEventListener('keydown', handleKeyDown);
                document.addEventListener('selectionchange', handleSelectionChange);

                // Add smooth wheel zoom listener
                const workspace = workspaceRef.current;
                const handleWheel = (e) => {
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        // Smooth zoom logic: smaller steps for wheel
                        const delta = e.deltaY < 0 ? 2 : -2;
                        setZoom(prev => Math.min(300, Math.max(20, prev + delta)));
                    }
                };
                
                if (workspace) {
                    workspace.addEventListener('wheel', handleWheel, { passive: false });
                }

                return () => {
                    window.removeEventListener('keydown', handleKeyDown);
                    window.removeEventListener('popstate', preventBackNavigation);
                    document.removeEventListener('selectionchange', handleSelectionChange);
                    window.removeEventListener('resize', handleResize);
                    if (workspace) {
                        workspace.removeEventListener('wheel', handleWheel);
                    }
                    clearInterval(autoSaveTimerRef.current);
                };
            }, [isEditable, hasUnsavedChanges, currentFileId]);

            // Pinch to Zoom Logic for Mobile
            const handleTouchStart = (e) => {
                if (e.touches.length === 2) {
                    const d = Math.hypot(
                        e.touches[0].clientX - e.touches[1].clientX,
                        e.touches[0].clientY - e.touches[1].clientY
                    );
                    touchStartDist.current = d;
                    touchStartZoom.current = zoom;
                }
            };

            const handleTouchMove = (e) => {
                if (e.touches.length === 2 && touchStartDist.current !== null) {
                    // Prevent default to stop browser full-page zoom
                    if(e.cancelable) e.preventDefault(); 
                    
                    const d = Math.hypot(
                        e.touches[0].clientX - e.touches[1].clientX,
                        e.touches[0].clientY - e.touches[1].clientY
                    );
                    
                    const scale = d / touchStartDist.current;
                    const newZoom = Math.min(300, Math.max(20, touchStartZoom.current * scale));
                    setZoom(newZoom);
                }
            };

            const handleTouchEnd = () => {
                touchStartDist.current = null;
                touchStartZoom.current = null;
            };

            // Update content height
            useEffect(() => {
                const updateHeight = () => {
                    if (editorRef.current) {
                        const h = editorRef.current.scrollHeight;
                        setContentHeight(Math.max(1056, h + 100)); 
                    }
                };
                updateHeight();
            }, [zoom, wordCount, fileName, viewMode]);

            const adjustZoom = (delta) => {
                setZoom(prev => Math.min(300, Math.max(20, prev + delta)));
            };
            
            const handleZoomSlider = (e) => {
                setZoom(parseInt(e.target.value));
            };
            
            const fitToWidth = () => {
                const workspaceWidth = workspaceRef.current ? workspaceRef.current.clientWidth : window.innerWidth;
                const fitZoom = Math.floor(((workspaceWidth - 48) / A4_WIDTH_PX) * 100); 
                setZoom(Math.min(300, Math.max(20, fitZoom)));
            };

            const loadInitialFile = async (url, name, type) => {
                setIsLoading(true);
                setStatusMessage('Loading file...');
                try {
                    const response = await fetch(url);
                    if (!response.ok) throw new Error("Failed to fetch file");
                    const blob = await response.blob();
                    let fullName = name.includes('.') ? name : name + '.' + type;
                    const file = new File([blob], fullName, { type: blob.type });
                    await processFile(file, true);
                } catch (error) {
                    console.error(error);
                    alert("Error loading file: " + error.message);
                } finally {
                    setIsLoading(false);
                    setStatusMessage('');
                }
            };

            const handleLoadUserFile = (file) => {
                setShowFileModal(false);
                setCurrentFileId(file.id);
                setFileName(file.display_name);
                const extension = file.filename.split('.').pop().toLowerCase();
                const url = 'uploads/' + file.filename;
                if(editorRef.current) editorRef.current.innerHTML = '';
                setPdfBuffer(null);
                setViewMode('editor');
                setPdfMode('text');
                loadInitialFile(url, file.display_name, extension);
            };

            const updateStats = () => {
                if (editorRef.current) {
                    setHasUnsavedChanges(true);
                    const text = editorRef.current.innerText || '';
                    setWordCount(text.trim() === '' ? 0 : text.trim().split(/\s+/).length);
                    const height = editorRef.current.scrollHeight;
                    setPageCount(Math.max(1, Math.ceil(height / 1056)));
                    setContentHeight(Math.max(1056, height + 50));
                    
                    if(document.activeElement === editorRef.current) {
                        saveSelection();
                    }

                    if (isEditable) {
                        try {
                            const blockStyle = document.queryCommandValue('formatBlock') || 'p';
                            const fontName = document.queryCommandValue('fontName') || 'Arial';
                            const fontSizeRaw = document.queryCommandValue('fontSize') || '3';
                            
                            const mapFontSize = (val) => {
                                if (!val) return '3';
                                if (val.match(/^[1-7]$/)) return val;
                                const px = parseInt(val, 10);
                                if (px <= 10) return '1'; if (px <= 13) return '2'; if (px <= 16) return '3';
                                if (px <= 18) return '4'; if (px <= 24) return '5'; if (px <= 32) return '6'; return '7';
                            };

                            setActiveFormats({
                                blockStyle: blockStyle.toLowerCase(),
                                fontName: fontName.replace(/['"]+/g, ''),
                                fontSize: mapFontSize(fontSizeRaw),
                                bold: document.queryCommandState('bold'),
                                italic: document.queryCommandState('italic'),
                                underline: document.queryCommandState('underline'),
                                strikethrough: document.queryCommandState('strikeThrough'),
                                justifyLeft: document.queryCommandState('justifyLeft'),
                                justifyCenter: document.queryCommandState('justifyCenter'),
                                justifyRight: document.queryCommandState('justifyRight')
                            });
                        } catch(e) { }
                    }
                }
            };

            const execCmd = (command, value = null) => {
                if (!isEditable) return;
                
                if (document.activeElement !== editorRef.current) {
                    restoreSelection();
                }

                document.execCommand(command, false, value);
                
                if(editorRef.current) {
                    editorRef.current.focus();
                }
                
                updateStats();
            };

            // NEW: Add Page at End functionality
            const addPageAtEnd = () => {
                if (!isEditable) return;
                const breakHtml = '<div class="visual-page-break" contenteditable="false"></div><p><br/></p>';
                if (editorRef.current) {
                    editorRef.current.insertAdjacentHTML('beforeend', breakHtml);
                    updateStats();
                    // Scroll to the new page area
                    setTimeout(() => {
                        if (workspaceRef.current) {
                            workspaceRef.current.scrollTo({ top: workspaceRef.current.scrollHeight, behavior: 'smooth' });
                        }
                        // Set cursor to end
                        const range = document.createRange();
                        range.selectNodeContents(editorRef.current);
                        range.collapse(false);
                        const sel = window.getSelection();
                        sel.removeAllRanges();
                        sel.addRange(range);
                    }, 50);
                }
            };

            // NEW: Paste Handler to strip formatting
            const handlePaste = (e) => {
                e.preventDefault();
                const text = e.clipboardData.getData('text/plain');
                document.execCommand("insertText", false, text);
                updateStats();
            };

            // COLOR HANDLERS
            const applyForeColor = () => {
                execCmd('foreColor', lastForeColor);
            };
            
            const handleForeColorChange = (e) => {
                const color = e.target.value;
                setLastForeColor(color);
                execCmd('foreColor', color);
            };

            const applyHiliteColor = () => {
                execCmd('hiliteColor', lastHiliteColor);
            };

            const handleHiliteColorChange = (e) => {
                const color = e.target.value;
                setLastHiliteColor(color);
                execCmd('hiliteColor', color);
            };

            const resetColors = () => {
                if (!isEditable) return;
                
                // Force restoration of selection if it was lost
                if (savedRange.current) restoreSelection();
                
                // Reset Text Color -> Black
                document.execCommand('foreColor', false, '#000000');
                
                // Reset Highlight/Background -> Transparent
                document.execCommand('hiliteColor', false, 'rgba(0,0,0,0)');
                document.execCommand('backColor', false, 'rgba(0,0,0,0)');
                
                setLastForeColor('#000000');
                setLastHiliteColor('transparent');
                
                if(editorRef.current) editorRef.current.focus();
                updateStats();
            };

            const insertLink = () => {
                const url = prompt("Enter URL:", "https://");
                if (url) {
                    execCmd("createLink", url);
                }
            };

            const handleResizeImage = (percentage) => {
                if (selectedImage) {
                    selectedImage.style.width = percentage + '%';
                    selectedImage.style.height = 'auto';
                    updateStats();
                }
            };

            const handleClear = () => {
                if (confirm("Are you sure you want to clear everything?")) {
                    if (editorRef.current) {
                        editorRef.current.innerHTML = '<p><br></p>';
                        setPdfBuffer(null);
                        setViewMode('editor');
                        setFileName('Document1');
                        setCurrentFileId(null);
                        setWordCount(0);
                        updateStats();
                        // Clear local storage for this
                        localStorage.removeItem('doc_backup_new');
                    }
                }
            };

            const openTableModal = () => {
                if (!isEditable) return;
                saveSelection(); 
                setShowTableModal(true);
            };

            const confirmInsertTable = () => {
                const rows = Math.max(1, Math.min(20, parseInt(tableRows) || 2));
                const cols = Math.max(1, Math.min(20, parseInt(tableCols) || 2));

                let html = '<div class="table-wrapper"><table style="width:100%; border-collapse: collapse; border: 1px solid #ccc; margin: 10px 0;"><tbody>';
                
                for (let r = 0; r < rows; r++) {
                    html += '<tr>';
                    for (let c = 0; c < cols; c++) {
                        html += `<td style="border:1px solid #ccc; padding:8px;">&nbsp;</td>`;
                    }
                    html += '</tr>';
                }
                
                html += '</tbody></table></div><p><br/></p>';

                restoreSelection();
                if (editorRef.current) editorRef.current.focus();
                document.execCommand('insertHTML', false, html);
                
                updateStats();
                setShowTableModal(false);
            };

            const performFind = () => {
                if (window.find && findText) {
                    if (!window.find(findText)) {
                        window.getSelection().collapse(document.body, 0);
                        window.find(findText);
                    }
                }
            };

            const performReplace = () => {
                if (!isEditable) return;
                if (window.getSelection().toString().toLowerCase() === findText.toLowerCase()) {
                    document.execCommand('insertText', false, replaceText);
                }
                performFind();
            };

            const processFile = async (file, shouldResetEditable = true) => {
                setIsLoading(true);
                setStatusMessage(`Processing ${file.name}...`);
                setPdfBuffer(null);
                
                let extension = file.name.split('.').pop().toLowerCase();
                const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (extension === 'doc') {
                    setIsLoading(false);
                    alert("This editor supports .docx files (Word 2007+). Please save your .doc file as .docx and try again.");
                    return;
                }

                if (shouldResetEditable) {
                    if (imageExtensions.includes(extension)) {
                        setViewMode('image');
                        setIsEditable(false);
                    } else if (extension === 'pdf') {
                        setViewMode('pdf');
                        setIsEditable(false);
                    } else if (['html', 'htm', 'txt', 'docx'].includes(extension)) {
                        setViewMode('editor');
                        setIsEditable(true);
                    } else {
                        setViewMode('editor');
                        setIsEditable(false);
                    }
                }

                try {
                    let newContent = '';

                    if (extension === 'docx') {
                        try {
                            const arrayBuffer = await file.arrayBuffer();
                            if (typeof window.mammoth === 'undefined') throw new Error("Mammoth library not loaded.");
                            
                            const result = await window.mammoth.convertToHtml({ arrayBuffer }, {
                                styleMap: [
                                    "p[style-name='Heading 1'] => h1:fresh", "p[style-name='Heading 2'] => h2:fresh",
                                    "p[style-name='Heading 3'] => h3:fresh", "p[style-name='Title'] => h1:fresh",
                                    "p[style-name='Subtitle'] => h2:fresh", "p[style-name='Normal'] => p:fresh",
                                    "b => strong", "i => em", "u => u", "table => table.table.table-bordered"
                                ],
                                includeDefaultStyleMap: true
                            });
                            
                            if (!result.value) throw new Error("Document empty.");
                            newContent = result.value;
                            setStatusMessage("Note: Word document converted.");
                            setTimeout(() => setStatusMessage(''), 4000);

                        } catch (docxError) {
                            console.warn("Mammoth failed, fallback to text", docxError);
                            setStatusMessage("Word conversion incomplete, showing text.");
                            const text = await file.text();
                            const cleanText = text.replace(/[\x00-\x09\x0B-\x0C\x0E-\x1F\x7F]/g, '');
                            newContent = cleanText.split('\n').map(line => `<p>${line}</p>`).join('');
                        }
                    } 
                    else if (extension === 'pdf') {
                        const arrayBuffer = await file.arrayBuffer();
                        setPdfBuffer(arrayBuffer);
                        setPdfMode('overlay');
                        await renderPdfContent(arrayBuffer.slice(0), 'overlay');
                        return;
                    } 
                    else if (imageExtensions.includes(extension)) {
                        const reader = new FileReader();
                        reader.readAsDataURL(file);
                        await new Promise(resolve => reader.onload = resolve);
                        
                        if (shouldResetEditable) {
                            newContent = `
                                <div class="image-view-mode">
                                    <img src="${reader.result}" />
                                </div>
                            `;
                        } else {
                            newContent = `<img src="${reader.result}" style="max-width: 100%; height: auto;" /><p><br></p>`;
                        }
                    } 
                    else {
                        const text = await file.text();
                        if (['html', 'htm'].includes(extension)) {
                            const parser = new DOMParser();
                            newContent = parser.parseFromString(text, 'text/html').body.innerHTML || text;
                        } else {
                            newContent = text.split('\n').map(line => `<p>${line}</p>`).join('');
                        }
                    }

                    if (editorRef.current) {
                        editorRef.current.innerHTML = newContent;
                        if (shouldResetEditable) {
                            setFileName(file.name.replace(/\.[^/.]+$/, ""));
                            setCurrentFileId(null); 
                        }
                        updateStats();
                    }
                } catch (err) {
                    console.error(err);
                    alert('Error processing file: ' + err.message);
                } finally {
                    setIsLoading(false);
                    if (statusMessage !== "Note: Word document converted." && statusMessage !== "Word conversion incomplete, showing text.") setStatusMessage('');
                }
            };

            const convertImageToDocument = () => {
                if (viewMode !== 'image' || !editorRef.current) return;
                const img = editorRef.current.querySelector('img');
                if (img) {
                    const src = img.getAttribute('src');
                    const newHtml = `<p><img src="${src}" style="max-width: 100%; height: auto;" /></p><p><br/></p>`;
                    editorRef.current.innerHTML = newHtml;
                    setViewMode('editor');
                    setIsEditable(true);
                    updateStats();
                    setTimeout(() => { if (editorRef.current) editorRef.current.focus(); }, 50);
                }
            };

            const convertPdfToDocument = async () => {
                if (viewMode !== 'pdf' || !pdfBuffer) return;
                if (!confirm("This will convert all PDF pages into images so you can type around them. This might be slow for large PDFs. Continue?")) return;
                setIsLoading(true);
                setStatusMessage('Converting PDF to Document...');
                
                try {
                     if (typeof window.pdfjsLib === 'undefined') throw new Error("PDF Library missing");
                     const pdf = await window.pdfjsLib.getDocument(pdfBuffer).promise;
                     let fullHtml = '';
                     
                     for (let i = 1; i <= pdf.numPages; i++) {
                        const page = await pdf.getPage(i);
                        const viewport = page.getViewport({ scale: 1.5 });
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        await page.render({ canvasContext: ctx, viewport }).promise;
                        const imgData = canvas.toDataURL('image/jpeg', 0.8);
                        fullHtml += `<p><img src="${imgData}" style="max-width: 100%; height: auto; border: 1px solid #ccc;" /></p><p>Type here...</p><br/><hr/><br/>`;
                     }
                     
                     if (editorRef.current) {
                         editorRef.current.innerHTML = fullHtml;
                         setViewMode('editor');
                         setIsEditable(true);
                         updateStats();
                         setTimeout(() => { if (editorRef.current) editorRef.current.focus(); }, 50);
                     }
                } catch(e) {
                    console.error(e);
                    alert("Conversion failed: " + e.message);
                } finally {
                    setIsLoading(false);
                    setStatusMessage('');
                }
            };

            const renderPdfContent = async (buffer, mode) => {
                if (typeof window.pdfjsLib === 'undefined') return;
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                
                try {
                    const pdf = await window.pdfjsLib.getDocument(buffer).promise;
                    let fullHtml = '';
                    
                    if (mode === 'text') {
                        for (let i = 1; i <= pdf.numPages; i++) {
                            const page = await pdf.getPage(i);
                            const content = await page.getTextContent();
                            let text = content.items.map(item => item.str).join(' ');
                            fullHtml += `<p>${text}</p><br><hr/><br>`;
                        }
                    } else {
                        for (let i = 1; i <= pdf.numPages; i++) {
                            setStatusMessage(`Rendering HQ Page ${i}/${pdf.numPages}...`);
                            const page = await pdf.getPage(i);
                            const viewport = page.getViewport({ scale: 2.5 }); 
                            const canvas = document.createElement('canvas');
                            const ctx = canvas.getContext('2d');
                            canvas.height = viewport.height;
                            canvas.width = viewport.width;
                            await page.render({ canvasContext: ctx, viewport }).promise;
                            const imgData = canvas.toDataURL('image/jpeg', 0.9);
                            fullHtml += `
                                <div class="pdf-page-container">
                                    <img src="${imgData}" alt="Page ${i}" />
                                </div>
                            `;
                        }
                    }
                    if (editorRef.current) {
                        editorRef.current.innerHTML = fullHtml;
                        updateStats();
                    }
                } catch(e) {
                    console.error(e);
                    alert("PDF Error: " + e.message);
                } finally {
                    setStatusMessage('');
                }
            };

            const exportFile = (type) => {
                if (!editorRef.current) return;
                setShowExportMenu(false);

                if (type === 'pdf') {
                    window.print();
                    return;
                }

                const content = editorRef.current.innerHTML;
                let mimeType = 'text/html';
                let fileExtension = 'html';
                let data = content;

                if (type === 'word') {
                    mimeType = 'application/msword';
                    fileExtension = 'doc';
                    // Add Word-specific wrapper for better formatting
                    data = `
                        <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
                        <head><meta charset='utf-8'><title>${fileName}</title>
                        <style>body { font-family: 'Arial'; font-size: 11pt; }</style>
                        </head><body>${content}</body></html>
                    `;
                }

                const blob = new Blob([data], { type: mimeType });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `${fileName}.${fileExtension}`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            };

            const handleSaveToServer = async () => {
                if (!editorRef.current || !isEditable) return;
                setIsLoading(true);
                setStatusMessage('Saving...');

                const content = `<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; line-height: 1.15; padding: 40px; max-width: 816px; margin: 0 auto; }
h1 { font-size: 24pt; color: #2F5496; } h2 { font-size: 18pt; color: #2F5496; }
table { border-collapse: collapse; width: 100%; border: 1px solid #aaa; }
td, th { border: 1px solid #aaa; padding: 5px; } img { max-width: 100%; }
</style></head><body>${editorRef.current.innerHTML}</body></html>`;

                try {
                    const res = await fetch('save_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ content, title: fileName, file_id: currentFileId })
                    });
                    const data = await res.json();
                    if (data.success) {
                        setCurrentFileId(data.file_id);
                        setStatusMessage('Saved to server');
                        setHasUnsavedChanges(false);
                        // Clear local backup on successful server save
                        localStorage.removeItem('doc_backup_' + (data.file_id || 'new'));
                        setTimeout(() => setStatusMessage(''), 3000);
                    } else {
                        alert('Save failed: ' + data.message);
                    }
                } catch (e) {
                    alert('Network error while saving');
                } finally {
                    setIsLoading(false);
                }
            };

            const toggleEditMode = () => {
                if (viewMode === 'image') {
                    convertImageToDocument();
                } else if (viewMode === 'pdf') {
                    convertPdfToDocument();
                } else {
                    const nextState = !isEditable;
                    setIsEditable(nextState);
                    if (nextState) {
                        setTimeout(() => {
                            if (editorRef.current) {
                                editorRef.current.focus();
                            }
                        }, 10);
                    }
                }
            };

            // Container Styles
            const scaleWrapperStyle = {
                width: `${A4_WIDTH_PX * (zoom / 100)}px`,
                height: `${contentHeight * (zoom / 100)}px`,
                margin: '32px auto',
                position: 'relative',
                transition: 'width 0.2s cubic-bezier(0.4, 0, 0.2, 1), height 0.2s cubic-bezier(0.4, 0, 0.2, 1)',
                willChange: 'width, height',
                flexShrink: 0
            };

            const contentStyle = {
                width: `${A4_WIDTH_PX}px`,
                minHeight: viewMode === 'image' ? 'auto' : '1056px',
                height: viewMode === 'image' ? '100%' : 'auto',
                transform: `scale(${zoom / 100}) translateZ(0)`,
                transformOrigin: 'top left', 
                backgroundColor: (viewMode === 'pdf' && pdfMode === 'overlay') || viewMode === 'image' ? 'transparent' : 'white',
                boxShadow: (viewMode === 'pdf' && pdfMode === 'overlay') || viewMode === 'image' ? 'none' : '0 25px 50px -12px rgba(0, 0, 0, 0.25)', 
                margin: 0, 
                padding: (viewMode === 'pdf' && pdfMode === 'overlay') || viewMode === 'image' ? '0' : '96px',
                border: isEditable ? 'none' : ((viewMode === 'pdf' && pdfMode === 'overlay') || viewMode === 'image' ? 'none' : '1px solid #d1d5db'),
                outline: 'none',
                overflow: 'hidden',
                display: viewMode === 'image' ? 'flex' : 'block',
                justifyContent: 'center',
                alignItems: 'center',
                transition: 'transform 0.2s cubic-bezier(0.4, 0, 0.2, 1)', // Smooth zoom animation
                willChange: 'transform'
            };

            const editBtnConfig = isEditable 
                ? { text: "Done Editing", icon: "fa-check", className: "bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-300", title: "Switch to Read-Only Mode" }
                : (viewMode === 'image' || viewMode === 'pdf')
                    ? { text: "Convert & Edit", icon: "fa-magic", className: "bg-emerald-600 text-white hover:bg-emerald-700 shadow-md ring-1 ring-emerald-600", title: "Convert this file into an editable document" }
                    : { text: "Edit Document", icon: "fa-pen-to-square", className: "bg-primary text-white hover:bg-secondary shadow-md", title: "Enable Editing Mode" };

            const handleScroll = (e) => {
                if (!e.target) return;
                const scrollTop = e.target.scrollTop;
                const pageHeight = 1056 * (zoom / 100); 
                // Add a small threshold (e.g. 1/3 of a page) to switch page number
                const newPage = Math.max(1, Math.floor((scrollTop + (pageHeight * 0.3)) / pageHeight) + 1);
                setCurrentPage(newPage);
            };

            return (
                <div className="flex flex-col h-screen bg-[#E3E5E8] font-sans text-gray-900 relative">
                    <input type="file" ref={fileInputRef} onChange={(e) => e.target.files[0] && processFile(e.target.files[0])} className="hidden" />

                    {/* Table Insert Modal */}
                    {showTableModal && (
                        <div className="absolute inset-0 z-[60] bg-black/50 flex items-center justify-center p-4 animate-fade-in" onClick={() => setShowTableModal(false)}>
                            <div className="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm" onClick={e => e.stopPropagation()}>
                                <h3 className="text-lg font-bold mb-4 text-gray-800">Insert Table</h3>
                                <div className="flex gap-4 mb-4">
                                    <div className="flex-1">
                                        <label className="block text-xs font-bold text-gray-500 mb-1">Rows</label>
                                        <input type="number" min="1" max="20" value={tableRows} onChange={e => setTableRows(e.target.value)} className="w-full border border-gray-300 rounded px-3 py-2 focus:border-primary focus:outline-none" />
                                    </div>
                                    <div className="flex-1">
                                        <label className="block text-xs font-bold text-gray-500 mb-1">Columns</label>
                                        <input type="number" min="1" max="20" value={tableCols} onChange={e => setTableCols(e.target.value)} className="w-full border border-gray-300 rounded px-3 py-2 focus:border-primary focus:outline-none" />
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <button onClick={() => setShowTableModal(false)} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                                    <button onClick={confirmInsertTable} className="px-4 py-2 bg-primary text-white rounded hover:bg-secondary">Insert</button>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Image Context Menu (Popup) */}
                    {selectedImage && isEditable && (
                        <div className="absolute z-50 bg-white shadow-xl rounded-lg p-2 flex gap-2 border border-gray-200 animate-fade-in" 
                             style={{ 
                                 top: '150px', 
                                 left: '50%', 
                                 transform: 'translateX(-50%)',
                                 position: 'fixed'
                             }}>
                            <span className="text-xs font-bold text-gray-500 uppercase flex items-center px-2">Image Size:</span>
                            <button onClick={() => handleResizeImage(25)} className="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-sm">25%</button>
                            <button onClick={() => handleResizeImage(50)} className="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-sm">50%</button>
                            <button onClick={() => handleResizeImage(100)} className="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-sm">100%</button>
                            <div className="w-px h-6 bg-gray-200"></div>
                            <button onClick={() => setSelectedImage(null)} className="px-2 text-gray-400 hover:text-gray-600"><i className="fas fa-times"></i></button>
                        </div>
                    )}

                    {/* File Modal */}
                    {showFileModal && (
                        <div className="absolute inset-0 z-[60] bg-black/60 flex items-center justify-center p-4 backdrop-blur-sm animate-fade-in" onClick={() => setShowFileModal(false)}>
                            <div className="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col overflow-hidden" onClick={e => e.stopPropagation()}>
                                <div className="p-4 border-b flex justify-between items-center bg-gray-50">
                                    <h3 className="font-bold text-gray-800 text-lg"><i className="fas fa-folder-open mr-2 text-primary"></i> Open File</h3>
                                    <button onClick={() => setShowFileModal(false)} className="w-8 h-8 rounded-full hover:bg-gray-200 flex items-center justify-center text-gray-500"><i className="fas fa-times"></i></button>
                                </div>
                                <div className="overflow-y-auto p-4 bg-gray-50/50 flex-grow custom-scrollbar">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div 
                                            onClick={() => { fileInputRef.current.click(); setShowFileModal(false); }}
                                            className="p-3 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 hover:border-primary hover:bg-blue-50 cursor-pointer transition-all flex items-center space-x-3 group"
                                        >
                                            <div className="w-10 h-10 bg-white rounded-lg flex items-center justify-center text-gray-400 group-hover:text-primary transition-colors flex-shrink-0 shadow-sm">
                                                <i className="fas fa-cloud-upload-alt text-xl"></i>
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <h4 className="font-bold text-sm text-gray-700 group-hover:text-primary">Open from Device</h4>
                                                <p className="text-xs text-gray-500">Browse local files...</p>
                                            </div>
                                        </div>

                                        {USER_FILES.map(file => (
                                            <div key={file.id} onClick={() => handleLoadUserFile(file)} className={`p-3 rounded-lg border border-gray-200 bg-white hover:border-primary hover:shadow-md cursor-pointer transition-all flex items-center space-x-3 group ${currentFileId == file.id ? 'border-primary ring-1 ring-primary/20 bg-blue-50/50' : ''}`}>
                                                <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 group-hover:text-primary transition-colors flex-shrink-0">
                                                    <i className={`fas ${file.file_type.includes('image') ? 'fa-file-image text-purple-500' : file.file_type.includes('pdf') ? 'fa-file-pdf text-red-500' : file.file_type.includes('word') ? 'fa-file-word text-blue-500' : 'fa-file-alt text-gray-500'} text-xl`}></i>
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <h4 className="font-medium text-sm truncate text-gray-800">{file.display_name}</h4>
                                                    <p className="text-xs text-gray-500">{file.upload_date}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Top Bar - Z-Index High */}
                    <div className="z-50 shadow-md sticky top-0 bg-white">
                        {/* Header */}
                        <header className="bg-white border-b border-gray-200 px-3 py-2 flex items-center justify-between gap-2 flex-wrap sm:flex-nowrap">
                            <div className="flex items-center gap-3 flex-grow sm:flex-grow-0">
                                <button onClick={() => window.location.href='home.php'} className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-600 transition-colors"><i className="fas fa-arrow-left"></i></button>
                                <div className="flex flex-col">
                                    <input type="text" value={fileName} onChange={(e) => setFileName(e.target.value)} disabled={!isEditable} className="font-bold text-gray-800 text-sm sm:text-base bg-transparent border-b border-transparent hover:border-gray-300 focus:border-primary focus:outline-none transition-colors px-1 w-32 sm:w-auto" placeholder="Untitled" />
                                </div>
                            </div>
                            <div className="flex items-center gap-1 sm:gap-2 ml-auto relative">
                                {isLoading && <span className="text-xs text-primary font-medium bg-blue-50 px-2 py-1 rounded-full animate-pulse"><i className="fas fa-circle-notch fa-spin mr-1"></i> Processing</span>}
                                
                                <button onClick={() => setShowFileModal(true)} className="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                    <i className="fas fa-folder-open"></i> <span className="hidden sm:inline">Files</span>
                                </button>
                                
                                {/* Export Menu */}
                                <div className="relative">
                                    <button onClick={() => setShowExportMenu(!showExportMenu)} className="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                        <i className="fas fa-download"></i> <span className="hidden sm:inline">Export</span>
                                    </button>
                                    {showExportMenu && (
                                        <div className="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl w-48 py-1 z-50">
                                            <button onClick={() => exportFile('word')} className="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm text-gray-700 flex items-center gap-2"><i className="fas fa-file-word text-blue-600"></i> Word (.doc)</button>
                                            <button onClick={() => exportFile('html')} className="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm text-gray-700 flex items-center gap-2"><i className="fas fa-code text-orange-600"></i> HTML Source</button>
                                            <div className="border-t border-gray-100 my-1"></div>
                                            <button onClick={() => exportFile('pdf')} className="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm text-gray-700 flex items-center gap-2"><i className="fas fa-print text-gray-600"></i> Print / PDF</button>
                                        </div>
                                    )}
                                </div>

                                <button onClick={toggleEditMode} className={`px-4 py-1.5 rounded-lg text-sm font-bold transition-all flex items-center gap-2 ${editBtnConfig.className}`} title={editBtnConfig.title}>
                                    <i className={`fas ${editBtnConfig.icon}`}></i> 
                                    <span className="hidden sm:inline">{editBtnConfig.text}</span>
                                </button>
                                {isEditable && <button onClick={handleSaveToServer} className="px-4 py-1.5 rounded-lg text-sm font-bold bg-primary text-white hover:bg-secondary shadow-md hover:shadow-lg transition-all flex items-center gap-2"><i className="fas fa-cloud-upload-alt"></i> <span className="hidden sm:inline">Save</span></button>}
                            </div>
                        </header>

                        {/* Find Bar */}
                        {showFindReplace && (
                            <div className="bg-gray-50 border-b border-gray-200 px-4 py-2 flex flex-wrap items-center gap-2 text-sm shadow-inner">
                               <div className="flex items-center bg-white border border-gray-300 rounded-md px-2 py-1 flex-grow max-w-xs"><i className="fas fa-search text-gray-400 mr-2"></i><input type="text" value={findText} onChange={e => setFindText(e.target.value)} className="outline-none w-full text-sm" placeholder="Find..." autoFocus /></div>
                               {isEditable && <div className="flex items-center bg-white border border-gray-300 rounded-md px-2 py-1 flex-grow max-w-xs"><i className="fas fa-pen text-gray-400 mr-2"></i><input type="text" value={replaceText} onChange={e => setReplaceText(e.target.value)} className="outline-none w-full text-sm" placeholder="Replace..." /></div>}
                               <button onClick={performFind} className="px-3 py-1 bg-gray-200 hover:bg-gray-300 rounded text-gray-700 text-xs font-bold">Find</button>
                               {isEditable && <button onClick={performReplace} className="px-3 py-1 bg-gray-200 hover:bg-gray-300 rounded text-gray-700 text-xs font-bold">Replace</button>}
                               <button onClick={() => setShowFindReplace(false)} className="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-red-500 rounded"><i className="fas fa-times"></i></button>
                            </div>
                        )}

                        {/* Main Toolbar */}
                        {isEditable && (
                            <div className="bg-white border-b border-gray-200 px-2 sm:px-4 py-2 flex items-center shadow-sm overflow-x-auto whitespace-nowrap gap-1 custom-scrollbar">
                                <ToolbarButton onClick={() => execCmd('undo')} icon="fa-undo" tooltip="Undo" />
                                <ToolbarButton onClick={() => execCmd('redo')} icon="fa-redo" tooltip="Redo" />
                                <Divider />
                                <Dropdown label="Style" value={activeFormats.blockStyle} onChange={(v) => execCmd('formatBlock', v)} options={[{label:'Normal',value:'p'},{label:'Heading 1',value:'h1'},{label:'Heading 2',value:'h2'},{label:'Heading 3',value:'h3'},{label:'Quote',value:'blockquote'}]} />
                                <Dropdown label="Font" value={activeFormats.fontName} onChange={(v) => execCmd('fontName', v)} options={[{label:'Arial',value:'Arial'},{label:'Calibri',value:'Calibri'},{label:'Times',value:'Times New Roman'},{label:'Courier',value:'Courier New'}, {label:'Verdana',value:'Verdana'}]} />
                                <Dropdown label="Size" value={activeFormats.fontSize} onChange={(v) => execCmd('fontSize', v)} options={[{label:'10pt',value:'1'},{label:'12pt',value:'2'},{label:'14pt',value:'3'},{label:'18pt',value:'4'},{label:'24pt',value:'5'},{label:'36pt',value:'6'},{label:'48pt',value:'7'}]} />
                                <Divider />
                                <ToolbarButton onClick={() => execCmd('bold')} isActive={activeFormats.bold} icon="fa-bold" />
                                <ToolbarButton onClick={() => execCmd('italic')} isActive={activeFormats.italic} icon="fa-italic" />
                                <ToolbarButton onClick={() => execCmd('underline')} isActive={activeFormats.underline} icon="fa-underline" />
                                <ToolbarButton onClick={() => execCmd('strikeThrough')} isActive={activeFormats.strikethrough} icon="fa-strikethrough" />
                                <div className="w-px h-6 bg-gray-200 mx-1 hidden sm:block"></div>
                                
                                {/* SPLIT BUTTON: Text Color */}
                                <div className="relative flex items-center bg-gray-100 rounded-lg p-0.5 mr-1 group">
                                    <ToolbarButton 
                                        onClick={applyForeColor} 
                                        icon="fa-font" 
                                        className="text-gray-700 relative hover:bg-white" 
                                        tooltip="Text Color (Click to apply)"
                                        style={{ borderBottom: `4px solid ${lastForeColor}` }}
                                    />
                                    <div className="w-px h-6 bg-gray-300 mx-0.5"></div>
                                    <button 
                                        onClick={(e) => { e.preventDefault(); colorInputRef.current.click(); }}
                                        className="w-4 h-9 flex items-center justify-center text-gray-600 hover:text-gray-900 text-[10px] hover:bg-white rounded-r-lg"
                                        title="Select Text Color"
                                    >
                                        <i className="fas fa-caret-down"></i>
                                    </button>
                                    <input 
                                        type="color" 
                                        ref={colorInputRef} 
                                        value={lastForeColor}
                                        onChange={handleForeColorChange} 
                                        className="absolute opacity-0 w-0 h-0 pointer-events-none" 
                                    />
                                </div>

                                {/* SPLIT BUTTON: Highlight Color */}
                                <div className="relative flex items-center bg-gray-100 rounded-lg p-0.5 mr-1 group">
                                    <ToolbarButton 
                                        onClick={applyHiliteColor} 
                                        icon="fa-highlighter" 
                                        className="text-gray-700 relative hover:bg-white" 
                                        tooltip="Highlight Color (Click to apply)"
                                        style={{ borderBottom: `4px solid ${lastHiliteColor}` }}
                                    />
                                    <div className="w-px h-6 bg-gray-300 mx-0.5"></div>
                                    <button 
                                        onClick={(e) => { e.preventDefault(); highlightInputRef.current.click(); }}
                                        className="w-4 h-9 flex items-center justify-center text-gray-600 hover:text-gray-900 text-[10px] hover:bg-white rounded-r-lg"
                                        title="Select Highlight Color"
                                    >
                                        <i className="fas fa-caret-down"></i>
                                    </button>
                                    <input 
                                        type="color" 
                                        ref={highlightInputRef} 
                                        value={lastHiliteColor}
                                        onChange={handleHiliteColorChange} 
                                        className="absolute opacity-0 w-0 h-0 pointer-events-none" 
                                    />
                                </div>

                                <ToolbarButton onClick={resetColors} icon="fa-tint-slash" tooltip="Reset Colors (Clear Text/Highlight)" />

                                <Divider />
                                <ToolbarButton onClick={() => execCmd('justifyLeft')} isActive={activeFormats.justifyLeft} icon="fa-align-left" />
                                <ToolbarButton onClick={() => execCmd('justifyCenter')} isActive={activeFormats.justifyCenter} icon="fa-align-center" />
                                <ToolbarButton onClick={() => execCmd('justifyRight')} isActive={activeFormats.justifyRight} icon="fa-align-right" />
                                <Divider />
                                <ToolbarButton onClick={() => execCmd('insertUnorderedList')} icon="fa-list-ul" />
                                <ToolbarButton onClick={() => execCmd('insertOrderedList')} icon="fa-list-ol" />
                                <ToolbarButton onClick={openTableModal} icon="fa-table" />
                                <ToolbarButton onClick={insertLink} icon="fa-link" tooltip="Insert Link" />
                                <ToolbarButton onClick={addPageAtEnd} icon="fa-file-circle-plus" tooltip="Add New Page" />
                                <ToolbarButton onClick={() => setShowFindReplace(!showFindReplace)} icon="fa-search" />
                                <div className="flex-grow"></div>
                                <ToolbarButton onClick={handleClear} icon="fa-trash-alt" className="text-red-500 hover:bg-red-50" tooltip="Clear All" />
                            </div>
                        )}

                        {/* Visual Ruler */}
                        {isEditable && (
                            <div className="bg-[#f8f9fa] border-b border-gray-300 w-full overflow-hidden flex justify-center">
                                <div style={{ width: `${A4_WIDTH_PX * (zoom/100)}px`, transition: 'width 0.2s' }}>
                                    <Ruler width={A4_WIDTH_PX * (zoom/100)} />
                                </div>
                            </div>
                        )}
                        
                        {/* PDF Toolbar */}
                        {viewMode === 'pdf' && (
                             <div className="bg-slate-800 border-b border-slate-700 px-4 py-2 flex items-center justify-center gap-4 text-sm z-10 text-white shadow-md">
                                <span className="font-bold flex items-center"><i className="fas fa-file-pdf mr-2 text-red-400"></i> PDF Mode</span>
                                <div className="flex bg-slate-700 rounded-lg p-1">
                                    <button onClick={() => { setPdfMode('text'); renderPdfContent(pdfBuffer, 'text'); }} className={`px-3 py-1 rounded transition-colors ${pdfMode === 'text' ? 'bg-white text-slate-800 font-bold' : 'text-slate-300 hover:text-white'}`}>Text</button>
                                    <button onClick={() => { setPdfMode('overlay'); renderPdfContent(pdfBuffer, 'overlay'); }} className={`px-3 py-1 rounded transition-colors ${pdfMode === 'overlay' ? 'bg-white text-slate-800 font-bold' : 'text-slate-300 hover:text-white'}`}>HQ View</button>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Workspace */}
                    <div 
                        ref={workspaceRef} 
                        className={`workspace flex-grow overflow-auto relative custom-scrollbar flex flex-col items-start pt-8 pb-20 touch-pan-x touch-pan-y ${viewMode === 'image' || (viewMode === 'pdf' && pdfMode === 'overlay') ? 'bg-[#333]' : 'bg-[#E3E5E8]'}`}
                        onClick={(e) => {
                            if (e.target === e.currentTarget && isEditable && editorRef.current) {
                                editorRef.current.focus();
                            }
                        }}
                        onScroll={handleScroll}
                        onTouchStart={handleTouchStart}
                        onTouchMove={handleTouchMove}
                        onTouchEnd={handleTouchEnd}
                    >
                        <div className="scale-wrapper" style={scaleWrapperStyle}>
                            <div className="page-content transition-shadow duration-300" style={contentStyle}>
                                <div
                                    ref={editorRef}
                                    className={`w-full h-full outline-none prose ${!isEditable ? 'view-only' : ''}`}
                                    contentEditable={isEditable}
                                    onInput={updateStats} onKeyUp={updateStats} onMouseUp={updateStats} onClick={updateStats} 
                                    spellCheck={isEditable}
                                ></div>
                            </div>
                        </div>
                    </div>

                    {/* Footer */}
                    <footer className="bg-white border-t border-gray-200 text-gray-600 text-xs py-2 px-4 flex items-center justify-between z-20 shadow-[0_-2px_10px_rgba(0,0,0,0.05)]">
                        <div className="flex space-x-4 font-medium">
                            <span className="flex items-center"><i className="far fa-file-alt mr-1.5 text-gray-400"></i> Page {currentPage} of {pageCount}</span>
                            <span className="flex items-center"><i className="fas fa-align-left mr-1.5 text-gray-400"></i> {wordCount} words</span>
                        </div>
                        <div className="flex items-center gap-2">
                             {/* New Fit Width Button */}
                             <button onClick={fitToWidth} title="Fit to Width" className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 hover:text-primary transition-colors">
                                <i className="fas fa-expand text-xs"></i>
                            </button>
                            
                            <div className="h-4 w-px bg-gray-200 mx-1"></div>

                            {/* Zoom Controls */}
                            <div className="flex items-center bg-gray-100 rounded-full px-2 py-1 gap-2">
                                <button onClick={() => adjustZoom(-10)} className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 text-gray-600 transition-colors"><i className="fas fa-minus text-[10px]"></i></button>
                                
                                {/* New Zoom Slider */}
                                <input 
                                    type="range" 
                                    min="20" 
                                    max="200" 
                                    step="1"
                                    value={zoom} 
                                    onChange={handleZoomSlider}
                                    className="w-20 h-1 bg-gray-300 rounded-lg appearance-none cursor-pointer accent-primary"
                                    title="Zoom Level"
                                />
                                
                                <span className="w-8 text-center font-bold text-gray-700 text-[10px]">{Math.round(zoom)}%</span>
                                <button onClick={() => adjustZoom(10)} className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 text-gray-600 transition-colors"><i className="fas fa-plus text-[10px]"></i></button>
                            </div>
                        </div>
                    </footer>
                </div>
            );
        }

        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<WordProcessor />);
    </script>
</body>
</html>