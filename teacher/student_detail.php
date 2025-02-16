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
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

if (!$studentId || !$classId) {
    header('Location: reports.php');
    exit();
}

try {
    // Get student details
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            u.username as lrn,
            c.subject_name,
            sec.section_name,
            sec.grade_level
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN class_enrollments ce ON s.id = ce.student_id
        JOIN classes cl ON ce.class_id = cl.id
        JOIN subjects c ON cl.subject_id = c.id
        JOIN sections sec ON cl.section_id = sec.id
        WHERE s.id = ? AND cl.id = ? AND cl.teacher_id = ?
    ");
    $stmt->execute([$studentId, $classId, $teacherId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Student not found or access denied');
    }

    // Get assignment submissions
    $stmt = $conn->prepare("
        SELECT 
            a.id,
            a.title,
            a.description,
            a.due_date,
            ss.submitted_at,
            ss.score,
            ss.status,
            ss.feedback,
            sf.file_name,
            sf.original_name
        FROM assignments a
        LEFT JOIN student_submissions ss ON ss.assignment_id = a.id AND ss.student_id = ?
        LEFT JOIN submission_files sf ON sf.submission_id = ss.id
        WHERE a.class_id = ?
        ORDER BY a.due_date DESC
    ");
    $stmt->execute([$studentId, $classId]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $totalAssignments = count($submissions);
    $submittedCount = 0;
    $totalScore = 0;
    $gradedCount = 0;
    $lateSubmissions = 0;

    foreach ($submissions as $submission) {
        if ($submission['status'] === 'submitted' || $submission['status'] === 'graded') {
            $submittedCount++;
            if ($submission['score'] !== null) {
                $totalScore += $submission['score'];
                $gradedCount++;
            }
            if (strtotime($submission['submitted_at']) > strtotime($submission['due_date'])) {
                $lateSubmissions++;
            }
        }
    }

    $average = $gradedCount > 0 ? ($totalScore / $gradedCount) : null;
    $submissionRate = $totalAssignments > 0 ? ($submittedCount / $totalAssignments) * 100 : 0;

} catch(Exception $e) {
    error_log("Error in student detail page: " . $e->getMessage());
    $error = "An error occurred while loading the student details.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Detail - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/student_detail.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="header-content">
                <a href="reports.php?class_id=<?php echo $classId; ?>" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
                <h2>Student Performance</h2>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php else: ?>
            <!-- Student Info Card -->
            <div class="student-card">
                <div class="student-info">
                    <h3><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></h3>
                    <p class="lrn">LRN: <?php echo htmlspecialchars($student['lrn']); ?></p>
                    <p class="class-info">
                        Grade <?php echo $student['grade_level']; ?> - 
                        <?php echo htmlspecialchars($student['section_name']); ?> | 
                        <?php echo htmlspecialchars($student['subject_name']); ?>
                    </p>
                </div>
            </div>

            <!-- Performance Overview -->
            <div class="performance-overview">
                <div class="stat-card">
                    <i class="fas fa-chart-line"></i>
                    <div class="stat-info">
                        <h4>Average Score</h4>
                        <p><?php echo $average ? number_format($average, 1) . '%' : 'N/A'; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-tasks"></i>
                    <div class="stat-info">
                        <h4>Submission Rate</h4>
                        <p><?php echo number_format($submissionRate, 1); ?>%</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <div class="stat-info">
                        <h4>Late Submissions</h4>
                        <p><?php echo $lateSubmissions; ?></p>
                    </div>
                </div>
            </div>

            <!-- Submissions List -->
            <div class="submissions-section">
                <h3>Assignment Submissions</h3>
                <div class="table-responsive">
                    <table class="submissions-table">
                        <thead>
                            <tr>
                                <th>Assignment</th>
                                <th>Due Date</th>
                                <th>Submission Date</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $submission): ?>
                                <tr>
                                    <td>
                                        <div class="assignment-info">
                                            <div class="title"><?php echo htmlspecialchars($submission['title']); ?></div>
                                            <div class="description"><?php echo htmlspecialchars(substr($submission['description'], 0, 100)) . '...'; ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y h:i A', strtotime($submission['due_date'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($submission['submitted_at']): ?>
                                            <span class="<?php echo strtotime($submission['submitted_at']) > strtotime($submission['due_date']) ? 'late' : 'on-time'; ?>">
                                                <?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="no-submission">Not submitted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $submission['status'] ?? 'missing'; ?>">
                                            <?php echo ucfirst($submission['status'] ?? 'Missing'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($submission['score'] !== null): ?>
                                            <span class="score <?php echo $submission['score'] >= 75 ? 'passing' : 'failing'; ?>">
                                                <?php echo $submission['score']; ?>/100
                                            </span>
                                        <?php else: ?>
                                            <span class="no-score">Not graded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($submission['file_name']): ?>
                                            <a href="../uploads/submissions/<?php echo $submission['file_name']; ?>" 
                                               class="btn-download" 
                                               download="<?php echo $submission['original_name']; ?>">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html> 