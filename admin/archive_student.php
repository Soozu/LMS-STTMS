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

    // Get student details before archiving
    $stmt = $conn->prepare("
        SELECT s.*, u.id as user_id 
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Student not found');
    }

    // Insert into archived_students
    $stmt = $conn->prepare("
        INSERT INTO archived_students 
        (student_id, lrn, first_name, last_name, email, contact_number, 
         grade_level, section_id, archived_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $student_id,
        $student['lrn'],
        $student['first_name'],
        $student['last_name'],
        $student['email'],
        $student['contact_number'],
        $student['grade_level'],
        $student['section_id'],
        $_SESSION['user_id']
    ]);

    // Deactivate user account
    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$student['user_id']]);

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description) 
        VALUES (?, 'Archive Student', ?)
    ");
    $logDescription = "Archived student: {$student['first_name']} {$student['last_name']} (LRN: {$student['lrn']})";
    $stmt->execute([$_SESSION['user_id'], $logDescription]);

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error archiving student: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to archive student']);
} 