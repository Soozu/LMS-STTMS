<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required'
        ]);
        exit;
    }

    // Try to login with each user type until successful
    $userTypes = ['student', 'teacher', 'admin'];
    $loginSuccessful = false;
    $redirect = '';

    foreach ($userTypes as $userType) {
        if ($auth->login($username, $password, $userType)) {
            $loginSuccessful = true;
            
            // Set redirect based on user type
            switch ($userType) {
                case 'admin':
                    $redirect = 'admin/dashboard.php';
                    break;
                case 'teacher':
                    $redirect = 'teacher/dashboard.php';
                    break;
                case 'student':
                    $redirect = 'student/dashboard.php';
                    break;
            }
            break;
        }
    }

    if ($loginSuccessful) {
        echo json_encode([
            'success' => true,
            'redirect' => $redirect
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials or account is inactive'
        ]);
    }
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Invalid request method'
]); 