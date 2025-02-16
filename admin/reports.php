<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize variables
$error = null;
$success = null;
$reportData = [];
$reportType = isset($_GET['type']) ? $_GET['type'] : 'enrollment';
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'current';

try {
    switch($reportType) {
        case 'enrollment':
            // Enrollment statistics
            $stmt = $conn->prepare("
                SELECT 
                    c.grade_level,
                    c.section,
                    COUNT(ce.id) as enrolled_count,
                    s.subject_name,
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                FROM classes c
                LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
                JOIN subjects s ON c.subject_id = s.id
                JOIN teachers t ON c.teacher_id = t.id
                GROUP BY c.id
                ORDER BY c.grade_level ASC, c.section ASC
            ");
            $stmt->execute();
            $reportData['classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Total enrollment summary
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(DISTINCT s.id) as total_students,
                    COUNT(DISTINCT ce.class_id) as total_classes,
                    COUNT(DISTINCT t.id) as total_teachers
                FROM students s
                JOIN class_enrollments ce ON s.id = ce.student_id AND ce.status = 'active'
                JOIN classes c ON ce.class_id = c.id
                JOIN teachers t ON c.teacher_id = t.id
            ");
            $stmt->execute();
            $reportData['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;

        case 'academic':
            // Academic performance statistics
            $stmt = $conn->prepare("
                SELECT 
                    c.grade_level,
                    c.section,
                    s.subject_name,
                    ROUND(AVG(g.grade), 2) as average_grade,
                    COUNT(CASE WHEN g.grade >= 75 THEN 1 END) as passing_count,
                    COUNT(CASE WHEN g.grade < 75 THEN 1 END) as failing_count
                FROM classes c
                JOIN grades g ON c.id = g.class_id
                JOIN subjects s ON c.subject_id = s.id
                GROUP BY c.id
                ORDER BY c.grade_level ASC, c.section ASC
            ");
            $stmt->execute();
            $reportData['performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch(PDOException $e) {
    error_log("Error generating report: " . $e->getMessage());
    $error = "An error occurred while generating the report.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/reports.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Reports</h2>
            <div class="report-controls">
                <select id="reportType" onchange="changeReport(this.value)">
                    <option value="enrollment" <?php echo $reportType === 'enrollment' ? 'selected' : ''; ?>>
                        Enrollment Report
                    </option>
                    <option value="academic" <?php echo $reportType === 'academic' ? 'selected' : ''; ?>>
                        Academic Performance
                    </option>
                </select>
                <button class="btn-primary" onclick="printReport()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="report-container">
            <?php if ($reportType === 'enrollment'): ?>
                <!-- Enrollment Report -->
                <div class="report-section">
                    <div class="summary-cards">
                        <div class="summary-card">
                            <i class="fas fa-user-graduate"></i>
                            <div class="summary-info">
                                <h3><?php echo $reportData['summary']['total_students']; ?></h3>
                                <p>Total Students</p>
                            </div>
                        </div>
                        <div class="summary-card">
                            <i class="fas fa-chalkboard"></i>
                            <div class="summary-info">
                                <h3><?php echo $reportData['summary']['total_classes']; ?></h3>
                                <p>Total Classes</p>
                            </div>
                        </div>
                        <div class="summary-card">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <div class="summary-info">
                                <h3><?php echo $reportData['summary']['total_teachers']; ?></h3>
                                <p>Total Teachers</p>
                            </div>
                        </div>
                    </div>

                    <div class="chart-container">
                        <canvas id="enrollmentChart"></canvas>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Grade & Section</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Enrolled Students</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['classes'] as $class): ?>
                                    <tr>
                                        <td>
                                            Grade <?php echo $class['grade_level']; ?> - 
                                            Section <?php echo $class['section']; ?>
                                        </td>
                                        <td><?php echo $class['subject_name']; ?></td>
                                        <td><?php echo $class['teacher_name']; ?></td>
                                        <td class="text-center"><?php echo $class['enrolled_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($reportType === 'academic'): ?>
                <!-- Academic Performance Report -->
                <div class="report-section">
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Grade & Section</th>
                                    <th>Subject</th>
                                    <th>Average Grade</th>
                                    <th>Passing</th>
                                    <th>Failing</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['performance'] as $performance): ?>
                                    <tr>
                                        <td>
                                            Grade <?php echo $performance['grade_level']; ?> - 
                                            Section <?php echo $performance['section']; ?>
                                        </td>
                                        <td><?php echo $performance['subject_name']; ?></td>
                                        <td class="text-center">
                                            <span class="grade-badge <?php echo $performance['average_grade'] >= 75 ? 'passing' : 'failing'; ?>">
                                                <?php echo $performance['average_grade']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center text-success"><?php echo $performance['passing_count']; ?></td>
                                        <td class="text-center text-danger"><?php echo $performance['failing_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Change report type
        function changeReport(type) {
            window.location.href = `reports.php?type=${type}`;
        }

        // Print report
        function printReport() {
            window.print();
        }

        // Initialize charts based on report type
        <?php if ($reportType === 'enrollment'): ?>
            // Enrollment chart
            new Chart(document.getElementById('enrollmentChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($class) {
                        return "Grade {$class['grade_level']} - {$class['section']}";
                    }, $reportData['classes'])); ?>,
                    datasets: [{
                        label: 'Enrolled Students',
                        data: <?php echo json_encode(array_column($reportData['classes'], 'enrolled_count')); ?>,
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
        <?php elseif ($reportType === 'academic'): ?>
            // Academic performance chart
            new Chart(document.getElementById('performanceChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($perf) {
                        return "Grade {$perf['grade_level']} - {$perf['section']}";
                    }, $reportData['performance'])); ?>,
                    datasets: [{
                        label: 'Average Grade',
                        data: <?php echo json_encode(array_column($reportData['performance'], 'average_grade')); ?>,
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
        <?php endif; ?>
    </script>
</body>
</html> 