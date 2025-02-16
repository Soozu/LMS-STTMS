<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$class_id = $_GET['id'] ?? null;
if (!$class_id) {
    header('Location: sections.php');
    exit();
}

try {
    // Fetch class details with subject, teacher, and section info
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            s.subject_name,
            s.grade_level,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
            sec.section_name,
            (SELECT COUNT(*) 
             FROM class_enrollments ce 
             WHERE ce.class_id = c.id 
             AND ce.status = 'active') as enrolled_students
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        JOIN teachers t ON c.teacher_id = t.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE c.id = ?
    ");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        throw new Exception('Class not found');
    }

    // Fetch enrolled students
    $stmt = $conn->prepare("
        SELECT 
            s.id,
            s.lrn,
            COALESCE(s.first_name, '') as first_name,
            COALESCE(s.last_name, '') as last_name,
            COALESCE(s.gender, 'Not set') as gender,
            u.status as account_status,
            ce.status as enrollment_status,
            ce.enrollment_date
        FROM class_enrollments ce
        JOIN students s ON ce.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE ce.class_id = ?
        ORDER BY s.last_name ASC, s.first_name ASC
    ");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    error_log("Error in class details: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load class details: " . $e->getMessage();
    header('Location: sections.php');
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
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/class_details.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="header-content">
                <button onclick="window.location.href='classes.php'" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h2><?php echo htmlspecialchars($class['subject_name']); ?></h2>
            </div>
        </div>

        <div class="class-overview">
            <div class="info-card">
                <h3>Class Information</h3>
                <div class="info-content">
                    <div class="info-item">
                        <label>Subject</label>
                        <span><?php echo htmlspecialchars($class['subject_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Grade Level</label>
                        <span>Grade <?php echo htmlspecialchars($class['grade_level']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Section</label>
                        <span><?php echo htmlspecialchars($class['section_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Teacher</label>
                        <span><?php echo htmlspecialchars($class['teacher_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>School Year</label>
                        <span><?php echo htmlspecialchars($class['school_year']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Status</label>
                        <span class="status-badge <?php echo $class['status']; ?>">
                            <?php echo ucfirst($class['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-section">
            <h3>
                <i class="fas fa-user-graduate"></i>
                Enrolled Students (<?php echo count($students); ?>)
            </h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>LRN</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Enrollment Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['lrn']); ?></td>
                                    <td>
                                        <?php 
                                            $fullName = trim(htmlspecialchars($student['last_name']) . ', ' . htmlspecialchars($student['first_name']));
                                            echo !empty($fullName) ? $fullName : 'Not set';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            echo !empty($student['gender']) ? 
                                                ucfirst(htmlspecialchars($student['gender'])) : 
                                                'Not set'; 
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            echo !empty($student['enrollment_date']) ? 
                                                date('M d, Y', strtotime($student['enrollment_date'])) : 
                                                'Not set'; 
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo htmlspecialchars($student['enrollment_status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($student['enrollment_status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No students enrolled in this class</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html> 