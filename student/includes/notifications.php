<?php
function getUnreadNotifications($userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            n.*,
            CASE 
                WHEN n.type = 'assignment' THEN a.title
                WHEN n.type = 'grade' THEN CONCAT('Grade for ', a.title)
                ELSE n.title
            END as notification_title
        FROM notifications n
        LEFT JOIN assignments a ON n.reference_id = a.id AND n.type IN ('assignment', 'grade')
        WHERE n.user_id = ? 
        AND n.is_read = 0
        ORDER BY n.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUnreadMessages($userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            m.*,
            CASE 
                WHEN u.user_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name)
                WHEN u.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
            END as sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        LEFT JOIN teachers t ON u.id = t.user_id
        LEFT JOIN students s ON u.id = s.user_id
        WHERE m.receiver_id = ? 
        AND m.status = 'unread'
        ORDER BY m.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNotificationCount($userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function getMessageCount($userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM messages 
        WHERE receiver_id = ? AND status = 'unread'
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function markNotificationsAsRead($userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ? AND is_read = 0
    ");
    return $stmt->execute([$userId]);
}

function markMessagesAsRead($userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE messages 
        SET status = 'read' 
        WHERE receiver_id = ? AND status = 'unread'
    ");
    return $stmt->execute([$userId]);
} 