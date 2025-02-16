<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$submissionId = $_POST['submission_id'] ?? null;
$comment = $_POST['comment'] ?? '';
$userId = $_SESSION['user_id'];

try {
    // Validate required fields
    if (!$submissionId || !$comment) {
        throw new Exception('Missing required fields');
    }

    // Verify the submission belongs to a class taught by this teacher
    $stmt = $conn->prepare("
        SELECT 1 
        FROM student_submissions ss
        JOIN assignments a ON ss.assignment_id = a.id
        JOIN classes c ON a.class_id = c.id
        WHERE ss.id = ? 
        AND c.teacher_id = ?
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
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 