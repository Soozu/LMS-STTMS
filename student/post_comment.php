<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$submissionId = $_POST['submission_id'] ?? null;
$comment = $_POST['comment'] ?? '';
$userId = $_SESSION['user_id'];

try {
    // Verify this submission belongs to the student
    $stmt = $conn->prepare("
        SELECT 1 FROM student_submissions 
        WHERE id = ? AND student_id = ?
    ");
    $stmt->execute([$submissionId, $_SESSION['role_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Invalid submission');
    }

    // Insert comment
    $stmt = $conn->prepare("
        INSERT INTO assignment_comments (submission_id, user_id, comment, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$submissionId, $userId, $comment]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Error posting comment: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to post comment']);
} 