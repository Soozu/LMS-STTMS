<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get teacher's classes for the dropdown
try {
    $stmt = $conn->prepare("
        SELECT c.id, c.grade_level, s.subject_name, sec.section_name
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        JOIN teachers t ON c.teacher_id = t.id
        WHERE t.user_id = ? AND c.status = 'active'
        ORDER BY c.grade_level, s.subject_name
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load classes.";
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Get form data
        $classId = $_POST['class_id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $dueDate = $_POST['due_date'];
        $type = $_POST['type'];

        // Insert assignment
        $stmt = $conn->prepare("
            INSERT INTO assignments (
                class_id, 
                title, 
                description, 
                due_date, 
                type, 
                status, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([
            $classId,
            $title,
            $description,
            $dueDate,
            $type
        ]);

        $assignmentId = $conn->lastInsertId();

        // Handle file uploads
        if (!empty($_FILES['files']['name'][0])) {
            $uploadDir = '../uploads/assignments/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Process each uploaded file
            foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileInfo = [
                        'name' => $_FILES['files']['name'][$key],
                        'type' => $_FILES['files']['type'][$key],
                        'tmp_name' => $tmpName,
                        'error' => $_FILES['files']['error'][$key],
                        'size' => $_FILES['files']['size'][$key]
                    ];

                    // Validate file size (increase limit for video files)
                    $maxSize = 100 * 1024 * 1024; // 100MB limit
                    if ($fileInfo['size'] > $maxSize) {
                        throw new Exception("File {$fileInfo['name']} exceeds 100MB limit");
                    }

                    // Validate file type
                    $allowedTypes = [
                        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'mp4', 'webm', 'mov'
                    ];
                    $fileExt = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
                    if (!in_array($fileExt, $allowedTypes)) {
                        throw new Exception("File type not allowed for {$fileInfo['name']}");
                    }

                    // Additional validation for video files
                    if (in_array($fileExt, ['mp4', 'webm', 'mov'])) {
                        // Check if it's actually a video file
                        $mimeType = mime_content_type($tmpName);
                        if (strpos($mimeType, 'video/') !== 0) {
                            throw new Exception("Invalid video file format for {$fileInfo['name']}");
                        }
                    }

                    // Generate unique filename
                    $uniqueFileName = uniqid() . '.' . $fileExt;
                    $filePath = $uploadDir . $uniqueFileName;

                    // Move uploaded file
                    if (move_uploaded_file($tmpName, $filePath)) {
                        // Insert file record
                        $stmt = $conn->prepare("
                            INSERT INTO assignment_files (
                                assignment_id,
                                file_name,
                                original_name,
                                file_type,
                                file_size,
                                uploaded_at
                            ) VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $assignmentId,
                            $uniqueFileName,
                            $fileInfo['name'],
                            $fileInfo['type'],
                            $fileInfo['size']
                        ]);
                    } else {
                        throw new Exception("Failed to move uploaded file {$fileInfo['name']}");
                    }
                }
            }
        }

        // Log the action
        $stmt = $conn->prepare("
            INSERT INTO system_logs (
                user_id, 
                action, 
                description, 
                created_at
            ) VALUES (?, 'Create Assignment', ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Created $type: $title for class $classId"
        ]);

        $conn->commit();
        $_SESSION['success'] = ucfirst($type) . " created successfully";
        header("Location: assignments.php?class_id=$classId");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error creating assignment: " . $e->getMessage());
        $_SESSION['error'] = "Failed to create " . strtolower($type) . ". " . $e->getMessage();
        header("Location: assignments.php?class_id=$classId");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assignment - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/assignments.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Create New Assignment/Activity</h2>
        </div>

        <div class="create-assignment-form">
            <form action="create_assignment.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="type">Type</label>
                    <select name="type" id="type" required>
                        <option value="assignment">Assignment</option>
                        <option value="activity">Activity</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="class_id">Class</label>
                    <select name="class_id" id="class_id" required>
                        <option value="">Select a class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>">
                                Grade <?php echo $class['grade_level']; ?> - 
                                <?php echo $class['section_name']; ?> - 
                                <?php echo $class['subject_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" name="title" id="title" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="datetime-local" name="due_date" id="due_date" required>
                </div>

                <div class="form-group">
                    <label for="files">Upload Files (Optional)</label>
                    <input type="file" name="files[]" id="files" multiple>
                    <p class="file-help">Accepted file types: PDF, DOC, DOCX, PPT, PPTX, JPG, PNG, MP4, WEBM, MOV (Max size: 100MB per file)</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-plus"></i> Create
                    </button>
                    <a href="assignments.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>

    <script src="js/sidebar.js"></script>
    <script>
        // Set minimum date for due date to today
        const dueDateInput = document.getElementById('due_date');
        const today = new Date();
        today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
        dueDateInput.min = today.toISOString().slice(0, 16);

        // File upload validation
        document.getElementById('files').addEventListener('change', function(e) {
            const files = e.target.files;
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'];

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const ext = file.name.split('.').pop().toLowerCase();

                if (file.size > maxSize) {
                    alert(`File ${file.name} exceeds 5MB limit`);
                    e.target.value = '';
                    return;
                }

                if (!allowedTypes.includes(ext)) {
                    alert(`File type not allowed for ${file.name}`);
                    e.target.value = '';
                    return;
                }
            }
        });
    </script>
</body>
</html> 