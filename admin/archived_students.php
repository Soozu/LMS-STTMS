<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

try {
    // Fetch all archived students
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            CONCAT(a.first_name, ' ', a.last_name) as full_name,
            s.section_name,
            u.username,
            CONCAT(admin.first_name, ' ', admin.last_name) as archived_by_name
        FROM archived_students a
        LEFT JOIN sections s ON a.section_id = s.id
        LEFT JOIN users u ON u.id = (
            SELECT user_id FROM students WHERE id = a.student_id
        )
        LEFT JOIN users admin_u ON a.archived_by = admin_u.id
        LEFT JOIN admins admin ON admin_u.id = admin.user_id
        ORDER BY a.archived_at DESC
    ");
    $stmt->execute();
    $archived_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error in archived students page: " . $e->getMessage());
    $error = "An error occurred while loading the archived students data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Students - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/archived_students.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-archive"></i> Archived Students</h2>
            <a href="students.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Students
            </a>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>LRN</th>
                        <th>NAME</th>
                        <th>GRADE & SECTION</th>
                        <th>ARCHIVED BY</th>
                        <th>ARCHIVED DATE</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archived_students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['lrn']); ?></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td>
                                Grade <?php echo htmlspecialchars($student['grade_level']); ?>
                                <small><?php echo htmlspecialchars($student['section_name'] ?? 'Not set'); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($student['archived_by_name']); ?></td>
                            <td><?php echo date('M d, Y g:ia', strtotime($student['archived_at'])); ?></td>
                            <td class="actions">
                                <button onclick="restoreStudent(<?php echo $student['student_id']; ?>)" 
                                        class="btn-icon restore" title="Restore Student">
                                    <i class="fas fa-undo-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($archived_students)): ?>
                <div class="no-records">
                    <i class="fas fa-archive"></i>
                    <p>No archived students found</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function restoreStudent(studentId) {
            if (confirm('Are you sure you want to restore this student?')) {
                fetch('restore_student.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `student_id=${studentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Student restored successfully');
                        location.reload();
                    } else {
                        throw new Error(data.message || 'Failed to restore student');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to restore student');
                });
            }
        }
    </script>
</body>
</html> 