<?php
// Turn off output buffering
ob_clean();

// Prevent any unwanted output
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';  // Direct include instead of using namespace

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$teacherId = $_SESSION['role_id'];

if (!$classId) {
    exit('Invalid class ID');
}

try {
    // Get class details
    $stmt = $conn->prepare("
        SELECT 
            c.id as class_id,
            s.subject_name,
            sec.section_name,
            sec.grade_level,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        JOIN teachers t ON c.teacher_id = t.id
        WHERE c.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$classId, $teacherId]);
    $classDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$classDetails) {
        exit('Class not found or access denied');
    }

    // Get assignments and activities separately
    $stmt = $conn->prepare("
        SELECT 
            id, 
            title, 
            type,
            due_date,
            COALESCE(max_score, 100) as max_score
        FROM assignments
        WHERE class_id = ? AND status = 'active'
        ORDER BY type, due_date ASC
    ");
    $stmt->execute([$classId]);
    $allAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Separate assignments and activities
    $assignments = array_filter($allAssignments, fn($a) => $a['type'] === 'assignment');
    $activities = array_filter($allAssignments, fn($a) => $a['type'] === 'activity');

    // Get students and their submissions
    $stmt = $conn->prepare("
        SELECT 
            s.id as student_id,
            s.first_name,
            s.last_name,
            s.lrn,
            a.id as assignment_id,
            a.type,
            ss.score,
            CASE 
                WHEN ss.score IS NOT NULL THEN ss.score
                WHEN a.due_date < CURRENT_TIMESTAMP THEN 0
                ELSE NULL 
            END as final_score
        FROM students s
        JOIN class_enrollments ce ON s.id = ce.student_id
        CROSS JOIN assignments a
        LEFT JOIN student_submissions ss ON s.id = ss.student_id AND a.id = ss.assignment_id
        WHERE ce.class_id = ? AND ce.status = 'active' AND a.class_id = ? AND a.status = 'active'
        ORDER BY s.last_name ASC, s.first_name ASC, a.type, a.due_date ASC
    ");
    $stmt->execute([$classId, $classId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process grades
    $students = [];
    foreach ($results as $row) {
        if (!isset($students[$row['student_id']])) {
            $students[$row['student_id']] = [
                'name' => $row['last_name'] . ', ' . $row['first_name'],
                'lrn' => $row['lrn'],
                'assignments' => [],
                'activities' => [],
                'assignment_average' => 0,
                'activity_average' => 0,
                'final_average' => 0
            ];
        }

        if ($row['type'] === 'assignment') {
            $students[$row['student_id']]['assignments'][$row['assignment_id']] = $row['final_score'];
        } else {
            $students[$row['student_id']]['activities'][$row['assignment_id']] = $row['final_score'];
        }
    }

    // Calculate averages
    foreach ($students as &$student) {
        // Calculate assignment average (60%)
        $assignmentScores = array_filter($student['assignments'], fn($score) => $score !== null);
        $student['assignment_average'] = !empty($assignmentScores) ? 
            array_sum($assignmentScores) / count($assignmentScores) : 0;

        // Calculate activity average (40%)
        $activityScores = array_filter($student['activities'], fn($score) => $score !== null);
        $student['activity_average'] = !empty($activityScores) ? 
            array_sum($activityScores) / count($activityScores) : 0;

        // Calculate final weighted average
        $student['final_average'] = ($student['assignment_average'] * 0.6) + 
                                  ($student['activity_average'] * 0.4);
    }

    // Create PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('STMA LMS');
    $pdf->SetAuthor($classDetails['teacher_name']);
    $pdf->SetTitle('Class Grades - ' . $classDetails['subject_name']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage('L', 'A4');

    // Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'St. Thomas More Academy', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Learning Management System', 0, 1, 'C');
    $pdf->Ln(5);

    // Class details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, $classDetails['subject_name'], 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Grade ' . $classDetails['grade_level'] . ' - ' . $classDetails['section_name'], 0, 1, 'L');
    $pdf->Cell(0, 10, 'Teacher: ' . $classDetails['teacher_name'], 0, 1, 'L');
    $pdf->Ln(5);

    // Assignments Section
    if (!empty($assignments)) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 10, 'Assignments (60%)', 0, 1, 'L');
        $pdf->SetFillColor(220, 220, 220);
        $pdf->SetFont('helvetica', 'B', 10);

        // Calculate column widths
        $nameWidth = 60;
        $lrnWidth = 30;
        $gradeWidth = (265 - $nameWidth - $lrnWidth) / (count($assignments) + 1);

        // Header row
        $pdf->Cell($nameWidth, 7, 'Student Name', 1, 0, 'C', true);
        $pdf->Cell($lrnWidth, 7, 'LRN', 1, 0, 'C', true);
        foreach ($assignments as $assignment) {
            $pdf->Cell($gradeWidth, 7, substr($assignment['title'], 0, 15), 1, 0, 'C', true);
        }
        $pdf->Cell($gradeWidth, 7, 'Average', 1, 1, 'C', true);

        // Data rows
        $pdf->SetFont('helvetica', '', 9);
        foreach ($students as $student) {
            $pdf->Cell($nameWidth, 6, $student['name'], 1, 0, 'L');
            $pdf->Cell($lrnWidth, 6, $student['lrn'], 1, 0, 'C');
            foreach ($assignments as $assignment) {
                $grade = isset($student['assignments'][$assignment['id']]) ? 
                         number_format($student['assignments'][$assignment['id']], 0) : '-';
                $pdf->Cell($gradeWidth, 6, $grade, 1, 0, 'C');
            }
            $pdf->Cell($gradeWidth, 6, number_format($student['assignment_average'], 0), 1, 1, 'C');
        }
        $pdf->Ln(5);
    }

    // Activities Section
    if (!empty($activities)) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 10, 'Activities (40%)', 0, 1, 'L');
        $pdf->SetFillColor(220, 220, 220);
        $pdf->SetFont('helvetica', 'B', 10);

        // Header row
        $pdf->Cell($nameWidth, 7, 'Student Name', 1, 0, 'C', true);
        $pdf->Cell($lrnWidth, 7, 'LRN', 1, 0, 'C', true);
        foreach ($activities as $activity) {
            $pdf->Cell($gradeWidth, 7, substr($activity['title'], 0, 15), 1, 0, 'C', true);
        }
        $pdf->Cell($gradeWidth, 7, 'Average', 1, 1, 'C', true);

        // Data rows
        $pdf->SetFont('helvetica', '', 9);
        foreach ($students as $student) {
            $pdf->Cell($nameWidth, 6, $student['name'], 1, 0, 'L');
            $pdf->Cell($lrnWidth, 6, $student['lrn'], 1, 0, 'C');
            foreach ($activities as $activity) {
                $grade = isset($student['activities'][$activity['id']]) ? 
                         number_format($student['activities'][$activity['id']], 0) : '-';
                $pdf->Cell($gradeWidth, 6, $grade, 1, 0, 'C');
            }
            $pdf->Cell($gradeWidth, 6, number_format($student['activity_average'], 0), 1, 1, 'C');
        }
        $pdf->Ln(5);
    }

    // Final Averages Section
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'Final Averages', 0, 1, 'L');
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('helvetica', 'B', 10);

    // Header
    $pdf->Cell($nameWidth + $lrnWidth, 7, 'Student', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Assignment Avg', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Activity Avg', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Final Average', 1, 1, 'C', true);

    // Data
    $pdf->SetFont('helvetica', '', 9);
    foreach ($students as $student) {
        $pdf->Cell($nameWidth + $lrnWidth, 6, $student['name'] . ' (' . $student['lrn'] . ')', 1, 0, 'L');
        $pdf->Cell(40, 6, number_format($student['assignment_average'], 0) . '%', 1, 0, 'C');
        $pdf->Cell(40, 6, number_format($student['activity_average'], 0) . '%', 1, 0, 'C');
        $pdf->Cell(40, 6, number_format($student['final_average'], 0) . '%', 1, 1, 'C');
    }

    // Output PDF
    $pdf->Output('Class_Grades_' . date('Y-m-d') . '.pdf', 'D');
    exit();

} catch (Exception $e) {
    error_log("Error generating grades PDF: " . $e->getMessage());
    exit('Error generating PDF: ' . $e->getMessage());
} 