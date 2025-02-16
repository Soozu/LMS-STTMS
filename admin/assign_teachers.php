<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: sections.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $section_id = $_POST['section_id'];
        $adviser_id = $_POST['adviser_id'];

        // Start transaction
        $conn->beginTransaction();

        // First, remove existing adviser assignment
        $stmt = $conn->prepare("
            UPDATE section_teachers 
            SET status = 'inactive', is_adviser = 0
            WHERE section_id = ? AND is_adviser = 1
        ");
        $stmt->execute([$section_id]);

        // Then, assign new adviser
        if (!empty($adviser_id)) {
            // Check if teacher already has a record
            $stmt = $conn->prepare("
                SELECT id FROM section_teachers 
                WHERE section_id = ? AND teacher_id = ?
            ");
            $stmt->execute([$section_id, $adviser_id]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing record
                $stmt = $conn->prepare("
                    UPDATE section_teachers 
                    SET status = 'active', is_adviser = 1
                    WHERE section_id = ? AND teacher_id = ?
                ");
                $stmt->execute([$section_id, $adviser_id]);
            } else {
                // Insert new record
                $stmt = $conn->prepare("
                    INSERT INTO section_teachers (section_id, teacher_id, is_adviser, status)
                    VALUES (?, ?, 1, 'active')
                ");
                $stmt->execute([$section_id, $adviser_id]);
            }
        }

        // Commit transaction
        $conn->commit();
        $_SESSION['success'] = "Section adviser assigned successfully";

    } catch(PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error assigning teachers: " . $e->getMessage());
        $_SESSION['error'] = "Failed to assign teachers";
    }

    header('Location: sections.php');
    exit();
}
?> 