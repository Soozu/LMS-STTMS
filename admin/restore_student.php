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

$student_id = $_POST['student_id'] ?? null;

if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit();
}

try {
    $conn->beginTransaction();

    // Get archived student details
    $stmt = $conn->prepare("
        SELECT a.*, s.user_id 
        FROM archived_students a
        JOIN students s ON a.student_id = s.id
        WHERE a.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Archived student not found');
    }

    // Reactivate user account
    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    $stmt->execute([$student['user_id']]);

    // Delete from archived_students
    $stmt = $conn->prepare("DELETE FROM archived_students WHERE student_id = ?");
    $stmt->execute([$student_id]);

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description) 
        VALUES (?, 'Restore Student', ?)
    ");
    $logDescription = "Restored student: {$student['first_name']} {$student['last_name']} (LRN: {$student['lrn']})";
    $stmt->execute([$_SESSION['user_id'], $logDescription]);

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error restoring student: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to restore student']);
} 