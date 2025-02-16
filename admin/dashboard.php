<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize variables with default values
$stats = [
    'total_students' => 0,
    'total_teachers' => 0,
    'total_classes' => 0,
    'total_subjects' => 0
];
$recentLogs = [];
$announcements = [];
$enrollmentData = [];
$gradeLevelData = [];
$subjectData = [];
$error = null;

try {
    // Get admin information
    $stmt = $conn->prepare("
        SELECT a.*, u.username
        FROM admins a
        JOIN users u ON a.user_id = u.id
        WHERE a.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        throw new Exception('Admin not found');
    }

    // Initialize statistics array with default values
    $stats = [
        'total_students' => 0,
        'total_teachers' => 0,
        'total_classes' => 0,
        'total_subjects' => 0
    ];

    // Get total active students
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM students s
        JOIN users u ON s.user_id = u.id 
        WHERE u.status = 'active'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_students'] = $result['count'];

    // Get total active teachers
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM teachers t
        JOIN users u ON t.user_id = u.id 
        WHERE u.status = 'active'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_teachers'] = $result['count'];

    // Get total active classes
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM classes 
        WHERE status = 'active'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_classes'] = $result['count'];

    // Get total active subjects
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM subjects 
        WHERE status = 'active'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_subjects'] = $result['count'];

    // Get enrollment data for chart (last 6 months)
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(ce.enrollment_date, '%Y-%m') as month,
            COUNT(*) as count
        FROM class_enrollments ce
        WHERE ce.enrollment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        AND ce.status = 'active'
        GROUP BY DATE_FORMAT(ce.enrollment_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute();
    $enrollmentData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get grade level distribution
    $stmt = $conn->prepare("
        SELECT 
            grade_level,
            COUNT(DISTINCT s.id) as count
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.status = 'active'
        GROUP BY grade_level
        ORDER BY grade_level ASC
    ");
    $stmt->execute();
    $gradeLevelData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get subject distribution
    $stmt = $conn->prepare("
        SELECT 
            s.subject_name,
            COUNT(DISTINCT c.id) as class_count
        FROM subjects s
        LEFT JOIN classes c ON s.id = c.subject_id AND c.status = 'active'
        WHERE s.status = 'active'
        GROUP BY s.id, s.subject_name
        ORDER BY class_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $subjectData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent system logs
    $stmt = $conn->prepare("
        SELECT sl.*, u.username, u.user_type
        FROM system_logs sl
        JOIN users u ON sl.user_id = u.id
        ORDER BY sl.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get active announcements
    $stmt = $conn->prepare("
        SELECT a.*, CONCAT(adm.first_name, ' ', adm.last_name) as posted_by
        FROM announcements a
        JOIN admins adm ON a.admin_id = adm.id
        WHERE a.status = 'active'
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error = "An error occurred while loading the dashboard data. Error: " . $e->getMessage();
} catch(Exception $e) {
    error_log("Error: " . $e->getMessage());
    $error = "An error occurred while loading your data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Quick Access Section -->
        <div class="quick-access">
            <h2 class="section-title">Quick Access</h2>
            <div class="quick-access-grid">
                <a href="students.php" class="quick-access-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Add User</h3>
                    <p>Create new student or teacher account</p>
                </a>
                <a href="classes.php" class="quick-access-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <h3>Create Class</h3>
                    <p>Set up a new class section</p>
                </a>
                <a href="announcements.php" class="quick-access-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <h3>Post Announcement</h3>
                    <p>Create system-wide announcement</p>
                </a>
                <a href="reports.php" class="quick-access-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Generate Reports</h3>
                    <p>View and export system reports</p>
                </a>
                <a href="subjects.php" class="quick-access-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Manage Subjects</h3>
                    <p>Add or modify subject offerings</p>
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-user-graduate"></i>
                <div class="stat-info">
                    <h3><?php echo $stats['total_students']; ?></h3>
                    <p>Students</p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-chalkboard-teacher"></i>
                <div class="stat-info">
                    <h3><?php echo $stats['total_teachers']; ?></h3>
                    <p>Teachers</p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-chalkboard"></i>
                <div class="stat-info">
                    <h3><?php echo $stats['total_classes']; ?></h3>
                    <p>Classes</p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-book"></i>
                <div class="stat-info">
                    <h3><?php echo $stats['total_subjects']; ?></h3>
                    <p>Subjects</p>
                </div>
            </div>
        </div>

        <div class="charts-grid">
            <!-- Enrollment Trend Chart -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Student Enrollment Trend</h3>
                </div>
                <div class="card-content">
                    <canvas id="enrollmentChart"></canvas>
                </div>
            </div>

            <!-- Grade Level Distribution Chart -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Grade Level Distribution</h3>
                </div>
                <div class="card-content">
                    <canvas id="gradeLevelChart"></canvas>
                </div>
            </div>

            <!-- Subject Distribution Chart -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Popular Subjects</h3>
                </div>
                <div class="card-content">
                    <canvas id="subjectChart"></canvas>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Announcements Section -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Recent Announcements</h3>
                    <a href="announcements.php" class="view-all">View All</a>
                </div>
                <div class="card-content">
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="announcement-item">
                                <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                <p><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . '...'; ?></p>
                                <div class="announcement-meta">
                                    <span>Posted by: <?php echo htmlspecialchars($announcement['posted_by']); ?></span>
                                    <span><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-data">No announcements found</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Logs Section -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Recent System Logs</h3>
                    <a href="system_logs.php" class="view-all">View All</a>
                </div>
                <div class="card-content">
                    <?php if (!empty($recentLogs)): ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="log-item">
                                <div class="log-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div class="log-details">
                                    <p class="log-action"><?php echo htmlspecialchars($log['action']); ?></p>
                                    <div class="log-meta">
                                        <span><?php echo htmlspecialchars($log['username']); ?></span>
                                        <span><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-data">No recent activities</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Prepare chart data
    const enrollmentData = <?php echo json_encode($enrollmentData); ?>;
    const gradeLevelData = <?php echo json_encode($gradeLevelData); ?>;
    const subjectData = <?php echo json_encode($subjectData); ?>;

    // Enrollment Trend Chart
    new Chart(document.getElementById('enrollmentChart'), {
        type: 'line',
        data: {
            labels: enrollmentData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('default', { month: 'short', year: 'numeric' });
            }),
            datasets: [{
                label: 'New Enrollments',
                data: enrollmentData.map(item => item.count),
                borderColor: '#8B0000',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Grade Level Distribution Chart
    new Chart(document.getElementById('gradeLevelChart'), {
        type: 'bar',
        data: {
            labels: gradeLevelData.map(item => 'Grade ' + item.grade_level),
            datasets: [{
                label: 'Number of Students',
                data: gradeLevelData.map(item => item.count),
                backgroundColor: '#8B0000'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Subject Distribution Chart
    new Chart(document.getElementById('subjectChart'), {
        type: 'doughnut',
        data: {
            labels: subjectData.map(item => item.subject_name),
            datasets: [{
                data: subjectData.map(item => item.class_count),
                backgroundColor: [
                    '#8B0000',
                    '#A52A2A',
                    '#CD5C5C',
                    '#DC143C',
                    '#FF0000'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    </script>
</body>
</html> 