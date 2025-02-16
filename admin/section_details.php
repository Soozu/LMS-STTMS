<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$section_id = $_GET['id'] ?? null;
if (!$section_id) {
    header('Location: sections.php');
    exit();
}

$section = null;
$adviser = null;
$subject_teachers = [];
$students = [];
$error = null;

try {
    // Fetch section details
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            CONCAT(t.first_name, ' ', t.last_name) as adviser_name,
            (SELECT COUNT(*) 
             FROM students st 
             JOIN users u ON st.user_id = u.id
             WHERE st.section_id = s.id 
             AND u.status = 'active') as total_students,
            (SELECT COUNT(*) 
             FROM class_enrollments ce
             JOIN classes c ON ce.class_id = c.id
             WHERE c.section_id = s.id
             AND ce.status = 'active') as total_enrollments
        FROM sections s
        LEFT JOIN section_teachers st ON s.id = st.section_id AND st.is_adviser = 1
        LEFT JOIN teachers t ON st.teacher_id = t.id
        WHERE s.id = ?
    ");
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$section) {
        throw new Exception('Section not found');
    }

    // Update the students query with corrected enrollment count
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            u.status as account_status,
            u.username,
            (SELECT COUNT(*) FROM class_enrollments ce
             JOIN classes c ON ce.class_id = c.id 
             WHERE ce.student_id = s.id 
             AND ce.status = 'active'
             AND c.section_id = ?) as enrolled_classes,
            (SELECT AVG(g.grade) FROM grades g
             JOIN class_enrollments ce ON g.class_id = ce.class_id
             JOIN classes c ON ce.class_id = c.id
             WHERE ce.student_id = s.id 
             AND c.section_id = ?) as average_grade
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.section_id = ? 
        AND u.status = 'active'
        ORDER BY s.last_name ASC, s.first_name ASC
    ");
    $stmt->execute([$section_id, $section_id, $section_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get teachers assigned to this section
    $stmt = $conn->prepare("
        SELECT t.*, st.is_adviser
        FROM teachers t
        JOIN section_teachers st ON t.id = st.teacher_id
        WHERE st.section_id = ? AND st.status = 'active'
    ");
    $stmt->execute([$section_id]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Separate adviser and subject teachers
    foreach ($teachers as $teacher) {
        if ($teacher['is_adviser']) {
            $adviser = $teacher;
        } else {
            $subject_teachers[] = $teacher;
        }
    }

} catch(PDOException $e) {
    error_log("Database error in section details: " . $e->getMessage());
    error_log("SQL State: " . $e->errorInfo[0]);
    error_log("Error Code: " . $e->errorInfo[1]);
    error_log("Error Message: " . $e->errorInfo[2]);
    $error = "An error occurred while loading the section details. Error: " . $e->getMessage();
} catch(Exception $e) {
    error_log("General error in section details: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section Details - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/section_details.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php else: ?>
            <div class="page-header">
                <div class="header-content">
                    <h2><?php echo htmlspecialchars($section['section_name']); ?></h2>
                </div>
                <a href="sections.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Sections
                </a>
            </div>

            <div class="section-overview">
                <div class="info-card">
                    <h3>Section Information</h3>
                    <div class="info-content">
                        <div class="info-item">
                            <label>Grade Level:</label>
                            <span>Grade <?php echo htmlspecialchars($section['grade_level']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Section Name:</label>
                            <span><?php echo htmlspecialchars($section['section_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Schedule:</label>
                            <span>
                                <?php 
                                    echo date('h:i A', strtotime($section['time_start'])) . ' - ' . 
                                         date('h:i A', strtotime($section['time_end'])); 
                                ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Status:</label>
                            <span class="status-badge <?php echo $section['status']; ?>">
                                <?php echo ucfirst($section['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="info-card">
                    <i class="fas fa-users"></i>
                    <div class="info-content">
                        <h3>Students</h3>
                        <p><?php echo $section['total_students']; ?> enrolled</p>
                    </div>
                </div>
                <div class="info-card">
                    <i class="fas fa-user-tie"></i>
                    <div class="info-content">
                        <h3>Adviser</h3>
                        <p><?php echo $adviser ? htmlspecialchars($adviser['first_name'] . ' ' . $adviser['last_name']) : 'Not assigned'; ?></p>
                    </div>
                </div>
            </div>

            <!-- Students List -->
            <div class="content-section">
                <div class="section-header">
                    <h3>Students (<?php echo count($students); ?>)</h3>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>LRN</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Contact</th>
                                <th>Guardian</th>
                                <th>Classes</th>
                                <th>Average Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($students)): ?>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['lrn']); ?></td>
                                        <td>
                                            <div class="student-info">
                                                <span class="student-name">
                                                    <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td><?php echo ucfirst(htmlspecialchars($student['gender'])); ?></td>
                                        <td><?php echo htmlspecialchars($student['contact_number']); ?></td>
                                        <td>
                                            <div class="guardian-info">
                                                <div><?php echo htmlspecialchars($student['guardian_name']); ?></div>
                                                <div class="guardian-contact"><?php echo htmlspecialchars($student['guardian_contact']); ?></div>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo $student['enrolled_classes']; ?></td>
                                        <td class="text-center">
                                            <?php if ($student['average_grade']): ?>
                                                <span class="grade-badge <?php echo $student['average_grade'] >= 75 ? 'passing' : 'failing'; ?>">
                                                    <?php echo number_format($student['average_grade'], 1); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="no-grade">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $student['account_status']; ?>">
                                                <?php echo ucfirst($student['account_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No students enrolled in this section</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html> 