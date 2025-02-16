<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$grade_level = $_GET['grade_level'] ?? null;

if (!$grade_level) {
    http_response_code(400);
    exit('Grade level is required');
}

try {
    $stmt = $conn->prepare("
        SELECT id, section_name 
        FROM sections 
        WHERE grade_level = ? 
        AND status = 'active' 
        ORDER BY section_name
    ");
    $stmt->execute([$grade_level]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($sections);

} catch(PDOException $e) {
    error_log("Error fetching sections: " . $e->getMessage());
    http_response_code(500);
    exit('Server error');
} 