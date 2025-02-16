<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacherId = $_SESSION['role_id'];
$userId = $_SESSION['user_id'];
$selectedSubmission = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : null;

try {
    // Fetch submissions with their details
    $stmt = $conn->prepare("
        SELECT 
            ss.id as submission_id,
            ss.submitted_at,
            a.title,
            s.subject_name,
            t.first_name as teacher_fname,
            t.last_name as teacher_lname
        FROM student_submissions ss
        JOIN assignments a ON ss.assignment_id = a.id
        JOIN classes c ON a.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN teachers t ON c.teacher_id = t.id
        WHERE c.teacher_id = ?
        ORDER BY ss.submitted_at DESC
    ");
    $stmt->execute([$teacherId]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If submission selected, get its details and comments
    if ($selectedSubmission) {
        $stmt = $conn->prepare("
            SELECT 
                ss.*,
                a.title,
                s.subject_name,
                t.first_name as teacher_fname,
                t.last_name as teacher_lname,
                ss.submitted_at
            FROM student_submissions ss
            JOIN assignments a ON ss.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            JOIN subjects s ON c.subject_id = s.id
            JOIN teachers t ON c.teacher_id = t.id
            WHERE ss.id = ?
        ");
        $stmt->execute([$selectedSubmission]);
        $submissionDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch comments
        if ($submissionDetails) {
            $stmt = $conn->prepare("
                SELECT 
                    ac.*,
                    u.user_type,
                    CASE 
                        WHEN u.user_type = 'teacher' THEN 
                            CONCAT(t.first_name, ' ', t.last_name)
                        WHEN u.user_type = 'student' THEN 
                            CONCAT(s.first_name, ' ', s.last_name)
                    END as sender_name
                FROM assignment_comments ac
                JOIN users u ON ac.user_id = u.id
                LEFT JOIN teachers t ON u.id = t.user_id
                LEFT JOIN students s ON u.id = s.user_id
                WHERE ac.submission_id = ?
                ORDER BY ac.created_at ASC
            ");
            $stmt->execute([$selectedSubmission]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (Exception $e) {
    error_log("Error in messages: " . $e->getMessage());
    $error = "An error occurred while loading messages.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Discussions - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/messages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="messages-container">
            <!-- Left sidebar with submissions list -->
            <div class="submissions-list">
                <?php foreach ($submissions as $submission): ?>
                    <a href="?submission_id=<?php echo $submission['submission_id']; ?>" 
                       class="submission-item <?php echo $selectedSubmission == $submission['submission_id'] ? 'active' : ''; ?>">
                        <h4><?php echo htmlspecialchars($submission['title']); ?></h4>
                        <p>
                            <?php echo htmlspecialchars($submission['subject_name']); ?><br>
                            Teacher: <?php echo htmlspecialchars($submission['teacher_fname'] . ' ' . $submission['teacher_lname']); ?>
                        </p>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Right side discussion area -->
            <div class="discussion-area">
                <?php if ($selectedSubmission && $submissionDetails): ?>
                    <div class="discussion-header">
                        <h3><?php echo htmlspecialchars($submissionDetails['title']); ?></h3>
                        <div class="meta-info">
                            <p>Subject: <?php echo htmlspecialchars($submissionDetails['subject_name']); ?></p>
                            <p>Teacher: <?php echo htmlspecialchars($submissionDetails['teacher_fname'] . ' ' . $submissionDetails['teacher_lname']); ?></p>
                            <p>Submitted on: <?php echo formatDateTime($submissionDetails['submitted_at']); ?></p>
                        </div>
                    </div>

                    <div class="comments-section">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment <?php echo $comment['user_type']; ?>">
                                <div class="comment-header">
                                    <span class="commenter-name">
                                        <?php echo htmlspecialchars($comment['sender_name']); ?>
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

                    <form class="comment-form" id="commentForm">
                        <input type="hidden" name="submission_id" value="<?php echo $selectedSubmission; ?>">
                        <textarea name="comment" placeholder="Write a comment..." required></textarea>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Send Comment
                        </button>
                    </form>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>Select a Submission</h3>
                        <p>Choose a submission to view the discussion</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Handle comment submission
        document.getElementById('commentForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            
            try {
                const response = await fetch('post_comment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    form.reset();
                    location.reload();
                } else {
                    alert(result.error || 'Failed to post comment');
                }
            } catch (error) {
                alert('Failed to post comment');
            }
        });

        // Auto-scroll to bottom of comments
        const commentsSection = document.querySelector('.comments-section');
        if (commentsSection) {
            commentsSection.scrollTop = commentsSection.scrollHeight;
        }
    </script>
</body>
</html> 