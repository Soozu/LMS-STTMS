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
    $grade_level = filter_input(INPUT_POST, 'grade_level', FILTER_VALIDATE_INT);
    $subject_name = trim($_POST['subject_name']);
    $description = trim($_POST['description']);

    if (!$grade_level || $grade_level < 1 || $grade_level > 6) {
        throw new Exception('Invalid grade level');
    }

    if (empty($subject_name)) {
        throw new Exception('Subject name is required');
    }

    // Check for duplicate subject name in the same grade level
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM subjects 
        WHERE subject_name = ? AND grade_level = ?
    ");
    $stmt->execute([$subject_name, $grade_level]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        throw new Exception('Subject already exists in this grade level');
    }

    // Insert new subject
    $stmt = $conn->prepare("
        INSERT INTO subjects (
            grade_level,
            subject_name, 
            description, 
            status
        ) VALUES (?, ?, ?, 'active')
    ");

    $stmt->execute([
        $grade_level,
        $subject_name,
        $description
    ]);

    // Log the action
    $subject_id = $conn->lastInsertId();
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description)
        VALUES (?, 'Add Subject', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Added new subject: {$subject_name} for Grade {$grade_level}"
    ]);

    $_SESSION['success'] = "Subject added successfully";

} catch(Exception $e) {
    error_log("Error adding subject: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

header('Location: subjects.php');
exit();
?> 