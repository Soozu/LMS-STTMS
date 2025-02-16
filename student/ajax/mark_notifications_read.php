<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../includes/notifications.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    markNotificationsAsRead($_SESSION['user_id']);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 