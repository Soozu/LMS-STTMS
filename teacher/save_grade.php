<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);
$teacherId = $_SESSION['role_id'];

// Validate input data
if (!isset($data['student_id']) || !isset($data['assignment_id']) || !isset($data['grade'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$studentId = (int)$data['student_id'];
$assignmentId = (int)$data['assignment_id'];
$grade = (float)$data['grade'];

// Validate grade value
if ($grade < 0 || $grade > 100) {
    echo json_encode(['success' => false, 'message' => 'Grade must be between 0 and 100']);
    exit();
}

try {
    // First verify that the teacher has access to this assignment/student
    $stmt = $conn->prepare("
        SELECT a.id, a.class_id
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN class_enrollments ce ON c.id = ce.class_id
        WHERE a.id = ?
        AND c.teacher_id = ?
        AND ce.student_id = ?
        AND ce.status = 'active'
    ");
    $stmt->execute([$assignmentId, $teacherId, $studentId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        throw new Exception('Unauthorized access to this assignment or student');
    }

    // Begin transaction
    $conn->beginTransaction();

    // Check if submission already exists
    $stmt = $conn->prepare("
        SELECT id 
        FROM student_submissions 
        WHERE student_id = ? AND assignment_id = ?
    ");
    $stmt->execute([$studentId, $assignmentId]);
    $existingSubmission = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingSubmission) {
        // Check if assignment is overdue
        $stmt = $conn->prepare("
            SELECT 
                CASE WHEN due_date < CURRENT_TIMESTAMP THEN 1 ELSE 0 END as is_overdue
            FROM assignments 
            WHERE id = ?
        ");
        $stmt->execute([$assignmentId]);
        $assignmentStatus = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($assignmentStatus['is_overdue']) {
            $grade = 0; // Force grade to 0 for overdue submissions
        }

        // Update existing submission
        $stmt = $conn->prepare("
            UPDATE student_submissions 
            SET score = ?, 
                status = 'graded',
                submitted_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$grade, $existingSubmission['id']]);
    } else {
        // Create new submission
        $stmt = $conn->prepare("
            INSERT INTO student_submissions 
            (student_id, assignment_id, score, status, submitted_at)
            VALUES (?, ?, ?, 'graded', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$studentId, $assignmentId, $grade]);
    }

    // Commit transaction
    $conn->commit();

    // Return success response with the rounded grade
    echo json_encode([
        'success' => true,
        'message' => 'Grade saved successfully',
        'data' => [
            'grade' => round($grade),
            'status' => 'graded'
        ]
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Database error saving grade: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred while saving grade'
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error saving grade: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 