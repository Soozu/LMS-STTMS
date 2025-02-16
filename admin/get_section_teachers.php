<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$section_id = $_GET['section_id'] ?? null;

if (!$section_id) {
    echo json_encode(['error' => 'Section ID required']);
    exit();
}

try {
    // Get adviser
    $stmt = $conn->prepare("
        SELECT st.*, t.first_name, t.last_name
        FROM section_teachers st
        JOIN teachers t ON st.teacher_id = t.id
        WHERE st.section_id = ? 
        AND st.is_adviser = 1 
        AND st.status = 'active'
    ");
    $stmt->execute([$section_id]);
    $adviser = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'adviser' => $adviser,
        'success' => true
    ]);

} catch(PDOException $e) {
    error_log("Error in get_section_teachers: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}
?> 