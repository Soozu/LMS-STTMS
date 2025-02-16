<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get student ID from session
$studentId = $_SESSION['user_id'];

// Initialize variables
$schedule = [];
$currentDay = strtolower(date('l')); // Get current day of week
$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

try {
    // First fetch student information with section name
    $stmt = $conn->prepare("
        SELECT 
            s.*, 
            u.username as lrn,
            sec.section_name,
            sec.grade_level
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN sections sec ON s.section_id = sec.id
        WHERE u.id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Student not found');
    }

    // Fetch student's class schedule
    $stmt = $conn->prepare("
        SELECT 
            cs.id as schedule_id,
            cs.day_of_week,
            cs.start_time,
            cs.end_time,
            cs.room_number,
            s.subject_name,
            c.grade_level,
            c.section,
            t.first_name as teacher_fname,
            t.last_name as teacher_lname,
            t.employee_id
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN teachers t ON c.teacher_id = t.id
        JOIN class_schedules cs ON cs.class_id = c.id
        WHERE ce.student_id = ? 
        AND ce.status = 'active'
        ORDER BY FIELD(cs.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'),
        cs.start_time ASC
    ");
    $stmt->execute([$student['id']]);
    $scheduleResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize schedule by day
    foreach ($days as $day) {
        $schedule[$day] = array_filter($scheduleResults, function($class) use ($day) {
            return strtolower($class['day_of_week']) === $day;
        });
    }

} catch(PDOException $e) {
    error_log("Error fetching schedule data: " . $e->getMessage());
    $error = "An error occurred while fetching your schedule.";
} catch(Exception $e) {
    error_log("Error: " . $e->getMessage());
    $error = "An error occurred while fetching your data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
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
            <h2>My Schedule</h2>
            <p>Grade <?php echo htmlspecialchars($student['grade_level']); ?> - 
               <?php echo htmlspecialchars($student['section_name']); ?></p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="schedule-container">
            <!-- Schedule Navigation -->
            <div class="schedule-nav">
                <?php foreach ($days as $day): ?>
                    <button class="day-btn <?php echo $day === $currentDay ? 'active' : ''; ?>" data-day="<?php echo $day; ?>">
                        <?php echo ucfirst($day); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Schedule Content -->
            <?php foreach ($days as $day): ?>
                <div class="schedule-day <?php echo $day === $currentDay ? 'active' : ''; ?>" id="<?php echo $day; ?>">
                    <h3><?php echo ucfirst($day); ?></h3>
                    
                    <?php if (!empty($schedule[$day])): ?>
                        <div class="class-list">
                            <?php foreach ($schedule[$day] as $class): ?>
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
                                            <span class="teacher">
                                                <i class="fas fa-user-tie"></i>
                                                <?php echo htmlspecialchars($class['teacher_fname'] . ' ' . $class['teacher_lname']); ?>
                                            </span>
                                            <span class="room">
                                                <i class="fas fa-door-open"></i>
                                                Room <?php echo htmlspecialchars($class['room_number']); ?>
                                            </span>
                                        </div>
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
    </script>
</body>
</html> 