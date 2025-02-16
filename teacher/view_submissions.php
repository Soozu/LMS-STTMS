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
$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$assignmentId) {
    $_SESSION['error'] = "Invalid assignment ID";
    header('Location: assignments.php');
    exit();
}

try {
    // Get assignment details
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            s.subject_name,
            sec.section_name,
            sec.grade_level,
            (SELECT COUNT(*) 
             FROM student_submissions ss 
             WHERE ss.assignment_id = a.id) as submission_count,
            (SELECT COUNT(*) 
             FROM class_enrollments ce 
             WHERE ce.class_id = a.class_id 
             AND ce.status = 'active') as total_students
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE a.id = ?
        AND c.teacher_id = ?
    ");
    $stmt->execute([$assignmentId, $teacherId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        throw new Exception('Assignment not found or access denied');
    }

    // Update the SQL query to get all enrolled students and their submissions
    $stmt = $conn->prepare("
        SELECT 
            s.id as student_id,
            s.first_name,
            s.last_name,
            u.username as lrn,
            ss.id as submission_id,
            ss.submitted_at,
            ss.submission_text,
            ss.score,
            ss.status,
            CASE 
                WHEN ss.submitted_at > ass.due_date THEN 'late'
                WHEN ss.submitted_at IS NOT NULL THEN 'on_time'
                ELSE 'not_submitted'
            END as submission_timing,
            TIMESTAMPDIFF(HOUR, ass.due_date, ss.submitted_at) as hours_late,
            (
                SELECT COUNT(*)
                FROM assignment_comments ac
                WHERE ac.submission_id = ss.id
            ) as comment_count,
            g.grade as current_grade
        FROM class_enrollments ce
        JOIN students s ON ce.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN classes c ON ce.class_id = c.id
        JOIN assignments ass ON ass.id = ? AND ass.class_id = ce.class_id
        LEFT JOIN student_submissions ss ON s.id = ss.student_id AND ss.assignment_id = ass.id
        LEFT JOIN grades g ON g.student_id = s.id 
            AND g.class_id = ce.class_id 
            AND g.quarter = (
                CASE 
                    WHEN MONTH(CURRENT_DATE) >= 11 THEN 2
                    WHEN MONTH(CURRENT_DATE) >= 8 THEN 1
                    WHEN MONTH(CURRENT_DATE) >= 5 THEN 4
                    WHEN MONTH(CURRENT_DATE) >= 2 THEN 3
                    ELSE 2
                END
            )
        WHERE ce.class_id = ? 
        AND ce.status = 'active'
        ORDER BY s.last_name ASC, s.first_name ASC
    ");
    $stmt->execute([$assignmentId, $assignment['class_id']]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If there are no submissions, let's also get students who haven't submitted
    if (empty($submissions)) {
        $stmt = $conn->prepare("
            SELECT 
                s.id as student_id,
                s.first_name,
                s.last_name,
                u.username as lrn,
                NULL as submission_id,
                NULL as submitted_at,
                NULL as submission_text,
                NULL as score,
                'not_submitted' as status,
                'not_submitted' as submission_timing,
                NULL as hours_late,
                0 as comment_count,
                g.grade as current_grade,
                ass.due_date
            FROM class_enrollments ce
            JOIN students s ON ce.student_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN classes c ON ce.class_id = c.id
            JOIN assignments ass ON ass.class_id = c.id
            LEFT JOIN grades g ON g.student_id = s.id 
                AND g.class_id = ce.class_id 
                AND g.quarter = (
                    CASE 
                        WHEN MONTH(CURRENT_DATE) >= 11 THEN 2
                        WHEN MONTH(CURRENT_DATE) >= 8 THEN 1
                        WHEN MONTH(CURRENT_DATE) >= 5 THEN 4
                        WHEN MONTH(CURRENT_DATE) >= 2 THEN 3
                        ELSE 2
                    END
                )
            WHERE ce.class_id = ? 
            AND ce.status = 'active'
            AND ass.id = ?
            ORDER BY s.last_name ASC, s.first_name ASC
        ");
        $stmt->execute([$assignment['class_id'], $assignmentId]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add this before the HTML to get assignment files
    $stmt = $conn->prepare("
        SELECT * FROM assignment_files 
        WHERE assignment_id = ?
    ");
    $stmt->execute([$assignmentId]);
    $assignmentFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    error_log("Error in view submissions: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading submissions";
    header('Location: assignments.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submissions - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/submissions.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php 
                    echo htmlspecialchars($_SESSION['success_message']);
                    unset($_SESSION['success_message']); 
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    echo htmlspecialchars($_SESSION['error_message']);
                    unset($_SESSION['error_message']); 
                ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="header-content">
                <h2><?php echo htmlspecialchars($assignment['title']); ?></h2>
                <p class="assignment-meta">
                    Grade <?php echo htmlspecialchars($assignment['grade_level']); ?> - 
                    <?php echo htmlspecialchars($assignment['section_name']); ?> | 
                    <?php echo htmlspecialchars($assignment['subject_name']); ?>
                </p>
                <div class="assignment-details">
                    <span class="type-badge <?php echo $assignment['type']; ?>">
                        <?php echo ucfirst($assignment['type']); ?>
                    </span>
                    <span class="due-date">
                        <i class="fas fa-calendar"></i>
                        Due: <?php echo date('M d, Y h:i A', strtotime($assignment['due_date'])); ?>
                    </span>
                    <?php if (!empty($assignmentFiles)): ?>
                        <div class="attached-files">
                            <h5>Assignment Files:</h5>
                            <?php foreach ($assignmentFiles as $file): ?>
                                <div class="file-item">
                                    <i class="<?php echo getFileIcon($file['file_type']); ?>"></i>
                                    <a href="download.php?file=<?php echo htmlspecialchars($file['id']); ?>&type=assignment">
                                        <?php echo htmlspecialchars($file['original_name']); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="submission-stats">
                <div class="stat">
                    <span class="label">Submissions</span>
                    <span class="value"><?php echo $assignment['submission_count']; ?>/<?php echo $assignment['total_students']; ?></span>
                </div>
            </div>
        </div>

        <div class="submissions-container">
            <?php if (!empty($submissions)): ?>
                <?php foreach ($submissions as $submission): ?>
                    <div class="submission-card <?php echo $submission['submission_timing']; ?>" 
                         id="submission-<?php echo $submission['submission_id']; ?>">
                        <div class="submission-header">
                            <div class="student-info">
                                <h4><?php echo htmlspecialchars($submission['last_name'] . ', ' . $submission['first_name']); ?></h4>
                                <span class="lrn"><?php echo htmlspecialchars($submission['lrn']); ?></span>
                            </div>
                            <div class="submission-meta">
                                <?php if ($submission['submitted_at']): ?>
                                    <span class="timestamp">
                                        Submitted: <?php echo formatDateTime($submission['submitted_at']); ?>
                                    </span>
                                    <?php if ($submission['submission_timing'] === 'late'): ?>
                                        <span class="late-badge">
                                            Late by <?php 
                                                $hours = $submission['hours_late'];
                                                if ($hours < 24) {
                                                    echo $hours . ' hour' . ($hours > 1 ? 's' : '');
                                                } else {
                                                    $days = floor($hours / 24);
                                                    echo $days . ' day' . ($days > 1 ? 's' : '');
                                                }
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="no-submission-badge">Not Submitted</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($submission['submission_id']): ?>
                            <div class="submission-content">
                                <?php if ($submission['submission_text']): ?>
                                    <div class="text-content">
                                        <?php echo nl2br(htmlspecialchars($submission['submission_text'])); ?>
                                    </div>
                                <?php endif; ?>

                                <?php
                                // Get submission files
                                $stmt = $conn->prepare("
                                    SELECT * FROM submission_files 
                                    WHERE submission_id = ?
                                ");
                                $stmt->execute([$submission['submission_id']]);
                                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($files)):
                                ?>
                                    <div class="attached-files">
                                        <h5>Attached Files:</h5>
                                        <?php foreach ($files as $file): ?>
                                            <div class="file-item">
                                                <i class="<?php echo getFileIcon($file['file_type']); ?>"></i>
                                                <?php if (file_exists("../uploads/submissions/" . $file['file_name'])): ?>
                                                    <a href="download.php?file=<?php echo $file['id']; ?>&type=submission">
                                                        <?php echo htmlspecialchars($file['original_name']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-error">
                                                        <?php echo htmlspecialchars($file['original_name']); ?> (File not found)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="grading-section">
                                <form class="grade-form" action="update_grade.php" method="POST">
                                    <input type="hidden" name="submission_id" value="<?php echo $submission['submission_id']; ?>">
                                    <div class="form-group">
                                        <label for="score-<?php echo $submission['submission_id']; ?>">Score:</label>
                                        <input type="number" 
                                               id="score-<?php echo $submission['submission_id']; ?>" 
                                               name="score" 
                                               value="<?php echo $submission['score'] ?? ''; ?>"
                                               min="0" 
                                               max="100"
                                               required>
                                        <span class="max-score">/100</span>
                                    </div>
                                    <?php if (isset($submission['current_grade'])): ?>
                                        <div class="current-grade">
                                            Current Quarter Grade: <?php echo number_format($submission['current_grade'], 2); ?>
                                        </div>
                                    <?php endif; ?>
                                    <button type="submit" class="btn-save">Save Score</button>
                                </form>
                            </div>

                            <div class="comments-section">
                                <h5>
                                    Comments 
                                    <div class="comment-count">
                                        <?php if (isset($submission['comment_count'])): ?>
                                            <i class="fas fa-comments"></i>
                                            <span><?php echo (int)$submission['comment_count']; ?> comments</span>
                                        <?php else: ?>
                                            <i class="fas fa-comments"></i>
                                            <span>0 comments</span>
                                        <?php endif; ?>
                                    </div>
                                </h5>
                                <div class="comments-container" id="comments-<?php echo $submission['submission_id']; ?>">
                                    <!-- Comments will be loaded here via AJAX -->
                                </div>
                                <form class="comment-form" onsubmit="return addComment(this, <?php echo $submission['submission_id']; ?>)">
                                    <textarea name="comment" placeholder="Add a comment..." required></textarea>
                                    <button type="submit" class="btn-submit">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="no-submission-message">
                                <p>This student has not submitted their work yet.</p>
                                <?php if (strtotime($assignment['due_date']) < time()): ?>
                                    <div class="overdue-warning">
                                        <i class="fas fa-exclamation-circle"></i>
                                        Assignment is overdue
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-users"></i>
                    <p>No students enrolled in this class yet</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Load comments for each submission when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const submissions = document.querySelectorAll('.submission-card');
            submissions.forEach(submission => {
                const submissionId = submission.id.split('-')[1];
                loadComments(submissionId);
            });
        });

        function loadComments(submissionId) {
            fetch(`get_comments.php?submission_id=${submissionId}`)
                .then(response => response.json())
                .then(comments => {
                    const container = document.getElementById(`comments-${submissionId}`);
                    container.innerHTML = comments.map(comment => `
                        <div class="comment ${comment.user_type === 'teacher' ? 'teacher-comment' : 'student-comment'}">
                            <div class="comment-header">
                                <span class="commenter">${comment.user_name}</span>
                                <span class="timestamp">${comment.created_at}</span>
                            </div>
                            <div class="comment-content">${comment.comment}</div>
                        </div>
                    `).join('');
                })
                .catch(error => console.error('Error loading comments:', error));
        }

        function addComment(form, submissionId) {
            const comment = form.comment.value;
            
            fetch('add_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `submission_id=${submissionId}&comment=${encodeURIComponent(comment)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    form.reset();
                    loadComments(submissionId);
                } else {
                    alert('Failed to add comment');
                }
            })
            .catch(error => console.error('Error:', error));

            return false; // Prevent form submission
        }
    </script>
</body>
</html> 