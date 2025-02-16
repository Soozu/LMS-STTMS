<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacherId = $_SESSION['role_id'];
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

try {
    // Fetch teacher's classes
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            sec.grade_level,
            sec.section_name,
            s.subject_name
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE c.teacher_id = ? 
        AND c.status = 'active'
        ORDER BY sec.grade_level ASC, sec.section_name ASC
    ");
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch announcements for calendar
    $firstDay = date('Y-m-d', strtotime("$selectedYear-$selectedMonth-01"));
    $lastDay = date('Y-m-t', strtotime($firstDay));

    $stmt = $conn->prepare("
        SELECT 
            ta.*,
            c.id as class_id,
            s.subject_name,
            sec.grade_level,
            sec.section_name,
            TIME_FORMAT(ta.event_time, '%h:%i %p') as formatted_time,
            (
                SELECT COUNT(*) 
                FROM announcement_views av 
                WHERE av.announcement_id = ta.id
                AND av.announcement_type = 'teacher'
            ) as view_count
        FROM teacher_announcements ta
        JOIN classes c ON ta.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE ta.teacher_id = ?
        AND (ta.class_id = ? OR ? IS NULL)
        AND ta.event_date BETWEEN ? AND ?
        AND ta.status = 'active'
        ORDER BY ta.event_date ASC, ta.created_at DESC
    ");
    $stmt->execute([$teacherId, $selectedClass, $selectedClass, $firstDay, $lastDay]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize announcements by date
    $calendarEvents = [];
    foreach ($announcements as $announcement) {
        $date = date('Y-m-d', strtotime($announcement['event_date']));
        if (!isset($calendarEvents[$date])) {
            $calendarEvents[$date] = [];
        }
        $calendarEvents[$date][] = $announcement;
    }

} catch (Exception $e) {
    error_log("Error in announcements: " . $e->getMessage());
    $error = "An error occurred while loading the announcements.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/announcements.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
    .event {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 2px 5px;
    }

    .event-time {
        font-size: 0.8em;
        color: #666;
    }

    .day-events {
        margin-top: 5px;
    }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Announcements & Events</h2>
            <div class="header-actions">
                <div class="class-selector">
                    <select id="classSelect" onchange="filterAnnouncements(this.value)">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $selectedClass == $class['id'] ? 'selected' : ''; ?>>
                                Grade <?php echo $class['grade_level']; ?> - 
                                <?php echo $class['section_name']; ?> - 
                                <?php echo $class['subject_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn-primary" onclick="showAnnouncementModal()">
                    <i class="fas fa-plus"></i> New Announcement
                </button>
            </div>
        </div>

        <!-- Calendar Navigation -->
        <div class="calendar-nav">
            <button onclick="changeMonth(-1)" class="btn-nav">
                <i class="fas fa-chevron-left"></i>
            </button>
            <h3><?php echo date('F Y', strtotime("$selectedYear-$selectedMonth-01")); ?></h3>
            <button onclick="changeMonth(1)" class="btn-nav">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <!-- Calendar View -->
        <div class="calendar">
            <div class="calendar-header">
                <div>Sunday</div>
                <div>Monday</div>
                <div>Tuesday</div>
                <div>Wednesday</div>
                <div>Thursday</div>
                <div>Friday</div>
                <div>Saturday</div>
            </div>
            <div class="calendar-grid">
                <?php
                $firstDayOfMonth = strtotime("$selectedYear-$selectedMonth-01");
                $daysInMonth = date('t', $firstDayOfMonth);
                $firstDayOfWeek = date('w', $firstDayOfMonth);
                $currentDay = date('Y-m-d');

                // Add empty cells for days before the first of the month
                for ($i = 0; $i < $firstDayOfWeek; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }

                // Add cells for each day of the month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = date('Y-m-d', strtotime("$selectedYear-$selectedMonth-$day"));
                    $isToday = $date === $currentDay;
                    $hasEvents = isset($calendarEvents[$date]);
                    
                    echo '<div class="calendar-day' . 
                         ($isToday ? ' today' : '') . 
                         ($hasEvents ? ' has-events' : '') . '">';
                    echo '<span class="day-number">' . $day . '</span>';
                    
                    if ($hasEvents) {
                        echo '<div class="day-events">';
                        foreach ($calendarEvents[$date] as $event) {
                            echo '<div class="event" onclick="showEventDetails(' . 
                                 htmlspecialchars(json_encode($event)) . ')">';
                            echo '<span class="event-title">' . 
                                 htmlspecialchars($event['title']) . '</span>';
                            if (!is_null($event['formatted_time'])) {
                                echo '<span class="event-time">' . 
                                     htmlspecialchars($event['formatted_time']) . '</span>';
                            }
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </main>

    <!-- New Announcement Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <h3>New Announcement</h3>
            <form id="announcementForm">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="class_id">Class</label>
                    <select id="class_id" name="class_id" required>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>">
                                Grade <?php echo $class['grade_level']; ?> - 
                                <?php echo $class['section_name']; ?> - 
                                <?php echo $class['subject_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="event_date">Event Date</label>
                    <input type="date" id="event_date" name="event_date" required>
                </div>

                <div class="form-group">
                    <label for="event_time">Event Time</label>
                    <input type="time" id="event_time" name="event_time" required>
                </div>

                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" required></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="hideAnnouncementModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary">
                        Post Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAnnouncementModal() {
            document.getElementById('announcementModal').style.display = 'block';
        }

        function hideAnnouncementModal() {
            document.getElementById('announcementModal').style.display = 'none';
            document.getElementById('announcementForm').reset();
        }

        function filterAnnouncements(classId) {
            window.location.href = 'announcements.php?class_id=' + classId;
        }

        function changeMonth(delta) {
            const urlParams = new URLSearchParams(window.location.search);
            let month = parseInt(urlParams.get('month')) || <?php echo date('n'); ?>;
            let year = parseInt(urlParams.get('year')) || <?php echo date('Y'); ?>;
            
            month += delta;
            
            if (month > 12) {
                month = 1;
                year++;
            } else if (month < 1) {
                month = 12;
                year--;
            }
            
            urlParams.set('month', month);
            urlParams.set('year', year);
            window.location.search = urlParams.toString();
        }

        function showEventDetails(event) {
            const detailsHtml = `
                <div class="announcement-details">
                    <h3>${event.title}</h3>
                    <p class="event-meta">
                        Posted for: Grade ${event.grade_level} - ${event.section_name} - ${event.subject_name}<br>
                        Date: ${new Date(event.event_date).toLocaleDateString()}<br>
                        ${event.formatted_time ? `Time: ${event.formatted_time}<br>` : ''}
                        Views: ${event.view_count}
                    </p>
                    <div class="event-content">
                        ${event.content}
                    </div>
                </div>
            `;
            
            // You can create a modal or use any other method to display the details
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    ${detailsHtml}
                    <div class="modal-actions">
                        <button onclick="this.closest('.modal').remove()" class="btn-secondary">Close</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            modal.style.display = 'block';
        }

        // Form submission
        document.getElementById('announcementForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('api/post_teacher_announcement.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Failed to post announcement');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to post announcement');
            }
        });
    </script>
</body>
</html> 