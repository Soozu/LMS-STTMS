<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: teachers.php');
    exit();
}

try {
    // Validate inputs
    $teacher_id = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
    $employee_id = trim($_POST['employee_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $username = trim($_POST['username']);
    $status = $_POST['status'];

    if (!$teacher_id || empty($employee_id) || empty($first_name) || empty($last_name) || 
        empty($email) || empty($contact_number) || empty($username)) {
        throw new Exception('All fields are required');
    }

    // Get current teacher data
    $stmt = $conn->prepare("SELECT user_id, employee_id FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $current_teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_teacher) {
        throw new Exception('Teacher not found');
    }

    // Check for duplicate employee ID
    if ($employee_id !== $current_teacher['employee_id']) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM teachers WHERE employee_id = ? AND id != ?");
        $stmt->execute([$employee_id, $teacher_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            throw new Exception('Employee ID already exists');
        }
    }

    // Start transaction
    $conn->beginTransaction();

    // Update teacher record
    $stmt = $conn->prepare("
        UPDATE teachers 
        SET employee_id = ?,
            first_name = ?,
            last_name = ?,
            email = ?,
            contact_number = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $employee_id,
        $first_name,
        $last_name,
        $email,
        $contact_number,
        $teacher_id
    ]);

    // Update user status
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$status, $current_teacher['user_id']]);

    // Log the action
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description)
        VALUES (?, 'Update Teacher', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Updated teacher: {$first_name} {$last_name}"
    ]);

    $conn->commit();
    $_SESSION['success'] = "Teacher updated successfully";

} catch(Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error updating teacher: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

header('Location: teachers.php');
exit();
?> 