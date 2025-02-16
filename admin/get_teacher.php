<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$teacher_id = $_GET['id'] ?? null;

if (!$teacher_id) {
    http_response_code(400);
    exit('Teacher ID is required');
}

try {
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            u.username,
            u.status as account_status
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        http_response_code(404);
        exit('Teacher not found');
    }

    echo json_encode($teacher);

} catch(PDOException $e) {
    error_log("Error fetching teacher: " . $e->getMessage());
    http_response_code(500);
    exit('Server error');
} 