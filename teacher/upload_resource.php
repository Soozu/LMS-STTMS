<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Access denied']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

try {
    $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if (!$classId || empty($title) || empty($_FILES['resource'])) {
        throw new Exception('Missing required fields');
    }

    $file = $_FILES['resource'];
    $fileName = basename($file['name']);
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileSize = $file['size'];

    // Validate file type
    $allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Invalid file type');
    }

    // Validate file size (10MB max)
    if ($fileSize > 10 * 1024 * 1024) {
        throw new Exception('File too large (max 10MB)');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = '../uploads/resources/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate unique filename
    $newFileName = uniqid() . '.' . $fileType;
    $filePath = $uploadDir . $newFileName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to upload file');
    }

    // Save to database
    $stmt = $conn->prepare("
        INSERT INTO resources (
            class_id, 
            title, 
            description, 
            file_name, 
            file_type, 
            file_size, 
            file_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $classId,
        $title,
        $description,
        $fileName,
        $fileType,
        $fileSize,
        $newFileName
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Resource uploaded successfully'
    ]);

} catch (Exception $e) {
    error_log("Error uploading resource: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 