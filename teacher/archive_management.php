<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacherId = $_SESSION['role_id'];

// Handle auto-archive settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO archive_settings 
                (teacher_id, school_year, auto_archive) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                auto_archive = VALUES(auto_archive)
        ");
        
        $stmt->execute([
            $teacherId,
            $_POST['school_year'],
            isset($_POST['auto_archive']) ? 1 : 0
        ]);
        
        $_SESSION['success'] = "Archive settings updated successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update settings: " . $e->getMessage();
    }
}

// Fetch active and archived academic years
try {
    // Fetch active academic years
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            c.school_year,
            c.status,
            ars.auto_archive,
            COUNT(DISTINCT c.id) as class_count,
            COUNT(DISTINCT ce.student_id) as student_count,
            COUNT(DISTINCT a.id) as assignment_count
        FROM classes c
        LEFT JOIN archive_settings ars ON c.school_year = ars.school_year 
            AND ars.teacher_id = c.teacher_id
        LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
        LEFT JOIN assignments a ON c.id = a.class_id AND a.status = 'active'
        WHERE c.teacher_id = ?
        GROUP BY c.school_year, c.status
        ORDER BY c.school_year DESC
    ");
    $stmt->execute([$teacherId]);
    $academicYears = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching data: " . $e->getMessage();
    $academicYears = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Management - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/archive_management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="archive-container">
            <div class="page-header">
                <div class="header-content">
                    <h2><i class="fas fa-archive"></i> Academic Year Archive</h2>
                    <p class="header-description">Manage and archive your academic years, classes, and assignments</p>
                </div>
                <div class="header-actions">
                    <button class="btn-help" onclick="showHelp()">
                        <i class="fas fa-question-circle"></i>
                        Help Guide
                    </button>
                </div>
            </div>

            <?php if (isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
                <div class="alert <?php echo isset($_SESSION['success']) ? 'alert-success' : 'alert-error'; ?>">
                    <i class="fas <?php echo isset($_SESSION['success']) ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <span>
                        <?php 
                            echo isset($_SESSION['success']) ? $_SESSION['success'] : $_SESSION['error'];
                            unset($_SESSION['success']);
                            unset($_SESSION['error']);
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="archive-overview">
                <div class="overview-card total-classes">
                    <i class="fas fa-chalkboard"></i>
                    <div class="overview-details">
                        <span class="overview-value"><?php echo array_sum(array_column($academicYears, 'class_count')); ?></span>
                        <span class="overview-label">Total Classes</span>
                    </div>
                </div>
                <div class="overview-card total-students">
                    <i class="fas fa-users"></i>
                    <div class="overview-details">
                        <span class="overview-value"><?php echo array_sum(array_column($academicYears, 'student_count')); ?></span>
                        <span class="overview-label">Total Students</span>
                    </div>
                </div>
                <div class="overview-card total-assignments">
                    <i class="fas fa-tasks"></i>
                    <div class="overview-details">
                        <span class="overview-value"><?php echo array_sum(array_column($academicYears, 'assignment_count')); ?></span>
                        <span class="overview-label">Total Assignments</span>
                    </div>
                </div>
            </div>

            <div class="academic-years">
                <?php if (!empty($academicYears)): ?>
                    <?php foreach ($academicYears as $year): ?>
                        <div class="year-card <?php echo ($year['auto_archive'] ?? false) ? 'auto-archive-enabled' : ''; ?>">
                            <div class="year-header">
                                <div class="year-title">
                                    <h3>Academic Year <?php echo htmlspecialchars($year['school_year'] ?? ''); ?></h3>
                                    <span class="status-badge <?php echo htmlspecialchars($year['status'] ?? 'active'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($year['status'] ?? 'Active')); ?>
                                    </span>
                                </div>
                                <div class="quick-actions">
                                    <a href="year_details.php?year=<?php echo urlencode($year['school_year']); ?>" 
                                       class="btn-view">
                                        <i class="fas fa-eye"></i>
                                        View Details
                                    </a>
                                </div>
                            </div>

                            <div class="year-stats">
                                <div class="stat-item">
                                    <i class="fas fa-chalkboard"></i>
                                    <div class="stat-details">
                                        <span class="stat-value"><?php echo (int)($year['class_count'] ?? 0); ?></span>
                                        <span class="stat-label">Classes</span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    <div class="stat-details">
                                        <span class="stat-value"><?php echo (int)($year['student_count'] ?? 0); ?></span>
                                        <span class="stat-label">Students</span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-tasks"></i>
                                    <div class="stat-details">
                                        <span class="stat-value"><?php echo (int)($year['assignment_count'] ?? 0); ?></span>
                                        <span class="stat-label">Assignments</span>
                                    </div>
                                </div>
                            </div>

                            <form action="archive_management.php" method="POST" class="archive-settings-form">
                                <input type="hidden" name="school_year" 
                                       value="<?php echo htmlspecialchars($year['school_year'] ?? ''); ?>">
                                
                                <div class="settings-grid">
                                    <div class="form-group">
                                        <label class="switch-label">
                                            <span>Auto-Archive</span>
                                            <div class="switch">
                                                <input type="checkbox" name="auto_archive" 
                                                       <?php echo ($year['auto_archive'] ?? false) ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </div>
                                        </label>
                                        <p class="setting-description">
                                            Academic year will automatically archive on May 16
                                        </p>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="update_settings" class="btn-save">
                                        <i class="fas fa-save"></i> Save Settings
                                    </button>
                                    <?php if (($year['status'] ?? 'active') === 'active'): ?>
                                        <button type="button" 
                                                onclick="archiveYear('<?php echo htmlspecialchars($year['school_year'] ?? ''); ?>')" 
                                                class="btn-archive">
                                            <i class="fas fa-archive"></i> Archive Now
                                        </button>
                                    <?php else: ?>
                                        <button type="button" 
                                                onclick="retrieveYear('<?php echo htmlspecialchars($year['school_year'] ?? ''); ?>')" 
                                                class="btn-retrieve">
                                            <i class="fas fa-box-open"></i> Retrieve Data
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-folder-open"></i>
                        <p>No academic years found</p>
                        <span>Start by creating classes for this academic year</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Help Modal -->
    <div id="helpModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-question-circle"></i> Archive Management Guide</h3>
                <button class="close-modal" onclick="closeHelp()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h4><i class="fas fa-info-circle"></i> About Academic Year Archive</h4>
                    <p>The archive system helps you organize and maintain records of past academic years while keeping your current year organized.</p>
                </div>

                <div class="help-section">
                    <h4><i class="fas fa-clock"></i> Automatic Archiving</h4>
                    <ul>
                        <li>Enable auto-archive to automatically store your academic year data on May 16</li>
                        <li>All classes, assignments, and student records will be archived</li>
                        <li>Archived data remains accessible but protected from changes</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h4><i class="fas fa-hand-pointer"></i> Manual Archiving</h4>
                    <ul>
                        <li>Use "Archive Now" to immediately archive an academic year</li>
                        <li>Useful when you need to archive before the automatic date</li>
                        <li>Cannot be undone - please archive with care</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h4><i class="fas fa-exclamation-triangle"></i> Important Notes</h4>
                    <ul>
                        <li>Archived data cannot be modified</li>
                        <li>Students can still view their archived records</li>
                        <li>Make sure all grades are finalized before archiving</li>
                        <li>Keep auto-archive enabled for hassle-free year-end processing</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <button class="scroll-top" id="scrollTop" title="Scroll to top">
        <i class="fas fa-chevron-up"></i>
    </button>

    <script>
    function archiveYear(schoolYear) {
        if (!confirm('Are you sure you want to archive this academic year? This action cannot be undone.')) {
            return;
        }

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
                alert('Academic year archived successfully');
                window.location.reload();
            } else {
                alert('Failed to archive: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while archiving');
        });
    }

    function showHelp() {
        document.getElementById('helpModal').classList.add('active');
        document.querySelector('.modal-content').scrollTop = 0;
    }

    function closeHelp() {
        document.getElementById('helpModal').classList.remove('active');
    }

    document.addEventListener('click', function(event) {
        const modal = document.getElementById('helpModal');
        if (event.target === modal) {
            closeHelp();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeHelp();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const scrollButton = document.getElementById('scrollTop');
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollButton.classList.add('visible');
            } else {
                scrollButton.classList.remove('visible');
            }
        });
        
        scrollButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    });

    function retrieveYear(schoolYear) {
        if (!confirm('Are you sure you want to retrieve this archived academic year? This will make the data active again.')) {
            return;
        }

        fetch('archive_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `school_year=${encodeURIComponent(schoolYear)}&action=retrieve`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Academic year retrieved successfully');
                window.location.reload();
            } else {
                alert('Failed to retrieve: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while retrieving data');
        });
    }
    </script>
</body>
</html> 