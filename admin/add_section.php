<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sections.php');
    exit();
}

try {
    // Validate inputs
    $grade_level = filter_input(INPUT_POST, 'grade_level', FILTER_VALIDATE_INT);
    $section_name = trim($_POST['section_name']);
    $time_start = $_POST['time_start'];
    $time_end = $_POST['time_end'];

    if (!$grade_level || $grade_level < 1 || $grade_level > 6) {
        throw new Exception('Invalid grade level');
    }

    if (empty($section_name)) {
        throw new Exception('All fields are required');
    }

    // Validate time format and range
    $start_time = strtotime($time_start);
    $end_time = strtotime($time_end);
    
    if (!$start_time || !$end_time || $end_time <= $start_time) {
        throw new Exception('Invalid time range');
    }

    // Check for duplicate sections in the same grade level
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM sections 
        WHERE grade_level = ? AND section_name = ? AND status = 'active'
    ");
    $stmt->execute([$grade_level, $section_name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        throw new Exception('A section with this name already exists in this grade level');
    }

    // Insert new section
    $stmt = $conn->prepare("
        INSERT INTO sections (
            grade_level, 
            section_name, 
            time_start, 
            time_end, 
            status
        ) VALUES (?, ?, ?, ?, 'active')
    ");

    $stmt->execute([
        $grade_level,
        $section_name,
        $time_start,
        $time_end
    ]);

    // Log the action
    $section_id = $conn->lastInsertId();
    $stmt = $conn->prepare("
        INSERT INTO system_logs (user_id, action, description)
        VALUES (?, 'Add Section', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Added new section: {$section_name} for Grade {$grade_level}"
    ]);

    $_SESSION['success'] = "Section added successfully";

    // After successfully creating a section, create default classes
    createDefaultClasses($conn, $section_id);

} catch(Exception $e) {
    error_log("Error adding section: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

header('Location: sections.php');
exit();

// After successfully creating a section, create default classes
function createDefaultClasses($conn, $section_id) {
    try {
        // Get all active subjects
        $stmt = $conn->prepare("
            SELECT id 
            FROM subjects 
            WHERE status = 'active'
        ");
        $stmt->execute();
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create a class for each subject
        $stmt = $conn->prepare("
            INSERT INTO classes 
            (section_id, subject_id, status, created_at) 
            VALUES (?, ?, 'active', NOW())
        ");

        foreach ($subjects as $subject) {
            $stmt->execute([$section_id, $subject['id']]);
        }

        return true;
    } catch (PDOException $e) {
        error_log("Error creating default classes: " . $e->getMessage());
        return false;
    }
}
?> 