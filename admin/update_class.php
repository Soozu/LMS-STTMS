<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: classes.php');
    exit();
}

try {
    // Update class details
    $stmt = $conn->prepare("
        UPDATE classes 
        SET subject_id = ?, teacher_id = ?, section_id = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['subject_id'],
        $_POST['teacher_id'],
        $_POST['section_id'],
        $_POST['status'],
        $_POST['class_id']
    ]);

    $_SESSION['success'] = "Class updated successfully";

} catch(PDOException $e) {
    error_log("Error updating class: " . $e->getMessage());
    $_SESSION['error'] = "Failed to update class";
}

header('Location: classes.php');
exit();
?> 