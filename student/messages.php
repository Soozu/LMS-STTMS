<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$studentId = $_SESSION['role_id'];
$userId = $_SESSION['user_id'];
$selectedSubmission = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : null;

// Initialize variables
$submissions = [];
$comments = [];
$submissionDetails = null;

try {
    // Fetch student's submissions with unread comments
    $stmt = $conn->prepare("
        SELECT DISTINCT
            ss.id as submission_id,
            ss.submitted_at,
            a.title as assignment_title,
            t.first_name as teacher_fname,
            t.last_name as teacher_lname,
            s.subject_name,
            (
                SELECT COUNT(*)
                FROM assignment_comments ac
                WHERE ac.submission_id = ss.id
                AND ac.created_at > COALESCE(
                    (SELECT MAX(last_viewed) 
                     FROM submission_views 
                     WHERE submission_id = ss.id 
                     AND user_id = ?), '1970-01-01'
                )
            ) as unread_comments
        FROM student_submissions ss
        JOIN assignments a ON ss.assignment_id = a.id
        JOIN classes c ON a.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN teachers t ON c.teacher_id = t.id
        WHERE ss.student_id = ?
        ORDER BY ss.submitted_at DESC
    ");
    $stmt->execute([$userId, $studentId]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If submission is selected, fetch its details and comments
    if ($selectedSubmission) {
        // Get submission details
        $stmt = $conn->prepare("
            SELECT 
                ss.*,
                a.title as assignment_title,
                t.first_name as teacher_fname,
                t.last_name as teacher_lname,
                s.subject_name,
                ss.submitted_at
            FROM student_submissions ss
            JOIN assignments a ON ss.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            JOIN subjects s ON c.subject_id = s.id
            JOIN teachers t ON c.teacher_id = t.id
            WHERE ss.id = ?
            AND ss.student_id = ?
        ");
        $stmt->execute([$selectedSubmission, $studentId]);
        $submissionDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($submissionDetails) {
            // Fetch comments
            $stmt = $conn->prepare("
                SELECT 
                    ac.*,
                    u.user_type,
                    CASE 
                        WHEN u.user_type = 'teacher' THEN 
                            (SELECT CONCAT(t.first_name, ' ', t.last_name) 
                             FROM teachers t 
                             WHERE t.user_id = u.id)
                        ELSE 
                            (SELECT CONCAT(s.first_name, ' ', s.last_name) 
                             FROM students s 
                             WHERE s.user_id = u.id)
                    END as commenter_name
                FROM assignment_comments ac
                JOIN users u ON ac.user_id = u.id
                WHERE ac.submission_id = ?
                ORDER BY ac.created_at ASC
            ");
            $stmt->execute([$selectedSubmission]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mark comments as read
            $stmt = $conn->prepare("
                INSERT INTO submission_views (submission_id, user_id, last_viewed)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE last_viewed = NOW()
            ");
            $stmt->execute([$selectedSubmission, $userId]);
        }
    }

} catch (Exception $e) {
    error_log("Error in messages: " . $e->getMessage());
    $error = "An error occurred while loading the messages.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Discussions - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/messages.css?v=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Assignment Discussions</h2>
        </div>

        <div class="messages-container">
            <!-- Submissions List -->
            <div class="submissions-list">
                <?php foreach ($submissions as $submission): ?>
                    <a href="?submission_id=<?php echo $submission['submission_id']; ?>" 
                       class="submission-item <?php echo $selectedSubmission == $submission['submission_id'] ? 'active' : ''; ?>">
                        <div class="submission-info">
                            <h4><?php echo htmlspecialchars($submission['assignment_title']); ?></h4>
                            <p>
                                <?php echo htmlspecialchars($submission['subject_name']); ?><br>
                                Teacher: <?php echo htmlspecialchars($submission['teacher_fname'] . ' ' . $submission['teacher_lname']); ?>
                            </p>
                        </div>
                        <?php if ($submission['unread_comments'] > 0): ?>
                            <span class="unread-badge"><?php echo $submission['unread_comments']; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Discussion Area -->
            <div class="discussion-area">
                <?php if ($selectedSubmission && isset($submissionDetails)): ?>
                    <div class="submission-header">
                        <h3><?php echo htmlspecialchars($submissionDetails['assignment_title']); ?></h3>
                        <div class="submission-meta">
                            <p>
                                Subject: <?php echo htmlspecialchars($submissionDetails['subject_name']); ?><br>
                                Teacher: <?php echo htmlspecialchars($submissionDetails['teacher_fname'] . ' ' . $submissionDetails['teacher_lname']); ?><br>
                                Submitted on: <?php echo formatDateTime($submissionDetails['submitted_at']); ?>
                            </p>
                        </div>
                    </div>

                    <div class="comments-section">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment <?php echo $comment['user_type'] === 'teacher' ? 'teacher-comment' : 'student-comment'; ?>">
                                <div class="comment-header">
                                    <span class="commenter-name">
                                        <?php echo htmlspecialchars($comment['commenter_name']); ?>
                                    </span>
                                    <span class="comment-time">
                                        <?php echo formatDateTime($comment['created_at']); ?>
                                    </span>
                                </div>
                                <div class="comment-content">
                                    <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form id="commentForm" class="comment-form">
                        <input type="hidden" name="submission_id" value="<?php echo $selectedSubmission; ?>">
                        <textarea name="comment" placeholder="Write a comment..." required></textarea>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Comment
                        </button>
                    </form>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>Select a Submission</h3>
                        <p>Choose a submission to view and participate in the discussion</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('commentForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('post_comment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Failed to post comment');
                }
            } catch (error) {
                alert('Failed to post comment');
            }
        });
    </script>
</body>
</html> 