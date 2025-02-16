<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['error' => 'Access denied']));
}

// Get file name from URL
$fileName = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($fileName)) {
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['error' => 'Missing file parameter']));
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
        exit(json_encode(['error' => 'Access denied']));
    }

    $filePath = "../uploads/assignments/" . basename($fileName);

    if (!file_exists($filePath)) {
        header('HTTP/1.1 404 Not Found');
        exit(json_encode(['error' => 'File not found']));
    }

    // Read file and convert to base64
    $fileContent = file_get_contents($filePath);
    $base64 = base64_encode($fileContent);

    // Return JSON response with base64 data
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $base64,
        'filename' => $file['original_name']
    ]);
    exit();

} catch(Exception $e) {
    error_log("Error in get_file.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit(json_encode(['error' => 'Error accessing file']));
}
 