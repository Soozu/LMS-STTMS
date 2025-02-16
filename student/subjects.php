<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$studentId = $_SESSION['role_id'];

try {
    // Fetch student's enrolled subjects with additional details
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            s.subject_name,
            s.description,
            t.first_name as teacher_fname,
            t.last_name as teacher_lname,
            sec.section_name,
            sec.grade_level,
            (
                SELECT COUNT(*) 
                FROM assignments a 
                WHERE a.class_id = c.id 
                AND a.status = 'active'
            ) as total_assignments,
            (
                SELECT COUNT(*) 
                FROM student_submissions ss
                JOIN assignments a ON ss.assignment_id = a.id
                WHERE a.class_id = c.id 
                AND ss.student_id = ce.student_id
                AND ss.status = 'submitted'
            ) as completed_assignments
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        LEFT JOIN teachers t ON c.teacher_id = t.id
        WHERE ce.student_id = ?
        AND ce.status = 'active'
        ORDER BY sec.grade_level ASC, s.subject_name ASC
    ");
    
    $stmt->execute([$studentId]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group subjects by grade level
    $groupedSubjects = [];
    foreach ($subjects as $subject) {
        $gradeLevel = $subject['grade_level'];
        if (!isset($groupedSubjects[$gradeLevel])) {
            $groupedSubjects[$gradeLevel] = [];
        }
        $groupedSubjects[$gradeLevel][] = $subject;
    }
    ksort($groupedSubjects); // Sort by grade level

} catch(PDOException $e) {
    error_log("Error in subjects page: " . $e->getMessage());
    $error = "An error occurred while loading your subjects.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subjects - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/subjects.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>My Subjects</h2>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($subjects)): ?>
            <div class="no-subjects">
                <i class="fas fa-books"></i>
                <p>You are not enrolled in any subjects yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($groupedSubjects as $gradeLevel => $gradeSubjects): ?>
                <div class="grade-section">
                    <div class="grade-header">
                        <h3><i class="fas fa-graduation-cap"></i> Grade <?php echo $gradeLevel; ?></h3>
                    </div>
                    <div class="subjects-grid">
                        <?php foreach ($gradeSubjects as $subject): ?>
                            <div class="subject-card">
                                <div class="subject-header">
                                    <div class="subject-icon">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="subject-info">
                                        <h3><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                                        <p class="teacher-name">
                                            <i class="fas fa-user-tie"></i>
                                            <?php echo htmlspecialchars($subject['teacher_fname'] . ' ' . $subject['teacher_lname']); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="subject-details">
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span>Section <?php echo htmlspecialchars($subject['section_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-tasks"></i>
                                        <span><?php echo $subject['total_assignments']; ?> Assignments</span>
                                    </div>
                                </div>

                                <?php if ($subject['total_assignments'] > 0): ?>
                                    <div class="progress-section">
                                        <small>Progress</small>
                                        <div class="progress-bar">
                                            <?php 
                                            $progress = ($subject['total_assignments'] > 0) 
                                                ? ($subject['completed_assignments'] / $subject['total_assignments']) * 100 
                                                : 0;
                                            ?>
                                            <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <a href="subject_detail.php?id=<?php echo $subject['id']; ?>" class="view-subject">
                                    <i class="fas fa-arrow-right"></i> View Subject
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <script src="js/sidebar.js"></script>
</body>
</html> 