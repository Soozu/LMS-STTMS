<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get teacher ID from role_id in session
$teacherId = $_SESSION['role_id'];
$selectedClass = isset($_GET['class_id']) ? $_GET['class_id'] : null;

try {
    // Fetch teacher's information
    $stmt = $conn->prepare("
        SELECT t.*, u.username, u.status
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND u.status = 'active'
    ");
    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        throw new Exception('Teacher not found or inactive');
    }

    // Fetch teacher's classes for dropdown
    $stmt = $conn->prepare("
        SELECT 
            c.id as class_id,
            s.subject_name,
            sec.section_name,
            sec.grade_level
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE c.teacher_id = ? 
        AND c.status = 'active'
        ORDER BY sec.grade_level ASC, sec.section_name ASC, s.subject_name ASC
    ");
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If class is selected, fetch students
    if ($selectedClass) {
        $stmt = $conn->prepare("
            SELECT 
                s.id as student_id,
                s.first_name,
                s.last_name,
                s.lrn,
                ce.status as enrollment_status,
                (SELECT COUNT(*) 
                 FROM class_enrollments ce2 
                 WHERE ce2.class_id = ? 
                 AND ce2.status = 'active') as total_students
            FROM students s
            JOIN class_enrollments ce ON s.id = ce.student_id
            WHERE ce.class_id = ?
            ORDER BY s.last_name ASC, s.first_name ASC
        ");
        $stmt->execute([$selectedClass, $selectedClass]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get class details
        $stmt = $conn->prepare("
            SELECT 
                c.id as class_id,
                s.subject_name,
                sec.section_name,
                sec.grade_level
            FROM classes c
            JOIN subjects s ON c.subject_id = s.id
            JOIN sections sec ON c.section_id = sec.id
            WHERE c.id = ?
        ");
        $stmt->execute([$selectedClass]);
        $classDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Add this after fetching the classes
    if (!$selectedClass && !empty($classes)) {
        // Automatically select the first class
        $selectedClass = $classes[0]['class_id'];
        
        // Fetch students for the first class
        $stmt = $conn->prepare("
            SELECT 
                s.id as student_id,
                s.first_name,
                s.last_name,
                s.lrn,
                ce.status as enrollment_status,
                (SELECT COUNT(*) 
                 FROM class_enrollments ce2 
                 WHERE ce2.class_id = ? 
                 AND ce2.status = 'active') as total_students
            FROM students s
            JOIN class_enrollments ce ON s.id = ce.student_id
            WHERE ce.class_id = ?
            ORDER BY s.last_name ASC, s.first_name ASC
        ");
        $stmt->execute([$selectedClass, $selectedClass]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get class details for the first class
        $stmt = $conn->prepare("
            SELECT 
                c.id as class_id,
                s.subject_name,
                sec.section_name,
                sec.grade_level
            FROM classes c
            JOIN subjects s ON c.subject_id = s.id
            JOIN sections sec ON c.section_id = sec.id
            WHERE c.id = ?
        ");
        $stmt->execute([$selectedClass]);
        $classDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch(PDOException $e) {
    error_log("Error fetching students data: " . $e->getMessage());
    $error = "An error occurred while fetching the data.";
} catch(Exception $e) {
    error_log("Error: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/students.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header animate__animated animate__fadeIn">
            <h2>My Students</h2>
            <div class="class-selector">
                <select id="classSelect" onchange="window.location.href='?class_id=' + this.value">
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>" 
                                <?php echo $selectedClass == $class['class_id'] ? 'selected' : ''; ?>>
                            Grade <?php echo $class['grade_level']; ?> - 
                            <?php echo $class['section_name']; ?> - 
                            <?php echo $class['subject_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="select-icon">
                    <i class="fas fa-filter"></i>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error animate__animated animate__fadeIn"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($selectedClass && isset($classDetails)): ?>
            <div class="section-header animate__animated animate__fadeIn">
                <h3>
                    <i class="fas fa-users"></i>
                    Grade <?php echo htmlspecialchars($classDetails['grade_level']); ?> - 
                    <?php echo htmlspecialchars($classDetails['section_name']); ?> - 
                    <?php echo htmlspecialchars($classDetails['subject_name']); ?>
                </h3>
            </div>

            <div class="table-container animate__animated animate__fadeIn">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>LRN</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $index => $student): ?>
                                <tr style="animation-delay: <?php echo $index * 0.1; ?>s">
                                    <td>
                                        <div class="lrn"><?php echo htmlspecialchars($student['lrn']); ?></div>
                                    </td>
                                    <td>
                                        <div class="student-name">
                                            <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($student['enrollment_status']); ?>">
                                            <?php echo ucfirst($student['enrollment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="student_details.php?class_id=<?php echo $selectedClass; ?>&student_id=<?php echo $student['student_id']; ?>" 
                                               class="btn-view">
                                                <i class="fas fa-eye"></i>
                                                View Details
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">
                                    <div class="no-data">
                                        <i class="fas fa-users-slash fa-3x"></i>
                                        <p>No students enrolled in this class</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data animate__animated animate__fadeIn">
                <i class="fas fa-chalkboard-teacher fa-3x"></i>
                <p>Please select a class to view its students</p>
            </div>
        <?php endif; ?>
    </main>

    <script>
    // Add smooth scrolling to the table
    document.addEventListener('DOMContentLoaded', function() {
        const tableContainer = document.querySelector('.table-container');
        if (tableContainer) {
            tableContainer.style.opacity = '0';
            setTimeout(() => {
                tableContainer.style.opacity = '1';
                tableContainer.style.transition = 'opacity 0.3s ease-in-out';
            }, 100);
        }
    });
    </script>
</body>
</html> 