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
        ORDER BY sec.grade_level ASC, sec.section_name ASC
    ");
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Auto-select first class if no class is selected
    if (!$selectedClass && !empty($classes)) {
        $selectedClass = $classes[0]['class_id'];
    }

    // If we have a selected class (either auto-selected or from GET parameter)
    if ($selectedClass) {
        // Get class details
        $stmt = $conn->prepare("
            SELECT 
                c.*,
                s.subject_name,
                sec.section_name,
                sec.grade_level,
                COUNT(DISTINCT ce.student_id) as total_students,
                COUNT(DISTINCT a.id) as total_assignments
            FROM classes c
            JOIN subjects s ON c.subject_id = s.id
            JOIN sections sec ON c.section_id = sec.id
            LEFT JOIN class_enrollments ce ON ce.class_id = c.id AND ce.status = 'active'
            LEFT JOIN assignments a ON a.class_id = c.id
            WHERE c.id = ? AND c.teacher_id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$selectedClass, $teacherId]);
        $classDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get submission statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT ss.id) as total_submissions,
                COUNT(DISTINCT CASE WHEN ss.status = 'graded' THEN ss.id END) as graded_submissions,
                COUNT(DISTINCT CASE WHEN ss.submitted_at > a.due_date THEN ss.id END) as late_submissions,
                AVG(ss.score) as average_score
            FROM assignments a
            LEFT JOIN student_submissions ss ON ss.assignment_id = a.id
            WHERE a.class_id = ?
        ");
        $stmt->execute([$selectedClass]);
        $submissionStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get student progress
        $stmt = $conn->prepare("
            SELECT 
                s.id,
                s.first_name,
                s.last_name,
                u.username as lrn,
                COUNT(DISTINCT a.id) as total_assignments,
                COUNT(DISTINCT ss.id) as submitted_assignments,
                COUNT(DISTINCT CASE WHEN ss.status = 'graded' THEN ss.id END) as graded_assignments,
                AVG(ss.score) as average_score
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN class_enrollments ce ON ce.student_id = s.id
            LEFT JOIN assignments a ON a.class_id = ce.class_id
            LEFT JOIN student_submissions ss ON ss.student_id = s.id AND ss.assignment_id = a.id
            WHERE ce.class_id = ? AND ce.status = 'active'
            GROUP BY s.id
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute([$selectedClass]);
        $studentProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch(Exception $e) {
    error_log("Error in reports page: " . $e->getMessage());
    $error = "An error occurred while loading the reports.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Class Reports</h2>
            <div class="class-selector">
                <select id="classSelect" onchange="window.location.href='?class_id=' + this.value">
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>" 
                                <?php echo $selectedClass == $class['class_id'] ? 'selected' : ''; ?>>
                            Grade <?php echo $class['grade_level']; ?> - 
                            <?php echo $class['section_name']; ?> - 
                            <?php echo $class['subject_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="select-icon">
                    <i class="fas fa-filter"></i>
                </div>
            </div>
        </div>

        <?php if ($selectedClass && isset($classDetails)): ?>
            <!-- Class Overview -->
            <div class="report-section">
                <h3>Class Overview</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <div class="stat-info">
                            <h4>Total Students</h4>
                            <p><?php echo $classDetails['total_students']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-tasks"></i>
                        <div class="stat-info">
                            <h4>Total Assignments</h4>
                            <p><?php echo $classDetails['total_assignments']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-check-circle"></i>
                        <div class="stat-info">
                            <h4>Submissions</h4>
                            <p><?php 
                                echo $submissionStats['total_submissions'] ?? 0;
                            ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-chart-line"></i>
                        <div class="stat-info">
                            <h4>Class Average</h4>
                            <p><?php echo $submissionStats['average_score'] ? number_format($submissionStats['average_score'], 1) : 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Progress -->
            <div class="report-section">
                <h3>Student Progress</h3>
                <div class="table-responsive">
                    <table class="progress-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Submissions</th>
                                <th>Progress</th>
                                <th>Average Score</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentProgress as $progress): ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <div class="student-name">
                                                <?php echo htmlspecialchars($progress['last_name'] . ', ' . $progress['first_name']); ?>
                                            </div>
                                            <div class="student-lrn"><?php echo htmlspecialchars($progress['lrn']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo $progress['submitted_assignments']; ?>/<?php echo $progress['total_assignments']; ?>
                                    </td>
                                    <td>
                                        <div class="progress-bar">
                                            <?php 
                                            $progressPercent = $progress['total_assignments'] > 0 ? 
                                                ($progress['submitted_assignments'] / $progress['total_assignments']) * 100 : 0;
                                            ?>
                                            <div class="progress" style="width: <?php echo $progressPercent; ?>%"></div>
                                            <span class="progress-text"><?php echo number_format($progressPercent, 1); ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($progress['average_score']): ?>
                                            <span class="score <?php echo $progress['average_score'] >= 75 ? 'passing' : 'failing'; ?>">
                                                <?php echo number_format($progress['average_score'], 1); ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="no-score">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="student_detail.php?student_id=<?php echo $progress['id']; ?>&class_id=<?php echo $selectedClass; ?>" 
                                           class="btn-view">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <h3>Select a Class</h3>
                <p>Please select a class to view its reports</p>
            </div>
        <?php endif; ?>
    </main>
</body>
</html> 