<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacherId = $_SESSION['role_id'];
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

try {
    // Fetch teacher's classes for dropdown
    $stmt = $conn->prepare("
        SELECT 
            c.id as class_id,
            s.subject_name,
            sec.section_name,
            sec.grade_level
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE c.teacher_id = ? 
        AND c.status = 'active'
        ORDER BY sec.grade_level ASC, sec.section_name ASC, s.subject_name ASC
    ");
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If class is selected, fetch grades and assignments
    if ($selectedClass) {
        // Get class details
        $stmt = $conn->prepare("
            SELECT 
                c.id as class_id,
                s.subject_name,
                sec.section_name,
                sec.grade_level,
                (SELECT COUNT(*) 
                 FROM class_enrollments ce 
                 WHERE ce.class_id = c.id 
                 AND ce.status = 'active') as enrolled_students
            FROM classes c
            JOIN subjects s ON c.subject_id = s.id
            JOIN sections sec ON c.section_id = sec.id
            WHERE c.id = ?
            AND c.teacher_id = ?
        ");
        $stmt->execute([$selectedClass, $teacherId]);
        $classDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$classDetails) {
            throw new Exception('Class not found or access denied');
        }

        // Fetch all assignments for this class
        $stmt = $conn->prepare("
            SELECT 
                a.*,
                100 as max_score,
                CASE 
                    WHEN ss.score IS NOT NULL THEN ss.score
                    WHEN a.due_date < CURRENT_TIMESTAMP AND ss.id IS NULL THEN 0  -- Automatic zero for overdue
                    ELSE NULL 
                END as score,
                CASE 
                    WHEN ss.id IS NOT NULL THEN 'submitted'
                    WHEN a.due_date < CURRENT_TIMESTAMP AND ss.id IS NULL THEN 'overdue'
                    ELSE 'pending'
                END as submission_status,
                CASE 
                    WHEN a.due_date < CURRENT_TIMESTAMP AND ss.id IS NULL THEN 
                        DATEDIFF(CURRENT_TIMESTAMP, a.due_date)
                    ELSE NULL
                END as days_overdue,
                CASE 
                    WHEN a.type = 'assignment' THEN 'assignment'
                    ELSE 'activity'
                END as type
            FROM assignments a
            LEFT JOIN student_submissions ss ON a.id = ss.assignment_id 
                AND ss.student_id = ?
            WHERE a.class_id = ? 
            AND a.status = 'active'
            ORDER BY a.due_date DESC
        ");
        $stmt->execute([$selectedClass, $selectedClass]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // First, fetch all students enrolled in the class
        $stmt = $conn->prepare("
            SELECT DISTINCT
                s.id as student_id,
                s.first_name,
                s.last_name,
                u.username as lrn,
                ce.status as enrollment_status
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN class_enrollments ce ON s.id = ce.student_id 
            WHERE ce.class_id = ? AND ce.status = 'active'
            ORDER BY s.last_name ASC, s.first_name ASC
        ");
        $stmt->execute([$selectedClass]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Then, fetch submissions for each student
        foreach ($students as &$student) {
            // Initialize submissions array
            $student['submissions'] = [];
            $student['average'] = null;
            $student['submission_rate'] = 0;
            
            // Fetch submissions for this student
            $stmt = $conn->prepare("
                SELECT 
                    a.id as assignment_id,
                    a.type,
                    CASE 
                        WHEN ss.score IS NOT NULL THEN ss.score
                        WHEN a.due_date < CURRENT_TIMESTAMP THEN 0  -- Automatic zero for overdue
                        ELSE NULL 
                    END as score,
                    CASE 
                        WHEN ss.status IS NOT NULL THEN ss.status
                        WHEN a.due_date < CURRENT_TIMESTAMP THEN 'overdue'
                        ELSE 'pending'
                    END as submission_status
                FROM assignments a
                LEFT JOIN student_submissions ss ON ss.assignment_id = a.id 
                    AND ss.student_id = ?
                WHERE a.class_id = ?
                AND a.status = 'active'
                ORDER BY a.due_date ASC
            ");
            $stmt->execute([$student['student_id'], $selectedClass]);
            $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process submissions
            $totalAssignmentScore = 0;
            $totalActivityScore = 0;
            $assignmentCount = 0;
            $activityCount = 0;
            $submittedCount = 0;
            
            foreach ($submissions as $submission) {
                $student['submissions'][$submission['assignment_id']] = [
                    'score' => $submission['score'],
                    'status' => $submission['submission_status'],
                    'type' => $submission['type']
                ];
                
                // Count submission if it's either submitted or overdue
                if ($submission['submission_status'] !== 'pending') {
                    $submittedCount++;
                    
                    // Add to respective totals based on type
                    if ($submission['type'] === 'assignment') {
                        $totalAssignmentScore += ($submission['score'] ?? 0);
                        $assignmentCount++;
                    } else { // activity
                        $totalActivityScore += ($submission['score'] ?? 0);
                        $activityCount++;
                    }
                }
            }
            
            // Calculate weighted average (60% assignments, 40% activities)
            $assignmentAverage = $assignmentCount > 0 ? ($totalAssignmentScore / $assignmentCount) : 0;
            $activityAverage = $activityCount > 0 ? ($totalActivityScore / $activityCount) : 0;
            
            if ($assignmentCount > 0 || $activityCount > 0) {
                // If there are both assignments and activities
                if ($assignmentCount > 0 && $activityCount > 0) {
                    $student['average'] = ($assignmentAverage * 0.6) + ($activityAverage * 0.4);
                }
                // If there are only assignments
                else if ($assignmentCount > 0) {
                    $student['average'] = $assignmentAverage;
                }
                // If there are only activities
                else if ($activityCount > 0) {
                    $student['average'] = $activityAverage;
                }
            } else {
                $student['average'] = null;
            }
            
            $totalAssignments = count($submissions);
            $student['submission_rate'] = $totalAssignments > 0 ? 
                ($submittedCount / $totalAssignments) * 100 : 0;
        }

        // Calculate class statistics
        $classStats = [
            'highest_average' => 0,
            'lowest_average' => 100,
            'class_average' => 0,
            'submission_rate' => 0,
            'total_students' => count($students)
        ];

        $totalAverages = 0;
        $studentsWithGrades = 0;
        $totalSubmissionRates = 0;

        foreach ($students as $student) {
            if ($student['average'] !== null) {
                $classStats['highest_average'] = max($classStats['highest_average'], $student['average']);
                $classStats['lowest_average'] = min($classStats['lowest_average'], $student['average']);
                $totalAverages += $student['average'];
                $studentsWithGrades++;
            }
            $totalSubmissionRates += $student['submission_rate'];
        }

        if ($studentsWithGrades > 0) {
            $classStats['class_average'] = $totalAverages / $studentsWithGrades;
            $classStats['submission_rate'] = $totalSubmissionRates / count($students);
        } else {
            $classStats['lowest_average'] = 0;
        }
    }

} catch(Exception $e) {
    error_log("Error in grades page: " . $e->getMessage());
    $error = "An error occurred while loading the data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/grades.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Grades</h2>
            <div class="class-selector">
                <select id="classSelect" onchange="window.location.href='?class_id=' + this.value">
                    <option value="">Select a Class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>" 
                                <?php echo $selectedClass == $class['class_id'] ? 'selected' : ''; ?>>
                            Grade <?php echo htmlspecialchars($class['grade_level']); ?> - 
                            <?php echo htmlspecialchars($class['section_name']); ?> - 
                            <?php echo htmlspecialchars($class['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($selectedClass && isset($classDetails)): ?>
            <div class="class-info">
                <div class="class-details">
                    <h3><?php echo htmlspecialchars($classDetails['subject_name']); ?></h3>
                    <p class="class-meta">
                        Grade <?php echo htmlspecialchars($classDetails['grade_level']); ?> - 
                        <?php echo htmlspecialchars($classDetails['section_name']); ?> | 
                        <?php echo $classDetails['enrolled_students']; ?> Students
                    </p>
                </div>
                <div class="class-actions">
                    <a href="export_grades.php?class_id=<?php echo $selectedClass; ?>" class="btn-primary">
                        <i class="fas fa-download"></i> Export Grades
                    </a>
                </div>
            </div>

            <?php if (!empty($assignments)): ?>
                <div class="grades-table-container">
                    <table class="grades-table">
                        <thead>
                            <tr>
                                <th class="student-info">Student</th>
                                <?php foreach ($assignments as $assignment): ?>
                                    <th class="assignment-grade">
                                        <div class="assignment-header">
                                            <div class="assignment-type">
                                                <span class="type-badge <?php echo $assignment['type']; ?>">
                                                    <?php echo ucfirst($assignment['type']); ?>
                                                </span>
                                            </div>
                                            <span class="assignment-title" title="<?php echo htmlspecialchars($assignment['title']); ?>">
                                                <?php echo htmlspecialchars($assignment['title']); ?>
                                            </span>
                                            <span class="max-score">(<?php echo isset($assignment['max_score']) ? $assignment['max_score'] : 100; ?>)</span>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                                <th class="final-grade">Average</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td class="student-info">
                                        <div class="student-name">
                                            <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?>
                                        </div>
                                        <div class="student-lrn"><?php echo htmlspecialchars($student['lrn']); ?></div>
                                    </td>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <td class="assignment-grade <?php echo ($student['submissions'][$assignment['id']]['status'] === 'overdue') ? 'overdue' : ''; ?>">
                                            <?php 
                                            $submission = $student['submissions'][$assignment['id']] ?? null;
                                            if ($submission): 
                                                if ($submission['score'] !== null): ?>
                                                    <div class="grade-display">
                                                        <span class="score <?php echo $submission['score'] >= 75 ? 'passing' : 'failing'; ?>">
                                                            <?php echo number_format($submission['score'], 0); ?>/100
                                                        </span>
                                                        <button class="edit-grade" onclick="showEditGrade(this)" title="Edit Grade">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                    <div class="grade-edit" style="display: none;">
                                                        <div class="grade-input-group">
                                                            <input type="number" 
                                                                   class="grade-input" 
                                                                   min="0" 
                                                                   max="100" 
                                                                   value="<?php echo $submission['score']; ?>"
                                                                   data-student="<?php echo $student['student_id']; ?>"
                                                                   data-assignment="<?php echo $assignment['id']; ?>">
                                                            <button class="save-grade" onclick="saveGrade(this)">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button class="cancel-edit" onclick="cancelEdit(this)">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php elseif ($submission['status'] === 'overdue'): ?>
                                                    <div class="overdue-status">
                                                        <span class="overdue-label">
                                                            <i class="fas fa-exclamation-circle"></i> 
                                                            Overdue <?php echo $submission['days_overdue']; ?> days
                                                        </span>
                                                        <div class="grade-input-group">
                                                            <input type="number" 
                                                                   class="grade-input" 
                                                                   min="0" 
                                                                   max="100" 
                                                                   placeholder="Grade"
                                                                   data-student="<?php echo $student['student_id']; ?>"
                                                                   data-assignment="<?php echo $assignment['id']; ?>">
                                                            <button class="save-grade" onclick="saveGrade(this)">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="no-submission">Not submitted</span>
                                                <?php endif; 
                                            else: ?>
                                                <span class="no-submission">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="final-grade">
                                        <?php if ($student['average'] !== null): ?>
                                            <span class="average <?php echo $student['average'] >= 75 ? 'passing' : 'failing'; ?>">
                                                <?php echo number_format($student['average'], 0); ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="no-grade">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h3>No Assignments</h3>
                    <p>There are no assignments created for this class yet.</p>
                    <a href="create_assignment.php?class_id=<?php echo $selectedClass; ?>" class="btn-create">
                        Create Your First Assignment
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>Select a Class</h3>
                <p>Please select a class to view student grades</p>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Remove or replace this function since we're using a direct link now
        /*
        function exportGrades() {
            alert('Export functionality will be implemented here');
        }
        */
    </script>

    <script>
    function saveGrade(button) {
        const inputGroup = button.parentElement;
        const input = inputGroup.querySelector('.grade-input');
        const grade = input.value;
        const studentId = input.dataset.student;
        const assignmentId = input.dataset.assignment;

        if (!grade || grade < 0 || grade > 100) {
            alert('Please enter a valid grade between 0 and 100');
            return;
        }

        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;

        // Send grade to server
        fetch('save_grade.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                student_id: studentId,
                assignment_id: assignmentId,
                grade: grade
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Replace input with saved grade
                const cell = button.closest('.assignment-grade');
                cell.innerHTML = `
                    <div class="grade-display">
                        <span class="score ${grade >= 75 ? 'passing' : 'failing'}">
                            ${Math.round(parseFloat(grade))}/100
                        </span>
                        <button class="edit-grade" onclick="showEditGrade(this)" title="Edit Grade">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                    <div class="grade-edit" style="display: none;">
                        <div class="grade-input-group">
                            <input type="number" 
                                   class="grade-input" 
                                   min="0" 
                                   max="100" 
                                   value="${Math.round(grade)}"
                                   data-student="${studentId}"
                                   data-assignment="${assignmentId}">
                            <button class="save-grade" onclick="saveGrade(this)">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="cancel-edit" onclick="cancelEdit(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                // Update the average grade
                updateStudentAverage(button.closest('tr'));
            } else {
                alert('Error saving grade: ' + data.message);
                // Reset button
                button.innerHTML = '<i class="fas fa-check"></i>';
                button.disabled = false;
            }
        })
        .catch(error => {
            alert('Error saving grade');
            console.error('Error:', error);
            // Reset button
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.disabled = false;
        });
    }

    function updateStudentAverage(row) {
        const scores = Array.from(row.querySelectorAll('.score'))
            .map(span => parseFloat(span.textContent));
        
        if (scores.length > 0) {
            const average = scores.reduce((a, b) => a + b, 0) / scores.length;
            const averageCell = row.querySelector('.final-grade');
            averageCell.innerHTML = `
                <span class="average ${average >= 75 ? 'passing' : 'failing'}">
                    ${Math.round(average)}%
                </span>
            `;
        }
    }

    function showEditGrade(button) {
        const cell = button.closest('.assignment-grade');
        const displayDiv = cell.querySelector('.grade-display');
        const editDiv = cell.querySelector('.grade-edit');
        
        displayDiv.style.display = 'none';
        editDiv.style.display = 'block';
        editDiv.querySelector('.grade-input').focus();
    }

    function cancelEdit(button) {
        const cell = button.closest('.assignment-grade');
        const displayDiv = cell.querySelector('.grade-display');
        const editDiv = cell.querySelector('.grade-edit');
        
        displayDiv.style.display = 'flex';
        editDiv.style.display = 'none';
    }
    </script>
</body>
</html> 