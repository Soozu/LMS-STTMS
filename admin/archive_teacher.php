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

$teacher_id = $_POST['teacher_id'] ?? null;

if (!$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Teacher ID is required']);
    exit();
}

try {
    $conn->beginTransaction();

    // Get teacher details before archiving
    $stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        throw new Exception('Teacher not found');
    }

    // Insert into archived_teachers
    $stmt = $conn->prepare("
        INSERT INTO archived_teachers 
        (teacher_id, first_name, last_name, employee_id, email, contact_number, archived_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $teacher_id,
        $teacher['first_name'],
        $teacher['last_name'],
        $teacher['employee_id'],
        $teacher['email'],
        $teacher['contact_number'],
        $_SESSION['user_id']
    ]);

    // Deactivate user account
    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$teacher['user_id']]);

    // Remove from active classes
    $stmt = $conn->prepare("UPDATE classes SET status = 'inactive' WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);

    // Remove from section assignments
    $stmt = $conn->prepare("DELETE FROM section_teachers WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error archiving teacher: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to archive teacher']);
} 