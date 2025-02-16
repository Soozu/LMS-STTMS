<?php
function enrollStudentInSectionClasses($conn, $student_id, $section_id) {
    try {
        error_log(date('[Y-m-d H:i:s] ') . "Starting enrollment for student $student_id in section $section_id");
        
        // Check if we're already in a transaction
        $isNewTransaction = !$conn->inTransaction();
        if ($isNewTransaction) {
            $conn->beginTransaction();
        }

        // 1. Get section grade level
        $stmt = $conn->prepare("SELECT grade_level FROM sections WHERE id = ?");
        $stmt->execute([$section_id]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$section) {
            error_log(date('[Y-m-d H:i:s] ') . "Section not found");
            return false;
        }
        
        $grade_level = $section['grade_level'];
        error_log(date('[Y-m-d H:i:s] ') . "Grade level: $grade_level");

        // 2. Get all subjects for this grade
        $stmt = $conn->prepare("
            SELECT * FROM subjects 
            WHERE status = 'active' 
            AND (grade_level = ? OR grade_level IS NULL)
        ");
        $stmt->execute([$grade_level]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log(date('[Y-m-d H:i:s] ') . "Found " . count($subjects) . " subjects");
        
        if (empty($subjects)) {
            error_log(date('[Y-m-d H:i:s] ') . "No subjects found for grade $grade_level. Please make sure subjects are added for this grade level.");
            return false;
        }

        // Add debug info about found subjects
        foreach ($subjects as $subject) {
            error_log(date('[Y-m-d H:i:s] ') . "Found subject: {$subject['subject_name']} (ID: {$subject['id']})");
        }

        try {
            // 3. Create classes and enroll student
            $school_year = date('Y') . '-' . (date('Y') + 1);
            
            foreach ($subjects as $subject) {
                error_log(date('[Y-m-d H:i:s] ') . "Processing subject: " . $subject['subject_name']);
                
                // First create or get class
                $stmt = $conn->prepare("
                    SELECT id FROM classes 
                    WHERE section_id = ? 
                    AND subject_id = ? 
                    AND school_year = ? 
                    AND status = 'active'
                ");
                $stmt->execute([$section_id, $subject['id'], $school_year]);
                $existing_class = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_class) {
                    $class_id = $existing_class['id'];
                    error_log(date('[Y-m-d H:i:s] ') . "Found existing class ID: $class_id");
                } else {
                    // Create new class
                    $stmt = $conn->prepare("
                        INSERT INTO classes 
                        (section_id, subject_id, grade_level, school_year, status, created_at) 
                        VALUES 
                        (?, ?, ?, ?, 'active', NOW())
                    ");
                    
                    $stmt->execute([
                        $section_id,
                        $subject['id'],
                        $grade_level,
                        $school_year
                    ]);
                    
                    $class_id = $conn->lastInsertId();
                    error_log(date('[Y-m-d H:i:s] ') . "Created new class ID: $class_id");
                }

                // Then enroll student
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO class_enrollments 
                        (class_id, student_id, status, enrollment_date, created_at)
                        VALUES 
                        (?, ?, 'active', NOW(), NOW())
                    ");
                    
                    $stmt->execute([$class_id, $student_id]);
                    error_log(date('[Y-m-d H:i:s] ') . "Enrolled in class $class_id");
                } catch (PDOException $e) {
                    // If enrollment fails due to duplicate, it's ok
                    if ($e->getCode() != 23000) { // 23000 is duplicate entry error
                        throw $e;
                    }
                    error_log(date('[Y-m-d H:i:s] ') . "Student already enrolled in class $class_id");
                }
            }

            // Only commit if we started the transaction
            if ($isNewTransaction) {
                $conn->commit();
            }
            
            error_log(date('[Y-m-d H:i:s] ') . "Enrollment completed successfully");
            return true;

        } catch (Exception $e) {
            // Only rollback if we started the transaction
            if ($isNewTransaction && $conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }

    } catch (Exception $e) {
        error_log(date('[Y-m-d H:i:s] ') . "Error in enrollStudentInSectionClasses: " . $e->getMessage());
        error_log(date('[Y-m-d H:i:s] ') . "Stack trace: " . $e->getTraceAsString());
        return false;
    }
}
?> 