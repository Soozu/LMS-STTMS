<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get student ID from session
$studentId = $_SESSION['role_id'];

try {
    // Fetch student's basic information
    $stmt = $conn->prepare("
        SELECT 
            s.*, 
            u.username as lrn,
            sec.grade_level,
            sec.section_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN sections sec ON s.section_id = sec.id
        WHERE s.id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT ce.class_id) as total_subjects,
            COALESCE(AVG(ss.score), 0) as overall_average
        FROM class_enrollments ce
        LEFT JOIN student_submissions ss ON ss.student_id = ce.student_id
        WHERE ce.student_id = ?
        AND ce.status = 'active'
    ");
    $stmt->execute([$studentId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set defaults and handle null values
    $totalSubjects = (int)$stats['total_subjects'];
    $overallAverage = number_format((float)$stats['overall_average'], 2);

    // Fetch recent grades with assignment details
    $stmt = $conn->prepare("
        SELECT 
            ss.score as grade,
            ss.submitted_at,
            a.title as assignment_title,
            s.subject_name,
            QUARTER(a.due_date) as quarter
        FROM student_submissions ss
        JOIN assignments a ON ss.assignment_id = a.id
        JOIN classes c ON a.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        WHERE ss.student_id = ?
        AND ss.status = 'graded'
        ORDER BY ss.submitted_at DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $recentGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch upcoming assignments
    $stmt = $conn->prepare("
        SELECT 
            a.id,
            a.title,
            a.description,
            a.due_date,
            s.subject_name,
            (
                SELECT COUNT(*)
                FROM student_submissions ss
                WHERE ss.assignment_id = a.id
                AND ss.student_id = ?
                AND ss.status = 'submitted'
            ) as has_submitted
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN class_enrollments ce ON c.id = ce.class_id
        WHERE ce.student_id = ?
        AND a.due_date >= CURDATE()
        AND a.status = 'active'
        AND ce.status = 'active'
        ORDER BY a.due_date ASC
        LIMIT 5
    ");
    $stmt->execute([$studentId, $studentId]);
    $upcomingAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add query to fetch enrolled classes
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            s.subject_name,
            t.first_name as teacher_fname,
            t.last_name as teacher_lname
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        LEFT JOIN teachers t ON c.teacher_id = t.id
        WHERE ce.student_id = ?
        AND ce.status = 'active'
        LIMIT 4
    ");
    $stmt->execute([$studentId]);
    $enrolledClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error fetching student data: " . $e->getMessage());
    $error = "An error occurred while fetching your data.";
    
    // Set default values in case of error
    $totalSubjects = 0;
    $overallAverage = '0.00';
    $recentGrades = [];
    $upcomingAssignments = [];
    $enrolledClasses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css?v=1.0">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="dashboard-stats">
            <div class="stat-card">
                <i class="fas fa-list"></i>
                <div class="stat-info">
                    <h3><?php echo $totalSubjects; ?></h3>
                    <p>Enrolled Subjects</p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-chart-line"></i>
                <div class="stat-info">
                    <h3><?php echo $overallAverage; ?></h3>
                    <p>Overall Average</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="assignments.php" class="quick-action-btn">
                <div class="icon-wrapper">
                    <i class="fas fa-tasks"></i>
                </div>
                <span>View Assignments</span>
            </a>
            <a href="submit_assignment.php" class="quick-action-btn">
                <div class="icon-wrapper">
                    <i class="fas fa-upload"></i>
                </div>
                <span>Submit Work</span>
            </a>
            <a href="grades.php" class="quick-action-btn">
                <div class="icon-wrapper">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span>Check Grades</span>
            </a>
            <a href="calendar.php" class="quick-action-btn">
                <div class="icon-wrapper">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <span>Calendar</span>
            </a>
            <a href="announcements.php" class="quick-action-btn">
                <div class="icon-wrapper">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <span>Announcements</span>
            </a>
            <a href="messages.php" class="quick-action-btn">
                <div class="icon-wrapper">
                    <i class="fas fa-envelope"></i>
                </div>
                <span>Messages</span>
            </a>
        </div>

        <!-- My Subjects Section -->
        <div class="section-header">
            <h3>My Subjects</h3>
            <a href="subjects.php" class="view-all">View All</a>
        </div>
        <div class="subjects-grid">
            <?php foreach ($enrolledClasses as $class): ?>
            <div class="subject-card">
                <div class="subject-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="subject-details">
                    <h4><?php echo htmlspecialchars($class['subject_name']); ?></h4>
                    <p class="teacher-name">
                        <i class="fas fa-user"></i> 
                        <?php echo htmlspecialchars($class['teacher_fname'] . ' ' . $class['teacher_lname']); ?>
                    </p>
                </div>
                <a href="subject_detail.php?id=<?php echo $class['id']; ?>" class="subject-link">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Activities Section -->
        <div class="dashboard-grid">
            <!-- Recent Grades -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Recent Grades</h3>
                   
                </div>
                <div class="grades-list">
                    <?php if (!empty($recentGrades)): ?>
                        <?php foreach ($recentGrades as $grade): ?>
                        <div class="grade-item">
                            <div class="grade-info">
                                <h4><?php echo htmlspecialchars($grade['subject_name']); ?></h4>
                                <p class="quarter">Quarter <?php echo htmlspecialchars($grade['quarter']); ?></p>
                            </div>
                            <div class="grade-value <?php echo $grade['grade'] >= 75 ? 'passing' : 'failing'; ?>">
                                <?php echo number_format($grade['grade'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <p>No grades available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Activities -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Upcoming Activities</h3>
                    <a href="calendar.php" class="view-all">View Calendar</a>
                </div>
                <div class="activities-list">
                    <?php if (!empty($upcomingAssignments)): ?>
                        <?php foreach ($upcomingAssignments as $assignment): ?>
                            <div class="activity-item <?php echo $assignment['has_submitted'] ? 'submitted' : ''; ?>">
                                <div class="activity-date">
                                    <span class="date"><?php echo date('d', strtotime($assignment['due_date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($assignment['due_date'])); ?></span>
                                </div>
                                <div class="activity-info">
                                    <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                    <p>
                                        <?php echo htmlspecialchars($assignment['subject_name']); ?>
                                        <?php if ($assignment['has_submitted']): ?>
                                            <span class="status-badge submitted">Submitted</span>
                                        <?php else: ?>
                                            <span class="status-badge pending">Pending</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <p>No upcoming activities</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html> 