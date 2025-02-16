<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get activity ID from URL
$activityId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$activityId) {
    $_SESSION['error_message'] = "No activity specified.";
    header('Location: assignments.php');
    exit();
}

try {
    // Fetch activity details
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            s.subject_name,
            c.grade_level,
            c.section,
            t.first_name as teacher_fname,
            t.last_name as teacher_lname,
            af.file_name,
            af.original_name,
            ss.submission_text,
            ss.submitted_at,
            ss.score,
            ss.feedback,
            sf.file_name as submission_file_name,
            sf.original_name as submission_original_name
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN teachers t ON c.teacher_id = t.id
        LEFT JOIN assignment_files af ON a.id = af.assignment_id
        LEFT JOIN student_submissions ss ON a.id = ss.assignment_id AND ss.student_id = (
            SELECT id FROM students WHERE user_id = ?
        )
        LEFT JOIN submission_files sf ON ss.id = sf.submission_id
        WHERE a.id = ? AND a.type = 'activity'
    ");
    
    $stmt->execute([$_SESSION['user_id'], $activityId]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activity) {
        throw new Exception('Activity not found');
    }

    // Fetch comments
    $stmt = $conn->prepare("
        SELECT 
            ac.*,
            u.user_type,
            CASE 
                WHEN u.user_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name)
                WHEN u.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
            END as commenter_name
        FROM assignment_comments ac
        JOIN users u ON ac.user_id = u.id
        LEFT JOIN teachers t ON u.id = t.user_id
        LEFT JOIN students s ON u.id = s.user_id
        WHERE ac.submission_id = (
            SELECT id FROM student_submissions 
            WHERE assignment_id = ? AND student_id = (
                SELECT id FROM students WHERE user_id = ?
            )
        )
        ORDER BY ac.created_at ASC
    ");
    $stmt->execute([$activityId, $_SESSION['user_id']]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    error_log("Error in view_activity.php: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while fetching the activity details.";
    header('Location: assignments.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Activity - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/assignments.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // Set worker path for PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <script src="js/preview.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Activity Details</h2>
        </div>

        <div class="assignment-card">
            <div class="assignment-header">
                <h3><?php echo htmlspecialchars($activity['title']); ?></h3>
                <div class="subject-info">
                    <span class="subject-code"><?php echo htmlspecialchars($activity['subject_name']); ?></span>
                </div>
            </div>

            <div class="assignment-details">
                <div class="detail-item">
                    <i class="fas fa-user-tie"></i>
                    <span>Teacher: <?php echo htmlspecialchars($activity['teacher_fname'] . ' ' . $activity['teacher_lname']); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-calendar"></i>
                    <span>Due: <?php echo date('M d, Y h:i A', strtotime($activity['due_date'])); ?></span>
                </div>
            </div>

            <div class="assignment-description">
                <h4>Description</h4>
                <p><?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>
            </div>

            <?php if ($activity['file_name']): ?>
            <div class="assignment-materials">
                <h4>Activity Materials</h4>
                <div class="file-item">
                    <?php
                    $fileExt = strtolower(pathinfo($activity['file_name'], PATHINFO_EXTENSION));
                    $fileIcon = getFileIcon($fileExt);
                    $filePath = "../uploads/assignments/" . $activity['file_name'];
                    $fileUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                        . "://{$_SERVER['HTTP_HOST']}"
                        . dirname($_SERVER['PHP_SELF'], 2) 
                        . "/uploads/assignments/" 
                        . rawurlencode($activity['file_name']);
                    $previewable = in_array($fileExt, ['pdf']);
                    ?>
                    <div class="file-info">
                        <i class="<?php echo $fileIcon; ?>"></i>
                        <span class="file-name"><?php echo htmlspecialchars($activity['original_name']); ?></span>
                        <div class="file-actions">
                            <a href="download.php?file=<?php echo urlencode($activity['file_name']); ?>" 
                               class="download-file" download>
                                <i class="fas fa-download"></i> Download
                            </a>
                            <?php if ($previewable): ?>
                            <button class="preview-btn" 
                                    onclick="previewFile('<?php echo $fileUrl; ?>', '<?php echo $fileExt; ?>', '<?php echo htmlspecialchars(addslashes($activity['original_name'])); ?>')">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="submission-section">
                <div class="submission-header">
                    Your Submission
                </div>
                <?php if ($activity['submission_file_name']): ?>
                    <div class="submission-info">
                        <div class="status">
                            <i class="fas fa-check-circle"></i>
                            Submitted
                        </div>
                        <div class="timestamp">
                            Submitted on: <?php echo date('M d, Y h:i A', strtotime($activity['submitted_at'])); ?>
                        </div>
                        <div class="submission-file">
                            <i class="fas fa-file-pdf"></i>
                            <span class="file-name"><?php echo htmlspecialchars($activity['submission_original_name']); ?></span>
                            <?php
                            $submissionExt = strtolower(pathinfo($activity['submission_file_name'], PATHINFO_EXTENSION));
                            $previewable = in_array($submissionExt, ['pdf', 'doc', 'docx', 'ppt', 'pptx']);
                            $submissionPath = "../uploads/submissions/" . $activity['submission_file_name'];
                            ?>
                            <a href="<?php echo $submissionPath; ?>" 
                               class="download-link" 
                               download>
                                <i class="fas fa-download"></i>
                            </a>
                            <?php if ($previewable): ?>
                            <button class="preview-btn" 
                                    onclick="previewFile('<?php echo $submissionPath; ?>', '<?php echo $submissionExt; ?>', '<?php echo htmlspecialchars($activity['submission_original_name']); ?>')">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php if (!$activity['score']): ?>
                            <button type="button" 
                                    class="unsubmit-btn" 
                                    onclick="if(confirm('Are you sure you want to unsubmit this activity? This will allow you to submit a new version.')) { document.getElementById('unsubmitForm').submit(); }">
                                <i class="fas fa-undo"></i>
                                Unsubmit Activity
                            </button>
                            <form id="unsubmitForm" action="unsubmit_assignment.php" method="POST" style="display: none;">
                                <input type="hidden" name="assignment_id" value="<?php echo $activityId; ?>">
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if (strtotime($activity['due_date']) > time()): ?>
                        <div class="upload-section">
                            <form action="submit_assignment.php" method="POST" enctype="multipart/form-data" class="submit-form">
                                <input type="hidden" name="assignment_id" value="<?php echo $activity['id']; ?>">
                                <div class="upload-area">
                                    <input type="file" name="submission_file" id="submission_file" class="file-input" required>
                                    <div class="upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="selected-file-name"></div>
                                </div>
                                <button type="submit" class="submit-btn">
                                    <i class="fas fa-paper-plane"></i> Submit Work
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="submission-status overdue">
                            <i class="fas fa-exclamation-circle"></i>
                            Activity is overdue. Submission is no longer accepted.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($comments)): ?>
            <div class="comments-section">
                <h4>Comments</h4>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <div class="comment-header">
                            <strong><?php echo htmlspecialchars($comment['commenter_name']); ?></strong>
                            <span class="comment-date">
                                <?php echo date('M d, Y h:i A', strtotime($comment['created_at'])); ?>
                            </span>
                        </div>
                        <div class="comment-content">
                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="assignment-actions">
                <a href="activity.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Activities
                </a>
            </div>
        </div>
    </main>

    <script src="js/sidebar.js"></script>
    <script src="js/submission.js"></script>
</body>
</html> 