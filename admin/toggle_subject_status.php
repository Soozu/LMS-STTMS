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

$subject_id = $_POST['subject_id'] ?? null;

if (!$subject_id) {
    http_response_code(400);
    exit('Subject ID is required');
}

try {
    // First get current status
    $stmt = $conn->prepare("SELECT status FROM subjects WHERE id = ?");
    $stmt->execute([$subject_id]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subject) {
        http_response_code(404);
        exit('Subject not found');
    }

    // Toggle the status
    $new_status = $subject['status'] === 'active' ? 'inactive' : 'active';

    // Update the status
    $stmt = $conn->prepare("UPDATE subjects SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $subject_id]);

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description)
        VALUES (?, 'Toggle Subject Status', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Changed subject ID {$subject_id} status to {$new_status}"
    ]);

    // Return the new status
    echo json_encode(['status' => $new_status]);

} catch(PDOException $e) {
    error_log("Error toggling subject status: " . $e->getMessage());
    http_response_code(500);
    exit('Server error');
} 