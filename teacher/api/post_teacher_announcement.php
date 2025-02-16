<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/helpers.php';

// Ensure user is logged in as teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // Validate inputs
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $classId = (int)($_POST['class_id'] ?? 0);
    $eventDate = $_POST['event_date'] ?? '';
    $teacherId = $_SESSION['role_id'];

    if (empty($title) || empty($content) || empty($classId) || empty($eventDate)) {
        throw new Exception('All fields are required');
    }

    // Verify the class belongs to this teacher
    $stmt = $conn->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$classId, $teacherId]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid class selected');
    }

    // Insert the announcement
    $stmt = $conn->prepare("
        INSERT INTO teacher_announcements 
        (title, content, teacher_id, class_id, event_date) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$title, $content, $teacherId, $classId, $eventDate]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Error in post_teacher_announcement: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 