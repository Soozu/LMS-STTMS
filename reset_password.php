<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Initialize variables
$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Verify token exists and is valid
if (empty($token)) {
    header('Location: login.php');
    exit();
}

// Check if database connection exists
if (!isset($db)) {
    die("Database connection not available");
}

// Check if token exists and is valid in database
try {
    $stmt = $db->prepare("
        SELECT pr.*, u.username 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? 
        AND pr.expiry > NOW() 
        AND pr.used = 0
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $db->error);
    }
    
    $stmt->bind_param("s", $token);
    if (!$stmt->execute()) {
        throw new Exception("Database execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $resetRequest = $result->fetch_assoc();

    if (!$resetRequest) {
        $error = 'Invalid or expired reset link. Please request a new password reset.';
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    $error = 'A system error occurred. Please try again later.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($password) || empty($confirmPassword)) {
        $error = 'Both password fields are required';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        // Update password and mark token as used
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $db->begin_transaction();
        try {
            // Update user password
            $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $resetRequest['user_id']);
            $updateStmt->execute();
            
            // Mark token as used
            $markUsedStmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $markUsedStmt->bind_param("i", $resetRequest['id']);
            $markUsedStmt->execute();
            
            $db->commit();
            $success = 'Password has been successfully reset. You can now login with your new password.';
            
            // Log the password reset
            $logStmt = $db->prepare("
                INSERT INTO system_logs (user_id, action, description) 
                VALUES (?, 'Password Reset', 'User reset their password')
            ");
            $logStmt->bind_param("i", $resetRequest['user_id']);
            $logStmt->execute();
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - STMA LMS</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <img src="images/logo.png" alt="School Logo" class="logo">
            <div class="school-name">
                <h1>St. Thomas More Academy Bacoor</h1>
                <p>Learning Management System Portal</p>
            </div>
        </div>
    </div>

    <div class="main-container">
        <div class="login-container">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <p><a href="login.php">Click here to login</a></p>
                </div>
            <?php elseif (!$error): ?>
                <div class="login-form">
                    <h2>Reset Password</h2>
                    <p class="reset-info">Setting new password for: <?php echo htmlspecialchars($resetRequest['username']); ?></p>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   minlength="8">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required 
                                   minlength="8">
                        </div>
                        <button type="submit" class="login-btn">Reset Password</button>
                    </form>
                </div>
            <?php endif; ?>
            
            <div class="form-footer">
                <a href="login.php">Back to Login</a>
            </div>
        </div>

        <div class="guide-container">
            <h2>Password Requirements</h2>
            <div class="guide-section">
                <ul>
                    <li>Minimum 8 characters long</li>
                    <li>Both passwords must match</li>
                    <li>Choose a strong password that includes:</li>
                    <ul>
                        <li>Upper and lowercase letters</li>
                        <li>Numbers</li>
                        <li>Special characters</li>
                    </ul>
                </ul>
            </div>
        </div>
    </div>
</body>
</html> 