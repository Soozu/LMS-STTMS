<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get and validate student ID
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
error_log("Processing student ID: " . $student_id);

if ($student_id <= 0) {
    $_SESSION['error'] = "Invalid student ID provided";
    header('Location: students.php');
    exit();
}

try {
    // Fetch student details with user status
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            u.username,
            u.status as account_status,
            u.created_at,
            sec.section_name,
            (SELECT COUNT(*) FROM class_enrollments 
             WHERE student_id = s.id AND status = 'active') as enrolled_classes
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.id = ?
        LIMIT 1
    ");
    
    error_log("Executing query for student ID: " . $student_id);
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        error_log("No student found with ID: " . $student_id);
        $_SESSION['error'] = "Student not found";
        header('Location: students.php');
        exit();
    }

    error_log("Student found: " . print_r($student, true));

    // Fetch enrolled classes with simpler query first
    $stmt = $conn->prepare("
        SELECT 
            ce.class_id,
            ce.status as enrollment_status,
            c.subject_id,
            c.teacher_id,
            c.time_start,
            c.time_end,
            s.subject_name,
            t.first_name as teacher_first_name,
            t.last_name as teacher_last_name
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN teachers t ON c.teacher_id = t.id
        WHERE ce.student_id = ?
        ORDER BY s.subject_name ASC
    ");
    
    error_log("Executing classes query for student ID: " . $student_id);
    $stmt->execute([$student_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Classes found: " . count($classes));

} catch(PDOException $e) {
    error_log("Database error in student_details.php: " . $e->getMessage());
    error_log("SQL State: " . $e->errorInfo[0]);
    error_log("Error Code: " . $e->errorInfo[1]);
    error_log("Error Message: " . $e->errorInfo[2]);
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: students.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/student_details.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="header-content">
                <button onclick="window.location.href='students.php'" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div>
                    <h2><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                    <span class="lrn"><?php echo htmlspecialchars($student['lrn']); ?></span>
                </div>
            </div>
            <button class="btn-primary" onclick="editStudent(<?php echo $student['id']; ?>)">
                <i class="fas fa-edit"></i>
                <span>Edit Student</span>
            </button>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <div class="student-overview">
            <div class="info-card">
                <h3>Student Information</h3>
                <div class="info-content">
                    <div class="info-item">
                        <label>LRN:</label>
                        <span><?php echo htmlspecialchars($student['lrn'] ?? ''); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Name:</label>
                        <span><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Gender:</label>
                        <span><?php echo !empty($student['gender']) ? ucfirst(htmlspecialchars($student['gender'])) : 'Not specified'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Birthdate:</label>
                        <span>
                            <?php 
                                if (!empty($student['birth_date'])) {
                                    echo date('F d, Y', strtotime($student['birth_date']));
                                } else {
                                    echo 'Not specified';
                                }
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Address:</label>
                        <span><?php echo !empty($student['address']) ? htmlspecialchars($student['address']) : 'Not specified'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Contact:</label>
                        <span><?php echo !empty($student['contact_number']) ? htmlspecialchars($student['contact_number']) : 'Not specified'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Guardian:</label>
                        <span><?php echo !empty($student['guardian_name']) ? htmlspecialchars($student['guardian_name']) : 'Not specified'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Guardian Contact:</label>
                        <span><?php echo !empty($student['guardian_contact']) ? htmlspecialchars($student['guardian_contact']) : 'Not specified'; ?></span>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <h3>Academic Information</h3>
                <div class="info-content">
                    <div class="info-item">
                        <label>Grade Level:</label>
                        <span>Grade <?php echo htmlspecialchars($student['grade_level'] ?? 'Not assigned'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Section:</label>
                        <span><?php echo !empty($student['section_name']) ? htmlspecialchars($student['section_name']) : 'Not assigned'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Enrolled Classes:</label>
                        <span><?php echo $student['enrolled_classes'] ?? '0'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Status:</label>
                        <span class="status-badge <?php echo strtolower($student['account_status'] ?? 'inactive'); ?>">
                            <?php echo ucfirst($student['account_status'] ?? 'Inactive'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrolled Classes -->
        <div class="content-section">
            <h3>Enrolled Classes</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Schedule</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($classes)): ?>
                            <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($class['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($class['teacher_first_name'] . ' ' . $class['teacher_last_name']); ?></td>
                                    <td>
                                        <?php 
                                            if (!empty($class['time_start']) && !empty($class['time_end'])) {
                                                echo date('h:i A', strtotime($class['time_start'])) . ' - ' . 
                                                     date('h:i A', strtotime($class['time_end']));
                                            } else {
                                                echo 'Schedule not set';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $class['enrollment_status']; ?>">
                                            <?php echo ucfirst($class['enrollment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No classes enrolled</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
    function editStudent(studentId) {
        window.location.href = `students.php?edit=${studentId}`;
    }
    </script>
</body>
</html> 