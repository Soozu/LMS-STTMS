<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/enrollment_helper.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: students.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        $student_id = $_POST['student_id'];
        
        // Get current section_id
        $stmt = $conn->prepare("SELECT section_id FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $current_section = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_section) {
            throw new Exception("Student not found");
        }

        // Update student information
        $stmt = $conn->prepare("
            UPDATE students 
            SET lrn = ?,
                first_name = ?,
                last_name = ?,
                gender = ?,
                birth_date = ?,
                address = ?,
                contact_number = ?,
                guardian_name = ?,
                guardian_contact = ?,
                section_id = ?,
                grade_level = (SELECT grade_level FROM sections WHERE id = ?)
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['lrn'],
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['gender'],
            $_POST['birthdate'],
            $_POST['address'],
            $_POST['contact_number'],
            $_POST['guardian_name'],
            $_POST['guardian_contact'],
            $_POST['section_id'],
            $_POST['section_id'], // For getting grade_level from sections
            $student_id
        ]);

        // If section has changed, update enrollments
        if ($current_section['section_id'] != $_POST['section_id']) {
            // Deactivate current enrollments
            $stmt = $conn->prepare("
                UPDATE class_enrollments ce
                JOIN classes c ON ce.class_id = c.id
                SET ce.status = 'dropped'
                WHERE ce.student_id = ?
                AND c.section_id = ?
                AND ce.status = 'active'
            ");
            $stmt->execute([$student_id, $current_section['section_id']]);

            // Enroll in new section classes
            if (!enrollStudentInSectionClasses($conn, $student_id, $_POST['section_id'])) {
                throw new Exception("Failed to enroll student in new section classes");
            }
        }

        // Log the update
        $stmt = $conn->prepare("
            INSERT INTO system_logs (user_id, action, description)
            VALUES (?, 'Update Student', ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Updated student: {$_POST['first_name']} {$_POST['last_name']}"
        ]);

        $conn->commit();
        $_SESSION['success'] = "Student information has been updated successfully";

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error updating student: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: students.php');
    exit();
}

// Get sections for the dropdown
try {
    $stmt = $conn->prepare("
        SELECT id, grade_level, section_name 
        FROM sections 
        WHERE status = 'active' 
        ORDER BY grade_level ASC, section_name ASC
    ");
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching sections: " . $e->getMessage());
    $sections = [];
}
?> 