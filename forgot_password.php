<?php
require_once 'includes/config.php';
require_once 'includes/email_config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    
    if (empty($username)) {
        $error = 'Please enter your username.';
    } else {
        try {
            // Check if username exists and get user details
            $stmt = $conn->prepare("
                SELECT u.id, u.username, u.user_type,
                    CASE 
                        WHEN u.user_type = 'student' THEN s.email
                        WHEN u.user_type = 'teacher' THEN t.email
                        WHEN u.user_type = 'admin' THEN a.email
                    END as email
                FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN teachers t ON u.id = t.user_id
                LEFT JOIN admins a ON u.id = a.user_id
                WHERE u.username = ? AND u.status = 'active'
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['email']) {
                // Generate unique token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Save reset token
                $stmt = $conn->prepare("
                    INSERT INTO password_resets (user_id, token, expiry)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$user['id'], $token, $expiry]);

                // Send reset email
                $resetLink = "http://{$_SERVER['HTTP_HOST']}/LMS-STMA/reset_password.php?token=" . $token;
                $emailBody = "
                    <h2>Password Reset Request</h2>
                    <p>Hello,</p>
                    <p>We received a request to reset your password for your STMA LMS account.</p>
                    <p>Click the link below to reset your password:</p>
                    <p><a href='{$resetLink}'>{$resetLink}</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <br>
                    <p>Best regards,</p>
                    <p>STMA LMS Team</p>
                ";

                if (sendEmail($user['email'], 'Password Reset Request - STMA LMS', $emailBody)) {
                    $success = 'Password reset instructions have been sent to your email.';
                } else {
                    $error = 'Failed to send reset email. Please try again later.';
                }
            } else {
                // For security, show the same message even if user doesn't exist
                $success = 'If an account exists with this username, password reset instructions will be sent to the associated email.';
            }
        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - STMA LMS</title>
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
            <div class="login-form">
                <h2>Forgot Password</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if (!$success): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                            <p class="help-text">Enter your username (LRN for students)</p>
                        </div>
                        <button type="submit" class="login-btn">Reset Password</button>
                    </form>
                <?php endif; ?>

                <div class="form-footer">
                    <a href="login.php" class="back-to-login">Back to Login</a>
                </div>
            </div>
        </div>

        <div class="guide-container">
            <h2>Password Reset Guide</h2>
            <div class="guide-section">
                <h3>Instructions</h3>
                <ul>
                    <li>Enter your username (LRN for students)</li>
                    <li>A password reset link will be sent to your registered email</li>
                    <li>The reset link will expire after 1 hour</li>
                    <li>Check your spam folder if you don't receive the email</li>
                </ul>
            </div>
            <div class="guide-section">
                <h3>Need Help?</h3>
                <ul>
                    <li>Make sure you're using the correct username</li>
                    <li>Verify that your email address is registered in the system</li>
                    <li>Contact the administrator if you continue to have issues</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html> 