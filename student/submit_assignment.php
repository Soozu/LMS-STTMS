<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: assignments.php');
    exit();
}

$assignmentId = $_POST['assignment_id'] ?? null;
$submissionText = $_POST['submission_text'] ?? '';

try {
    // First check if this is an assignment or activity
    $stmt = $conn->prepare("
        SELECT type, due_date 
        FROM assignments 
        WHERE id = ?
    ");
    $stmt->execute([$assignmentId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Invalid submission');
    }

    // Check if it's past due date
    if (strtotime($item['due_date']) < time()) {
        throw new Exception('Submission period has ended');
    }

    // Handle file upload
    if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload failed');
    }

    $file = $_FILES['submission_file'];
    $fileName = $file['name'];
    $fileType = $file['type'];
    $fileTmpName = $file['tmp_name'];
    $fileError = $file['error'];
    $fileSize = $file['size'];

    // Validate file size (5MB max)
    if ($fileSize > 5 * 1024 * 1024) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }

    // Generate unique filename
    $newFileName = uniqid() . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
    $uploadPath = '../uploads/submissions/' . $newFileName;

    // Move uploaded file
    if (!move_uploaded_file($fileTmpName, $uploadPath)) {
        throw new Exception('Failed to save file');
    }

    $conn->beginTransaction();

    // Create submission record
    $stmt = $conn->prepare("
        INSERT INTO student_submissions (
            assignment_id, 
            student_id, 
            submission_text, 
            status
        ) VALUES (?, ?, ?, 'submitted')
    ");
    $stmt->execute([
        $assignmentId,
        $_SESSION['role_id'],
        $submissionText
    ]);

    $submissionId = $conn->lastInsertId();

    // Save file record
    $stmt = $conn->prepare("
        INSERT INTO submission_files (
            submission_id,
            file_name,
            original_name,
            file_type,
            file_size
        ) VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $submissionId,
        $newFileName,
        $fileName,
        $fileType,
        $fileSize
    ]);

    $conn->commit();

    $_SESSION['success_message'] = ucfirst($item['type']) . ' submitted successfully!';
    header('Location: assignments.php');
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: assignments.php');
    exit();
}
?> 