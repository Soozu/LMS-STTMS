<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    $teacherId = $_SESSION['role_id'];
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $classId = $_POST['class_id'] ?? '';
    $eventDate = $_POST['event_date'] ?? date('Y-m-d');

    if (empty($title) || empty($content) || empty($classId)) {
        throw new Exception('Missing required fields');
    }

    // Start transaction
    $conn->beginTransaction();

    // Insert announcement
    $stmt = $conn->prepare("
        INSERT INTO announcements (title, content, teacher_id, class_id, event_date)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$title, $content, $teacherId, $classId, $eventDate]);
    $announcementId = $conn->lastInsertId();

    // Handle file upload if present
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $fileName = uniqid() . '_' . basename($file['name']);
        $uploadPath = '../uploads/announcements/' . $fileName;

        // Create directory if it doesn't exist
        if (!file_exists('../uploads/announcements')) {
            mkdir('../uploads/announcements', 0777, true);
        }

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $stmt = $conn->prepare("
                INSERT INTO announcement_files (announcement_id, file_name, original_name, file_type, file_size)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $announcementId,
                $fileName,
                $file['name'],
                $file['type'],
                $file['size']
            ]);
        }
    }

    // Commit transaction
    $conn->commit();

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description)
        VALUES (?, 'Create Announcement', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Created announcement: $title for class $classId"
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error creating announcement: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 