<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Get file name from URL
$fileName = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($fileName)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Missing file parameter');
}

try {
    // Verify file access permission
    $stmt = $conn->prepare("
        SELECT af.file_name, af.original_name
        FROM assignment_files af
        JOIN assignments a ON af.assignment_id = a.id
        JOIN classes c ON a.class_id = c.id
        JOIN class_enrollments ce ON c.id = ce.class_id
        JOIN students s ON ce.student_id = s.id
        WHERE af.file_name = ? AND s.user_id = ?
    ");
    
    $stmt->execute([$fileName, $_SESSION['user_id']]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        header('HTTP/1.1 403 Forbidden');
        exit('Access denied');
    }

    $filePath = "../uploads/assignments/" . basename($fileName);

    if (!file_exists($filePath)) {
        header('HTTP/1.1 404 Not Found');
        exit('File not found');
    }

    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    // Clear output buffer
    if (ob_get_level()) ob_end_clean();
    
    // Output file
    readfile($filePath);
    exit();

} catch(Exception $e) {
    error_log("Error in download.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error downloading file');
} 