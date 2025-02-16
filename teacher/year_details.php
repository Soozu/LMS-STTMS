<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacherId = $_SESSION['role_id'];
$schoolYear = $_GET['year'] ?? '';

if (!$schoolYear) {
    header('Location: archive_management.php');
    exit();
}

try {
    // Fetch classes with basic info
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            s.subject_name,
            sec.section_name
        FROM classes c
        LEFT JOIN subjects s ON c.subject_id = s.id
        LEFT JOIN sections sec ON c.section_id = sec.id
        WHERE c.teacher_id = ? 
        AND c.school_year = ?
    ");
    $stmt->execute([$teacherId, $schoolYear]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each class, fetch students and assignments
    foreach ($classes as &$class) {
        // Fetch students
        $stmt = $conn->prepare("
            SELECT 
                s.id,
                s.lrn as student_number,
                s.first_name,
                s.last_name
            FROM students s
            JOIN class_enrollments ce ON s.id = ce.student_id
            WHERE ce.class_id = ?
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute([$class['id']]);
        $class['students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch assignments and activities
        $stmt = $conn->prepare("
            SELECT 
                id,
                title,
                type,
                due_date,
                max_score,
                status
            FROM assignments
            WHERE class_id = ?
            ORDER BY due_date DESC
        ");
        $stmt->execute([$class['id']]);
        $class['assignments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching data: " . $e->getMessage();
    header('Location: archive_management.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Year Details - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/archive_management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="archive-container">
            <div class="page-header">
                <div class="header-content">
                    <h2>
                        <a href="archive_management.php" class="back-link">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        Academic Year <?php echo htmlspecialchars($schoolYear); ?>
                    </h2>
                </div>
            </div>

            <?php foreach ($classes as $class): ?>
                <div class="class-details-card">
                    <div class="class-header">
                        <div class="class-title">
                            <h3><?php echo htmlspecialchars($class['subject_name']); ?></h3>
                            <span class="section-badge">
                                <?php echo htmlspecialchars($class['section_name']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="class-content">
                        <!-- Left Column: Students List -->
                        <div class="content-column students-column">
                            <div class="section-header">
                                <h4><i class="fas fa-users"></i> Students</h4>
                            </div>
                            <?php if (!empty($class['students'])): ?>
                                <div class="table-container">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>LRN</th>
                                                <th>Name</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($class['students'] as $student): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="no-data">No students enrolled</p>
                            <?php endif; ?>
                        </div>

                        <!-- Right Column: Assignments List -->
                        <div class="content-column assignments-column">
                            <div class="section-header">
                                <h4><i class="fas fa-tasks"></i> Assignments & Activities</h4>
                            </div>
                            <?php if (!empty($class['assignments'])): ?>
                                <div class="table-container">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Due Date</th>
                                                <th>Score</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($class['assignments'] as $assignment): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $assignment['type']; ?>">
                                                            <?php echo ucfirst($assignment['type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></td>
                                                    <td><?php echo $assignment['max_score']; ?></td>
                                                    <td>
                                                        <span class="status-badge <?php echo $assignment['status']; ?>">
                                                            <?php echo ucfirst($assignment['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="no-data">No assignments or activities</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html> 