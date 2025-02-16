<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacherId = $_SESSION['role_id'];
$userId = $_SESSION['user_id'];

try {
    // Fetch teacher's information
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            u.username,
            u.status,
            u.last_login
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND u.status = 'active'
    ");
    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        throw new Exception('Teacher not found or inactive');
    }

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['contact_number']);
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Start transaction
        $conn->beginTransaction();

        try {
            // Update basic info including email in teachers table
            $stmt = $conn->prepare("
                UPDATE teachers 
                SET first_name = ?, 
                    last_name = ?, 
                    contact_number = ?,
                    email = ?
                WHERE id = ?
            ");
            $stmt->execute([$firstName, $lastName, $phone, $email, $teacherId]);

            // Update password if provided
            if (!empty($currentPassword) && !empty($newPassword)) {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if (!password_verify($currentPassword, $user['password'])) {
                    throw new Exception('Current password is incorrect');
                }

                if ($newPassword !== $confirmPassword) {
                    throw new Exception('New passwords do not match');
                }

                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
            }

            $conn->commit();
            $success = "Profile updated successfully";
            
            // Refresh teacher data
            $stmt = $conn->prepare("
                SELECT t.*, u.username, u.status, u.last_login
                FROM teachers t
                JOIN users u ON t.user_id = u.id
                WHERE t.id = ?
            ");
            $stmt->execute([$teacherId]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }

} catch (Exception $e) {
    error_log("Error in profile: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - STMA LMS</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h2>My Profile</h2>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="../images/default-avatar.png" alt="Profile Picture">
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h3>
                    <p class="user-role">Teacher</p>
                    <p class="last-login">
                        Last login: <?php echo formatDateTime($teacher['last_login']); ?>
                    </p>
                </div>
            </div>

            <form class="profile-form" method="POST" action="">
                <div class="form-section">
                    <h4>Personal Information</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($teacher['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($teacher['last_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number" 
                                   value="<?php echo htmlspecialchars($teacher['contact_number']); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Change Password</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password">
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Password validation
        document.getElementById('profile-form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;

            if (newPassword || confirmPassword || currentPassword) {
                if (!currentPassword) {
                    e.preventDefault();
                    alert('Please enter your current password');
                    return;
                }
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match');
                    return;
                }
            }
        });
    </script>
</body>
</html> 