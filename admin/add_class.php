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
    $conn->beginTransaction();

    // Insert class
    $stmt = $conn->prepare("
        INSERT INTO classes (subject_id, teacher_id, section_id, status)
        VALUES (?, ?, ?, 'active')
    ");
    $stmt->execute([
        $_POST['subject_id'],
        $_POST['teacher_id'],
        $_POST['section_id']
    ]);
    
    $class_id = $conn->lastInsertId();

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description)
        VALUES (?, 'Add Class', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Added new class ID: {$class_id}"
    ]);

    $conn->commit();
    $_SESSION['success'] = "Class added successfully";

} catch(PDOException $e) {
    $conn->rollBack();
    error_log("Error adding class: " . $e->getMessage());
    $_SESSION['error'] = "Failed to add class. Please try again.";
}

header('Location: classes.php');
exit();
?>
 