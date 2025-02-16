<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in as student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

require_once 'notifications.php';

// Get counts and items
$notificationCount = getNotificationCount($_SESSION['user_id']);
$messageCount = getMessageCount($_SESSION['user_id']);
$notifications = getUnreadNotifications($_SESSION['user_id']);
$messages = getUnreadMessages($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - STMA LMS</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
    <!-- Add SweetAlert2 CSS and JS after your existing head content -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui@5/material-ui.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <header class="dashboard-header">
        <div class="header-left">
            <button id="sidebar-toggle" class="sidebar-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="school-info">
                <h1>St. Thomas More Academy</h1>
                <p>Learning Management System</p>
            </div>
        </div>
        <div class="header-right">
            <div class="header-actions">
                <button class="action-btn notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                    <div class="dropdown-menu">
                        <div class="dropdown-header">
                            <h3>Notifications</h3>
                            <?php if ($notificationCount > 0): ?>
                                <a href="#" class="mark-all" onclick="markAllNotificationsAsRead()">Mark all as read</a>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <a href="#" class="notification-item unread" 
                                       onclick="handleNotification('<?php echo $notification['type']; ?>', <?php echo $notification['reference_id']; ?>)">
                                        <div class="notification-icon">
                                            <i class="<?php echo getNotificationIcon($notification['type']); ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <p><?php echo htmlspecialchars($notification['notification_title']); ?></p>
                                            <span class="notification-time">
                                                <?php echo getTimeAgo($notification['created_at']); ?>
                                            </span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>No new notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </button>
                <button class="action-btn messages">
                    <i class="fas fa-envelope"></i>
                    <?php if ($messageCount > 0): ?>
                        <span class="badge"><?php echo $messageCount; ?></span>
                    <?php endif; ?>
                    <div class="dropdown-menu">
                        <div class="dropdown-header">
                            <h3>Messages</h3>
                            <a href="messages.php" class="view-all">View All</a>
                        </div>
                        <div class="message-list">
                            <?php if (!empty($messages)): ?>
                                <?php foreach ($messages as $message): ?>
                                    <a href="messages.php?id=<?php echo $message['id']; ?>" class="message-item unread">
                                        <img src="../images/default-avatar.png" alt="Sender Avatar" class="message-avatar">
                                        <div class="message-content">
                                            <div class="message-info">
                                                <span class="sender"><?php echo htmlspecialchars($message['sender_name']); ?></span>
                                                <span class="time"><?php echo getTimeAgo($message['created_at']); ?></span>
                                            </div>
                                            <p><?php echo htmlspecialchars(substr($message['content'], 0, 50)) . '...'; ?></p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-envelope-open"></i>
                                    <p>No new messages</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </button>
            </div>
            <div class="user-menu">
                <img src="../images/default-avatar.png" alt="Student Avatar" class="avatar">
                <div class="user-info">
                    <span class="user-name"><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Student'; ?></span>
                    <span class="user-details">
                        <?php 
                            if (isset($_SESSION['grade_level']) && isset($_SESSION['section'])) {
                                echo 'Grade ' . htmlspecialchars($_SESSION['grade_level']) . 
                                     ' - Section ' . htmlspecialchars($_SESSION['section']);
                            }
                        ?>
                    </span>
                </div>
                <div class="dropdown-menu">
                    <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                    <a href="subjects.php"><i class="fas fa-book"></i> My Subjects</a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="logout-button" onclick="showLogoutConfirmation(); return false;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <script>
        async function showLogoutConfirmation() {
            const result = await Swal.fire({
                title: '<span class="logout-title">Logout Confirmation</span>',
                html: `
                    <div class="logout-content">
                        <div class="logout-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <p class="logout-message">Are you sure you want to end your session?</p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Yes, Logout',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#8b0000',
                cancelButtonColor: '#6e7881',
                reverseButtons: true,
                customClass: {
                    popup: 'logout-popup',
                    actions: 'logout-actions',
                    confirmButton: 'logout-confirm',
                    cancelButton: 'logout-cancel',
                    container: 'logout-container'
                },
                buttonsStyling: false,
                showClass: {
                    popup: 'animate__animated animate__fadeInDown animate__faster'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp animate__faster'
                }
            });

            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    html: `
                        <div class="logout-loading">
                            <div class="logout-loading-icon">
                                <i class="fas fa-circle-notch fa-spin"></i>
                            </div>
                            <div class="logout-loading-text">
                                <p class="loading-title">Logging Out</p>
                                <p class="loading-message">Thank you for using STMA LMS</p>
                            </div>
                        </div>
                    `,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    customClass: {
                        popup: 'logout-popup loading-popup'
                    }
                });

                try {
                    const response = await fetch('../logout.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        await Swal.fire({
                            html: `
                                <div class="logout-success">
                                    <div class="success-icon">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <p class="success-title">See You Later!</p>
                                    <p class="success-message">You have been successfully logged out</p>
                                </div>
                            `,
                            timer: 1500,
                            showConfirmButton: false,
                            customClass: {
                                popup: 'logout-popup success-popup'
                            }
                        });

                        window.location.href = '../login.php';
                    }
                } catch (error) {
                    console.error('Logout error:', error);
                    
                    await Swal.fire({
                        html: `
                            <div class="logout-error">
                                <div class="error-icon">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <p class="error-title">Error</p>
                                <p class="error-message">An error occurred during logout</p>
                            </div>
                        `,
                        showConfirmButton: true,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#8b0000',
                        customClass: {
                            popup: 'logout-popup error-popup',
                            confirmButton: 'logout-confirm'
                        },
                        buttonsStyling: false
                    });
                    
                    window.location.href = '../login.php';
                }
            }
        }

        function markAllNotificationsAsRead() {
            fetch('ajax/mark_notifications_read.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function handleNotification(type, referenceId) {
            let url;
            switch(type) {
                case 'assignment':
                    url = `view_activity.php?id=${referenceId}`;
                    break;
                case 'grade':
                    url = `grades.php?assignment=${referenceId}`;
                    break;
                default:
                    url = '#';
            }
            window.location.href = url;
        }

        function getNotificationIcon(type) {
            switch(type) {
                case 'assignment':
                    return 'fas fa-book';
                case 'grade':
                    return 'fas fa-star';
                default:
                    return 'fas fa-bell';
            }
        }

        function getTimeAgo(timestamp) {
            const now = new Date();
            const past = new Date(timestamp);
            const diff = Math.floor((now - past) / 1000);

            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            return Math.floor(diff / 86400) + ' days ago';
        }
    </script>

    <!-- Update the style section with these enhanced button styles -->
    <style>
        /* Base styles for the popup */
        .logout-popup {
            padding: 2rem;
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            max-width: 400px !important;
        }

        /* Logout content styles */
        .logout-content {
            text-align: center;
            padding: 1rem 0;
        }

        .logout-icon {
            font-size: 3rem;
            color: #8b0000;
            margin-bottom: 1.5rem;
        }

        .logout-title {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .logout-message {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        /* Enhanced Button Styles */
        .logout-actions {
            display: flex !important;
            justify-content: center !important;
            gap: 1rem !important;
            padding-top: 1rem;
        }

        .logout-confirm,
        .logout-cancel {
            position: relative;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 140px;
            overflow: hidden;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Confirm Button Styles */
        .logout-confirm {
            background: linear-gradient(145deg, #8b0000, #a00000);
            color: white;
            box-shadow: 0 4px 15px rgba(139, 0, 0, 0.2);
        }

        .logout-confirm:hover {
            background: linear-gradient(145deg, #a00000, #8b0000);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 0, 0, 0.3);
        }

        .logout-confirm:active {
            transform: translateY(1px);
            box-shadow: 0 2px 10px rgba(139, 0, 0, 0.2);
        }

        /* Cancel Button Styles */
        .logout-cancel {
            background: linear-gradient(145deg, #f0f0f0, #e6e6e6);
            color: #666;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .logout-cancel:hover {
            background: linear-gradient(145deg, #e6e6e6, #f0f0f0);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .logout-cancel:active {
            transform: translateY(1px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Button Ripple Effect */
        .logout-confirm::after,
        .logout-cancel::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s ease-out, height 0.3s ease-out;
        }

        .logout-confirm:active::after,
        .logout-cancel:active::after {
            width: 200px;
            height: 200px;
            opacity: 0;
        }

        /* Loading state styles */
        .logout-loading {
            text-align: center;
            padding: 2rem 0;
        }

        .logout-loading-icon {
            font-size: 3rem;
            color: #8b0000;
            margin-bottom: 1.5rem;
        }

        .loading-title {
            font-size: 1.3rem;
            color: #333;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .loading-message {
            color: #666;
            font-size: 1rem;
        }

        /* Success state styles */
        .logout-success {
            text-align: center;
            padding: 2rem 0;
        }

        .success-icon {
            font-size: 3rem;
            color: #4CAF50;
            margin-bottom: 1.5rem;
        }

        .success-title {
            font-size: 1.3rem;
            color: #333;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .success-message {
            color: #666;
            font-size: 1rem;
        }

        /* Error state styles */
        .logout-error {
            text-align: center;
            padding: 2rem 0;
        }

        .error-icon {
            font-size: 3rem;
            color: #f44336;
            margin-bottom: 1.5rem;
        }

        .error-title {
            font-size: 1.3rem;
            color: #333;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .error-message {
            color: #666;
            font-size: 1rem;
        }

        /* Animation for loading icon */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .fa-circle-notch {
            animation: spin 1s linear infinite;
        }

        /* Responsive styles */
        @media (max-width: 480px) {
            .logout-popup {
                padding: 1.5rem;
                margin: 1rem;
            }

            .logout-icon,
            .logout-loading-icon,
            .success-icon,
            .error-icon {
                font-size: 2.5rem;
                margin-bottom: 1rem;
            }

            .logout-title,
            .loading-title,
            .success-title,
            .error-title {
                font-size: 1.2rem;
            }

            .logout-message,
            .loading-message,
            .success-message,
            .error-message {
                font-size: 0.9rem;
            }

            .logout-confirm,
            .logout-cancel {
                padding: 0.7rem 1.2rem;
                font-size: 0.9rem;
                width: 120px;
            }
        }

        /* Add smooth hover transition for all interactive elements */
        .logout-popup * {
            transition: all 0.3s ease;
        }
    </style>
</body>
</html> 