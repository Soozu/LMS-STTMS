<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - STMA LMS</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Ready to Leave?',
                text: "Are you sure you want to end your current session?",
                icon: 'question',
                background: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#8b0000',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-sign-out-alt"></i> Logout',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                customClass: {
                    popup: 'swal-wide',
                    title: 'swal-title',
                    content: 'swal-text',
                    confirmButton: 'swal-confirm',
                    cancelButton: 'swal-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../logout.php';
                }
            });
        }
    </script>
</head>
<body>
    <header class="dashboard-header">
        <div class="header-left">
            <button id="sidebar-toggle" class="sidebar-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="school-info">
                <h1>STMA Admin Panel</h1>
                <p>Learning Management System</p>
            </div>
        </div>
        <div class="header-right">
            <div class="header-actions">
                <button class="action-btn system-logs">
                    <i class="fas fa-history"></i>
                    <span class="badge" id="logsCount">0</span>
                    <div class="dropdown-menu">
                        <div class="dropdown-header">
                            <h3>Recent System Logs</h3>
                            <a href="system_logs.php" class="view-all">View All</a>
                        </div>
                        <div class="logs-list">
                            <?php
                            try {
                                $stmt = $conn->prepare("
                                    SELECT sl.*, u.username, u.user_type
                                    FROM system_logs sl 
                                    JOIN users u ON sl.user_id = u.id 
                                    ORDER BY sl.created_at DESC 
                                    LIMIT 5
                                ");
                                $stmt->execute();
                                $logs = $stmt->fetchAll();
                                
                                // Count for badge
                                echo "<script>document.getElementById('logsCount').textContent = '" . count($logs) . "';</script>";
                                
                                foreach ($logs as $log) {
                                    $iconClass = 'fa-info-circle';
                                    if (strpos(strtolower($log['action']), 'create') !== false) {
                                        $iconClass = 'fa-plus-circle';
                                    } elseif (strpos(strtolower($log['action']), 'update') !== false) {
                                        $iconClass = 'fa-edit';
                                    } elseif (strpos(strtolower($log['action']), 'delete') !== false) {
                                        $iconClass = 'fa-trash';
                                    }
                                    
                                    echo '<div class="log-item">';
                                    echo '<div class="log-icon"><i class="fas ' . $iconClass . '"></i></div>';
                                    echo '<div class="log-content">';
                                    echo '<div class="log-header">';
                                    echo '<span class="log-user">' . htmlspecialchars($log['username']) . '</span>';
                                    echo '<span class="log-type">' . htmlspecialchars($log['action']) . '</span>';
                                    echo '</div>';
                                    echo '<p>' . htmlspecialchars($log['description']) . '</p>';
                                    echo '<span class="log-time">' . date('M d, Y H:i', strtotime($log['created_at'])) . '</span>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            } catch (PDOException $e) {
                                error_log("Error fetching system logs: " . $e->getMessage());
                            }
                            ?>
                        </div>
                    </div>
                </button>
            </div>
            <div class="user-menu">
                <img src="../images/default-avatar.png" alt="Admin Avatar" class="avatar">
                <div class="user-info">
                    <span class="user-name">Admin User</span>
                    <span class="user-role">Administrator</span>
                </div>
                <div class="dropdown-menu">
                    <a href="#"><i class="fas fa-user"></i> Profile</a>
                    <a href="#"><i class="fas fa-cog"></i> Settings</a>
                    <a href="#"><i class="fas fa-key"></i> Change Password</a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="logout" onclick="confirmLogout()">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>
</body>
</html> 