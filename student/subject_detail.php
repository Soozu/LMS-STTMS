<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get student ID and class ID
$studentId = $_SESSION['role_id'];
$classId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$classId) {
    header('Location: subjects.php');
    exit();
}

try {
    // Fetch class and subject details
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            s.subject_name,
            s.description,
            t.first_name as teacher_fname,
            t.last_name as teacher_lname,
            t.email as teacher_email,
            sec.section_name,
            (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id AND status = 'active') as total_students
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        LEFT JOIN teachers t ON c.teacher_id = t.id
        JOIN sections sec ON c.section_id = sec.id
        JOIN class_enrollments ce ON c.id = ce.class_id
        WHERE c.id = ? AND ce.student_id = ? AND ce.status = 'active'
    ");
    $stmt->execute([$classId, $studentId]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subject) {
        $_SESSION['error_message'] = "You don't have access to this subject or it doesn't exist.";
        header('Location: subjects.php');
        exit();
    }

    // Fetch class schedule
    $stmt = $conn->prepare("
        SELECT *
        FROM class_schedules
        WHERE class_id = ?
        AND status = 'active'
        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday')
    ");
    $stmt->execute([$classId]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent assignments with better error handling
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            (SELECT COUNT(*) FROM student_submissions ss 
             WHERE ss.assignment_id = a.id 
             AND ss.student_id = ? 
             AND ss.status = 'submitted') as has_submitted
        FROM assignments a
        WHERE a.class_id = ?
        AND a.status = 'active'
        AND EXISTS (
            SELECT 1 FROM class_enrollments ce 
            WHERE ce.class_id = a.class_id 
            AND ce.student_id = ? 
            AND ce.status = 'active'
        )
        ORDER BY a.due_date DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId, $classId, $studentId]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch student's grades with better error handling
    $stmt = $conn->prepare("
        SELECT 
            ss.score,
            ss.submitted_at,
            ss.status,
            a.title as assignment_title,
            a.due_date
        FROM student_submissions ss
        JOIN assignments a ON ss.assignment_id = a.id
        JOIN class_enrollments ce ON a.class_id = ce.class_id
        WHERE a.class_id = ?
        AND ss.student_id = ?
        AND ce.status = 'active'
        AND ss.status = 'graded'
        ORDER BY ss.submitted_at DESC
        LIMIT 5
    ");
    $stmt->execute([$classId, $studentId]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    error_log("Error in subject detail: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while fetching subject details.";
    header('Location: subjects.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subject['subject_name'] ?? 'Subject'); ?> - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/subject_detail.css?v=1.0">
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
        <?php else: ?>
            <div class="subject-header">
                <div class="back-button">
                    <a href="subjects.php"><i class="fas fa-arrow-left"></i> Back to Subjects</a>
                </div>
                <h2><?php echo htmlspecialchars($subject['subject_name']); ?></h2>
                <p class="section-info">
                    Section <?php echo htmlspecialchars($subject['section_name']); ?> | 
                    School Year <?php echo htmlspecialchars($subject['school_year']); ?>
                </p>
            </div>

            <div class="content-grid">
                <!-- Teacher Information -->
                <div class="info-card teacher-info">
                    <h3><i class="fas fa-user-tie"></i> Teacher Information</h3>
                    <div class="teacher-details">
                        <p class="teacher-name">
                            <?php echo htmlspecialchars($subject['teacher_fname'] . ' ' . $subject['teacher_lname']); ?>
                        </p>
                        <p class="teacher-email">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($subject['teacher_email']); ?>
                        </p>
                    </div>
                </div>

                <!-- Class Schedule -->
                <div class="info-card schedule-info">
                    <h3><i class="fas fa-calendar"></i> Class Schedule</h3>
                    <div class="schedule-list">
                        <?php if (!empty($schedules)): ?>
                            <?php foreach ($schedules as $schedule): ?>
                                <div class="schedule-item">
                                    <span class="day"><?php echo htmlspecialchars($schedule['day_of_week']); ?></span>
                                    <span class="time">
                                        <?php 
                                        echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . 
                                             date('h:i A', strtotime($schedule['end_time']));
                                        ?>
                                    </span>
                                    <span class="room">Room <?php echo htmlspecialchars($schedule['room_number']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-schedule">No schedule available</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Assignments -->
                <div class="info-card assignments-info">
                    <h3><i class="fas fa-tasks"></i> Recent Assignments</h3>
                    <div class="assignments-list">
                        <?php if (!empty($assignments)): ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <div class="assignment-item <?php echo $assignment['has_submitted'] ? 'submitted' : ''; ?>">
                                    <div class="assignment-status">
                                        <?php if ($assignment['has_submitted']): ?>
                                            <i class="fas fa-check-circle"></i>
                                        <?php else: ?>
                                            <i class="fas fa-clock"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="assignment-details">
                                        <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                        <p class="due-date">
                                            Due: <?php echo date('M d, Y h:i A', strtotime($assignment['due_date'])); ?>
                                        </p>
                                    </div>
                                    <a href="view_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn-view">
                                        View
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-assignments">No assignments yet</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Grades -->
                <div class="info-card grades-info">
                    <h3><i class="fas fa-chart-line"></i> Recent Grades</h3>
                    <div class="grades-list">
                        <?php if (!empty($grades)): ?>
                            <?php foreach ($grades as $grade): ?>
                                <div class="grade-item">
                                    <div class="grade-details">
                                        <h4><?php echo htmlspecialchars($grade['assignment_title']); ?></h4>
                                        <p class="submission-date">
                                            Submitted: <?php echo date('M d, Y', strtotime($grade['submitted_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="grade-score <?php echo $grade['score'] >= 75 ? 'passing' : 'failing'; ?>">
                                        <?php echo number_format($grade['score'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-grades">No grades available yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Subject Description -->
            <div class="subject-description">
                <h3><i class="fas fa-info-circle"></i> Subject Description</h3>
                <p><?php echo htmlspecialchars($subject['description']); ?></p>
            </div>
        <?php endif; ?>
    </main>
</body>
</html> 