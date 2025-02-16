<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$resourceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $stmt = $conn->prepare("
        SELECT r.*, c.teacher_id
        FROM resources r
        JOIN classes c ON r.class_id = c.id
        WHERE r.id = ? 
        AND r.status = 'active'
    ");
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource || $resource['teacher_id'] != $_SESSION['role_id']) {
        throw new Exception('Resource not found or access denied');
    }

    $filePath = '../uploads/resources/' . $resource['file_path'];
    if (!file_exists($filePath)) {
        throw new Exception('File not found');
    }

    // Log download
    $stmt = $conn->prepare("
        INSERT INTO resource_downloads (resource_id, user_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$resourceId, $_SESSION['user_id']]);

    // Send file
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $resource['file_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit();

} catch (Exception $e) {
    error_log("Error downloading resource: " . $e->getMessage());
    $_SESSION['error'] = "Failed to download resource";
    header('Location: resources.php');
    exit();
} 