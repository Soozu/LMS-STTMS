<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../vendor/autoload.php';
require_once 'includes/email_config.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function enrollStudentInClasses($conn, $studentId, $sectionId) {
    try {
        // Get all active classes for the section
        $stmt = $conn->prepare("
            SELECT id 
            FROM classes 
            WHERE section_id = ? 
            AND status = 'active'
        ");
        $stmt->execute([$sectionId]);
        $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($classes)) {
            throw new Exception("No active classes found for the section");
        }

        // Prepare enrollment statement
        $enrollStmt = $conn->prepare("
            INSERT INTO class_enrollments 
            (student_id, class_id, enrollment_date, status) 
            VALUES (?, ?, NOW(), 'active')
        ");

        // Enroll student in each class
        foreach ($classes as $classId) {
            $enrollStmt->execute([$studentId, $classId]);
        }

        return true;
    } catch (Exception $e) {
        error_log("Error enrolling student in classes: " . $e->getMessage());
        throw new Exception("Failed to enroll student in classes");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded or upload error occurred');
        }

        $file = $_FILES['csvFile']['tmp_name'];
        $fileName = $_FILES['csvFile']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Initialize variables for data reading
        $data = [];
        $headers = [];
        
        // Process file based on extension
        if ($fileExtension === 'csv') {
            $handle = fopen($file, 'r');
            if ($handle === false) {
                throw new Exception('Failed to open file');
            }
            
            $headers = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                $data[] = array_combine($headers, $row);
            }
            fclose($handle);
        } else if (in_array($fileExtension, ['xls', 'xlsx'])) {
            $spreadsheet = IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Get headers from first row
            $headers = [];
            foreach ($worksheet->getRowIterator(1, 1) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $headers[] = trim($cell->getValue());
                }
            }
            
            // Get data from remaining rows
            $highestRow = $worksheet->getHighestRow();
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                $column = 'A';
                foreach ($headers as $header) {
                    $rowData[$header] = trim($worksheet->getCell($column . $row)->getValue());
                    $column++;
                }
                $data[] = $rowData;
            }
        } else {
            throw new Exception('Invalid file type. Please upload a CSV or Excel file.');
        }

        // Required columns
        $requiredColumns = ['lrn', 'first_name', 'last_name', 'grade_level', 'section_id', 'email'];
        
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $headers)) {
                throw new Exception("Missing required column: $column");
            }
        }

        // Begin transaction
        $conn->beginTransaction();

        // Prepare statements
        $checkLrnStmt = $conn->prepare("SELECT id FROM students WHERE lrn = ?");
        $checkEmailStmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
        $createUserStmt = $conn->prepare("
            INSERT INTO users (username, password, user_type, status) 
            VALUES (?, ?, 'student', 'active')
        ");
        $createStudentStmt = $conn->prepare("
            INSERT INTO students (user_id, lrn, first_name, last_name, grade_level, email, section_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $importedCount = 0;
        $errors = [];
        $row = 2;

        foreach ($data as $rowData) {
            try {
                // Skip empty rows
                if (empty($rowData['lrn'])) {
                    continue;
                }

                // Check if LRN already exists
                $checkLrnStmt->execute([$rowData['lrn']]);
                if ($checkLrnStmt->fetch()) {
                    throw new Exception("Student with LRN {$rowData['lrn']} already exists");
                }

                // Check if email already exists
                $checkEmailStmt->execute([$rowData['email']]);
                if ($checkEmailStmt->fetch()) {
                    throw new Exception("Student with email {$rowData['email']} already exists");
                }

                // Validate LRN format
                if (!preg_match('/^\d{12}$/', $rowData['lrn'])) {
                    throw new Exception("Invalid LRN format - must be 12 digits");
                }

                // Validate email format
                if (!filter_var($rowData['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format for {$rowData['email']}");
                }

                // Validate required fields
                $requiredFields = ['first_name', 'last_name', 'grade_level', 'section_id'];
                foreach ($requiredFields as $field) {
                    if (empty($rowData[$field])) {
                        throw new Exception("Missing required field: " . str_replace('_', ' ', $field));
                    }
                }

                // Validate grade level
                if (!in_array($rowData['grade_level'], range(1, 6))) {
                    throw new Exception("Invalid grade level - must be between 1 and 6");
                }

                // Validate section exists
                $checkSectionStmt = $conn->prepare("SELECT id FROM sections WHERE id = ? AND status = 'active'");
                $checkSectionStmt->execute([$rowData['section_id']]);
                if (!$checkSectionStmt->fetch()) {
                    throw new Exception("Invalid or inactive section ID: {$rowData['section_id']}");
                }

                // Generate a random password
                $plainPassword = generatePassword();
                $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
                
                // Create user account with the new password
                $createUserStmt->execute([$rowData['lrn'], $hashedPassword]);
                $userId = $conn->lastInsertId();

                // Create student record
                $createStudentStmt->execute([
                    $userId,
                    $rowData['lrn'],
                    $rowData['first_name'],
                    $rowData['last_name'],
                    $rowData['grade_level'],
                    $rowData['email'],
                    $rowData['section_id']
                ]);
                $studentId = $conn->lastInsertId();

                // Enroll student in classes
                enrollStudentInClasses($conn, $studentId, $rowData['section_id']);

                // Send email with credentials
                $fullName = $rowData['first_name'] . ' ' . $rowData['last_name'];
                if (!sendStudentCredentials($rowData['email'], $fullName, $rowData['lrn'], $plainPassword)) {
                    throw new Exception("Student account created but failed to send email notification");
                }

                // Log the actions
                $logStmt = $conn->prepare("
                INSERT INTO system_logs (user_id, action, description)
                    VALUES 
                    (?, 'Create Student', ?),
                    (?, 'Enroll Student', ?)
            ");
                $logStmt->execute([
                    $_SESSION['user_id'],
                    "Created student account for {$rowData['first_name']} {$rowData['last_name']} through import",
                $_SESSION['user_id'],
                    "Enrolled student {$rowData['first_name']} {$rowData['last_name']} in section classes"
                ]);

                $importedCount++;

            } catch (Exception $e) {
                $errors[] = "Row {$row}: " . $e->getMessage();
                continue;
            }
            $row++;
        }

        if (!empty($errors)) {
            $errorMessage = "Import validation failed:\n\n";
            foreach ($errors as $error) {
                $errorMessage .= "• " . $error . "\n";
            }
            throw new Exception($errorMessage);
        }

        $conn->commit();
        $_SESSION['success'] = "Successfully imported $importedCount students";
        header('Location: students.php');
        exit();
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
        $conn->rollBack();
    }
        $_SESSION['error'] = $e->getMessage();
    header('Location: students.php');
    exit();
}
}
?> 