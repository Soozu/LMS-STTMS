<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_type'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Access denied']));
}

// Get submission ID from request
$submissionId = isset($_GET['submission_id']) ? (int)$_GET['id'] : 0;

if (!$submissionId) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid submission ID']));
}

try {
    // Verify access rights
    $sql = "";
    $params = [$submissionId];
    
    switch ($_SESSION['user_type']) {
        case 'teacher':
            // Teachers can view comments for submissions in their classes
            $sql = "
                SELECT ac.*, u.user_type
                FROM assignment_comments ac
                JOIN student_submissions ss ON ac.submission_id = ss.id
                JOIN assignments a ON ss.assignment_id = a.id
                JOIN classes c ON a.class_id = c.id
                JOIN users u ON ac.user_id = u.id
                LEFT JOIN teachers t ON u.id = t.user_id
                LEFT JOIN students s ON u.id = s.user_id
                WHERE ss.id = ?
                AND c.teacher_id = ?
                ORDER BY ac.created_at ASC
            ";
            $params[] = $_SESSION['role_id'];
            break;
            
        case 'student':
            // Students can only view comments for their own submissions
            $sql = "
                SELECT ac.*, u.user_type
                FROM assignment_comments ac
                JOIN student_submissions ss ON ac.submission_id = ss.id
                JOIN users u ON ac.user_id = u.id
                LEFT JOIN teachers t ON u.id = t.user_id
                LEFT JOIN students s ON u.id = s.user_id
                WHERE ss.id = ?
                AND ss.student_id = ?
                ORDER BY ac.created_at ASC
            ";
            $params[] = $_SESSION['role_id'];
            break;
            
        default:
            http_response_code(403);
            exit(json_encode(['error' => 'Invalid user type']));
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format comments for display
    $formattedComments = array_map(function($comment) {
        return [
            'id' => $comment['id'],
            'comment' => nl2br(htmlspecialchars($comment['comment'])),
            'user_type' => $comment['user_type'],
            'user_name' => getUserName($comment['user_id'], $comment['user_type']),
            'created_at' => formatDateTime($comment['created_at'])
        ];
    }, $comments);

    // Send response
    header('Content-Type: application/json');
    echo json_encode($formattedComments);

} catch (Exception $e) {
    error_log("Error fetching comments: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load comments']);
}

// Helper function to get user's name
function getUserName($userId, $userType) {
    global $conn;
    
    try {
        $sql = "";
        switch ($userType) {
            case 'teacher':
                $sql = "
                    SELECT CONCAT(first_name, ' ', last_name) as name 
                    FROM teachers 
                    WHERE user_id = ?
                ";
                break;
                
            case 'student':
                $sql = "
                    SELECT CONCAT(first_name, ' ', last_name) as name 
                    FROM students 
                    WHERE user_id = ?
                ";
                break;
                
            default:
                return 'Unknown User';
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['name'] : 'Unknown User';
        
    } catch (Exception $e) {
        error_log("Error getting user name: " . $e->getMessage());
        return 'Unknown User';
    }
} 