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
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$currentDay = date('l'); // Current day of the week

// Initialize statistics variables
$totalClasses = 0;
$uniqueSubjects = 0;
$totalStudents = 0;
$busyDay = 'N/A';
$schedules = [];

try {
    // Fetch all schedules for the teacher with class and subject details
    $stmt = $conn->prepare("
        SELECT 
            cs.id as schedule_id,
            cs.day_of_week,
            cs.start_time,
            cs.end_time,
            cs.room_number,
            c.id as class_id,
            s.subject_name,
            sec.grade_level,
            sec.section_name,
            (
                SELECT COUNT(DISTINCT ce.student_id) 
                FROM class_enrollments ce 
                WHERE ce.class_id = c.id 
                AND ce.status = 'active'
            ) as student_count,
            (
                SELECT COUNT(DISTINCT a.id)
                FROM assignments a
                WHERE a.class_id = c.id
                AND a.status = 'active'
            ) as assignment_count
        FROM class_schedules cs
        JOIN classes c ON cs.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE c.teacher_id = ? 
        AND c.status = 'active'
        ORDER BY 
            FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
            cs.start_time ASC
    ");
    $stmt->execute([$teacherId]);
    $allSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    if (!empty($allSchedules)) {
        // Count total classes
        $totalClasses = count($allSchedules);

        // Count unique subjects
        $uniqueSubjects = count(array_unique(array_column($allSchedules, 'subject_name')));

        // Sum all students
        $totalStudents = array_sum(array_column($allSchedules, 'student_count'));

        // Organize schedules by day and find busiest day
        $classesByDay = [];
        foreach ($days as $day) {
            $schedules[$day] = array_filter($allSchedules, function($schedule) use ($day) {
                return $schedule['day_of_week'] === $day;
            });
            
            $classCount = count($schedules[$day]);
            $classesByDay[$day] = $classCount;
        }

        // Find the busiest day(s)
        $maxClasses = max($classesByDay);
        $busiestDays = array_keys(array_filter($classesByDay, function($count) use ($maxClasses) {
            return $count === $maxClasses;
        }));
        $busyDay = implode('/', $busiestDays);

        // Get additional statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT a.id) as total_assignments,
                COUNT(DISTINCT ss.id) as total_submissions
            FROM classes c
            LEFT JOIN assignments a ON c.id = a.class_id
            LEFT JOIN student_submissions ss ON a.id = ss.assignment_id
            WHERE c.teacher_id = ? 
            AND c.status = 'active'
        ");
        $stmt->execute([$teacherId]);
        $additionalStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate submission rate
        $submissionRate = $additionalStats['total_assignments'] > 0 
            ? ($additionalStats['total_submissions'] / $additionalStats['total_assignments']) * 100 
            : 0;
    }

} catch(Exception $e) {
    error_log("Error in schedule page: " . $e->getMessage());
    $error = "An error occurred while loading the schedule: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/schedule.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Class Schedule</h2>
            <button class="btn-primary" onclick="printSchedule()">
                <i class="fas fa-print"></i> Print Schedule
            </button>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']); // Clear the error message
                ?>
            </div>
        <?php endif; ?>

        <!-- Schedule Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-chalkboard"></i>
                <div class="stat-info">
                    <h3><?php echo $totalClasses; ?></h3>
                    <p>Total Classes</p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-book"></i>
                <div class="stat-info">
                    <h3><?php echo $uniqueSubjects; ?></h3>
                    <p>Unique Subjects</p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-info">
                    <h3><?php echo $totalStudents; ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-day"></i>
                <div class="stat-info">
                    <h3><?php echo $busyDay; ?></h3>
                    <p>Busiest Day</p>
                </div>
            </div>
        </div>

        <!-- Schedule Tabs -->
        <div class="schedule-container">
            <div class="schedule-nav">
                <?php foreach ($days as $day): ?>
                    <button class="day-btn <?php echo $day === $currentDay ? 'active' : ''; ?>" 
                            data-day="<?php echo $day; ?>">
                        <?php echo $day; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($days as $day): ?>
                <div id="<?php echo $day; ?>" 
                     class="schedule-day <?php echo $day === $currentDay ? 'active' : ''; ?>">
                    <?php if (!empty($schedules[$day])): ?>
                        <div class="class-list">
                            <?php foreach ($schedules[$day] as $class): ?>
                                <div class="class-card">
                                    <div class="class-time">
                                        <span class="time">
                                            <?php 
                                                echo date('h:i A', strtotime($class['start_time'])) . ' - ' . 
                                                     date('h:i A', strtotime($class['end_time']));
                                            ?>
                                        </span>
                                    </div>
                                    <div class="class-info">
                                        <h4><?php echo htmlspecialchars($class['subject_name']); ?></h4>
                                        <div class="class-details">
                                            <span class="grade-section">
                                                <i class="fas fa-users"></i>
                                                Grade <?php echo htmlspecialchars($class['grade_level']); ?> - 
                                                <?php echo htmlspecialchars($class['section_name']); ?>
                                            </span>
                                            <span class="room">
                                                <i class="fas fa-door-open"></i>
                                                Room <?php echo htmlspecialchars($class['room_number']); ?>
                                            </span>
                                            <span class="students">
                                                <i class="fas fa-user-graduate"></i>
                                                <?php echo (int)$class['student_count']; ?> Students
                                            </span>
                                            <?php if ($class['assignment_count'] > 0): ?>
                                                <span class="assignments">
                                                    <i class="fas fa-tasks"></i>
                                                    <?php echo (int)$class['assignment_count']; ?> Assignments
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="class-actions">
                                        <?php
                                        // Debug output
                                        echo "<!-- Debug: class_id = " . $class['class_id'] . " -->";
                                        ?>
                                        <a href="class_details.php?id=<?php echo $class['class_id']; ?>" 
                                           class="btn-view" title="View Class Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="assignments.php?class_id=<?php echo $class['class_id']; ?>" 
                                           class="btn-assignments" title="View Assignments">
                                            <i class="fas fa-book"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-classes">
                            <i class="fas fa-coffee"></i>
                            <p>No classes scheduled</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        // Schedule tab switching
        document.querySelectorAll('.day-btn').forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons and content
                document.querySelectorAll('.day-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.schedule-day').forEach(day => day.classList.remove('active'));
                
                // Add active class to clicked button and corresponding content
                button.classList.add('active');
                document.getElementById(button.dataset.day).classList.add('active');
            });
        });

        function printSchedule() {
            window.print();
        }
    </script>
</body>
</html> 