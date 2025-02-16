<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No class ID provided']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            GROUP_CONCAT(
                JSON_OBJECT(
                    'id', cs.id,
                    'day_of_week', cs.day_of_week,
                    'start_time', cs.start_time,
                    'end_time', cs.end_time,
                    'room_number', cs.room_number
                )
            ) as schedules
        FROM classes c
        LEFT JOIN class_schedules cs ON c.id = cs.class_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    
    $stmt->execute([$_GET['id']]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($class) {
        // Parse the schedules JSON string
        $class['schedules'] = $class['schedules'] ? array_map(function($schedule) {
            return json_decode($schedule, true);
        }, explode(',', $class['schedules'])) : [];
    }
    
    echo json_encode($class);
} catch(PDOException $e) {
    error_log("Error fetching class: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch class details']);
}
?> 