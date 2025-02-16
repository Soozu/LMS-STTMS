<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$studentId = $_POST['student_id'] ?? null;
$message = $_POST['message'] ?? '';
$teacherId = $_SESSION['user_id'];

try {
    // Verify student is in teacher's class
    $stmt = $conn->prepare("
        SELECT 1 
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        JOIN students s ON ce.student_id = s.id
        WHERE s.user_id = ?
        AND c.teacher_id = ?
        AND ce.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$studentId, $_SESSION['role_id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Student not found in your classes');
    }

    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, content, status, created_at)
        VALUES (?, ?, ?, 'unread', NOW())
    ");
    $stmt->execute([$teacherId, $studentId, $message]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Error sending message: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
} 