<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacherId = $_SESSION['role_id'];
$selectedClass = isset($_GET['class_id']) ? $_GET['class_id'] : null;

try {
    // Fetch teacher's classes
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
        ORDER BY sec.grade_level ASC, sec.section_name ASC
    ");
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch archived assignments
    $query = "
        SELECT 
            a.*,
            c.id as class_id,
            s.subject_name,
            sec.section_name,
            sec.grade_level,
            (SELECT COUNT(*) FROM student_submissions WHERE assignment_id = a.id) as submission_count
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN subjects s ON c.subject_id = s.id
        JOIN sections sec ON c.section_id = sec.id
        WHERE c.teacher_id = ? 
        AND a.status = 'archived'
    ";
    
    if ($selectedClass) {
        $query .= " AND c.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$teacherId, $selectedClass]);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->execute([$teacherId]);
    }
    
    $archived = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    error_log("Error: " . $e->getMessage());
    $error = "An error occurred while fetching data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Items - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/assignments.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/archived.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Archived Items</h2>
            <div class="class-selector">
                <select onchange="window.location.href='?class_id=' + this.value">
                    <option value="">All Classes</option>
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
        </div>

        <div class="archived-container">
            <?php if (!empty($archived)): ?>
                <?php foreach ($archived as $item): ?>
                    <div class="archived-card <?php echo $item['type']; ?>">
                        <div class="archived-header">
                            <div class="type-badge <?php echo $item['type']; ?>">
                                <?php echo ucfirst($item['type']); ?>
                            </div>
                            <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                            <div class="class-info">
                                Grade <?php echo $item['grade_level']; ?> - 
                                <?php echo $item['section_name']; ?> - 
                                <?php echo $item['subject_name']; ?>
                            </div>
                        </div>
                        <div class="archived-content">
                            <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                            <div class="meta-info">
                                <span><i class="fas fa-calendar"></i> Due: <?php echo formatDateTime($item['due_date']); ?></span>
                                <span><i class="fas fa-users"></i> <?php echo $item['submission_count']; ?> Submissions</span>
                            </div>
                        </div>
                        <div class="archived-actions">
                            <button onclick="restoreItem(<?php echo $item['id']; ?>)" class="btn-secondary">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                            <button onclick="deleteItem(<?php echo $item['id']; ?>)" class="btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>No archived items found</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    function archiveAssignment(id) {
        if (confirm('Are you sure you want to archive this item?')) {
            fetch('archive_assignment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'assignment_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to archive item');
                }
            });
        }
    }

    function restoreItem(id) {
        if (confirm('Are you sure you want to restore this item?')) {
            fetch('restore_assignment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'assignment_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to restore item');
                }
            });
        }
    }

    function deleteItem(id) {
        if (confirm('Are you sure you want to permanently delete this item? This action cannot be undone.')) {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            button.disabled = true;

            fetch('delete_assignment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'assignment_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message and reload
                    alert('Item deleted successfully');
                    window.location.reload();
                } else {
                    // Show error message
                    alert('Failed to delete item: ' + (data.error || 'Unknown error'));
                    // Reset button
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the item');
                // Reset button
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
    }
    </script>
</body>
</html> 