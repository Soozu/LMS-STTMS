<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: assignments.php');
    exit();
}

$submissionId = $_POST['submission_id'] ?? null;
$score = $_POST['score'] ?? null;
$feedback = $_POST['feedback'] ?? '';
$teacherId = $_SESSION['role_id'];

try {
    // Start transaction
    $conn->beginTransaction();

    // Verify the submission belongs to a class taught by this teacher and get necessary info
    $stmt = $conn->prepare("
        SELECT 
            ss.student_id,
            a.class_id,
            ss.assignment_id
        FROM student_submissions ss
        JOIN assignments a ON ss.assignment_id = a.id
        JOIN classes c ON a.class_id = c.id
        WHERE ss.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$submissionId, $teacherId]);
    $submissionData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submissionData) {
        throw new Exception('Unauthorized access to submission');
    }

    // Update the submission with grade and feedback
    $stmt = $conn->prepare("
        UPDATE student_submissions 
        SET score = ?, 
            feedback = ?,
            status = 'graded',
            graded_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$score, $feedback, $submissionId]);

    // Calculate the quarter based on current date
    $currentMonth = date('n');
    $quarter = ceil($currentMonth / 3);
    if ($currentMonth >= 11) {
        $quarter = 2; // November-December is 2nd quarter
    } elseif ($currentMonth >= 8) {
        $quarter = 1; // August-October is 1st quarter
    } elseif ($currentMonth >= 5) {
        $quarter = 4; // May-July is 4th quarter
    } elseif ($currentMonth >= 2) {
        $quarter = 3; // February-April is 3rd quarter
    }

    // Check if a grade record already exists
    $stmt = $conn->prepare("
        SELECT id, grade 
        FROM grades 
        WHERE class_id = ? 
        AND student_id = ? 
        AND quarter = ?
    ");
    $stmt->execute([
        $submissionData['class_id'],
        $submissionData['student_id'],
        $quarter
    ]);
    $existingGrade = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate the new average grade for the quarter
    $stmt = $conn->prepare("
        SELECT AVG(score) as average_score
        FROM student_submissions
        WHERE student_id = ?
        AND assignment_id IN (
            SELECT id 
            FROM assignments 
            WHERE class_id = ?
        )
        AND status = 'graded'
    ");
    $stmt->execute([
        $submissionData['student_id'],
        $submissionData['class_id']
    ]);
    $averageScore = $stmt->fetch(PDO::FETCH_ASSOC)['average_score'];

    if ($existingGrade) {
        // Update existing grade
        $stmt = $conn->prepare("
            UPDATE grades 
            SET grade = ?,
                remarks = CASE 
                    WHEN ? >= 75 THEN 'Passed'
                    ELSE 'Failed'
                END,
                date_submitted = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$averageScore, $averageScore, $existingGrade['id']]);
    } else {
        // Insert new grade
        $stmt = $conn->prepare("
            INSERT INTO grades (
                class_id, 
                student_id, 
                quarter, 
                grade,
                remarks,
                date_submitted
            ) VALUES (?, ?, ?, ?, 
                CASE 
                    WHEN ? >= 75 THEN 'Passed'
                    ELSE 'Failed'
                END,
                NOW()
            )
        ");
        $stmt->execute([
            $submissionData['class_id'],
            $submissionData['student_id'],
            $quarter,
            $averageScore,
            $averageScore
        ]);
    }

    // Commit transaction
    $conn->commit();
    $_SESSION['success_message'] = 'Grade saved successfully!';
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("Error updating grade: " . $e->getMessage());
    $_SESSION['error_message'] = 'Failed to save grade. Please try again.';
}

// Redirect back to the submission view
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
 