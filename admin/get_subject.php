<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$subject_id = $_GET['id'] ?? null;

if (!$subject_id) {
    http_response_code(400);
    exit('Subject ID is required');
}

try {
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$subject_id]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subject) {
        http_response_code(404);
        exit('Subject not found');
    }

    echo json_encode($subject);

} catch(PDOException $e) {
    error_log("Error fetching subject: " . $e->getMessage());
    http_response_code(500);
    exit('Server error');
} 