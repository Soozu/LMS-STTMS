<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

function archiveSchoolYear($conn, $teacherId, $schoolYear) {
    try {
        $conn->beginTransaction();

        // Archive assignments
        $stmt = $conn->prepare("
            UPDATE assignments a
            JOIN classes c ON a.class_id = c.id
            SET a.status = 'archived'
            WHERE c.teacher_id = ? 
            AND c.school_year = ?
        ");
        $stmt->execute([$teacherId, $schoolYear]);

        // Archive classes
        $stmt = $conn->prepare("
            UPDATE classes 
            SET status = 'archived'
            WHERE teacher_id = ? 
            AND school_year = ?
        ");
        $stmt->execute([$teacherId, $schoolYear]);

        // Archive class enrollments
        $stmt = $conn->prepare("
            UPDATE class_enrollments ce
            JOIN classes c ON ce.class_id = c.id
            SET ce.status = 'archived'
            WHERE c.teacher_id = ? 
            AND c.school_year = ?
        ");
        $stmt->execute([$teacherId, $schoolYear]);

        // Log the archiving action
        $stmt = $conn->prepare("
            INSERT INTO system_logs (user_id, action, description)
            VALUES (?, 'Archive School Year', ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Archived school year: $schoolYear"
        ]);

        $conn->commit();
        return ['success' => true, 'message' => 'School year archived successfully'];

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error archiving school year: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to archive school year'];
    }
}

// Add this function to handle retrieval
function retrieveSchoolYear($conn, $teacherId, $schoolYear) {
    try {
        $conn->beginTransaction();

        // Retrieve assignments
        $stmt = $conn->prepare("
            UPDATE assignments a
            JOIN classes c ON a.class_id = c.id
            SET a.status = 'active'
            WHERE c.teacher_id = ? 
            AND c.school_year = ?
        ");
        $stmt->execute([$teacherId, $schoolYear]);

        // Retrieve classes
        $stmt = $conn->prepare("
            UPDATE classes 
            SET status = 'active'
            WHERE teacher_id = ? 
            AND school_year = ?
        ");
        $stmt->execute([$teacherId, $schoolYear]);

        // Retrieve class enrollments
        $stmt = $conn->prepare("
            UPDATE class_enrollments ce
            JOIN classes c ON ce.class_id = c.id
            SET ce.status = 'active'
            WHERE c.teacher_id = ? 
            AND c.school_year = ?
        ");
        $stmt->execute([$teacherId, $schoolYear]);

        // Log the retrieval action
        $stmt = $conn->prepare("
            INSERT INTO system_logs (user_id, action, description)
            VALUES (?, 'Retrieve School Year', ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Retrieved archived school year: $schoolYear"
        ]);

        $conn->commit();
        return ['success' => true, 'message' => 'School year retrieved successfully'];

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error retrieving school year: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to retrieve school year'];
    }
}

// Update the request handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolYear = $_POST['school_year'] ?? null;
    $action = $_POST['action'] ?? 'archive';
    $teacherId = $_SESSION['role_id'];

    if (!$schoolYear) {
        echo json_encode(['success' => false, 'error' => 'School year is required']);
        exit;
    }

    if ($action === 'retrieve') {
        $result = retrieveSchoolYear($conn, $teacherId, $schoolYear);
    } else {
        $result = archiveSchoolYear($conn, $teacherId, $schoolYear);
    }
    
    echo json_encode($result);
    exit;
} 