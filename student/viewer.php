<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$file = isset($_GET['file']) ? $_GET['file'] : '';
if (empty($file)) {
    exit('No file specified');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Viewer - STMA LMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script type="module">
        import * as pdfjsLib from './lib/pdfjs/pdf.mjs';

        // Configure worker
        pdfjsLib.GlobalWorkerOptions.workerSrc = './lib/pdfjs/pdf.worker.mjs';

        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            let pdfDoc = null,
                pageNum = 1,
                pageRendering = false,
                pageNumPending = null,
                scale = 1.5;

            const container = document.getElementById('pdf-render-container'),
                  loading = document.getElementById('loading');

            // Load the PDF with credentials
            loading.style.display = 'block';

            // Fetch the PDF data
            fetch('get_file.php?file=<?php echo urlencode($file); ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    // Convert base64 to binary
                    const pdfData = atob(data.data);
                    const array = new Uint8Array(pdfData.length);
                    for (let i = 0; i < pdfData.length; i++) {
                        array[i] = pdfData.charCodeAt(i);
                    }

                    // Load the PDF using the binary data
                    return pdfjsLib.getDocument({ data: array }).promise;
                })
                .then(function(pdf) {
                    pdfDoc = pdf;
                    document.getElementById('page-count').textContent = pdf.numPages;
                    
                    // Initial page render
                    renderPage(pageNum);
                })
                .catch(function(error) {
                    console.error('Error loading PDF:', error);
                    loading.textContent = 'Error loading PDF. Please try downloading instead.';
                });

            function renderPage(num) {
                pageRendering = true;
                loading.style.display = 'block';

                pdfDoc.getPage(num).then(function(page) {
                    const viewport = page.getViewport({scale: scale});
                    
                    // Create canvas for this page
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    // Clear previous content
                    container.innerHTML = '';
                    container.appendChild(canvas);

                    // Render PDF page into canvas context
                    const renderContext = {
                        canvasContext: ctx,
                        viewport: viewport
                    };

                    page.render(renderContext).promise.then(function() {
                        pageRendering = false;
                        loading.style.display = 'none';
                        
                        if (pageNumPending !== null) {
                            renderPage(pageNumPending);
                            pageNumPending = null;
                        }
                    });

                    document.getElementById('page-num').textContent = num;
                });
            }

            function queueRenderPage(num) {
                if (pageRendering) {
                    pageNumPending = num;
                } else {
                    renderPage(num);
                }
            }

            function onPrevPage() {
                if (pageNum <= 1) return;
                pageNum--;
                queueRenderPage(pageNum);
            }

            function onNextPage() {
                if (pageNum >= pdfDoc.numPages) return;
                pageNum++;
                queueRenderPage(pageNum);
            }

            // Add event listeners
            document.getElementById('prev-page').addEventListener('click', onPrevPage);
            document.getElementById('next-page').addEventListener('click', onNextPage);

            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.close();
                } else if (e.key === 'ArrowLeft') {
                    onPrevPage();
                } else if (e.key === 'ArrowRight') {
                    onNextPage();
                }
            });
        });
    </script>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #525659;
        }
        #pdf-container {
            width: 100%;
            height: calc(100vh - 50px);
            margin-top: 50px;
            overflow: auto;
            display: flex;
            justify-content: center;
            background: #525659;
        }
        #pdf-render-container {
            margin: 20px auto;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #333;
            padding: 8px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            height: 50px;
            box-sizing: border-box;
        }
        .toolbar-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .toolbar a, .toolbar button {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            background: #8B0000;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .toolbar a:hover, .toolbar button:hover {
            background: #660000;
        }
        .file-name {
            color: white;
            margin: 0;
            font-size: 16px;
        }
        #close-btn {
            background: #444;
        }
        #close-btn:hover {
            background: #555;
        }
        .page-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-right: 20px;
        }
        #page-num, #page-count {
            color: white;
        }
        #loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 16px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h3 class="file-name"><?php echo htmlspecialchars($file); ?></h3>
        <div class="toolbar-buttons">
            <div class="page-controls">
                <button id="prev-page">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span>Page <span id="page-num"></span> of <span id="page-count"></span></span>
                <button id="next-page">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <a href="download.php?file=<?php echo urlencode($file); ?>" download>
                <i class="fas fa-download"></i> Download
            </a>
            <button id="close-btn" onclick="window.close()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>

    <div id="pdf-container">
        <div id="pdf-render-container"></div>
    </div>
    <div id="loading">Loading PDF...</div>
</body>
</html>