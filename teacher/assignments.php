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
$selectedClass = isset($_GET['class_id']) ? $_GET['class_id'] : null;

try {
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

    // After fetching the classes, add this code to auto-select the first class
    if (!$selectedClass && !empty($classes)) {
        // Automatically select the first class
        $selectedClass = $classes[0]['class_id'];
        
        // Get class details for the first class
        $stmt = $conn->prepare("
            SELECT 
                c.id as class_id,
                s.subject_name,
                sec.section_name,
                sec.grade_level,
                (SELECT COUNT(*) 
                 FROM class_enrollments ce 
                 WHERE ce.class_id = c.id 
                 AND ce.status = 'active') as enrolled_students
            FROM classes c
            JOIN subjects s ON c.subject_id = s.id
            JOIN sections sec ON c.section_id = sec.id
            WHERE c.id = ?
        ");
        $stmt->execute([$selectedClass]);
        $classDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch assignments for the first class
        $stmt = $conn->prepare("
            SELECT 
                a.*,
                (SELECT COUNT(*) 
                 FROM assignment_files 
                 WHERE assignment_id = a.id) as file_count,
                (SELECT COUNT(*) 
                 FROM student_submissions 
                 WHERE assignment_id = a.id) as submission_count
            FROM assignments a
            WHERE a.class_id = ?
            AND a.status != 'archived'
            ORDER BY a.type ASC, a.created_at DESC
        ");
        $stmt->execute([$selectedClass]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch(PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $error = "An error occurred while fetching data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activities & Assignments - STMA LMS</title>
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
            <h2>Activities & Assignments</h2>
            <div class="header-actions">
                <div class="class-selector">
                    <select id="classSelect" onchange="window.location.href='?class_id=' + this.value">
                        <option value="">Select a Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>" 
                                    <?php echo $selectedClass == $class['class_id'] ? 'selected' : ''; ?>>
                                Grade <?php echo $class['grade_level']; ?> - 
                                <?php echo $class['section_name']; ?> - 
                                <?php echo $class['subject_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selectedClass): ?>
                    <div class="action-buttons">
                        <button class="btn-create" onclick="showCreateModal('assignment')">
                            <i class="fas fa-plus"></i> Create Assignment
                        </button>
                        <button class="btn-create" onclick="showCreateModal('activity')">
                            <i class="fas fa-plus"></i> Create Activity
                        </button>
                        <button class="btn-secondary" onclick="window.location.href='archived.php'">
                            <i class="fas fa-archive"></i> View Archived Items
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selectedClass && isset($classDetails)): ?>
            <div class="section-header">
                <h3>
                    Grade <?php echo htmlspecialchars($classDetails['grade_level']); ?> - 
                    <?php echo htmlspecialchars($classDetails['section_name']); ?> - 
                    <?php echo htmlspecialchars($classDetails['subject_name']); ?>
                </h3>
            </div>

            <div class="assignments-container">
                <?php if (!empty($assignments)): ?>
                    <?php 
                    $currentType = '';
                    foreach ($assignments as $assignment): 
                        if ($currentType !== $assignment['type']):
                            $currentType = $assignment['type'];
                    ?>
                        <div class="section-divider">
                            <h3><?php echo ucfirst($currentType) . 's'; ?></h3>
                        </div>
                    <?php endif; ?>
                    
                    <div class="assignment-card <?php echo $assignment['type']; ?>">
                        <div class="assignment-header">
                            <div class="type-badge <?php echo $assignment['type']; ?>">
                                <?php echo ucfirst($assignment['type']); ?>
                            </div>
                            <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
                            <span class="status-badge <?php echo $assignment['status']; ?>">
                                <?php echo ucfirst($assignment['status']); ?>
                            </span>
                        </div>
                        
                        <div class="assignment-content">
                            <div class="assignment-details">
                                <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                                
                                <div class="attached-files">
                                    <h5>Attached Files</h5>
                                    <?php if ($assignment['file_count'] > 0): ?>
                                        <?php
                                        $stmt = $conn->prepare("
                                            SELECT * FROM assignment_files 
                                            WHERE assignment_id = ?
                                        ");
                                        $stmt->execute([$assignment['id']]);
                                        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($files as $file):
                                            $icon = getFileIcon($file['file_type']);
                                        ?>
                                            <div class="file-item">
                                                <i class="<?php echo $icon; ?>"></i>
                                                <span class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></span>
                                                <a href="download.php?file=<?php echo $file['id']; ?>" class="download-btn">
                                                    <i class="fas fa-download"></i>
                                                    Download
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-files">
                                            <p>No files attached to this assignment</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="assignment-meta">
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    Due: <?php echo date('M d, Y h:i A', strtotime($assignment['due_date'])); ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-users"></i>
                                    <?php echo $assignment['submission_count']; ?>/<?php echo $classDetails['enrolled_students']; ?> Submissions
                                </div>
                            </div>
                        </div>

                        <div class="assignment-actions">
                            <button class="btn-secondary" onclick="viewSubmissions(<?php echo $assignment['id']; ?>)">
                                <i class="fas fa-eye"></i> View Submissions
                            </button>
                            <button class="btn-secondary" onclick="editAssignment(<?php echo $assignment['id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-secondary" onclick="archiveAssignment(<?php echo $assignment['id']; ?>)">
                                <i class="fas fa-archive"></i> Archive
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <p>No activities or assignments created yet</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="no-data">
                <p>Please select a class to view assignments</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Create Assignment Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Create New Assignment</h3>
                <button type="button" class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="assignmentForm" action="create_assignment.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="class_id" value="<?php echo $selectedClass; ?>">
                    <input type="hidden" id="type" name="type" value="assignment">
                    
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="dueDate">Due Date</label>
                        <input type="datetime-local" id="dueDate" name="due_date" required>
                    </div>

                    <div class="form-group">
                        <label>Attachments</label>
                        <div class="file-upload-container">
                            <div class="upload-box" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload files</p>
                                <span class="supported-files">Supports: Documents, Images, Videos, etc.</span>
                            </div>
                            <input type="file" id="fileInput" name="files[]" multiple style="display: none" 
                                   onchange="handleFileSelect(this)">
                            <div id="fileList" class="file-list"></div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Create Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showCreateModal(type) {
            document.getElementById('type').value = type;
            document.getElementById('modalTitle').textContent = 'Create New ' + type.charAt(0).toUpperCase() + type.slice(1);
            document.querySelector('.modal-content .btn-primary').textContent = 'Create ' + type.charAt(0).toUpperCase() + type.slice(1);
            document.getElementById('createModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('createModal').classList.remove('active');
            document.getElementById('assignmentForm').reset();
            document.getElementById('fileList').innerHTML = '';
        }

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

        function viewSubmissions(assignmentId) {
            window.location.href = `view_submissions.php?id=${assignmentId}`;
        }

        function editAssignment(assignmentId) {
            window.location.href = `edit_assignment.php?id=${assignmentId}`;
        }

        function archiveAssignment(assignmentId) {
            if (confirm('Are you sure you want to archive this item?')) {
                // Show loading state
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Archiving...';
                button.disabled = true;

                fetch('archive_assignment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'assignment_id=' + assignmentId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        alert('Item archived successfully');
                        window.location.reload();
                    } else {
                        // Show error message
                        alert('Failed to archive item: ' + (data.error || 'Unknown error'));
                        // Reset button
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while archiving the item');
                    // Reset button
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            }
        }
    </script>
</body>
</html> 