<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sections.php');
    exit();
}

try {
    $stmt = $conn->prepare("
        UPDATE sections 
        SET 
            grade_level = ?,
            section_name = ?,
            time_start = ?,
            time_end = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['grade_level'],
        $_POST['section_name'],
        $_POST['time_start'],
        $_POST['time_end'],
        $_POST['status'],
        $_POST['section_id']
    ]);

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description)
        VALUES (?, 'Update Section', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Updated section: {$_POST['section_name']}"
    ]);

    $_SESSION['success'] = "Section updated successfully";

} catch(PDOException $e) {
    error_log("Error updating section: " . $e->getMessage());
    $_SESSION['error'] = "Failed to update section";
}

header('Location: sections.php');
exit();
?> 