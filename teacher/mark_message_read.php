<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Access denied']));
}

try {
    $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$messageId) {
        throw new Exception('Invalid message ID');
    }

    // Update message status
    $stmt = $conn->prepare("
        UPDATE messages 
        SET status = 'read', read_at = NOW()
        WHERE id = ? AND receiver_id = ?
    ");

    $stmt->execute([$messageId, $_SESSION['user_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Message marked as read'
    ]);

} catch (Exception $e) {
    error_log("Error marking message as read: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 