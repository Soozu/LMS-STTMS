<?php
// Fetch teacher information if not already available
if (!isset($teacher) || !$teacher) {
    try {
        $teacherId = $_SESSION['role_id'];
        $stmt = $conn->prepare("
            SELECT 
                t.*,
                u.username,
                u.status
            FROM teachers t
            JOIN users u ON t.user_id = u.id
            WHERE t.id = ? AND u.status = 'active'
        ");
        $stmt->execute([$teacherId]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching teacher data: " . $e->getMessage());
    }
}
?>

<header class="dashboard-header">
    <button class="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="header-left">
        <div class="school-info">
            <h1>St. Thomas More Academy</h1>
            <p>Learning Management System</p>
        </div>
    </div>
    
    <div class="header-right">
        <div class="notifications">
            <button class="notification-btn">
                <i class="fas fa-bell"></i>
                <span class="notification-count">0</span>
            </button>
        </div>

        <div class="user-menu">
            <?php if ($teacher): ?>
                <div class="user-info">
                    <span class="user-name">
                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                    </span>
                    <span class="user-role">Teacher</span>
                </div>
                <div class="user-actions">
                    <a href="profile.php" class="profile-link">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="#" class="logout-button" onclick="showLogoutConfirmation(); return false;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            <?php else: ?>
                <div class="user-info">
                    <span class="user-name">Teacher</span>
                    <span class="user-role">Error loading profile</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Add SweetAlert2 CSS and JS in the head section -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui@5/material-ui.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
async function showLogoutConfirmation() {
    const result = await Swal.fire({
        title: '<span class="logout-title">Logout Confirmation</span>',
        html: `
            <div class="logout-content">
                <div class="logout-icon">
                    <i class="fas fa-sign-out-alt"></i>
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
                            <p class="success-title">Goodbye!</p>
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
</script>

<!-- Add custom styles for SweetAlert -->
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

/* Button styles */
.logout-actions {
    padding-top: 1rem;
    gap: 1rem;
}

.logout-confirm,
.logout-cancel {
    padding: 0.8rem 1.5rem;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
    width: 140px;
}

.logout-confirm {
    background: #8b0000;
    color: white;
}

.logout-confirm:hover {
    background: #6b0000;
    transform: translateY(-2px);
}

.logout-cancel {
    background: #f0f0f0;
    color: #666;
}

.logout-cancel:hover {
    background: #e0e0e0;
    transform: translateY(-2px);
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
</style>
</body>
</html> 