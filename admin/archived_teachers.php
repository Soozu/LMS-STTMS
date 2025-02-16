<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

try {
    // Fetch archived teachers
    $stmt = $conn->prepare("
        SELECT 
            at.*,
            u.username as archived_by_user
        FROM archived_teachers at
        JOIN users u ON at.archived_by = u.id
        ORDER BY at.archived_at DESC
    ");
    $stmt->execute();
    $archived_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error fetching archived teachers: " . $e->getMessage());
    $error = "An error occurred while loading the archived teachers.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Teachers - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/archived_teachers.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-archive"></i>
                <h2>Archived Teachers</h2>
            </div>
            <button class="btn-secondary" onclick="window.location.href='teachers.php'">
                <i class="fas fa-arrow-left"></i> Back to Teachers
            </button>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Archived Date</th>
                        <th>Archived By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archived_teachers as $teacher): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($teacher['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['contact_number']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($teacher['archived_at'])); ?></td>
                            <td><?php echo htmlspecialchars($teacher['archived_by_user']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon restore" 
                                            onclick="restoreTeacher(<?php echo $teacher['teacher_id']; ?>)"
                                            title="Restore Teacher">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
    function restoreTeacher(teacherId) {
        if (!confirm('Are you sure you want to restore this teacher?')) {
            return;
        }

        fetch('restore_teacher.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `teacher_id=${teacherId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Teacher restored successfully');
                location.reload();
            } else {
                throw new Error(data.message || 'Failed to restore teacher');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to restore teacher');
        });
    }
    </script>
</body>
</html> 