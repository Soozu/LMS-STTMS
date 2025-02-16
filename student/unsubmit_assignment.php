<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    try {
        $conn->beginTransaction();

        $assignmentId = $_POST['assignment_id'];
        $studentId = $_SESSION['role_id'];

        // First verify this submission belongs to the student and is not graded
        $stmt = $conn->prepare("
            SELECT ss.*, sf.file_name 
            FROM student_submissions ss
            LEFT JOIN submission_files sf ON ss.id = sf.submission_id
            WHERE ss.assignment_id = ? 
            AND ss.student_id = ?
            AND ss.score IS NULL
        ");
        $stmt->execute([$assignmentId, $studentId]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$submission) {
            throw new Exception('Submission not found or already graded');
        }

        // Delete the physical file if it exists
        if ($submission['file_name']) {
            $filePath = "../uploads/submissions/" . $submission['file_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Delete submission views
        $stmt = $conn->prepare("
            DELETE FROM submission_views 
            WHERE submission_id = ?
        ");
        $stmt->execute([$submission['id']]);

        // Delete submission files
        $stmt = $conn->prepare("
            DELETE FROM submission_files 
            WHERE submission_id = ?
        ");
        $stmt->execute([$submission['id']]);

        // Delete the submission
        $stmt = $conn->prepare("
            DELETE FROM student_submissions 
            WHERE id = ?
        ");
        $stmt->execute([$submission['id']]);

        // Log the action
        $stmt = $conn->prepare("
            INSERT INTO system_logs (
                user_id, 
                action, 
                description, 
                created_at
            ) VALUES (?, 'Unsubmit Assignment', ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Unsubmitted assignment ID: $assignmentId"
        ]);

        $conn->commit();
        $_SESSION['success_message'] = "Assignment unsubmitted successfully. You can now submit a new version.";
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error unsubmitting assignment: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred while unsubmitting the assignment.";
    }
} else {
    $_SESSION['error_message'] = "Invalid request.";
}

// Redirect back to the assignments page
if (isset($_SERVER['HTTP_REFERER'])) {
    header("Location: " . $_SERVER['HTTP_REFERER']);
} else {
    header("Location: assignments.php");
}
exit();
?> 