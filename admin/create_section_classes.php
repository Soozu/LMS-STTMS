<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    die('Unauthorized access');
}

try {
    $conn->beginTransaction();

    // Get all active sections
    $stmt = $conn->prepare("SELECT id FROM sections WHERE status = 'active'");
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all active subjects
    $stmt = $conn->prepare("SELECT id FROM subjects WHERE status = 'active'");
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create classes for each section-subject combination
    $stmt = $conn->prepare("
        INSERT INTO classes 
        (section_id, subject_id, status, created_at) 
        VALUES (?, ?, 'active', NOW())
        ON DUPLICATE KEY UPDATE status = 'active'
    ");

    $classesCreated = 0;
    foreach ($sections as $section) {
        foreach ($subjects as $subject) {
            // Check if class already exists
            $checkStmt = $conn->prepare("
                SELECT id FROM classes 
                WHERE section_id = ? AND subject_id = ?
            ");
            $checkStmt->execute([$section['id'], $subject['id']]);
            
            if (!$checkStmt->fetch()) {
                $stmt->execute([$section['id'], $subject['id']]);
                $classesCreated++;
            }
        }
    }

    $conn->commit();
    echo "Successfully created $classesCreated new classes";

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
?> 