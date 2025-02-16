<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$studentId = $_SESSION['role_id'];
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

try {
    // Fetch assignments with deadlines
    $stmt = $conn->prepare("
        SELECT 
            'assignment' as event_type,
            a.id,
            a.title,
            a.due_date as event_date,
            s.subject_name,
            CASE 
                WHEN ss.id IS NOT NULL THEN 'submitted'
                WHEN a.due_date < CURRENT_TIMESTAMP THEN 'overdue'
                ELSE 'pending'
            END as status
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN class_enrollments ce ON c.id = ce.class_id
        JOIN subjects s ON c.subject_id = s.id
        LEFT JOIN student_submissions ss ON a.id = ss.assignment_id AND ss.student_id = ?
        WHERE ce.student_id = ? 
        AND ce.status = 'active'
        AND MONTH(a.due_date) = ?
        AND YEAR(a.due_date) = ?

        UNION ALL

        SELECT 
            'announcement' as event_type,
            ta.id,
            ta.title,
            ta.event_date,
            s.subject_name,
            'announcement' as status
        FROM teacher_announcements ta
        JOIN classes c ON ta.class_id = c.id
        JOIN class_enrollments ce ON c.id = ce.class_id
        JOIN subjects s ON c.subject_id = s.id
        WHERE ce.student_id = ?
        AND ce.status = 'active'
        AND MONTH(ta.event_date) = ?
        AND YEAR(ta.event_date) = ?
        AND ta.status = 'active'

        ORDER BY event_date ASC
    ");
    $stmt->execute([$studentId, $studentId, $month, $year, $studentId, $month, $year]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group events by date
    $calendar = [];
    foreach ($events as $event) {
        $date = date('Y-m-d', strtotime($event['event_date']));
        if (!isset($calendar[$date])) {
            $calendar[$date] = [];
        }
        $calendar[$date][] = $event;
    }

} catch (Exception $e) {
    error_log("Error fetching calendar data: " . $e->getMessage());
    $error = "An error occurred while loading the calendar.";
}

// Get month details
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$startingDay = date('w', $firstDay);
$monthName = date('F Y', $firstDay);
$prevMonth = date('Y-m', strtotime('-1 month', $firstDay));
$nextMonth = date('Y-m', strtotime('+1 month', $firstDay));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/calendar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Calendar</h2>
            <div class="month-navigation">
                <a href="?month=<?php echo date('m', strtotime($prevMonth)); ?>&year=<?php echo date('Y', strtotime($prevMonth)); ?>" class="btn-nav">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <h3><?php echo $monthName; ?></h3>
                <a href="?month=<?php echo date('m', strtotime($nextMonth)); ?>&year=<?php echo date('Y', strtotime($nextMonth)); ?>" class="btn-nav">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>

        <div class="calendar-container">
            <div class="calendar-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #4caf50;"></div>
                    <span>Submitted</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #f44336;"></div>
                    <span>Overdue</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ff9800;"></div>
                    <span>Pending</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #2196f3;"></div>
                    <span>Announcement</span>
                </div>
            </div>
            <div class="calendar-grid">
                <div class="weekdays">
                    <div>Sun</div>
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>
                </div>
                <div class="days">
                    <?php
                    // Add empty cells for days before start of month
                    for ($i = 0; $i < $startingDay; $i++) {
                        echo '<div class="day empty"></div>';
                    }

                    // Add days of the month
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $hasEvents = isset($calendar[$date]);
                        $isToday = $date === date('Y-m-d');
                        
                        echo '<div class="day' . ($isToday ? ' today' : '') . ($hasEvents ? ' has-events' : '') . '">';
                        echo '<span class="date-number">' . $day . '</span>';
                        
                        if ($hasEvents) {
                            echo '<div class="events">';
                            foreach ($calendar[$date] as $event) {
                                echo '<div class="event ' . $event['event_type'] . ' ' . $event['status'] . '">';
                                echo '<span class="time">' . date('h:i A', strtotime($event['event_date'])) . '</span>';
                                echo '<span class="title" title="' . htmlspecialchars($event['title']) . '">';
                                echo htmlspecialchars($event['title']);
                                echo '</span>';
                                echo '<span class="subject">' . htmlspecialchars($event['subject_name']) . '</span>';
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                        
                        echo '</div>';
                    }

                    // Add empty cells for days after end of month
                    $endingDay = ($startingDay + $daysInMonth) % 7;
                    if ($endingDay > 0) {
                        for ($i = 0; $i < (7 - $endingDay); $i++) {
                            echo '<div class="day empty"></div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </main>

    <style>
    .event.announcement {
        background-color: #e3f2fd;
        border-left: 3px solid #2196f3;
    }

    .event.assignment.submitted {
        background-color: #e8f5e9;
        border-left: 3px solid #4caf50;
    }

    .event.assignment.overdue {
        background-color: #ffebee;
        border-left: 3px solid #f44336;
    }

    .event.assignment.pending {
        background-color: #fff3e0;
        border-left: 3px solid #ff9800;
    }
    </style>
</body>
</html> 