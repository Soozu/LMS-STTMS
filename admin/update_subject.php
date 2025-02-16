<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: subjects.php');
    exit();
}

try {
    // Validate inputs
    $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
    $grade_level = filter_input(INPUT_POST, 'grade_level', FILTER_VALIDATE_INT);
    $subject_name = trim($_POST['subject_name']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];

    if (!$subject_id) {
        throw new Exception('Invalid subject ID');
    }

    if (!$grade_level || $grade_level < 1 || $grade_level > 6) {
        throw new Exception('Invalid grade level');
    }

    if (empty($subject_name)) {
        throw new Exception('Subject name is required');
    }

    if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception('Invalid status');
    }

    // Check for duplicate subject name in the same grade level
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM subjects 
        WHERE subject_name = ? 
        AND grade_level = ? 
        AND id != ?
    ");
    $stmt->execute([$subject_name, $grade_level, $subject_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        throw new Exception('Subject already exists in this grade level');
    }

    // Update subject
    $stmt = $conn->prepare("
        UPDATE subjects 
        SET grade_level = ?,
            subject_name = ?,
            description = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $grade_level,
        $subject_name,
        $description,
        $status,
        $subject_id
    ]);

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description)
        VALUES (?, 'Update Subject', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Updated subject: {$subject_name} for Grade {$grade_level}"
    ]);

    $_SESSION['success'] = "Subject updated successfully";

} catch(Exception $e) {
    error_log("Error updating subject: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

header('Location: subjects.php');
exit();
?> 