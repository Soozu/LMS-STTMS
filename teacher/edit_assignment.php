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
$assignmentId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$assignmentId) {
    header('Location: assignments.php');
    exit();
}

try {
    // Fetch assignment details with class info
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            c.id as class_id,
            s.subject_name,
            sec.section_name,
            sec.grade_level
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE a.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$assignmentId, $teacherId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        $_SESSION['error'] = "Assignment not found or access denied.";
        header('Location: assignments.php');
        exit();
    }

    // Fetch attached files
    $stmt = $conn->prepare("
        SELECT * FROM assignment_files 
        WHERE assignment_id = ?
    ");
    $stmt->execute([$assignmentId]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load assignment details.";
    header('Location: assignments.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assignment - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/assignments.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Edit Assignment</h2>
            <div class="breadcrumb">
                <a href="assignments.php">Assignments</a> /
                <span>Edit Assignment</span>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="assignment-form-container">
                <div class="class-info">
                    <h3>
                        Grade <?php echo htmlspecialchars($assignment['grade_level']); ?> - 
                        <?php echo htmlspecialchars($assignment['section_name']); ?> - 
                        <?php echo htmlspecialchars($assignment['subject_name']); ?>
                    </h3>
                </div>

                <form id="editAssignmentForm" action="update_assignment.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                    <input type="hidden" name="class_id" value="<?php echo $assignment['class_id']; ?>">
                    
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($assignment['title']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($assignment['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="dueDate">Due Date</label>
                        <input type="datetime-local" id="dueDate" name="due_date" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime($assignment['due_date'])); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Current Attachments</label>
                        <div class="current-files">
                            <?php if (!empty($files)): ?>
                                <?php foreach ($files as $file): ?>
                                    <div class="file-item" id="file-<?php echo $file['id']; ?>">
                                        <i class="<?php echo getFileIcon($file['file_type']); ?>"></i>
                                        <span><?php echo htmlspecialchars($file['original_name']); ?></span>
                                        <button type="button" class="btn-icon" onclick="removeFile(<?php echo $file['id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-files">No files attached</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Add New Attachments</label>
                        <div class="file-upload-container">
                            <div class="upload-box" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload new files</p>
                                <span class="supported-files">Supports: Documents, Images, Videos, etc.</span>
                            </div>
                            <input type="file" id="fileInput" name="files[]" multiple style="display: none" 
                                   onchange="handleFileSelect(this)">
                            <div id="fileList" class="file-list"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" required>
                            <option value="active" <?php echo $assignment['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $assignment['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <a href="assignments.php?class_id=<?php echo $assignment['class_id']; ?>" class="btn-secondary">Cancel</a>
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function handleFileSelect(input) {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            
            Array.from(input.files).forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <i class="${getFileIcon(file.type)}"></i>
                    <span>${file.name}</span>
                    <button type="button" onclick="this.parentElement.remove()">×</button>
                `;
                fileList.appendChild(fileItem);
            });
        }

        function getFileIcon(mimeType) {
            if (mimeType.startsWith('image/')) return 'fas fa-image';
            if (mimeType.startsWith('video/')) return 'fas fa-video';
            if (mimeType.startsWith('audio/')) return 'fas fa-music';
            if (mimeType.includes('pdf')) return 'fas fa-file-pdf';
            if (mimeType.includes('word')) return 'fas fa-file-word';
            if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'fas fa-file-excel';
            if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'fas fa-file-powerpoint';
            return 'fas fa-file';
        }

        function removeFile(fileId) {
            if (confirm('Are you sure you want to remove this file?')) {
                fetch('remove_assignment_file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `file_id=${fileId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById(`file-${fileId}`).remove();
                        if (document.querySelectorAll('.file-item').length === 0) {
                            document.querySelector('.current-files').innerHTML = '<p class="no-files">No files attached</p>';
                        }
                    } else {
                        alert('Failed to remove file');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to remove file');
                });
            }
        }
    </script>
</body>
</html> 