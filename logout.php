<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Get user type before logout for animation
$userType = $_SESSION['user_type'] ?? '';

// Perform logout
$auth->logout();

// Return JSON response for AJAX request
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    echo json_encode([
        'success' => true,
        'userType' => $userType,
        'message' => 'Logout successful'
    ]);
    exit;
}

// Regular redirect for non-AJAX requests
$_SESSION['logout_message'] = 'You have been successfully logged out.';
header('Location: login.php');
exit();
?> 