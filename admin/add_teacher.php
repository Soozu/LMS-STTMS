<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate contact number (11 digits starting with 09)
        $contact = $_POST['contact_number'];
        if (!preg_match('/^09\d{9}$/', $contact)) {
            throw new Exception('Contact number must be 11 digits and start with 09');
        }

        // Validate email
        $email = $_POST['email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Add password validation
        $password = $_POST['password'];
        if (strlen($password) < 8 || 
            !preg_match('/[A-Z]/', $password) || 
            !preg_match('/[a-z]/', $password) || 
            !preg_match('/\d/', $password) || 
            !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            throw new Exception('Password does not meet requirements');
        }

        if ($password !== $_POST['confirm_password']) {
            throw new Exception('Passwords do not match');
        }

        // Generate Employee ID
        $currentYear = date('Y');
        
        // Get the last employee ID for the current year
        $stmt = $conn->prepare("
            SELECT employee_id 
            FROM teachers 
            WHERE employee_id LIKE ? 
            ORDER BY employee_id DESC 
            LIMIT 1
        ");
        $stmt->execute(["TCH-$currentYear-%"]);
        $lastId = $stmt->fetch(PDO::FETCH_COLUMN);

        if ($lastId) {
            // Extract the sequence number and increment
            $sequence = intval(substr($lastId, -3)) + 1;
        } else {
            // Start with 001 if no existing IDs for this year
            $sequence = 1;
        }

        // Format the new employee ID
        $employeeId = sprintf("TCH-%d-%03d", $currentYear, $sequence);

        $conn->beginTransaction();

        // Create user account
        $stmt = $conn->prepare("
            INSERT INTO users (username, password, user_type, status) 
            VALUES (?, ?, 'teacher', 'active')
        ");
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt->execute([$_POST['username'], $hashedPassword]);
        $userId = $conn->lastInsertId();

        // Create teacher record
        $stmt = $conn->prepare("
            INSERT INTO teachers (user_id, employee_id, first_name, last_name, email, contact_number) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $employeeId,
            $_POST['first_name'],
            $_POST['last_name'],
            $email,
            $contact
        ]);

        // Log the action
        $stmt = $conn->prepare("
            INSERT INTO system_logs (user_id, action, description) 
            VALUES (?, 'Add Teacher', ?)
        ");
        $logDescription = "Added new teacher: {$_POST['first_name']} {$_POST['last_name']} ($employeeId)";
        $stmt->execute([$_SESSION['user_id'], $logDescription]);

        $conn->commit();
        $_SESSION['success'] = "Teacher added successfully";
        header('Location: teachers.php');
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header('Location: teachers.php');
        exit();
    }
}
?> 