<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get teacher ID from role_id in session
$teacherId = $_SESSION['role_id'];

try {
    // Fetch teacher's information
    $stmt = $conn->prepare("
        SELECT t.*, u.username, u.status
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND u.status = 'active'
    ");
    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        throw new Exception('Teacher not found or inactive');
    }

    // Fetch all active classes taught by the teacher
    $stmt = $conn->prepare("
        SELECT 
            c.id as class_id,
            c.status,
            s.subject_name,
            s.id as subject_id,
            sec.section_name,
            sec.grade_level,
            (SELECT COUNT(*) 
             FROM class_enrollments ce 
             WHERE ce.class_id = c.id 
             AND ce.status = 'active') as enrolled_students
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE c.teacher_id = ? 
        AND c.status = 'active'
        ORDER BY sec.grade_level ASC, sec.section_name ASC, s.subject_name ASC
    ");
    $stmt->execute([$teacher['id']]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error fetching classes data: " . $e->getMessage());
    $error = "An error occurred while fetching your classes.";
} catch(Exception $e) {
    error_log("Error: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/classes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
   
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>My Classes</h2>
        </div>

        <div class="archive-section">
            <button class="btn-archive" onclick="showArchiveModal()">
                <i class="fas fa-archive"></i> Archive School Year
            </button>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="table-container">
            <?php
            // Group classes by grade level and section
            $grouped_classes = [];
            foreach ($classes as $class) {
                $key = "Grade {$class['grade_level']} - {$class['section_name']}";
                if (!isset($grouped_classes[$key])) {
                    $grouped_classes[$key] = [];
                }
                $grouped_classes[$key][] = $class;
            }
            
            // Sort by grade level
            ksort($grouped_classes);
            ?>

            <?php if (!empty($grouped_classes)): ?>
                <?php foreach ($grouped_classes as $section_name => $section_classes): ?>
                    <div class="section-header">
                        <h3><?php echo htmlspecialchars($section_name); ?></h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($section_classes as $class): ?>
                                <tr>
                                    <td>
                                        <div class="subject-info">
                                            <span class="subject-name"><?php echo htmlspecialchars($class['subject_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="student-count">
                                            <i class="fas fa-users"></i>
                                            <span><?php echo (int)$class['enrolled_students']; ?> Students</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="class_details.php?id=<?php echo (int)$class['class_id']; ?>" 
                                               class="btn-view">
                                                View Class
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="section-spacer"></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>No classes assigned to you yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="archiveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Archive School Year</h3>
                <button type="button" class="close-modal" onclick="closeArchiveModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p class="warning-text">
                    <i class="fas fa-exclamation-triangle"></i>
                    Warning: This action will archive all classes, assignments, and student enrollments for the selected school year.
                </p>
                <div class="form-group">
                    <label for="schoolYear">Select School Year</label>
                    <select id="schoolYear" class="form-control">
                        <?php
                        // Get unique school years from classes
                        $stmt = $conn->prepare("
                            SELECT DISTINCT school_year 
                            FROM classes 
                            WHERE teacher_id = ? 
                            AND status = 'active'
                            ORDER BY school_year DESC
                        ");
                        $stmt->execute([$teacherId]);
                        while ($row = $stmt->fetch()) {
                            echo "<option value='{$row['school_year']}'>{$row['school_year']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeArchiveModal()">Cancel</button>
                <button type="button" class="btn-danger" onclick="archiveSchoolYear()">
                    <i class="fas fa-archive"></i> Archive
                </button>
            </div>
        </div>
    </div>

    <script>
        function viewClass(classId) {
            window.location.href = `class_details.php?id=${classId}`;
        }

        function editClass(classId) {
            window.location.href = `edit_class.php?id=${classId}`;
        }

        function showArchiveModal() {
            document.getElementById('archiveModal').classList.add('active');
        }

        function closeArchiveModal() {
            document.getElementById('archiveModal').classList.remove('active');
        }

        function archiveSchoolYear() {
            if (!confirm('Are you sure you want to archive this school year? This action cannot be undone.')) {
                return;
            }

            const schoolYear = document.getElementById('schoolYear').value;
            
            fetch('archive_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `school_year=${encodeURIComponent(schoolYear)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('School year archived successfully');
                    window.location.reload();
                } else {
                    alert('Failed to archive school year: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while archiving');
            });
        }
    </script>
</body>
</html>