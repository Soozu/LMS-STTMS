<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$studentId = $_SESSION['role_id'];

try {
    // Fetch announcements for student's classes
    $stmt = $conn->prepare("
        SELECT 
            'admin' as announcement_type,
            a.id,
            a.title,
            a.content,
            a.created_at,
            a.status,
            NULL as teacher_fname,
            NULL as teacher_lname,
            NULL as subject_name,
            NULL as section_name,
            NULL as grade_level,
            NULL as event_date
        FROM announcements a
        WHERE a.status = 'active'
        
        UNION ALL
        
        SELECT 
            'teacher' as announcement_type,
            ta.id,
            ta.title,
            ta.content,
            ta.created_at,
            ta.status,
            t.first_name as teacher_fname,
            t.last_name as teacher_lname,
            s.subject_name,
            sec.section_name,
            sec.grade_level,
            ta.event_date
        FROM teacher_announcements ta
        JOIN classes cl ON ta.class_id = cl.id
        JOIN class_enrollments ce ON cl.id = ce.class_id
        JOIN teachers t ON ta.teacher_id = t.id
        JOIN subjects s ON cl.subject_id = s.id
        JOIN sections sec ON cl.section_id = sec.id
        WHERE ce.student_id = ? 
        AND ce.status = 'active'
        AND ta.status = 'active'
        
        ORDER BY created_at DESC
    ");
    $stmt->execute([$studentId]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching announcements: " . $e->getMessage());
    $error = "An error occurred while loading announcements.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/announcements.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>Announcements</h2>
        </div>

        <div class="announcements-container">
            <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card <?php echo $announcement['announcement_type']; ?>">
                        <div class="announcement-header">
                            <h3 style="color: #000000;"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <span class="timestamp" style="color: #000000;">
                                <?php echo formatDateTime($announcement['created_at']); ?>
                            </span>
                        </div>
                        <div class="announcement-meta">
                            <?php if ($announcement['announcement_type'] !== 'admin'): ?>
                                <span class="subject" style="color: #000000;">
                                    <i class="fas fa-book" style="color: #000000;"></i>
                                    <?php echo htmlspecialchars($announcement['subject_name']); ?>
                                </span>
                                <span class="teacher" style="color: #000000;">
                                    <i class="fas fa-user-tie" style="color: #000000;"></i>
                                    <?php echo htmlspecialchars($announcement['teacher_fname'] . ' ' . $announcement['teacher_lname']); ?>
                                </span>
                                <?php if ($announcement['event_date']): ?>
                                <span class="event-date" style="color: #000000;">
                                    <i class="fas fa-calendar" style="color: #000000;"></i>
                                    <?php echo date('F j, Y', strtotime($announcement['event_date'])); ?>
                                </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="admin-badge" style="color: #000000;">
                                    <i class="fas fa-shield-alt" style="color: #000000;"></i>
                                    Admin Announcement
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="announcement-preview" style="color: #000000;">
                            <?php 
                            $content = htmlspecialchars($announcement['content']);
                            echo nl2br(strlen($content) > 200 ? substr($content, 0, 200) . "..." : $content); 
                            ?>
                        </div>
                        <div class="announcement-actions">
                            <button onclick="viewAnnouncementDetails(<?php echo htmlspecialchars(json_encode($announcement)); ?>)" class="view-details-btn">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bullhorn"></i>
                    <h3>No Announcements</h3>
                    <p>There are no announcements at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add the modal for viewing details -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="modalContent"></div>
        </div>
    </div>

    <!-- Add JavaScript for handling the modal -->
    <script>
    function viewAnnouncementDetails(announcement) {
        const modal = document.getElementById('announcementModal');
        const modalContent = document.getElementById('modalContent');
        
        let detailsHtml = `
            <div class="announcement-details">
                <h2 style="color: #000000;">${announcement.title}</h2>
                
                <div class="details-meta">
                    <div class="meta-item">
                        <i class="fas fa-clock" style="color: #000000;"></i>
                        <span style="color: #000000;">${formatDateTime(announcement.created_at)}</span>
                    </div>
                    
                    ${announcement.announcement_type !== 'admin' ? `
                        <div class="meta-item">
                            <i class="fas fa-book" style="color: #000000;"></i>
                            <span style="color: #000000;">${announcement.subject_name}</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-user-tie" style="color: #000000;"></i>
                            <span style="color: #000000;">${announcement.teacher_fname} ${announcement.teacher_lname}</span>
                        </div>
                        ${announcement.event_date ? `
                            <div class="meta-item">
                                <i class="fas fa-calendar" style="color: #000000;"></i>
                                <span style="color: #000000;">${formatDate(announcement.event_date)}</span>
                            </div>
                        ` : ''}
                    ` : `
                        <div class="meta-item">
                            <i class="fas fa-shield-alt" style="color: #000000;"></i>
                            <span style="color: #000000;">Admin Announcement</span>
                        </div>
                    `}
                </div>
                
                <div class="details-content" style="color: #000000;">
                    ${announcement.content.replace(/\n/g, '<br>')}
                </div>
            </div>
        `;
        
        modalContent.innerHTML = detailsHtml;
        modal.style.display = "block";
    }

    // Helper function to format dates
    function formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    // Helper function to format date and time
    function formatDateTime(dateString) {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Close modal when clicking the X or outside the modal
    document.querySelector('.close').onclick = function() {
        document.getElementById('announcementModal').style.display = "none";
    }

    window.onclick = function(event) {
        const modal = document.getElementById('announcementModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
    </script>
</body>
</html> 