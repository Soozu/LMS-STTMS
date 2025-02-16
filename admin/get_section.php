<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$section_id = $_GET['id'] ?? null;

if (!$section_id) {
    http_response_code(400);
    exit('Section ID is required');
}

try {
    $stmt = $conn->prepare("SELECT * FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$section) {
        http_response_code(404);
        exit('Section not found');
    }

    echo json_encode($section);

} catch(PDOException $e) {
    error_log("Error fetching section: " . $e->getMessage());
    http_response_code(500);
    exit('Server error');
} 