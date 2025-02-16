<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$student_id) {
    echo json_encode(['error' => 'Invalid student ID']);
    exit();
}

try {
    // Fetch student data
    $stmt = $conn->prepare("
        SELECT s.*, u.status as account_status
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['error' => 'Student not found']);
        exit();
    }

    // Fetch available sections
    $stmt = $conn->prepare("
        SELECT id, grade_level, section_name 
        FROM sections 
        WHERE status = 'active'
        ORDER BY grade_level ASC, section_name ASC
    ");
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add sections to student data
    $student['sections'] = $sections;

    echo json_encode($student);

} catch(PDOException $e) {
    error_log("Error in get_student.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
} 