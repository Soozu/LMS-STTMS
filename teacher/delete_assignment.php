<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    try {
        $conn->beginTransaction();
        
        $assignmentId = $_POST['assignment_id'];
        
        // First, verify this assignment belongs to the current teacher
        $stmt = $conn->prepare("
            SELECT a.* FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN teachers t ON c.teacher_id = t.id
            WHERE a.id = ? AND t.user_id = ?
        ");
        $stmt->execute([$assignmentId, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Assignment not found or unauthorized');
        }

        // 1. Delete submission views
        $stmt = $conn->prepare("
            DELETE sv FROM submission_views sv
            JOIN student_submissions ss ON sv.submission_id = ss.id
            WHERE ss.assignment_id = ?
        ");
        $stmt->execute([$assignmentId]);

        // 2. Delete assignment comments
        $stmt = $conn->prepare("
            DELETE ac FROM assignment_comments ac
            JOIN student_submissions ss ON ac.submission_id = ss.id
            WHERE ss.assignment_id = ?
        ");
        $stmt->execute([$assignmentId]);

        // 3. Delete submission files
        $stmt = $conn->prepare("
            DELETE sf FROM submission_files sf
            JOIN student_submissions ss ON sf.submission_id = ss.id
            WHERE ss.assignment_id = ?
        ");
        $stmt->execute([$assignmentId]);

        // 4. Delete student submissions
        $stmt = $conn->prepare("
            DELETE FROM student_submissions WHERE assignment_id = ?
        ");
        $stmt->execute([$assignmentId]);

        // 5. Get assignment files before deleting them from database
        $stmt = $conn->prepare("
            SELECT file_name FROM assignment_files WHERE assignment_id = ?
        ");
        $stmt->execute([$assignmentId]);
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 6. Delete assignment files from database
        $stmt = $conn->prepare("
            DELETE FROM assignment_files WHERE assignment_id = ?
        ");
        $stmt->execute([$assignmentId]);

        // 7. Delete the assignment
        $stmt = $conn->prepare("
            DELETE FROM assignments WHERE id = ?
        ");
        $stmt->execute([$assignmentId]);

        // 8. Delete physical files
        foreach ($files as $file) {
            $filePath = "../uploads/assignments/" . $file;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Log the action
        $stmt = $conn->prepare("
            INSERT INTO system_logs (user_id, action, description, created_at)
            VALUES (?, 'Delete Assignment', ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], "Deleted assignment ID: $assignmentId"]);

        $conn->commit();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error deleting assignment: " . $e->getMessage());
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to delete assignment: ' . $e->getMessage()
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}