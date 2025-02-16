<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

if (isset($_POST['assignment_id'])) {
    try {
        $assignmentId = $_POST['assignment_id'];
        
        // Start transaction
        $conn->beginTransaction();
        
        // Debug log
        error_log("Attempting to archive assignment ID: " . $assignmentId);
        
        // First verify the teacher owns this assignment
        $stmt = $conn->prepare("
            SELECT a.id, a.status 
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            WHERE a.id = ? AND c.teacher_id = ?
        ");
        $stmt->execute([$assignmentId, $_SESSION['role_id']]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assignment) {
            throw new Exception('Assignment not found or unauthorized access');
        }
        
        // Update the status to archived
        $stmt = $conn->prepare("
            UPDATE assignments 
            SET status = 'archived',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        if (!$stmt->execute([$assignmentId])) {
            throw new Exception('Failed to update assignment status');
        }
        
        // Log the action
        error_log("Successfully archived assignment ID: " . $assignmentId);
        
        $conn->commit();
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error archiving assignment: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to archive assignment: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'No assignment ID provided'
    ]);
}
exit();