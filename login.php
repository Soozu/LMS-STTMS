<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if already logged in
if ($auth->isLoggedIn()) {
    $userType = $auth->getUserType();
    switch ($userType) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'teacher':
            header('Location: teacher/dashboard.php');
            break;
        case 'student':
            header('Location: student/dashboard.php');
            break;
    }
    exit();
}

$error = '';
$success = '';

// Check for logout message
if (isset($_SESSION['logout_message'])) {
    $success = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'All fields are required';
    } else {
        // Try to login with each user type until successful
        $userTypes = ['student', 'teacher', 'admin'];
        $loginSuccessful = false;

        foreach ($userTypes as $userType) {
            if ($auth->login($username, $password, $userType)) {
                $loginSuccessful = true;
                // Redirect based on user type
                switch ($userType) {
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        break;
                    case 'teacher':
                        header('Location: teacher/dashboard.php');
                        break;
                    case 'student':
                        header('Location: student/dashboard.php');
                        break;
                }
                exit();
            }
        }

        if (!$loginSuccessful) {
            $error = 'Invalid credentials or account is inactive';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>St. Thomas More Academy Bacoor - LMS Portal</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

            <div class="login-form">
                <h2>LOGIN</h2>
                <div class="success-checkmark">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="success-message">
                    Login successful! Redirecting...
                </div>
                <div class="teacher-success-animation">
                    <div class="teacher-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="welcome-message">Welcome back, Teacher!</div>
                </div>
                <form method="POST" id="loginForm" onsubmit="return handleLogin(event)">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               pattern="\d{12}|\D+"
                               title="LRN must be 12 digits, or enter teacher/admin username"
                               maxlength="12"
                               oninput="validateUsername(this)"
                               required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-field">
                            <input type="password" id="password" name="password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="show-password" viewBox="0 0 16 16">
                                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="login-btn">LOGIN</button>
                </form>
                <div class="form-footer">
                    <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                </div>
            </div>
        </div>

        <div class="guide-container">
            <h2>GUIDE</h2>
            <div class="guide-section">
                <h3>Account Creation</h3>
                <ul>
                    <li>Students: Use your 12-digit LRN as username</li>
                    <li>Teachers: Use your assigned username</li>
                    <li>Only enrolled students and registered teachers can access the system</li>
                </ul>
            </div>
            <div class="guide-section">
                <h3>Logging into your account</h3>
                <ul>
                    <li>The account must be activated</li>
                    <li>Enter your username and password correctly</li>
                    <li>Contact the administrator if you have login issues</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
    function validateUsername(input) {
        // If input contains only numbers
        if (/^\d+$/.test(input.value)) {
            // Enforce exactly 12 digits for LRN
            input.pattern = "\\d{12}";
            input.title = "LRN must be exactly 12 digits";
            
            // Remove any non-numeric characters
            input.value = input.value.replace(/\D/g, '');
            
            // Limit to 12 digits
            if (input.value.length > 12) {
                input.value = input.value.slice(0, 12);
            }
        } else {
            // For non-LRN usernames, allow any characters
            input.pattern = "\\D+";
            input.title = "Enter your username";
        }
    }

    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleButton = document.querySelector('.password-toggle');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="hide-password" viewBox="0 0 16 16">
                    <path d="m10.79 12.912-1.614-1.615a3.5 3.5 0 0 1-4.474-4.474l-2.06-2.06C.938 6.278 0 8 0 8s3 5.5 8 5.5a7.029 7.029 0 0 0 2.79-.588M5.21 3.088A7.028 7.028 0 0 1 8 2.5c5 0 8 5.5 8 5.5s-.939 1.721-2.641 3.238l-2.062-2.062a3.5 3.5 0 0 0-4.474-4.474L5.21 3.089z"/>
                    <path d="M5.525 7.646a2.5 2.5 0 0 0 2.829 2.829l-2.83-2.829zm4.95.708-2.829-2.83a2.5 2.5 0 0 1 2.829 2.829zm3.171 6-12-12 .708-.708 12 12-.708.708z"/>
                </svg>
            `;
        } else {
            passwordInput.type = 'password';
            toggleButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="show-password" viewBox="0 0 16 16">
                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                </svg>
            `;
        }
    }

    async function handleLogin(event) {
        event.preventDefault();
        const form = event.target;
        const submitButton = form.querySelector('.login-btn');
        const formData = new FormData(form);

        try {
            // Start loading animation
            submitButton.classList.add('loading');
            submitButton.disabled = true;

            // Simulate network delay (remove in production)
            await new Promise(resolve => setTimeout(resolve, 1500));

            // Send login request
            const response = await fetch('login_process.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Hide form elements
                form.classList.add('fade-out');
                
                setTimeout(() => {
                    form.style.display = 'none';
                    
                    // Check if it's a teacher login based on the redirect URL
                    if (data.redirect.includes('teacher')) {
                        // Show teacher-specific animation
                        const teacherAnimation = document.querySelector('.teacher-success-animation');
                        const teacherIcon = document.querySelector('.teacher-icon');
                        const welcomeMessage = document.querySelector('.welcome-message');
                        
                        teacherAnimation.style.display = 'block';
                        teacherIcon.classList.add('animate');
                        welcomeMessage.classList.add('animate');
                    } else {
                        // Show default success animation for other users
                        const successCheck = document.querySelector('.success-checkmark');
                        const successMessage = document.querySelector('.success-message');
                        successCheck.style.display = 'block';
                        successMessage.style.display = 'block';
                        successCheck.classList.add('fade-in');
                        successMessage.classList.add('fade-in');
                    }
                    
                    // Redirect after animation
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);
                }, 500);
            } else {
                // Show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-error fade-in';
                errorDiv.textContent = data.message || 'Invalid credentials';
                form.insertBefore(errorDiv, form.firstChild);
                
                // Remove loading state
                submitButton.classList.remove('loading');
                submitButton.disabled = false;
            }
        } catch (error) {
            console.error('Login error:', error);
            // Handle error state
            submitButton.classList.remove('loading');
            submitButton.disabled = false;
            
            // Show generic error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error fade-in';
            errorDiv.textContent = 'An error occurred. Please try again.';
            form.insertBefore(errorDiv, form.firstChild);
        }

        return false;
    }
    </script>
</body>
</html>