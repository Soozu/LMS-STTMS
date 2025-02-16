<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get student ID from session
$studentId = $_SESSION['user_id'];

// Initialize statistics variables
$totalAssignments = 0;
$submittedCount = 0;
$pendingCount = 0;
$overdueCount = 0;
$assignments = [];

try {
    // First fetch student information
    $stmt = $conn->prepare("
        SELECT s.*, u.username as lrn
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Student not found');
    }

    // Modified query to fetch only assignments (not activities)
    $stmt = $conn->prepare("
        SELECT 
            a.id as assignment_id,
            a.title,
            a.description,
            a.due_date,
            a.status,
            a.type,
            s.subject_name,
            c.grade_level,
            c.section,
            t.first_name as teacher_fname,
            t.last_name as teacher_lname,
            CASE 
                WHEN ss.id IS NOT NULL THEN 'submitted'
                WHEN a.due_date < CURRENT_TIMESTAMP THEN 'overdue'
                ELSE 'pending'
            END as submission_status,
            ss.score,
            ss.submitted_at as submission_date,
            ss.feedback
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN teachers t ON c.teacher_id = t.id
        JOIN assignments a ON a.class_id = c.id
        LEFT JOIN student_submissions ss ON ss.assignment_id = a.id AND ss.student_id = ?
        WHERE ce.student_id = ? 
        AND ce.status = 'active'
        AND a.type = 'assignment'
        ORDER BY 
            CASE 
                WHEN ss.id IS NULL AND a.due_date >= CURRENT_TIMESTAMP THEN 1
                WHEN ss.id IS NULL AND a.due_date < CURRENT_TIMESTAMP THEN 2
                ELSE 3
            END,
            a.due_date ASC
    ");
    $stmt->execute([$student['id'], $student['id']]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update statistics for assignments only
    $totalAssignments = count($assignments);
    $submittedCount = 0;
    $pendingCount = 0;
    $overdueCount = 0;

    foreach ($assignments as $assignment) {
        switch ($assignment['submission_status']) {
            case 'submitted':
                $submittedCount++;
                break;
            case 'pending':
                $pendingCount++;
                break;
            case 'overdue':
                $overdueCount++;
                break;
        }
    }

} catch(PDOException $e) {
    error_log("Error fetching assignments data: " . $e->getMessage());
    $error = "An error occurred while fetching your assignments.";
} catch(Exception $e) {
    error_log("Error: " . $e->getMessage());
    $error = "An error occurred while fetching your data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/assignments.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Assignments</h2>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Assignment Statistics -->
        <div class="assignment-stats">
            <div class="stat-card">
                <i class="fas fa-tasks"></i>
                <div class="stat-info">
                    <h3><?php echo $totalAssignments; ?></h3>
                    <p>Total Assignments</p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <div class="stat-info">
                    <h3><?php echo $submittedCount; ?></h3>
                    <p>Submitted</p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <div class="stat-info">
                    <h3><?php echo $pendingCount; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-exclamation-circle"></i>
                <div class="stat-info">
                    <h3><?php echo $overdueCount; ?></h3>
                    <p>Overdue</p>
                </div>
            </div>
        </div>

        <!-- Assignment List -->
        <div class="assignments-container">
            <?php if (!empty($assignments)): ?>
                <?php foreach ($assignments as $assignment): ?>
                    <div class="assignment-card" data-type="<?php echo $assignment['type']; ?>">
                        <a href="<?php echo $assignment['type'] === 'activity' ? 'view_activity.php' : 'view_assignment.php'; ?>?id=<?php echo $assignment['assignment_id']; ?>" 
                           class="assignment-card-link">
                            <div class="assignment-type">
                                <?php echo ucfirst($assignment['type']); ?>
                            </div>
                            <div class="assignment-status <?php echo $assignment['submission_status']; ?>">
                                <?php echo ucfirst($assignment['submission_status']); ?>
                            </div>
                            <div class="assignment-header">
                                <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            </div>
                            <div class="assignment-details">
                                <div class="detail-item">
                                    <i class="fas fa-user-tie"></i>
                                    <span><?php echo htmlspecialchars($assignment['teacher_fname'] . ' ' . $assignment['teacher_lname']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>Due: <?php echo date('M d, Y h:i A', strtotime($assignment['due_date'])); ?></span>
                                </div>
                            </div>
                            <?php if ($assignment['submission_status'] === 'submitted'): ?>
                                <div class="submission-details">
                                    <div class="score">
                                        Score: <?php echo $assignment['score'] ? number_format($assignment['score'], 0) : 'Not graded yet'; ?>
                                    </div>
                                    <?php if ($assignment['feedback']): ?>
                                        <div class="feedback">
                                            <strong>Feedback:</strong>
                                            <p><?php echo htmlspecialchars($assignment['feedback']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h3>No Assignments Found</h3>
                    <p>You don't have any assignments at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="js/assignments.js"></script>
</body>
</html> 