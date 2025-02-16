<?php
require_once '../includes/config.php';

try {
    // Get current date
    $currentDate = date('Y-m-d');
    
    // Check for academic year end date (May 16)
    $academicYearEnd = (date('m') < 6 ? date('Y') : date('Y') + 1) . '-05-16';

    if ($currentDate == $academicYearEnd) {
        // Get settings where auto-archive is enabled
        $stmt = $conn->prepare("
            SELECT DISTINCT 
                c.teacher_id,
                c.school_year
            FROM classes c
            JOIN archive_settings ars ON c.school_year = ars.school_year 
                AND ars.teacher_id = c.teacher_id
            WHERE ars.auto_archive = 1 
            AND c.status = 'active'
        ");
        $stmt->execute();
        $archiveSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($archiveSettings as $setting) {
            // Archive all data for the academic year
            $stmt = $conn->prepare("
                UPDATE assignments a
                JOIN classes c ON a.class_id = c.id
                SET a.status = 'archived'
                WHERE c.teacher_id = ?
                AND c.school_year = ?
            ");
            $stmt->execute([
                $setting['teacher_id'],
                $setting['school_year']
            ]);

            // Archive classes
            $stmt = $conn->prepare("
                UPDATE classes 
                SET status = 'archived'
                WHERE teacher_id = ?
                AND school_year = ?
            ");
            $stmt->execute([
                $setting['teacher_id'],
                $setting['school_year']
            ]);

            // Log the archive action
            $stmt = $conn->prepare("
                INSERT INTO system_logs (user_id, action, description)
                VALUES (?, 'Auto Archive', ?)
            ");
            $stmt->execute([
                $setting['teacher_id'],
                "Automatically archived school year {$setting['school_year']}"
            ]);
        }
    }

} catch (Exception $e) {
    error_log("Auto-archive error: " . $e->getMessage());
} 