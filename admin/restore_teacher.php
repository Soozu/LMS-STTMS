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

    // Get archived teacher details
    $stmt = $conn->prepare("
        SELECT at.*, t.user_id 
        FROM archived_teachers at
        JOIN teachers t ON at.teacher_id = t.id
        WHERE at.teacher_id = ?
    ");
    $stmt->execute([$teacher_id]);
    $archivedTeacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$archivedTeacher) {
        throw new Exception('Archived teacher not found');
    }

    // Reactivate user account
    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    $stmt->execute([$archivedTeacher['user_id']]);

    // Reactivate teacher's classes
    $stmt = $conn->prepare("UPDATE classes SET status = 'active' WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);

    // Remove from archived_teachers
    $stmt = $conn->prepare("DELETE FROM archived_teachers WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);

    // Log the restoration (using the existing system_logs structure)
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, ip_address, user_agent) 
        VALUES (?, ?, ?, ?)
    ");
    $logMessage = sprintf(
        "Restored teacher: %s %s (%s)",
        $archivedTeacher['first_name'],
        $archivedTeacher['last_name'],
        $archivedTeacher['employee_id']
    );
    $stmt->execute([
        $_SESSION['user_id'],
        'RESTORE_TEACHER',
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error restoring teacher: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to restore teacher: ' . $e->getMessage()
    ]);
} 