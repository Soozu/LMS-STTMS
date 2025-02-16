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

$section_id = $_POST['section_id'] ?? null;

if (!$section_id) {
    echo json_encode(['success' => false, 'message' => 'Section ID is required']);
    exit();
}

try {
    $conn->beginTransaction();

    // Get section details before archiving
    $stmt = $conn->prepare("SELECT * FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$section) {
        throw new Exception('Section not found');
    }

    // Insert into archived_sections
    $stmt = $conn->prepare("
        INSERT INTO archived_sections 
        (section_id, grade_level, section_name, time_start, time_end, archived_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $section_id,
        $section['grade_level'],
        $section['section_name'],
        $section['time_start'],
        $section['time_end'],
        $_SESSION['user_id']
    ]);

    // Update section status to inactive
    $stmt = $conn->prepare("UPDATE sections SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$section_id]);

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error archiving section: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to archive section']);
} 