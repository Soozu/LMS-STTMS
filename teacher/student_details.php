<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacherId = $_SESSION['role_id'];
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

try {
    // Verify that the teacher has access to this class and student
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            c.id as class_id,
            sub.subject_name,
            sec.section_name,
            sec.grade_level,
            ce.status as enrollment_status,
            ce.enrollment_date
        FROM students s
        JOIN class_enrollments ce ON s.id = ce.student_id
        JOIN classes c ON ce.class_id = c.id
        JOIN subjects sub ON c.subject_id = sub.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE c.teacher_id = ? 
        AND c.id = ? 
        AND s.id = ?
    ");
    $stmt->execute([$teacherId, $classId, $studentId]);
    $studentDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$studentDetails) {
        throw new Exception('Student not found or access denied');
    }

    // Fetch student's grades for this class
    $stmt = $conn->prepare("
        SELECT *
        FROM grades
        WHERE student_id = ? 
        AND class_id = ?
        ORDER BY quarter ASC
    ");
    $stmt->execute([$studentId, $classId]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch student's submissions for this class
    $stmt = $conn->prepare("
        SELECT 
            ss.*,
            a.title as assignment_title,
            a.due_date
        FROM student_submissions ss
        JOIN assignments a ON ss.assignment_id = a.id
        WHERE ss.student_id = ? 
        AND a.class_id = ?
        ORDER BY ss.submitted_at DESC
    ");
    $stmt->execute([$studentId, $classId]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch assignments
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            COALESCE(ss.score, NULL) as score,
            CASE 
                WHEN ss.id IS NOT NULL THEN 'submitted'
                WHEN a.due_date < CURRENT_TIMESTAMP THEN 'overdue'
                ELSE 'pending'
            END as submission_status,
            COALESCE(a.max_score, 100) as total_points
        FROM assignments a
        LEFT JOIN student_submissions ss ON a.id = ss.assignment_id 
            AND ss.student_id = ?
        WHERE a.class_id = ? 
        AND a.type = 'assignment'
        AND a.status = 'active'
        ORDER BY a.due_date DESC
    ");
    $stmt->execute([$studentId, $classId]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch activities
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            COALESCE(ss.score, NULL) as score,
            CASE 
                WHEN ss.id IS NOT NULL THEN 'submitted'
                WHEN a.due_date < CURRENT_TIMESTAMP THEN 'overdue'
                ELSE 'pending'
            END as submission_status,
            COALESCE(a.max_score, 100) as total_points
        FROM assignments a
        LEFT JOIN student_submissions ss ON a.id = ss.assignment_id 
            AND ss.student_id = ?
        WHERE a.class_id = ? 
        AND a.type = 'activity'
        AND a.status = 'active'
        ORDER BY a.due_date DESC
    ");
    $stmt->execute([$studentId, $classId]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error in student details: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/student_details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php else: ?>
            <div class="page-header">
                <div class="header-content">
                    <h2><i class="fas fa-user-graduate"></i> Student Details</h2>
                    <a href="students.php?class_id=<?php echo $classId; ?>" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Students List
                    </a>
                </div>
            </div>

            <div class="student-profile">
                <div class="profile-header">
                    <div class="student-info">
                        <h3><?php echo htmlspecialchars($studentDetails['first_name'] . ' ' . $studentDetails['last_name']); ?></h3>
                        <span class="lrn">LRN: <?php echo htmlspecialchars($studentDetails['lrn']); ?></span>
                    </div>
                    <div class="class-info">
                        <span class="subject"><?php echo htmlspecialchars($studentDetails['subject_name']); ?></span>
                        <span class="section">
                            Grade <?php echo htmlspecialchars($studentDetails['grade_level']); ?> - 
                            <?php echo htmlspecialchars($studentDetails['section_name']); ?>
                        </span>
                    </div>
                </div>

                <div class="profile-content">
                    <div class="info-card">
                        <h4><i class="fas fa-user"></i> Personal Information</h4>
                        <?php if (empty($studentDetails['gender']) && 
                                  empty($studentDetails['birth_date']) && 
                                  empty($studentDetails['contact_number']) && 
                                  empty($studentDetails['guardian_name']) && 
                                  empty($studentDetails['guardian_contact']) && 
                                  empty($studentDetails['address'])): ?>
                            <div class="incomplete-profile">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>This student has not completed their profile information yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Gender:</label>
                                    <span><?php echo $studentDetails['gender'] ? ucfirst($studentDetails['gender']) : 'Not set'; ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Birth Date:</label>
                                    <span><?php echo $studentDetails['birth_date'] ? date('F j, Y', strtotime($studentDetails['birth_date'])) : 'Not set'; ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Contact Number:</label>
                                    <span><?php echo $studentDetails['contact_number'] ? htmlspecialchars($studentDetails['contact_number']) : 'Not set'; ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Guardian Name:</label>
                                    <span><?php echo $studentDetails['guardian_name'] ? htmlspecialchars($studentDetails['guardian_name']) : 'Not set'; ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Guardian Contact:</label>
                                    <span><?php echo $studentDetails['guardian_contact'] ? htmlspecialchars($studentDetails['guardian_contact']) : 'Not set'; ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Address:</label>
                                    <span><?php echo $studentDetails['address'] ? htmlspecialchars($studentDetails['address']) : 'Not set'; ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="info-card">
                        <h4><i class="fas fa-chart-line"></i> Academic Performance</h4>
                        <div class="performance-tabs">
                            <button class="tab-btn active" data-tab="assignments">Assignments</button>
                            <button class="tab-btn" data-tab="activities">Activities</button>
                        </div>
                        
                        <div class="tab-content active" id="assignments">
                            <?php if (!empty($assignments)): ?>
                                <div class="performance-list">
                                    <?php foreach ($assignments as $assignment): ?>
                                        <div class="performance-item <?php echo $assignment['submission_status']; ?>">
                                            <div class="performance-header">
                                                <div class="performance-title">
                                                    <h5><?php echo htmlspecialchars($assignment['title']); ?></h5>
                                                    <span class="due-date">
                                                        <i class="fas fa-calendar"></i>
                                                        Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                                        <?php if ($assignment['submission_status'] === 'overdue'): ?>
                                                            <span class="overdue-label">Overdue</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="score">
                                                    <?php if ($assignment['submission_status'] === 'submitted'): ?>
                                                        <span class="score-value"><?php echo $assignment['score'] ?? '-'; ?></span>
                                                        <span class="total">/ <?php echo $assignment['total_points']; ?></span>
                                                    <?php else: ?>
                                                        <span class="status-badge <?php echo $assignment['submission_status']; ?>">
                                                            <i class="fas fa-<?php echo $assignment['submission_status'] === 'pending' ? 'clock' : 'exclamation-circle'; ?>"></i>
                                                            <?php echo ucfirst($assignment['submission_status']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($assignment['submission_status'] === 'submitted'): ?>
                                                <div class="performance-progress">
                                                    <div class="progress-bar" style="width: <?php echo ($assignment['score'] / $assignment['total_points'] * 100); ?>%"></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="no-data">No assignments yet</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tab-content" id="activities">
                            <?php if (!empty($activities)): ?>
                                <div class="performance-list">
                                    <?php foreach ($activities as $activity): ?>
                                        <div class="performance-item <?php echo $activity['submission_status']; ?>">
                                            <div class="performance-header">
                                                <div class="performance-title">
                                                    <h5><?php echo htmlspecialchars($activity['title']); ?></h5>
                                                    <span class="due-date">
                                                        <i class="fas fa-calendar"></i>
                                                        Due: <?php echo date('M d, Y', strtotime($activity['due_date'])); ?>
                                                        <?php if ($activity['submission_status'] === 'overdue'): ?>
                                                            <span class="overdue-label">Overdue</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="score">
                                                    <?php if ($activity['submission_status'] === 'submitted'): ?>
                                                        <span class="score-value"><?php echo $activity['score'] ?? '-'; ?></span>
                                                        <span class="total">/ <?php echo $activity['total_points']; ?></span>
                                                    <?php else: ?>
                                                        <span class="status-badge <?php echo $activity['submission_status']; ?>">
                                                            <i class="fas fa-<?php echo $activity['submission_status'] === 'pending' ? 'clock' : 'exclamation-circle'; ?>"></i>
                                                            <?php echo ucfirst($activity['submission_status']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($activity['submission_status'] === 'submitted'): ?>
                                                <div class="performance-progress">
                                                    <div class="progress-bar" style="width: <?php echo ($activity['score'] / $activity['total_points'] * 100); ?>%"></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="no-data">No activities yet</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="info-card">
                        <h4><i class="fas fa-tasks"></i> Recent Submissions</h4>
                        <?php if (!empty($submissions)): ?>
                            <div class="submissions-list">
                                <?php foreach ($submissions as $submission): ?>
                                    <div class="submission-item">
                                        <div class="submission-header">
                                            <h5><?php echo htmlspecialchars($submission['assignment_title']); ?></h5>
                                            <span class="status <?php echo strtolower($submission['status']); ?>">
                                                <i class="fas fa-<?php echo $submission['status'] === 'submitted' ? 'check-circle' : 'clock'; ?>"></i>
                                                <?php echo ucfirst($submission['status']); ?>
                                            </span>
                                        </div>
                                        <div class="submission-meta">
                                            <span>
                                                <i class="fas fa-clock"></i>
                                                Submitted: <?php echo formatDateTime($submission['submitted_at']); ?>
                                            </span>
                                            <?php if ($submission['score']): ?>
                                                <span>
                                                    <i class="fas fa-star"></i>
                                                    Score: <?php echo $submission['score']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No submissions yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Add this script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get all tab buttons and content sections
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            // Add click event listener to each tab button
            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Remove active class from all buttons and contents
                    tabBtns.forEach(b => b.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    // Add active class to clicked button
                    btn.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = btn.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html> 