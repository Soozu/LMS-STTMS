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
$classId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Debug logging
error_log("Accessing class_details.php - Class ID: $classId, Teacher ID: $teacherId");

if (!$classId) {
    error_log("Invalid class ID provided");
    $_SESSION['error'] = "Invalid class ID";
    header('Location: schedule.php');
    exit();
}

try {
    // Fetch class details with related information
    $stmt = $conn->prepare("
        SELECT 
            c.id as class_id,
            s.subject_name,
            sec.section_name,
            sec.grade_level,
            COALESCE(cs.day_of_week, 'Not Set') as day_of_week,
            cs.start_time,
            cs.end_time,
            COALESCE(cs.room_number, 'Not Set') as room_number,
            (
                SELECT COUNT(DISTINCT ce.student_id) 
                FROM class_enrollments ce 
                WHERE ce.class_id = c.id 
                AND ce.status = 'active'
            ) as enrolled_students,
            (
                SELECT COUNT(DISTINCT a.id)
                FROM assignments a
                WHERE a.class_id = c.id
                AND a.status = 'active'
            ) as total_assignments,
            (
                SELECT COUNT(DISTINCT ss.id)
                FROM assignments a
                LEFT JOIN student_submissions ss ON a.id = ss.assignment_id
                WHERE a.class_id = c.id
            ) as total_submissions
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        LEFT JOIN class_schedules cs ON c.id = cs.class_id
        WHERE c.id = ? 
        AND c.teacher_id = ?
        AND c.status = 'active'
    ");

    // Debug logging
    error_log("Executing query with Class ID: $classId, Teacher ID: $teacherId");
    $stmt->execute([$classId, $teacherId]);
    $classDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$classDetails) {
        throw new Exception('Class not found or access denied');
    }

    // Fetch enrolled students
    $stmt = $conn->prepare("
        SELECT 
            s.id as student_id,
            s.first_name,
            s.last_name,
            s.lrn,
            (
                SELECT COUNT(DISTINCT ss.id)
                FROM student_submissions ss
                JOIN assignments a ON ss.assignment_id = a.id
                WHERE ss.student_id = s.id
                AND a.class_id = ?
            ) as submission_count
        FROM students s
        JOIN class_enrollments ce ON s.id = ce.student_id
        WHERE ce.class_id = ?
        AND ce.status = 'active'
        ORDER BY s.last_name ASC, s.first_name ASC
    ");
    $stmt->execute([$classId, $classId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent assignments
    $stmt = $conn->prepare("
        SELECT 
            a.id,
            a.title,
            a.due_date,
            a.created_at,
            (
                SELECT COUNT(DISTINCT ss.id)
                FROM student_submissions ss
                WHERE ss.assignment_id = a.id
            ) as submission_count
        FROM assignments a
        WHERE a.class_id = ?
        AND a.status = 'active'
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$classId]);
    $recentAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate class statistics
    $submissionRate = $classDetails['total_assignments'] > 0 
        ? ($classDetails['total_submissions'] / ($classDetails['total_assignments'] * count($students))) * 100 
        : 0;

} catch(Exception $e) {
    error_log("Error in class details: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading the class details.";
    header('Location: schedule.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Details - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/class-details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="header-content">
                <h2><?php echo htmlspecialchars($classDetails['subject_name']); ?></h2>
                <p class="class-meta">
                    Grade <?php echo htmlspecialchars($classDetails['grade_level']); ?> - 
                    <?php echo htmlspecialchars($classDetails['section_name']); ?>
                </p>
            </div>
            <div class="header-actions">
                <a href="assignments.php?class_id=<?php echo $classId; ?>" class="btn-primary">
                    <i class="fas fa-tasks"></i> View Assignments
                </a>
                <a href="grades.php?class_id=<?php echo $classId; ?>" class="btn-secondary">
                    <i class="fas fa-chart-bar"></i> View Grades
                </a>
            </div>
        </div>

        <!-- Class Overview -->
        <div class="overview-grid">
            <div class="overview-card">
                <i class="fas fa-users"></i>
                <div class="overview-info">
                    <h3><?php echo $classDetails['enrolled_students']; ?></h3>
                    <p>Students</p>
                </div>
            </div>
            <div class="overview-card">
                <i class="fas fa-tasks"></i>
                <div class="overview-info">
                    <h3><?php echo $classDetails['total_assignments']; ?></h3>
                    <p>Assignments</p>
                </div>
            </div>
            <div class="overview-card">
                <i class="fas fa-check-circle"></i>
                <div class="overview-info">
                    <h3><?php echo number_format($submissionRate, 1); ?>%</h3>
                    <p>Submission Rate</p>
                </div>
            </div>
            <div class="overview-card">
                <i class="fas fa-clock"></i>
                <div class="overview-info">
                    <h3><?php echo $classDetails['day_of_week'] ?? 'Not Set'; ?></h3>
                    <p><?php 
                        if (!empty($classDetails['start_time'])) {
                            echo date('h:i A', strtotime($classDetails['start_time']));
                        } else {
                            echo 'Time not set';
                        }
                    ?></p>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <!-- Student List -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Enrolled Students</h3>
                    <span class="count"><?php echo count($students); ?> students</span>
                </div>
                <div class="student-list">
                    <?php foreach ($students as $student): ?>
                        <div class="student-item">
                            <div class="student-info">
                                <h4><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></h4>
                                <span class="lrn"><?php echo htmlspecialchars($student['lrn']); ?></span>
                            </div>
                            <div class="student-stats">
                                <span class="submission-count">
                                    <?php echo $student['submission_count']; ?> submissions
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Assignments -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Recent Assignments</h3>
                    <a href="assignments.php?class_id=<?php echo $classId; ?>" class="view-all">View All</a>
                </div>
                <div class="assignment-list">
                    <?php if (!empty($recentAssignments)): ?>
                        <?php foreach ($recentAssignments as $assignment): ?>
                            <div class="assignment-item">
                                <div class="assignment-info">
                                    <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                    <span class="due-date">
                                        Due: <?php echo formatDateTime($assignment['due_date']); ?>
                                    </span>
                                </div>
                                <div class="assignment-stats">
                                    <span class="submission-count">
                                        <?php echo $assignment['submission_count']; ?>/<?php echo $classDetails['enrolled_students']; ?> 
                                        submissions
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <p>No assignments created yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html> 