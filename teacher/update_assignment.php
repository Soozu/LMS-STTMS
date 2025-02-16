<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$teacherId = $_SESSION['role_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: assignments.php');
    exit();
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Verify teacher owns this assignment
    $stmt = $conn->prepare("
        SELECT a.id 
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE a.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$_POST['assignment_id'], $teacherId]);
    
    if (!$stmt->fetch()) {
        throw new Exception("Unauthorized access");
    }

    // Update assignment details
    $stmt = $conn->prepare("
        UPDATE assignments 
        SET title = ?,
            description = ?,
            due_date = ?,
            status = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $stmt->execute([
        $_POST['title'],
        $_POST['description'],
        $_POST['due_date'],
        $_POST['status'],
        $_POST['assignment_id']
    ]);

    // Handle new file uploads
    if (!empty($_FILES['files']['name'][0])) {
        $uploadDir = '../uploads/assignments/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $stmt = $conn->prepare("
            INSERT INTO assignment_files 
            (assignment_id, file_name, original_name, file_type, file_size) 
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            $fileName = $_FILES['files']['name'][$key];
            $fileType = $_FILES['files']['type'][$key];
            $fileSize = $_FILES['files']['size'][$key];
            
            // Generate unique filename
            $uniqueName = uniqid() . '_' . $fileName;
            $filePath = $uploadDir . $uniqueName;

            // Move uploaded file
            if (move_uploaded_file($tmp_name, $filePath)) {
                $stmt->execute([
                    $_POST['assignment_id'],
                    $uniqueName,
                    $fileName,
                    $fileType,
                    $fileSize
                ]);
            }
        }
    }

    // Log the update
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description)
        VALUES (?, 'Update Assignment', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Updated assignment ID: {$_POST['assignment_id']}"
    ]);

    $conn->commit();
    $_SESSION['success'] = "Assignment updated successfully";

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error updating assignment: " . $e->getMessage());
    $_SESSION['error'] = "Failed to update assignment. Please try again.";
}

// Redirect back to assignments page with class_id
header("Location: assignments.php?class_id=" . $_POST['class_id']);
exit();
?> 