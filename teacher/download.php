<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$fileId = isset($_GET['file']) ? (int)$_GET['file'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';
$teacherId = $_SESSION['role_id'];

try {
    if ($type === 'assignment') {
        // For assignment files
        $stmt = $conn->prepare("
            SELECT af.*, a.class_id
            FROM assignment_files af
            JOIN assignments a ON af.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            WHERE af.id = ?
            AND c.teacher_id = ?
        ");
        $stmt->execute([$fileId, $teacherId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            $filePath = "../uploads/assignments/" . $file['file_name'];
        }
    } else if ($type === 'submission') {
        // For submission files
        $stmt = $conn->prepare("
            SELECT sf.*, a.class_id, ss.student_id
            FROM submission_files sf
            JOIN student_submissions ss ON sf.submission_id = ss.id
            JOIN assignments a ON ss.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            WHERE sf.id = ?
            AND c.teacher_id = ?
        ");
        $stmt->execute([$fileId, $teacherId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            $filePath = "../uploads/submissions/" . $file['file_name'];
        }
    }

    if (!isset($file) || !$file) {
        throw new Exception('File not found or access denied');
    }

    if (!file_exists($filePath)) {
        throw new Exception('File does not exist on server');
    }

    // Set headers for file download
    header('Content-Type: ' . $file['file_type']);
    header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache');
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Output file
    readfile($filePath);
    exit();

} catch (Exception $e) {
    error_log("Error downloading file: " . $e->getMessage());
    header('HTTP/1.1 404 Not Found');
    exit('File not found or access denied: ' . $e->getMessage());
} 