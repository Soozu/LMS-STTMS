<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get teacher ID from role_id in session
$teacherId = $_SESSION['role_id'];

try {
    // Fetch teacher's information
    $stmt = $conn->prepare("
        SELECT t.*, u.username, u.status
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND u.status = 'active'
    ");
    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        throw new Exception('Teacher not found or inactive');
    }

    // Fetch teacher's classes count and stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as total_classes,
            (SELECT COUNT(DISTINCT ce.student_id) 
             FROM class_enrollments ce 
             JOIN classes c2 ON ce.class_id = c2.id 
             WHERE c2.teacher_id = ? AND ce.status = 'active') as total_students,
            COUNT(DISTINCT s.id) as total_subjects
        FROM classes c
        LEFT JOIN subjects s ON c.subject_id = s.id
        WHERE c.teacher_id = ? AND c.status = 'active'
    ");
    $stmt->execute([$teacherId, $teacherId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch today's schedule
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            s.subject_name,
            sec.section_name,
            sec.grade_level,
            (SELECT COUNT(*) 
             FROM class_enrollments ce 
             WHERE ce.class_id = c.id 
             AND ce.status = 'active') as student_count
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE c.teacher_id = ? 
        AND c.status = 'active'
        ORDER BY sec.grade_level ASC, sec.section_name ASC
    ");
    $stmt->execute([$teacherId]);
    $todaySchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For now, set empty arrays for features we'll implement later
    $upcomingAssignments = [];
    $pendingGrades = [];

} catch(PDOException $e) {
    error_log("Error fetching teacher dashboard data: " . $e->getMessage());
    $error = "An error occurred while fetching your dashboard data.";
} catch(Exception $e) {
    error_log("Error: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
    <script src="js/dashboard.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Quick Access Section -->
        <div class="quick-access">
            <h2 class="section-title">Quick Access</h2>
            <div class="quick-access-grid">
                <a href="assignments.php?action=create" class="quick-access-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h3>Create Assignment</h3>
                    <p>Create a new assignment or activity</p>
                </a>
                <a href="announcements.php?action=create" class="quick-access-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <h3>Post Announcement</h3>
                    <p>Create a new announcement</p>
                </a>
                <a href="grades.php" class="quick-access-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3>Grade Submissions</h3>
                    <p>Review and grade student work</p>
                </a>
                <a href="reports.php" class="quick-access-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>View Reports</h3>
                    <p>Check student performance</p>
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <a href="classes.php" class="stat-card">
                <i class="fas fa-chalkboard"></i>
                <div class="stat-info">
                    <h3><?php echo $stats['total_classes']; ?></h3>
                    <p>Active Classes</p>
                </div>
            </a>
            <a href="students.php" class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-info">
                    <h3><?php echo $stats['total_students']; ?></h3>
                    <p>Total Students</p>
                </div>
            </a>
            <a href="subjects.php" class="stat-card">
                <i class="fas fa-book"></i>
                <div class="stat-info">
                    <h3><?php echo $stats['total_subjects']; ?></h3>
                    <p>Subjects</p>
                </div>
            </a>
        </div>

        <div class="dashboard-grid">
            <!-- Today's Schedule -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>My Classes</h3>
                    <span class="date"><?php echo date('l, F j, Y'); ?></span>
                </div>
                <div class="schedule-list">
                    <?php if (!empty($todaySchedule)): ?>
                        <?php foreach ($todaySchedule as $class): ?>
                            <div class="schedule-item">
                                <a href="class_details.php?id=<?php echo $class['id']; ?>" class="class-info">
                                    <h4><?php echo htmlspecialchars($class['subject_name']); ?></h4>
                                    <p class="class-details">
                                        <span class="grade-section">
                                            Grade <?php echo htmlspecialchars($class['grade_level']); ?> - 
                                            <?php echo htmlspecialchars($class['section_name']); ?>
                                        </span>
                                        <div class="student-count">
                                            <i class="fas fa-users"></i>
                                            <span><?php echo (int)$class['student_count']; ?> students</span>
                                        </div>
                                    </p>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-coffee"></i>
                            <p>No classes assigned yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Assignments -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Upcoming Assignments</h3>
                    <a href="assignments.php" class="view-all">View All</a>
                </div>
                <div class="assignments-list">
                    <?php if (!empty($upcomingAssignments)): ?>
                        <?php foreach ($upcomingAssignments as $assignment): ?>
                            <div class="assignment-item">
                                <div class="assignment-info">
                                    <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                    <p class="subject"><?php echo htmlspecialchars($assignment['subject_name']); ?></p>
                                    <p class="class-details">
                                        Grade <?php echo htmlspecialchars($assignment['grade_level']); ?> - 
                                        Section <?php echo htmlspecialchars($assignment['section']); ?>
                                    </p>
                                </div>
                                <div class="assignment-meta">
                                    <div class="due-date">
                                        Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                    </div>
                                    <div class="submission-count">
                                        <?php echo $assignment['submissions_count']; ?>/<?php echo $assignment['total_students']; ?> 
                                        Submitted
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <p>No upcoming assignments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Grades -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Pending Grades</h3>
                    <a href="grades.php" class="view-all">View All</a>
                </div>
                <div class="submissions-list">
                    <?php if (!empty($pendingGrades)): ?>
                        <?php foreach ($pendingGrades as $submission): ?>
                            <div class="submission-item">
                                <div class="submission-info">
                                    <h4><?php echo htmlspecialchars($submission['assignment_title']); ?></h4>
                                    <p class="student-name"><?php echo htmlspecialchars($submission['student_name']); ?></p>
                                    <p class="class-details">
                                        <?php echo htmlspecialchars($submission['subject_code']); ?> | 
                                        Grade <?php echo htmlspecialchars($submission['grade_level']); ?> - 
                                        Section <?php echo htmlspecialchars($submission['section']); ?>
                                    </p>
                                </div>
                                <div class="submission-meta">
                                    <div class="submission-date">
                                        Submitted: <?php echo date('M d, Y', strtotime($submission['submission_date'])); ?>
                                    </div>
                                    <a href="grade_submission.php?id=<?php echo $submission['submission_id']; ?>" 
                                       class="btn-grade">Grade</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No pending submissions to grade</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html> 