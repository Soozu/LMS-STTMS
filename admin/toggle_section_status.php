<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$section_id = $_POST['section_id'] ?? null;

if (!$section_id) {
    http_response_code(400);
    exit('Section ID is required');
}

try {
    // First get current status
    $stmt = $conn->prepare("SELECT status FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$section) {
        http_response_code(404);
        exit('Section not found');
    }

    // Toggle the status
    $new_status = $section['status'] === 'active' ? 'inactive' : 'active';

    // Update the status
    $stmt = $conn->prepare("UPDATE sections SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $section_id]);

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description)
        VALUES (?, 'Toggle Section Status', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Changed section ID {$section_id} status to {$new_status}"
    ]);

    // Return the new status
    echo json_encode(['status' => $new_status]);

} catch(PDOException $e) {
    error_log("Error toggling section status: " . $e->getMessage());
    http_response_code(500);
    exit('Server error');
} 