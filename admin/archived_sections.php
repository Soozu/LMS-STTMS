<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

try {
    // Fetch archived sections with additional details
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            s.status,
            u.username as archived_by_user,
            (SELECT COUNT(*) 
             FROM students st 
             WHERE st.section_id = s.id) as total_students
        FROM archived_sections a
        JOIN sections s ON a.section_id = s.id
        JOIN users u ON a.archived_by = u.id
        ORDER BY a.archived_at DESC
    ");
    $stmt->execute();
    $archived_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error fetching archived sections: " . $e->getMessage());
    $error = "An error occurred while loading the archived sections.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Sections - STMA LMS</title>
    <!-- Include your CSS files -->
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/archived_sections.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-archive"></i>
                <h2>Archived Sections</h2>
            </div>
            <button class="btn-secondary" onclick="window.location.href='sections.php'">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Sections</span>
            </button>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Section Name</th>
                        <th>Grade Level</th>
                        <th>Schedule</th>
                        <th>Archived Date</th>
                        <th>Archived By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archived_sections as $section): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($section['section_name']); ?></td>
                            <td>Grade <?php echo htmlspecialchars($section['grade_level']); ?></td>
                            <td>
                                <?php 
                                    echo date('g:i A', strtotime($section['time_start'])) . ' - ' . 
                                         date('g:i A', strtotime($section['time_end'])); 
                                ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($section['archived_at'])); ?></td>
                            <td><?php echo htmlspecialchars($section['archived_by_user']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon restore" 
                                            onclick="restoreSection(<?php echo $section['section_id']; ?>)"
                                            title="Restore Section">
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
    function restoreSection(sectionId) {
        if (!confirm('Are you sure you want to restore this section?')) {
            return;
        }

        fetch('restore_section.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `section_id=${sectionId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Section restored successfully');
                location.reload();
            } else {
                throw new Error(data.message || 'Failed to restore section');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to restore section');
        });
    }
    </script>
</body>
</html> 