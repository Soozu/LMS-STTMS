<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$section_id = $_POST['section_id'] ?? null;

if (!$section_id) {
    echo json_encode(['success' => false, 'message' => 'Section ID is required']);
    exit();
}

try {
    $conn->beginTransaction();

    // Update section status to active
    $stmt = $conn->prepare("UPDATE sections SET status = 'active' WHERE id = ?");
    $stmt->execute([$section_id]);

    // Delete from archived_sections
    $stmt = $conn->prepare("DELETE FROM archived_sections WHERE section_id = ?");
    $stmt->execute([$section_id]);

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error restoring section: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to restore section']);
} 