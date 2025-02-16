<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_type'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Access denied']));
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

// Get POST data
$submissionId = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Validate input
if (!$submissionId || empty($comment)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid input']));
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Verify access rights and get submission details
    $sql = "";
    $params = [$submissionId];
    
    switch ($_SESSION['user_type']) {
        case 'teacher':
            // Teachers can comment on submissions in their classes
            $sql = "
                SELECT ss.id, ss.student_id, a.class_id
                FROM student_submissions ss
                JOIN assignments a ON ss.assignment_id = a.id
                JOIN classes c ON a.class_id = c.id
                WHERE ss.id = ?
                AND c.teacher_id = ?
                FOR UPDATE
            ";
            $params[] = $_SESSION['role_id'];
            break;
            
        case 'student':
            // Students can only comment on their own submissions
            $sql = "
                SELECT ss.id, ss.student_id, a.class_id
                FROM student_submissions ss
                WHERE ss.id = ?
                AND ss.student_id = ?
                FOR UPDATE
            ";
            $params[] = $_SESSION['role_id'];
            break;
            
        default:
            throw new Exception('Invalid user type');
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        throw new Exception('Submission not found or access denied');
    }

    // Insert the comment
    $stmt = $conn->prepare("
        INSERT INTO assignment_comments (
            submission_id,
            user_id,
            comment,
            created_at
        ) VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $submissionId,
        $_SESSION['user_id'],
        $comment
    ]);

    $commentId = $conn->lastInsertId();

    // Get the newly created comment
    $stmt = $conn->prepare("
        SELECT 
            ac.*,
            u.user_type,
            CASE 
                WHEN u.user_type = 'teacher' THEN 
                    (SELECT CONCAT(first_name, ' ', last_name) 
                     FROM teachers 
                     WHERE user_id = ac.user_id)
                WHEN u.user_type = 'student' THEN 
                    (SELECT CONCAT(first_name, ' ', last_name) 
                     FROM students 
                     WHERE user_id = ac.user_id)
                ELSE 'Unknown User'
            END as user_name
        FROM assignment_comments ac
        JOIN users u ON ac.user_id = u.id
        WHERE ac.id = ?
    ");
    
    $stmt->execute([$commentId]);
    $newComment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format the comment for response
    $formattedComment = [
        'id' => $newComment['id'],
        'comment' => nl2br(htmlspecialchars($newComment['comment'])),
        'user_type' => $newComment['user_type'],
        'user_name' => $newComment['user_name'],
        'created_at' => formatDateTime($newComment['created_at'])
    ];

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO system_logs (
            user_id,
            action,
            description,
            created_at
        ) VALUES (?, 'Add Comment', ?, NOW())
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        "Added comment to submission ID: $submissionId"
    ]);

    // Commit transaction
    $conn->commit();

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully',
        'comment' => $formattedComment
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error adding comment: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to add comment: ' . $e->getMessage()
    ]);
} 