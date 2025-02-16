<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/enrollment_helper.php';
require_once 'mailer/email_config.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: students.php');
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = 'Invalid request';
    header('Location: students.php');
    exit();
}

$transactionStarted = false;

try {
    // Validate input
    $lrn = trim($_POST['lrn']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $contact_number = trim($_POST['contact_number']);
    $guardian_contact = trim($_POST['guardian_contact']);
    
    // Validate LRN
    if (!preg_match('/^\d{12}$/', $lrn)) {
        throw new Exception('Invalid LRN format');
    }
    
    // Validate email
    if (!$email) {
        throw new Exception('Invalid email format');
    }

    // Validate contact numbers if provided
    if ($contact_number && !preg_match('/^09\d{9}$/', $contact_number)) {
        throw new Exception('Invalid contact number format');
    }
    if ($guardian_contact && !preg_match('/^09\d{9}$/', $guardian_contact)) {
        throw new Exception('Invalid guardian contact number format');
    }

    // Check for existing LRN or email
    $stmt = $conn->prepare("SELECT id FROM students WHERE lrn = ? OR email = ?");
    $stmt->execute([$lrn, $email]);
    if ($stmt->fetch()) {
        throw new Exception('A student with this LRN or email already exists');
    }

    // Add this validation after the email validation
    // Validate grade level
    $grade_level = (int)$_POST['grade_level'];
    if ($grade_level < 1 || $grade_level > 6) {
        throw new Exception('Invalid grade level. Must be between 1 and 6.');
    }

    // Validate that section matches grade level
    $stmt = $conn->prepare("SELECT grade_level FROM sections WHERE id = ? AND status = 'active'");
    $stmt->execute([$_POST['section_id']]);
    $section = $stmt->fetch();

    if (!$section) {
        throw new Exception('Invalid section selected');
    }

    if ($section['grade_level'] != $grade_level) {
        throw new Exception('Selected section does not match the grade level');
    }

    // Begin transaction
    $conn->beginTransaction();
    $transactionStarted = true;

    // Generate a random password
    $password = generateRandomPassword();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Create user account
    $stmt = $conn->prepare("INSERT INTO users (username, password, user_type) VALUES (?, ?, 'student')");
    $stmt->execute([$lrn, $hashedPassword]);
    $userId = $conn->lastInsertId();

    // Create student record with all fields
    $stmt = $conn->prepare("
        INSERT INTO students (
            user_id, lrn, first_name, last_name, email, 
            grade_level, gender, birth_date, contact_number,
            address, guardian_name, guardian_contact, section_id
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?,
            ?, ?, ?, ?
        )
    ");
    
    $stmt->execute([
        $userId,
        $lrn,
        trim($_POST['first_name']),
        trim($_POST['last_name']),
        $email,
        $_POST['grade_level'],
        $_POST['gender'],
        $_POST['birth_date'],
        $contact_number,
        $_POST['address'],
        trim($_POST['guardian_name']),
        $guardian_contact,
        $_POST['section_id']
    ]);

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description) 
        VALUES (?, 'Create Student', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Created student account for {$_POST['first_name']} {$_POST['last_name']} (Grade {$_POST['grade_level']})"
    ]);

    // After successful insertion, send email
    $studentName = $_POST['first_name'] . ' ' . $_POST['last_name'];
    if (sendCredentialsEmail($email, $studentName, $lrn, $password)) {
        $conn->commit();
        $transactionStarted = false;
        $_SESSION['success'] = "Student added successfully. Login credentials have been sent to their email.";
    } else {
        throw new Exception("Failed to send email credentials");
    }

} catch (Exception $e) {
    if ($transactionStarted) {
        $conn->rollBack();
    }
    error_log("Error in add_student.php: " . $e->getMessage());
    $_SESSION['error'] = "Failed to add student: " . $e->getMessage();
}

header('Location: students.php');
exit();

function generateRandomPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}
?> 